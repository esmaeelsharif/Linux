<?php
/**
 * One-click internal links.
 *
 * Finds places where a page already mentions another page's topic and links
 * the phrase, with a preview first and an undo after. Only visible text is
 * touched: never inside existing links, headings, code, buttons, HTML
 * attributes, block comments or shortcodes.
 *
 * @package OmniGEO_Ai_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Internal link suggestions and insertion.
 */
class AIGS_Links {

	const LOG_OPTION = 'aigs_link_log';

	/**
	 * Register hooks.
	 */
	public function hooks() {
		add_action( 'wp_ajax_aigs_links_preview', array( $this, 'ajax_preview' ) );
		add_action( 'wp_ajax_aigs_links_apply', array( $this, 'ajax_apply' ) );
		add_action( 'wp_ajax_aigs_links_undo', array( $this, 'ajax_undo' ) );
	}

	/* ============================================================
	 * Matching
	 * ============================================================ */

	/**
	 * Phrases that may link to a target page, best first.
	 *
	 * @param int $target_id Target post ID.
	 * @return array
	 */
	public static function anchors_for( $target_id ) {
		$title = trim( wp_strip_all_tags( html_entity_decode( get_the_title( $target_id ), ENT_QUOTES, 'UTF-8' ) ) );
		$cands = array(
			AIGS_SEO::focus_keyword( $target_id ),
			$title,
			trim( preg_replace( '/\s*[:|–—(\[].*$/u', '', $title ) ),
			trim( preg_replace( '/\s+-\s+.*$/u', '', $title ) ),
		);
		$extra = get_post_meta( $target_id, '_aigs_keywords', true );
		if ( $extra ) {
			foreach ( array_slice( array_map( 'trim', explode( ',', (string) $extra ) ), 0, 3 ) as $k ) {
				$cands[] = $k;
			}
		}
		$out = array();
		foreach ( $cands as $c ) {
			$c = trim( preg_replace( '/\s+/u', ' ', (string) $c ), " \t\n\r\0\x0B.,;!?؟،" );
			if ( self::usable_anchor( $c ) ) {
				$out[ AIGS_Text::normalize( $c ) ] = $c;
			}
		}
		return array_values( $out );
	}

	/**
	 * Is a phrase specific enough to be a link anchor?
	 *
	 * @param string $a Phrase.
	 * @return bool
	 */
	public static function usable_anchor( $a ) {
		$a = trim( (string) $a );
		if ( '' === $a ) {
			return false;
		}
		$words = AIGS_Text::word_count( $a );
		$len   = function_exists( 'mb_strlen' ) ? mb_strlen( $a, 'UTF-8' ) : strlen( $a );
		if ( $words > 8 || $len > 80 ) {
			return false;
		}
		if ( $words < 2 && $len < 6 ) {
			return false;
		}
		$generic = array( 'home', 'blog', 'contact', 'contact us', 'about', 'about us', 'shop', 'cart', 'checkout', 'my account', 'privacy policy', 'sample page', 'hello world' );
		return ! in_array( AIGS_Text::normalize( $a ), $generic, true );
	}

	/**
	 * Case-insensitive, word-bounded regex for a phrase in raw HTML text.
	 * Spaces in the phrase match any run of whitespace, &nbsp; or ZWNJ.
	 *
	 * @param string $phrase Phrase.
	 * @return string
	 */
	public static function phrase_regex( $phrase ) {
		$tokens = preg_split( '/\s+/u', trim( (string) $phrase ), -1, PREG_SPLIT_NO_EMPTY );
		$parts  = array();
		foreach ( (array) $tokens as $t ) {
			$parts[] = preg_quote( $t, '/' );
		}
		return '/(?<![\p{L}\p{N}])' . implode( '(?:\s|&nbsp;|\x{200C})+', $parts ) . '(?![\p{L}\p{N}])/iu';
	}

	/**
	 * Split HTML into tokens and mark which text tokens are safe to link.
	 *
	 * @param string $html Raw post content.
	 * @return array [ [text, safe(bool)] ]
	 */
	private static function segments( $html ) {
		$parts = preg_split( '/(<!--.*?-->|<[^>]+>)/s', (string) $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
		$skip  = array( 'a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'code', 'pre', 'script', 'style', 'button', 'textarea', 'select', 'option', 'label', 'figcaption', 'title', 'kbd', 'samp', 'svg' );
		$depth = array();
		$out   = array();
		foreach ( (array) $parts as $p ) {
			if ( '<' === $p[0] ) {
				if ( preg_match( '#^<(/)?([a-z0-9]+)#i', $p, $m ) ) {
					$tag = strtolower( $m[2] );
					if ( in_array( $tag, $skip, true ) && '/' !== substr( $p, -2, 1 ) ) {
						if ( '/' === $m[1] ) {
							$depth[ $tag ] = max( 0, ( isset( $depth[ $tag ] ) ? $depth[ $tag ] : 0 ) - 1 );
						} else {
							$depth[ $tag ] = ( isset( $depth[ $tag ] ) ? $depth[ $tag ] : 0 ) + 1;
						}
					}
				}
				$out[] = array( $p, false );
				continue;
			}
			$blocked = false;
			foreach ( $depth as $d ) {
				if ( $d > 0 ) {
					$blocked = true;
					break;
				}
			}
			$out[] = array( $p, ! $blocked );
		}
		return $out;
	}

	/**
	 * Is the offset inside an unclosed [shortcode …] in this text?
	 *
	 * @param string $text   Text.
	 * @param int    $offset Byte offset.
	 * @return bool
	 */
	private static function in_shortcode( $text, $offset ) {
		$before = substr( $text, 0, $offset );
		$open   = strrpos( $before, '[' );
		$close  = strrpos( $before, ']' );
		return false !== $open && ( false === $close || $open > $close );
	}

	/**
	 * Find the first linkable occurrence of any phrase.
	 *
	 * @param string $html    Raw content.
	 * @param array  $phrases Phrases, best first.
	 * @return array|null [phrase, matched_text, snippet]
	 */
	public static function find( $html, $phrases ) {
		$segs = self::segments( $html );
		foreach ( $phrases as $phrase ) {
			$re = self::phrase_regex( $phrase );
			foreach ( $segs as $seg ) {
				if ( ! $seg[1] || ! preg_match( $re, $seg[0], $m, PREG_OFFSET_CAPTURE ) ) {
					continue;
				}
				if ( self::in_shortcode( $seg[0], $m[0][1] ) ) {
					continue;
				}
				return array(
					'phrase'  => $phrase,
					'match'   => $m[0][0],
					'snippet' => self::snippet( $seg[0], $m[0][1], strlen( $m[0][0] ) ),
				);
			}
		}
		return null;
	}

	/**
	 * Plain-text context around a match: [before, match, after].
	 *
	 * @param string $text   Text segment.
	 * @param int    $offset Byte offset.
	 * @param int    $len    Byte length.
	 * @return array
	 */
	private static function snippet( $text, $offset, $len ) {
		$before = wp_strip_all_tags( html_entity_decode( substr( $text, 0, $offset ), ENT_QUOTES, 'UTF-8' ) );
		$match  = wp_strip_all_tags( html_entity_decode( substr( $text, $offset, $len ), ENT_QUOTES, 'UTF-8' ) );
		$after  = wp_strip_all_tags( html_entity_decode( substr( $text, $offset + $len ), ENT_QUOTES, 'UTF-8' ) );
		// Non-breaking spaces from &nbsp; display as plain spaces.
		$before = str_replace( "\xC2\xA0", ' ', $before );
		$match  = str_replace( "\xC2\xA0", ' ', $match );
		$after  = str_replace( "\xC2\xA0", ' ', $after );
		$b      = preg_split( '/\s+/u', trim( $before ), -1, PREG_SPLIT_NO_EMPTY );
		$a      = preg_split( '/\s+/u', trim( $after ), -1, PREG_SPLIT_NO_EMPTY );
		return array(
			( count( $b ) > 9 ? '… ' : '' ) . implode( ' ', array_slice( $b, -9 ) ),
			$match,
			implode( ' ', array_slice( $a, 0, 9 ) ) . ( count( $a ) > 9 ? ' …' : '' ),
		);
	}

	/**
	 * Insert one link at the first linkable occurrence of a phrase.
	 *
	 * @param string $html   Raw content.
	 * @param string $phrase Phrase.
	 * @param string $url    Target URL.
	 * @return string|null New content, or null when nothing was linked.
	 */
	public static function insert( $html, $phrase, $url ) {
		$segs = self::segments( $html );
		$re   = self::phrase_regex( $phrase );
		$done = false;
		$out  = '';
		foreach ( $segs as $seg ) {
			if ( ! $done && $seg[1] && preg_match( $re, $seg[0], $m, PREG_OFFSET_CAPTURE ) && ! self::in_shortcode( $seg[0], $m[0][1] ) ) {
				$off   = $m[0][1];
				$len   = strlen( $m[0][0] );
				$link  = '<a href="' . esc_url( $url ) . '">' . $m[0][0] . '</a>';
				$out  .= substr( $seg[0], 0, $off ) . $link . substr( $seg[0], $off + $len );
				$done  = true;
				continue;
			}
			$out .= $seg[0];
		}
		return $done ? $out : null;
	}

	/**
	 * Does content already link to a URL (ignoring scheme, www and trailing slash)?
	 *
	 * @param string $html Content.
	 * @param string $url  URL.
	 * @return bool
	 */
	public static function links_to( $html, $url ) {
		if ( ! preg_match_all( '/href\s*=\s*["\']([^"\']+)["\']/i', (string) $html, $m ) ) {
			return false;
		}
		foreach ( $m[1] as $href ) {
			if ( AIGS_Rules::same_url( $href, $url ) ) {
				return true;
			}
		}
		return false;
	}

	/* ============================================================
	 * Suggestions
	 * ============================================================ */

	/**
	 * Is a post a valid link source/target?
	 *
	 * @param WP_Post|null $p Post.
	 * @return bool
	 */
	private static function eligible( $p ) {
		return $p && 'publish' === $p->post_status && '' === $p->post_password && in_array( $p->post_type, AIGS_Scanner::post_types(), true );
	}

	/**
	 * Pages that mention the target and could link to it ("orphan" fix).
	 *
	 * @param int $target_id Target ID.
	 * @param int $limit     Max suggestions.
	 * @return array
	 */
	public static function suggest_sources( $target_id, $limit = 8 ) {
		$target = get_post( $target_id );
		if ( ! self::eligible( $target ) ) {
			return array();
		}
		$url     = get_permalink( $target_id );
		$anchors = self::anchors_for( $target_id );
		$ids     = array();
		foreach ( array_slice( $anchors, 0, 3 ) as $a ) {
			$q = new WP_Query(
				array(
					'post_type'              => AIGS_Scanner::post_types(),
					'post_status'            => 'publish',
					's'                      => $a,
					'sentence'               => true,
					'posts_per_page'         => 20,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'post__not_in'           => array( (int) $target_id ),
					'update_post_term_cache' => false,
				)
			);
			foreach ( (array) $q->posts as $pid ) {
				$ids[ (int) $pid ] = true;
			}
		}
		$out = array();
		foreach ( array_keys( $ids ) as $sid ) {
			$src = get_post( $sid );
			if ( ! self::eligible( $src ) || ! current_user_can( 'edit_post', $sid ) || self::links_to( $src->post_content, $url ) ) {
				continue;
			}
			$hit = self::find( $src->post_content, $anchors );
			if ( ! $hit ) {
				continue;
			}
			$out[] = self::row( $sid, (int) $target_id, $hit );
		}
		return self::rank( $out, 'source', $limit );
	}

	/**
	 * Pages a source could link to ("no internal links" fix).
	 *
	 * @param int $source_id Source ID.
	 * @param int $limit     Max suggestions.
	 * @return array
	 */
	public static function suggest_targets( $source_id, $limit = 8 ) {
		$src = get_post( $source_id );
		if ( ! self::eligible( $src ) || ! current_user_can( 'edit_post', $source_id ) ) {
			return array();
		}
		// Most important pages first; cap the work for large sites.
		$q   = new WP_Query(
			array(
				'post_type'              => AIGS_Scanner::post_types(),
				'post_status'            => 'publish',
				'posts_per_page'         => 300,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'post__not_in'           => array( (int) $source_id ),
				'meta_key'               => '_aigs_importance', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- bounded admin-only query.
				'orderby'                => 'meta_value_num',
				'order'                  => 'DESC',
				'update_post_term_cache' => false,
			)
		);
		$ids = (array) $q->posts;
		if ( ! $ids ) {
			$ids = get_posts(
				array(
					'post_type'      => AIGS_Scanner::post_types(),
					'post_status'    => 'publish',
					'posts_per_page' => 300,
					'fields'         => 'ids',
					'exclude'        => array( (int) $source_id ),
				)
			);
		}
		$out   = array();
		$used  = array();
		$work  = $src->post_content;
		foreach ( $ids as $tid ) {
			$tid = (int) $tid;
			$url = get_permalink( $tid );
			if ( ! $url || self::links_to( $work, $url ) ) {
				continue;
			}
			$hit = self::find( $work, self::anchors_for( $tid ) );
			if ( ! $hit || isset( $used[ AIGS_Text::normalize( $hit['match'] ) ] ) ) {
				continue;
			}
			$used[ AIGS_Text::normalize( $hit['match'] ) ] = true;
			$out[] = self::row( (int) $source_id, $tid, $hit );
			if ( count( $out ) >= $limit * 2 ) {
				break;
			}
		}
		return self::rank( $out, 'target', $limit );
	}

	/**
	 * Suggestion row for the browser.
	 *
	 * @param int   $source Source ID.
	 * @param int   $target Target ID.
	 * @param array $hit    find() result.
	 * @return array
	 */
	private static function row( $source, $target, $hit ) {
		return array(
			'source'       => $source,
			'source_title' => wp_strip_all_tags( get_the_title( $source ) ),
			'target'       => $target,
			'target_title' => wp_strip_all_tags( get_the_title( $target ) ),
			'anchor'       => $hit['phrase'],
			'snippet'      => $hit['snippet'],
		);
	}

	/**
	 * Sort by the importance of the side that benefits and trim.
	 *
	 * @param array  $rows  Rows.
	 * @param string $by    source|target.
	 * @param int    $limit Max.
	 * @return array
	 */
	private static function rank( $rows, $by, $limit ) {
		foreach ( $rows as &$r ) {
			$r['importance'] = (int) get_post_meta( $r[ $by ], '_aigs_importance', true );
		}
		unset( $r );
		usort(
			$rows,
			static function ( $a, $b ) {
				return $b['importance'] - $a['importance'];
			}
		);
		return array_slice( $rows, 0, $limit );
	}

	/* ============================================================
	 * Apply / undo
	 * ============================================================ */

	/**
	 * Insert approved links. Items: [source, target, anchor].
	 *
	 * @param array $items Items.
	 * @return array{applied:int,op:string,skipped:int}
	 */
	public static function apply( $items ) {
		$by_source = array();
		foreach ( $items as $it ) {
			$by_source[ (int) $it['source'] ][] = $it;
		}
		$log     = array();
		$applied = 0;
		$skipped = 0;
		$touched = array();
		foreach ( $by_source as $sid => $rows ) {
			$post = get_post( $sid );
			if ( ! self::eligible( $post ) || ! current_user_can( 'edit_post', $sid ) ) {
				$skipped += count( $rows );
				continue;
			}
			$old     = $post->post_content;
			$content = $old;
			$added   = array();
			foreach ( $rows as $it ) {
				$url = get_permalink( (int) $it['target'] );
				if ( ! $url || self::links_to( $content, $url ) ) {
					$skipped++;
					continue;
				}
				$new = self::insert( $content, (string) $it['anchor'], $url );
				if ( null === $new ) {
					$skipped++;
					continue;
				}
				$content = $new;
				$added[] = (int) $it['target'];
				$applied++;
			}
			if ( ! $added ) {
				continue;
			}
			// Keep the previous content for undo (last 3 per post).
			$op   = uniqid( 'lnk', false );
			$undo = get_post_meta( $sid, '_aigs_link_undo', true );
			$undo = is_array( $undo ) ? $undo : array();
			$undo[ $op ] = $old;
			update_post_meta( $sid, '_aigs_link_undo', array_slice( $undo, -3, 3, true ) );

			wp_update_post(
				wp_slash(
					array(
						'ID'           => $sid,
						'post_content' => $content,
					)
				)
			);
			$log[]                = array(
				'op'      => $op,
				'source'  => $sid,
				'targets' => $added,
			);
			$touched[ $sid ]      = true;
			foreach ( $added as $t ) {
				$touched[ $t ] = true;
			}
		}
		$opid = '';
		if ( $log ) {
			$opid = uniqid( 'lop', false );
			$ops  = get_option( self::LOG_OPTION, array() );
			$ops  = is_array( $ops ) ? $ops : array();
			array_unshift(
				$ops,
				array(
					'id'    => $opid,
					'time'  => time(),
					'user'  => get_current_user_id(),
					'items' => $log,
				)
			);
			update_option( self::LOG_OPTION, array_slice( $ops, 0, 20 ), false );
			foreach ( array_keys( $touched ) as $pid ) {
				AIGS_Scanner::scan_post( $pid, true );
			}
			AIGS_Scanner::refresh_link_rules( array_keys( $touched ) );
		}
		return array(
			'applied' => $applied,
			'skipped' => $skipped,
			'op'      => $opid,
		);
	}

	/**
	 * Undo one operation (only if the post was not edited since).
	 *
	 * @param string $opid Operation ID.
	 * @return array|WP_Error
	 */
	public static function undo( $opid ) {
		$ops = get_option( self::LOG_OPTION, array() );
		$ops = is_array( $ops ) ? $ops : array();
		foreach ( $ops as $i => $op ) {
			if ( $op['id'] !== $opid ) {
				continue;
			}
			$restored = 0;
			$touched  = array();
			foreach ( $op['items'] as $it ) {
				$sid  = (int) $it['source'];
				$undo = get_post_meta( $sid, '_aigs_link_undo', true );
				if ( ! current_user_can( 'edit_post', $sid ) || ! is_array( $undo ) || ! isset( $undo[ $it['op'] ] ) ) {
					continue;
				}
				wp_update_post(
					wp_slash(
						array(
							'ID'           => $sid,
							'post_content' => $undo[ $it['op'] ],
						)
					)
				);
				unset( $undo[ $it['op'] ] );
				update_post_meta( $sid, '_aigs_link_undo', $undo );
				$touched[ $sid ] = true;
				foreach ( $it['targets'] as $t ) {
					$touched[ (int) $t ] = true;
				}
				$restored++;
			}
			unset( $ops[ $i ] );
			update_option( self::LOG_OPTION, array_values( $ops ), false );
			foreach ( array_keys( $touched ) as $pid ) {
				AIGS_Scanner::scan_post( $pid, true );
			}
			AIGS_Scanner::refresh_link_rules( array_keys( $touched ) );
			return array( 'restored' => $restored );
		}
		return new WP_Error( 'aigs_undo', __( 'That operation can no longer be undone.', 'omnigeo-ai-seo' ) );
	}

	/* ============================================================
	 * AJAX
	 * ============================================================ */

	/**
	 * AJAX: suggestions for a page.
	 */
	public function ajax_preview() {
		check_ajax_referer( 'aigs_admin', 'nonce' );
		$pid  = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$mode = isset( $_POST['mode'] ) && 'from' === $_POST['mode'] ? 'from' : 'to';
		if ( ! $pid || ! current_user_can( 'edit_post', $pid ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'omnigeo-ai-seo' ) ) );
		}
		$rows = 'to' === $mode ? self::suggest_sources( $pid ) : self::suggest_targets( $pid );
		wp_send_json_success( array( 'items' => $rows ) );
	}

	/**
	 * AJAX: insert approved links.
	 */
	public function ajax_apply() {
		check_ajax_referer( 'aigs_admin', 'nonce' );
		$raw   = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field sanitized below.
		$items = array();
		foreach ( array_slice( $raw, 0, 50 ) as $r ) {
			$items[] = array(
				'source' => isset( $r['source'] ) ? absint( $r['source'] ) : 0,
				'target' => isset( $r['target'] ) ? absint( $r['target'] ) : 0,
				'anchor' => isset( $r['anchor'] ) ? sanitize_text_field( $r['anchor'] ) : '',
			);
		}
		$items = array_filter(
			$items,
			static function ( $i ) {
				return $i['source'] && $i['target'] && '' !== $i['anchor'];
			}
		);
		if ( ! $items ) {
			wp_send_json_error( array( 'message' => __( 'Nothing selected.', 'omnigeo-ai-seo' ) ) );
		}
		wp_send_json_success( self::apply( $items ) );
	}

	/**
	 * AJAX: undo.
	 */
	public function ajax_undo() {
		check_ajax_referer( 'aigs_admin', 'nonce' );
		$op  = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';
		$res = self::undo( $op );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}
		wp_send_json_success( $res );
	}
}
