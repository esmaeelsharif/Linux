<?php
/**
 * Site-wide scanner.
 *
 * Reads posts, pages and products straight from WordPress (no HTTP crawl of
 * your own site), in small batches so shared hosting never times out:
 *
 *   start → step (batch of N posts, page rules) → … → finalize (link graph,
 *   duplicates, AI-read pages, site rules, importance, scores, history)
 *
 * Unchanged pages are skipped by content hash, so weekly re-scans are cheap.
 *
 * @package OmniGEO_Ai_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Batch scanner + scoring.
 */
class AIGS_Scanner {

	const STATE_OPTION = 'aigs_scan_state';

	/**
	 * Register hooks.
	 */
	public function hooks() {
		add_action( 'wp_ajax_aigs_scan_start', array( $this, 'ajax_start' ) );
		add_action( 'wp_ajax_aigs_scan_step', array( $this, 'ajax_step' ) );
		add_action( 'wp_ajax_aigs_scan_post', array( $this, 'ajax_scan_post' ) );
		add_action( 'aigs_weekly', aigs_guard( array( $this, 'cron_weekly' ) ) );
		add_action( 'aigs_scan_continue', aigs_guard( array( $this, 'cron_continue' ) ) );
		add_action( 'save_post', aigs_guard( array( $this, 'on_save' ) ), 100, 2 );
	}

	/* ============================================================
	 * Scope
	 * ============================================================ */

	/**
	 * Post types included in the scan.
	 *
	 * @return array
	 */
	public static function post_types() {
		$types   = get_post_types( array( 'public' => true ), 'names' );
		$exclude = array( 'attachment', 'elementor_library', 'wp_block', 'et_pb_layout', 'fl-builder-template', 'ct_template', 'oxy_user_library', 'brizy_template' );
		$types   = array_values( array_diff( $types, $exclude ) );
		/**
		 * Filter the post types the site scan covers.
		 *
		 * @param array $types Post type names.
		 */
		return (array) apply_filters( 'aigs_scan_post_types', $types );
	}

	/**
	 * Maximum posts per scan (protects very large sites).
	 *
	 * @return int
	 */
	public static function max_posts() {
		return (int) apply_filters( 'aigs_scan_max_posts', 3000 );
	}

	/**
	 * Batch size per request.
	 *
	 * @return int
	 */
	public static function batch_size() {
		return max( 1, (int) apply_filters( 'aigs_scan_batch_size', 12 ) );
	}

	/**
	 * Count of in-scope published posts.
	 *
	 * @return int
	 */
	private static function count_scope() {
		global $wpdb;
		$types = self::post_types();
		if ( ! $types ) {
			return 0;
		}
		$in = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- IN-list placeholders.
		$n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_password = '' AND post_type IN ({$in})", $types ) );
		return min( $n, self::max_posts() );
	}

	/**
	 * Next batch of IDs after $last_id.
	 *
	 * @param int $last_id Last processed ID.
	 * @param int $limit   Batch size.
	 * @return array
	 */
	private static function next_ids( $last_id, $limit ) {
		global $wpdb;
		$types = self::post_types();
		if ( ! $types ) {
			return array();
		}
		$in   = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$args = array_merge( array( (int) $last_id ), $types, array( (int) $limit ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- IN-list placeholders.
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID > %d AND post_status = 'publish' AND post_password = '' AND post_type IN ({$in}) ORDER BY ID ASC LIMIT %d", $args ) ) );
	}

	/**
	 * All in-scope IDs (for finalize).
	 *
	 * @return array
	 */
	public static function scope_ids() {
		global $wpdb;
		$types = self::post_types();
		if ( ! $types ) {
			return array();
		}
		$in   = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$args = array_merge( $types, array( self::max_posts() ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- IN-list placeholders.
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_password = '' AND post_type IN ({$in}) ORDER BY ID ASC LIMIT %d", $args ) ) );
	}

	/* ============================================================
	 * State machine
	 * ============================================================ */

	/**
	 * Current scan state.
	 *
	 * @return array
	 */
	public static function state() {
		$s = get_option( self::STATE_OPTION, array() );
		return wp_parse_args(
			is_array( $s ) ? $s : array(),
			array(
				'status'  => 'idle',
				'mode'    => 'manual',
				'last_id' => 0,
				'done'    => 0,
				'total'   => 0,
				'started' => 0,
				'skipped' => 0,
			)
		);
	}

	/**
	 * Begin a new scan.
	 *
	 * @param string $mode manual|cron.
	 * @return array State.
	 */
	public static function start( $mode = 'manual' ) {
		$state = array(
			'status'  => 'running',
			'mode'    => $mode,
			'last_id' => 0,
			'done'    => 0,
			'total'   => self::count_scope(),
			'started' => time(),
			'skipped' => 0,
		);
		update_option( self::STATE_OPTION, $state, false );
		return $state;
	}

	/**
	 * Process one batch; finalize when the queue is empty.
	 *
	 * @return array State.
	 */
	public static function step() {
		$state = self::state();
		if ( 'running' !== $state['status'] ) {
			return $state;
		}
		$ids = ( $state['done'] < self::max_posts() ) ? self::next_ids( $state['last_id'], self::batch_size() ) : array();
		if ( ! $ids ) {
			self::finalize( $state['mode'] );
			$state           = self::state();
			$state['status'] = 'done';
			update_option( self::STATE_OPTION, $state, false );
			return $state;
		}
		update_meta_cache( 'post', $ids );
		foreach ( $ids as $id ) {
			$r = self::scan_post( $id );
			if ( 'skipped' === $r ) {
				$state['skipped']++;
			}
			$state['last_id'] = $id;
			$state['done']++;
		}
		update_option( self::STATE_OPTION, $state, false );
		return $state;
	}


	/* ============================================================
	 * Per-post analysis
	 * ============================================================ */

	/**
	 * Render content the way visitors see it, without printing anything.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public static function render( $post ) {
		$content = (string) $post->post_content;
		if ( '' === trim( $content ) ) {
			return '';
		}
		$prev            = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- shortcodes expect the current post; restored below.
		setup_postdata( $post );
		ob_start();
		if ( function_exists( 'has_blocks' ) && has_blocks( $content ) ) {
			$html = do_blocks( $content );
		} else {
			$html = wpautop( $content );
		}
		$html = do_shortcode( shortcode_unautop( $html ) );
		ob_end_clean();
		$GLOBALS['post'] = $prev; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restoring.
		if ( $prev instanceof WP_Post ) {
			setup_postdata( $prev );
		}
		return (string) $html;
	}

	/**
	 * Boilerplate pages that should not get content advice.
	 *
	 * @param WP_Post $post Post.
	 * @return bool
	 */
	public static function is_low_priority( $post ) {
		$ids = array( (int) get_option( 'wp_page_for_privacy_policy' ) );
		if ( function_exists( 'wc_get_page_id' ) ) {
			foreach ( array( 'cart', 'checkout', 'myaccount', 'terms' ) as $p ) {
				$ids[] = (int) wc_get_page_id( $p );
			}
		}
		if ( in_array( (int) $post->ID, array_filter( $ids ), true ) ) {
			return true;
		}
		if ( 'page' !== $post->post_type ) {
			return false;
		}
		$needles = array( 'privacy', 'terms', 'sample-page', 'contact', 'cookie', 'disclaimer', 'dmca', 'refund', 'shipping', 'returns', 'legal', 'checkout', 'cart', 'my-account', 'login', 'register', 'thank', 'حریم', 'قوانین', 'تماس', 'شرایط', 'سبد', 'پرداخت', 'حساب-کاربری', 'ورود', 'ثبت-نام' );
		$hay     = strtolower( urldecode( $post->post_name ) . ' ' . $post->post_title );
		foreach ( $needles as $n ) {
			if ( false !== strpos( $hay, $n ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * WooCommerce facts for a product.
	 *
	 * @param int $post_id Product ID.
	 * @return array
	 */
	public static function product_facts( $post_id ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return array();
		}
		$p = wc_get_product( $post_id );
		if ( ! $p ) {
			return array();
		}
		$img     = (int) $p->get_image_id();
		$alt     = $img ? trim( (string) get_post_meta( $img, '_wp_attachment_image_alt', true ) ) : '';
		$cats    = wp_get_post_terms( $post_id, 'product_cat', array( 'fields' => 'ids' ) );
		$default = (int) get_option( 'default_product_cat' );
		$cats    = is_wp_error( $cats ) ? array() : array_diff( array_map( 'intval', $cats ), array( $default ) );
		$type    = $p->get_type();
		if ( 'grouped' === $type ) {
			$has_price = true;
		} elseif ( 'variable' === $type ) {
			$has_price = '' !== (string) $p->get_price();
		} else {
			$has_price = '' !== (string) $p->get_regular_price() || '' !== (string) $p->get_price();
		}
		$gtin_ok = method_exists( $p, 'get_global_unique_id' );
		$long    = (string) $p->get_description();
		return array(
			'short_words'    => AIGS_Text::word_count( $p->get_short_description() ),
			'long_words'     => AIGS_Text::word_count( $long ),
			'long_hash'      => AIGS_Text::word_count( $long ) >= 20 ? md5( AIGS_Text::normalize( $long ) ) : '',
			'attributes'     => count( (array) $p->get_attributes() ),
			'image'          => $img,
			'image_alt'      => '' !== $alt,
			'categorized'    => ! empty( $cats ),
			'has_price'      => $has_price,
			'gtin_supported' => $gtin_ok,
			'gtin'           => $gtin_ok ? '' !== (string) $p->get_global_unique_id() : false,
		);
	}

	/**
	 * Build the evaluation context for one post.
	 *
	 * @param WP_Post $post Post.
	 * @return array
	 */
	public static function context( $post ) {
		$id        = (int) $post->ID;
		$html      = self::render( $post );
		$structure = AIGS_Answer::structure( $html );
		$keyword   = AIGS_SEO::focus_keyword( $id );
		$is_prod   = class_exists( 'AIGS_WooCommerce' ) && AIGS_WooCommerce::is_product_id( $id );
		$author_id = (int) $post->post_author;
		$faqs      = get_post_meta( $id, '_aigs_faqs', true );

		return array(
			'id'              => $id,
			'type'            => $post->post_type,
			'title'           => wp_strip_all_tags( $post->post_title ),
			'url'             => get_permalink( $id ),
			'structure'       => $structure,
			'answer'          => AIGS_Answer::analyze( $structure, $post->post_title, $keyword ),
			'keyword'         => $keyword,
			'has_seo_plugin'  => '' !== AIGS_SEO::active(),
			'seo_title'       => AIGS_SEO::seo_title( $id ),
			'meta_desc'       => AIGS_SEO::meta_description( $id ),
			'seo_auto_desc'   => AIGS_SEO::auto_description(),
			'noindex'         => AIGS_SEO::is_noindex( $id ),
			'canonical'       => AIGS_SEO::canonical( $id ),
			'low_priority'    => self::is_low_priority( $post ),
			'is_product'      => $is_prod,
			'product'         => $is_prod ? self::product_facts( $id ) : array(),
			'modified'        => (int) get_post_modified_time( 'U', true, $post ),
			'author_id'       => $author_id,
			'author_complete' => '' !== trim( (string) get_the_author_meta( 'description', $author_id ) ) || '' !== trim( (string) get_user_meta( $author_id, 'aigs_jobtitle', true ) ),
			'thumb_id'        => (int) get_post_thumbnail_id( $id ),
			'schema_type'     => (string) get_post_meta( $id, '_aigs_schema_type', true ),
			'faq_count'       => is_array( $faqs ) ? count( $faqs ) : 0,
		);
	}

	/**
	 * Fingerprint of everything page rules depend on.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	private static function fingerprint( $post ) {
		$id    = (int) $post->ID;
		$parts = array(
			AIGS_Rules::VERSION,
			$post->post_modified_gmt,
			md5( $post->post_content ),
			$post->post_title,
			AIGS_SEO::active(),
			AIGS_SEO::meta_description( $id ),
			AIGS_SEO::seo_title( $id ),
			AIGS_SEO::is_noindex( $id ) ? 1 : 0,
			AIGS_SEO::canonical( $id ),
			get_post_thumbnail_id( $id ),
			get_post_meta( $id, '_aigs_schema_type', true ),
			wp_json_encode( get_post_meta( $id, '_aigs_faqs', true ) ),
			get_the_author_meta( 'description', $post->post_author ),
			get_user_meta( $post->post_author, 'aigs_jobtitle', true ),
		);
		return md5( implode( '|', array_map( 'strval', $parts ) ) );
	}

	/**
	 * Resolve internal links in a post to post IDs.
	 *
	 * @param array $hrefs   Hrefs.
	 * @param int   $self_id Post ID (excluded).
	 * @return array Unique post IDs.
	 */
	private static function internal_targets( $hrefs, $self_id ) {
		static $cache = array();
		$home = AIGS_AI_Client::host( home_url() );
		$out  = array();
		foreach ( array_slice( (array) $hrefs, 0, 150 ) as $href ) {
			$href = trim( $href );
			if ( 0 === strpos( $href, '/' ) && 0 !== strpos( $href, '//' ) ) {
				$href = home_url( $href );
			}
			if ( ! preg_match( '#^https?://#i', $href ) || ! AIGS_AI_Client::host_matches( $href, $home ) ) {
				continue;
			}
			$clean = preg_replace( '/[#?].*$/', '', $href );
			if ( ! isset( $cache[ $clean ] ) ) {
				$cache[ $clean ] = (int) url_to_postid( $clean );
			}
			$pid = $cache[ $clean ];
			if ( $pid && $pid !== (int) $self_id ) {
				$out[ $pid ] = true;
			}
		}
		return array_keys( $out );
	}

	/**
	 * Scan one post: page rules, findings, cached facts for graph rules.
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $force   Ignore the unchanged-content cache.
	 * @return string scanned|skipped|gone
	 */
	public static function scan_post( $post_id, $force = false ) {
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status || '' !== $post->post_password ) {
			AIGS_Findings::sync( $post_id, array_merge( AIGS_Rules::ids( 'page' ), AIGS_Rules::ids( 'graph' ) ), array() );
			return 'gone';
		}
		$fp      = self::fingerprint( $post );
		$scanned = (int) get_post_meta( $post_id, '_aigs_scanned_at', true );
		if ( ! $force && $fp === get_post_meta( $post_id, '_aigs_scan_hash', true ) && ( time() - $scanned ) < 14 * DAY_IN_SECONDS ) {
			return 'skipped';
		}

		$c     = self::context( $post );
		$found = AIGS_Rules::evaluate_page( $c );
		AIGS_Findings::sync( $post_id, AIGS_Rules::ids( 'page' ), $found );

		$path = wp_parse_url( $c['url'], PHP_URL_PATH );
		update_post_meta( $post_id, '_aigs_scan_hash', $fp );
		update_post_meta( $post_id, '_aigs_scanned_at', time() );
		update_post_meta( $post_id, '_aigs_links_out', self::internal_targets( $c['structure']['links'], $post_id ) );
		update_post_meta( $post_id, '_aigs_path', $path ? untrailingslashit( $path ) : '' );
		update_post_meta( $post_id, '_aigs_words', (int) $c['structure']['words'] );
		update_post_meta( $post_id, '_aigs_desc_hash', '' !== $c['meta_desc'] ? md5( AIGS_Text::normalize( $c['meta_desc'] ) ) : '' );
		update_post_meta( $post_id, '_aigs_title_hash', md5( AIGS_Text::normalize( $c['seo_title'] ) ) );
		update_post_meta( $post_id, '_aigs_pdesc_hash', ! empty( $c['product']['long_hash'] ) ? $c['product']['long_hash'] : '' );
		update_post_meta( $post_id, '_aigs_low', $c['low_priority'] ? 1 : 0 );

		self::refresh_score( $post_id );
		return 'scanned';
	}

	/**
	 * Recompute and store a post's score from its open page + graph findings.
	 *
	 * @param int $post_id Post ID.
	 * @return int
	 */
	public static function refresh_score( $post_id ) {
		$score = AIGS_Rules::page_score( AIGS_Findings::open_rules( $post_id ) );
		update_post_meta( $post_id, '_aigs_page_score', $score );
		return $score;
	}

	/* ============================================================
	 * Finalize: whole-site rules
	 * ============================================================ */

	/**
	 * AI hits per URL path over 30 days.
	 *
	 * @param string $type fetch|crawl.
	 * @return array path => hits
	 */
	private static function hits_by_path( $type ) {
		global $wpdb;
		$table = AIGS_DB::table( 'hits' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT url, COUNT(*) AS c FROM {$table} WHERE hit_type = %s AND status < 400 AND created_at >= %s GROUP BY url ORDER BY c DESC LIMIT 3000", $type, AIGS_DB::since( 30 ) ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$path = wp_parse_url( $r['url'], PHP_URL_PATH );
			$path = $path ? untrailingslashit( $path ) : '';
			$out[ $path ] = ( isset( $out[ $path ] ) ? $out[ $path ] : 0 ) + (int) $r['c'];
		}
		return $out;
	}

	/**
	 * Post IDs linked from navigation menus.
	 *
	 * @return array id => true
	 */
	private static function menu_ids() {
		$out = array();
		foreach ( (array) wp_get_nav_menus() as $menu ) {
			$items = wp_get_nav_menu_items( $menu->term_id );
			foreach ( (array) $items as $it ) {
				if ( isset( $it->type, $it->object_id ) && 'post_type' === $it->type ) {
					$out[ (int) $it->object_id ] = true;
				}
			}
		}
		return $out;
	}

	/**
	 * Importance 0-100 for prioritising work.
	 *
	 * @param WP_Post $post    Post.
	 * @param int     $inbound Inbound internal links.
	 * @param int     $hits    AI bot hits (30d).
	 * @param bool    $in_menu Linked from a menu.
	 * @return int
	 */
	public static function importance( $post, $inbound, $hits, $in_menu ) {
		$id    = (int) $post->ID;
		$score = 10;
		if ( $id === (int) get_option( 'page_on_front' ) || $id === (int) get_option( 'page_for_posts' ) ) {
			$score += 40;
		}
		if ( $in_menu ) {
			$score += 20;
		}
		$score += min( 20, 2 * (int) $inbound );
		$score += min( 20, (int) $hits );
		if ( 'product' === $post->post_type ) {
			$score += 5;
			$sales  = (int) get_post_meta( $id, 'total_sales', true );
			if ( $sales > 0 ) {
				$score += min( 15, (int) round( 5 * log10( 1 + $sales ) ) );
			}
		}
		if ( strtotime( $post->post_modified_gmt . ' UTC' ) > time() - 90 * DAY_IN_SECONDS ) {
			$score += 5;
		}
		if ( get_post_meta( $id, '_aigs_important', true ) ) {
			$score += 30;
		}
		if ( get_post_meta( $id, '_aigs_low', true ) ) {
			$score = (int) round( $score * 0.3 );
		}
		return max( 0, min( 100, $score ) );
	}

	/**
	 * Related posts in the same terms (for link suggestions).
	 *
	 * @param WP_Post $post  Post.
	 * @param array   $skip  IDs to skip.
	 * @param int     $limit Count.
	 * @return array IDs
	 */
	private static function related( $post, $skip, $limit = 3 ) {
		$tax = array();
		foreach ( get_object_taxonomies( $post->post_type, 'objects' ) as $t ) {
			if ( $t->public && $t->hierarchical || in_array( $t->name, array( 'post_tag', 'product_tag' ), true ) ) {
				$terms = wp_get_post_terms( $post->ID, $t->name, array( 'fields' => 'ids' ) );
				if ( ! is_wp_error( $terms ) && $terms ) {
					$tax[] = array(
						'taxonomy' => $t->name,
						'terms'    => $terms,
					);
				}
			}
		}
		$args = array(
			'post_type'              => $post->post_type,
			'post_status'            => 'publish',
			'posts_per_page'         => $limit,
			'post__not_in'           => array_merge( array( (int) $post->ID ), $skip ),
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'orderby'                => 'modified',
		);
		if ( $tax ) {
			$args['tax_query'] = array_merge( array( 'relation' => 'OR' ), $tax ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- bounded, admin-only background job.
		} else {
			$kw = AIGS_SEO::focus_keyword( $post->ID );
			$args['s'] = '' !== $kw ? $kw : wp_trim_words( $post->post_title, 3, '' );
		}
		$q = new WP_Query( $args );
		return array_map( 'intval', (array) $q->posts );
	}

	/**
	 * Whole-site rules, importance, scores and history.
	 *
	 * @param string $mode manual|cron.
	 */
	public static function finalize( $mode = 'manual' ) {
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- may be disabled on the host.
		}
		AIGS_Findings::prune_missing();

		$ids = self::scope_ids();
		$in  = array();
		$out = array();
		$fetch_hits = self::hits_by_path( 'fetch' );
		$crawl_hits = self::hits_by_path( 'crawl' );
		$menu       = self::menu_ids();
		$front      = array( (int) get_option( 'page_on_front' ), (int) get_option( 'page_for_posts' ) );

		$titles = array();
		$descs  = array();
		$pdescs = array();
		$facts  = array();

		foreach ( array_chunk( $ids, 300 ) as $chunk ) {
			update_meta_cache( 'post', $chunk );
			foreach ( $chunk as $id ) {
				$links = get_post_meta( $id, '_aigs_links_out', true );
				$links = is_array( $links ) ? $links : array();
				$out[ $id ] = count( $links );
				foreach ( $links as $t ) {
					$in[ (int) $t ] = ( isset( $in[ (int) $t ] ) ? $in[ (int) $t ] : 0 ) + 1;
				}
				$th = (string) get_post_meta( $id, '_aigs_title_hash', true );
				$dh = (string) get_post_meta( $id, '_aigs_desc_hash', true );
				$ph = (string) get_post_meta( $id, '_aigs_pdesc_hash', true );
				if ( '' !== $th ) {
					$titles[ $th ][] = $id;
				}
				if ( '' !== $dh ) {
					$descs[ $dh ][] = $id;
				}
				if ( '' !== $ph ) {
					$pdescs[ $ph ][] = $id;
				}
				$facts[ $id ] = array(
					'path'  => (string) get_post_meta( $id, '_aigs_path', true ),
					'words' => (int) get_post_meta( $id, '_aigs_words', true ),
					'low'   => (int) get_post_meta( $id, '_aigs_low', true ),
					'score' => (int) get_post_meta( $id, '_aigs_page_score', true ),
					'seen'  => '' !== get_post_meta( $id, '_aigs_scanned_at', true ),
				);
			}
		}

		$found       = array();
		$suggest_cap = 120;
		foreach ( $ids as $id ) {
			if ( empty( $facts[ $id ]['seen'] ) ) {
				continue;
			}
			$post = get_post( $id );
			if ( ! $post ) {
				continue;
			}
			$f       = array();
			$is_prod = 'product' === $post->post_type;
			$inbound = isset( $in[ $id ] ) ? $in[ $id ] : 0;
			$path    = $facts[ $id ]['path'];
			$fetches = isset( $fetch_hits[ $path ] ) ? $fetch_hits[ $path ] : 0;

			if ( ! $is_prod && ! $facts[ $id ]['low'] && ! in_array( $id, $front, true ) && 0 === $inbound && ! isset( $menu[ $id ] ) ) {
				$f['orphan'] = array( 'suggest' => $suggest_cap-- > 0 ? self::related( $post, array() ) : array() );
			}
			if ( ! $is_prod && ! $facts[ $id ]['low'] && 0 === $out[ $id ] && $facts[ $id ]['words'] >= 300 ) {
				$f['no_internal_links'] = array( 'suggest' => $suggest_cap-- > 0 ? self::related( $post, array() ) : array() );
			}
			$th = (string) get_post_meta( $id, '_aigs_title_hash', true );
			if ( '' !== $th && isset( $titles[ $th ] ) && count( $titles[ $th ] ) > 1 ) {
				$f['duplicate_title'] = array( 'others' => array_slice( array_values( array_diff( $titles[ $th ], array( $id ) ) ), 0, 5 ) );
			}
			$dh = (string) get_post_meta( $id, '_aigs_desc_hash', true );
			if ( '' !== $dh && isset( $descs[ $dh ] ) && count( $descs[ $dh ] ) > 1 ) {
				$f['duplicate_desc'] = array( 'others' => array_slice( array_values( array_diff( $descs[ $dh ], array( $id ) ) ), 0, 5 ) );
			}
			$ph = (string) get_post_meta( $id, '_aigs_pdesc_hash', true );
			if ( '' !== $ph && isset( $pdescs[ $ph ] ) && count( $pdescs[ $ph ] ) > 1 ) {
				$f['product_duplicate_desc'] = array( 'others' => array_slice( array_values( array_diff( $pdescs[ $ph ], array( $id ) ) ), 0, 5 ) );
			}
			// Judge readiness without this rule's own penalty, or it would never clear.
			if ( $fetches > 0 && AIGS_Rules::page_score( array_diff( AIGS_Findings::open_rules( $id ), array( 'ai_read_weak' ) ) ) < 70 ) {
				$f['ai_read_weak'] = array( 'reads' => $fetches );
			}
			if ( $f ) {
				$found[ $id ] = $f;
			}

			// Importance + priorities.
			$hits = $fetches + ( isset( $crawl_hits[ $path ] ) ? (int) floor( $crawl_hits[ $path ] / 5 ) : 0 );
			$imp  = self::importance( $post, $inbound, $hits, isset( $menu[ $id ] ) );
			if ( (string) $imp !== (string) get_post_meta( $id, '_aigs_importance', true ) ) {
				update_post_meta( $id, '_aigs_importance', $imp );
				AIGS_Findings::reprioritize( $id, $imp );
			}
			update_post_meta( $id, '_aigs_links_in', $inbound );
		}

		$graph_stats = AIGS_Findings::sync_group( 'graph', AIGS_Rules::ids( 'graph' ), $found, $ids );
		$site_stats  = AIGS_Findings::sync( 0, AIGS_Rules::ids( 'site' ), self::site_rules() );

		// Scores now include graph findings.
		$sum_w = 0;
		$sum_s = 0;
		foreach ( $ids as $id ) {
			if ( empty( $facts[ $id ]['seen'] ) ) {
				continue;
			}
			$score = self::refresh_score( $id );
			$w     = max( 1, (int) get_post_meta( $id, '_aigs_importance', true ) );
			$sum_w += $w;
			$sum_s += $w * $score;
		}
		$site = $sum_w ? $sum_s / $sum_w : 0;
		foreach ( AIGS_Findings::open_rules( 0 ) as $rule ) {
			$m = AIGS_Rules::meta( $rule );
			if ( $m && 'site' === $m['grp'] ) {
				$site -= array( 0, 2, 4, 10, 25 )[ $m['sev'] ];
			}
		}
		$site = (int) max( 0, min( 100, round( $site ) ) );

		$counts  = AIGS_Findings::counts();
		$summary = array(
			'score'      => $site,
			'pages'      => count( $ids ),
			'scanned_at' => time(),
			'mode'       => $mode,
			'new'        => $graph_stats['new'] + $site_stats['new'],
			'open'       => $counts['open'],
		);
		$prev = get_option( 'aigs_scan_summary', array() );
		update_option( 'aigs_scan_summary', $summary, false );

		$hist   = get_option( 'aigs_score_history', array() );
		$hist   = is_array( $hist ) ? $hist : array();
		$hist[] = array(
			't'     => time(),
			'score' => $site,
			'open'  => $counts['open'],
		);
		update_option( 'aigs_score_history', array_slice( $hist, -26 ), false );

		/**
		 * Fires after a full site scan has been finalized.
		 *
		 * @param array $summary Scan summary.
		 * @param array $prev    Previous summary.
		 * @param string $mode   manual|cron.
		 */
		do_action( 'aigs_scan_finalized', $summary, is_array( $prev ) ? $prev : array(), $mode );
	}

	/**
	 * Re-evaluate the internal-link rules (orphan, no internal links) for a few
	 * posts right away, using the link data stored by scan_post(). The full
	 * scan does the same for every page.
	 *
	 * @param array $ids Post IDs.
	 */
	public static function refresh_link_rules( $ids ) {
		global $wpdb;
		$menu  = self::menu_ids();
		$front = array( (int) get_option( 'page_on_front' ), (int) get_option( 'page_for_posts' ) );
		foreach ( array_unique( array_map( 'intval', (array) $ids ) ) as $id ) {
			$post = get_post( $id );
			if ( ! $post || '' === get_post_meta( $id, '_aigs_scanned_at', true ) ) {
				continue;
			}
			// Serialized int arrays store each ID as i:<id>; — count posts linking here.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- small targeted count.
			$inbound = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = '_aigs_links_out' AND m.meta_value LIKE %s AND p.post_status = 'publish' AND p.ID <> %d", '%' . $wpdb->esc_like( 'i:' . $id . ';' ) . '%', $id ) );
			$out     = get_post_meta( $id, '_aigs_links_out', true );
			$out     = is_array( $out ) ? count( $out ) : 0;
			$low     = (int) get_post_meta( $id, '_aigs_low', true );
			$words   = (int) get_post_meta( $id, '_aigs_words', true );
			$is_prod = 'product' === $post->post_type;
			$found   = array();
			if ( ! $is_prod && ! $low && ! in_array( $id, $front, true ) && 0 === $inbound && ! isset( $menu[ $id ] ) ) {
				$found['orphan'] = array( 'suggest' => self::related( $post, array() ) );
			}
			if ( ! $is_prod && ! $low && 0 === $out && $words >= 300 ) {
				$found['no_internal_links'] = array( 'suggest' => self::related( $post, array() ) );
			}
			update_post_meta( $id, '_aigs_links_in', $inbound );
			AIGS_Findings::sync( $id, array( 'orphan', 'no_internal_links' ), $found );
			self::refresh_score( $id );
		}
	}

	/**
	 * Site-level rule evaluation.
	 *
	 * @return array rule => evidence
	 */
	public static function site_rules() {
		$f = array();
		if ( '0' === (string) get_option( 'blog_public' ) ) {
			$f['site_noindex'] = array();
		}
		if ( '' === (string) get_option( 'permalink_structure' ) ) {
			$f['site_plain_permalinks'] = array();
		}
		if ( '' === trim( (string) aigs_get( 'org_logo', '' ) ) || '' === trim( (string) aigs_get( 'org_sameas', '' ) ) ) {
			$f['site_entity_incomplete'] = array(
				'logo'   => '' !== trim( (string) aigs_get( 'org_logo', '' ) ),
				'sameas' => '' !== trim( (string) aigs_get( 'org_sameas', '' ) ),
			);
		}
		if ( 'disallow' === aigs_get( 'ai_access', 'allow' ) ) {
			$f['site_ai_blocked'] = array();
		}
		$local_missing = AIGS_Entity::local_missing();
		if ( $local_missing ) {
			$f['site_local_incomplete'] = array( 'missing' => $local_missing );
		}
		if ( '' === AIGS_SEO::active() && ! aigs_get( 'meta_fallback', 0 ) ) {
			$f['site_meta_fallback'] = array();
		}
		if ( '0' !== (string) get_option( 'blog_public' ) && 'disallow' !== aigs_get( 'ai_access', 'allow' ) ) {
			$probe = AIGS_Visibility::edge_probe();
			if ( '' === $probe['error'] && $probe['blocked'] ) {
				$f['edge_blocks_ai'] = array(
					'cdn'     => $probe['cdn'],
					'blocked' => $probe['blocked'],
					'tested'  => $probe['tested'],
				);
			}
			$since = (int) get_option( 'aigs_tracking_since', 0 );
			if ( aigs_get( 'enable_tracking', 1 ) && $since && ( time() - $since ) > 21 * DAY_IN_SECONDS && ! isset( $f['edge_blocks_ai'] ) && 0 === AIGS_Tracker::ai_visits( 21 ) ) {
				$f['ai_bots_absent'] = array( 'cdn' => $probe['cdn'] );
			}
		}
		$missing = AIGS_Tracker::bot_404s( 30, 10 );
		if ( $missing ) {
			$f['bot_404'] = array( 'urls' => $missing );
		}
		return $f;
	}

	/* ============================================================
	 * Entry points
	 * ============================================================ */

	/**
	 * Permission + nonce gate for scan endpoints.
	 */
	private function guard() {
		check_ajax_referer( 'aigs_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'omnigeo-ai-seo' ) ) );
		}
	}

	/**
	 * Shape a state for the browser.
	 *
	 * @param array $s State.
	 * @return array
	 */
	private function public_state( $s ) {
		$summary = get_option( 'aigs_scan_summary', array() );
		return array(
			'status'  => $s['status'],
			'done'    => (int) $s['done'],
			'total'   => (int) $s['total'],
			'skipped' => (int) $s['skipped'],
			'pct'     => $s['total'] ? min( 100, (int) floor( 100 * $s['done'] / $s['total'] ) ) : 100,
			'summary' => is_array( $summary ) ? $summary : array(),
		);
	}

	/**
	 * AJAX: start.
	 */
	public function ajax_start() {
		$this->guard();
		$force = ! empty( $_POST['force'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		if ( $force ) {
			// A full re-scan: forget fingerprints so every page is re-evaluated.
			delete_post_meta_by_key( '_aigs_scan_hash' );
		}
		wp_send_json_success( $this->public_state( self::start( 'manual' ) ) );
	}

	/**
	 * AJAX: one batch.
	 */
	public function ajax_step() {
		$this->guard();
		wp_send_json_success( $this->public_state( self::step() ) );
	}

	/**
	 * AJAX: re-check one post now (verifies a fix).
	 */
	public function ajax_scan_post() {
		check_ajax_referer( 'aigs_admin', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'omnigeo-ai-seo' ) ) );
		}
		$before = AIGS_Findings::open_rules( $post_id );
		self::scan_post( $post_id, true );
		$after = AIGS_Findings::open_rules( $post_id );
		wp_send_json_success(
			array(
				'score'    => (int) get_post_meta( $post_id, '_aigs_page_score', true ),
				'fixed'    => array_values( array_diff( $before, $after ) ),
				'open'     => array_values( $after ),
				'resolved' => array_map(
					static function ( $r ) {
						$m = AIGS_Rules::meta( $r );
						return $m ? $m['title'] : $r;
					},
					array_values( array_diff( $before, $after ) )
				),
			)
		);
	}

	/**
	 * Weekly background scan.
	 */
	public function cron_weekly() {
		if ( 'off' === aigs_get( 'scan_schedule', 'weekly' ) ) {
			return;
		}
		$s = self::state();
		if ( 'running' === $s['status'] && ( time() - (int) $s['started'] ) < DAY_IN_SECONDS ) {
			return;
		}
		self::start( 'cron' );
		$this->cron_continue();
	}

	/**
	 * Run batches for up to ~20 seconds, then hand over to a new cron event.
	 */
	public function cron_continue() {
		$deadline = microtime( true ) + 20;
		do {
			$s = self::step();
		} while ( 'running' === $s['status'] && microtime( true ) < $deadline );
		if ( 'running' === $s['status'] && ! wp_next_scheduled( 'aigs_scan_continue' ) ) {
			wp_schedule_single_event( time() + 60, 'aigs_scan_continue' );
		}
	}

	/**
	 * Keep findings fresh when an already-scanned post is saved.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public function on_save( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}
		if ( '' === get_post_meta( $post_id, '_aigs_scanned_at', true ) ) {
			return;
		}
		if ( 'publish' !== $post->post_status ) {
			AIGS_Findings::sync( $post_id, array_merge( AIGS_Rules::ids( 'page' ), AIGS_Rules::ids( 'graph' ) ), array() );
			return;
		}
		self::scan_post( $post_id, true );
	}
}
