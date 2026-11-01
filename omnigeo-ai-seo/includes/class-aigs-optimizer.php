<?php
/**
 * AI Autopilot: AI-written improvements for many pages, reviewed before they go live.
 *
 * For each selected page one request to the user's own AI provider proposes a
 * search title, a meta description, an FAQ section built only from the page's
 * own text, and question-style rewrites of its H2/H3 headings. Nothing changes
 * until the user approves it; every applied change can be undone, and content
 * edits also go through WordPress revisions.
 *
 * @package OmniGEO_Ai_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Proposal queue, apply and undo.
 */
class AIGS_Optimizer {

	const PROPOSAL = '_aigs_proposal';
	const UNDO     = '_aigs_opt_undo';
	const BATCH    = 10;

	/**
	 * Register hooks.
	 */
	public function hooks() {
		add_action( 'wp_ajax_aigs_opt_generate', array( $this, 'ajax_generate' ) );
		add_action( 'wp_ajax_aigs_opt_apply', array( $this, 'ajax_apply' ) );
		add_action( 'wp_ajax_aigs_opt_reject', array( $this, 'ajax_reject' ) );
		add_action( 'wp_ajax_aigs_opt_undo', array( $this, 'ajax_undo' ) );
	}

	/**
	 * Improvement kinds: key => [label, one-line help].
	 *
	 * @return array
	 */
	public static function kinds() {
		return array(
			'title'     => array( __( 'Search titles', 'omnigeo-ai-seo' ), __( 'Clear, specific titles of 30–60 characters that name the topic first.', 'omnigeo-ai-seo' ) ),
			'desc'      => array( __( 'Meta descriptions', 'omnigeo-ai-seo' ), __( 'A 120–155 character summary that answers the page\'s main question.', 'omnigeo-ai-seo' ) ),
			'faq'       => array( __( 'FAQ sections', 'omnigeo-ai-seo' ), __( '3–5 questions people ask, answered only with facts from the page, added as a visible FAQ plus FAQ schema.', 'omnigeo-ai-seo' ) ),
			'questions' => array( __( 'Question headings', 'omnigeo-ai-seo' ), __( 'Rewrites vague H2/H3 headings as the questions people type into ChatGPT. The text under them stays the same.', 'omnigeo-ai-seo' ) ),
		);
	}

	/**
	 * Can titles/descriptions be written for the active SEO plugin?
	 * All in One SEO keeps them in its own table, so we leave those to it.
	 *
	 * @return bool
	 */
	public static function can_write_snippets() {
		return 'aioseo' !== AIGS_SEO::active();
	}

	/* ------------------------------------------------------------
	 * Page analysis
	 * ------------------------------------------------------------ */

	/**
	 * Rendered HTML of a post's content.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public static function html( $post ) {
		$html = (string) $post->post_content;
		if ( function_exists( 'do_blocks' ) ) {
			$html = do_blocks( $html );
		}
		return strip_shortcodes( $html );
	}

	/**
	 * Which kinds a page would benefit from (pure apart from meta reads).
	 *
	 * @param WP_Post $post Post.
	 * @return array Kind keys.
	 */
	public static function needs( $post ) {
		$html  = self::html( $post );
		$words = AIGS_Text::word_count( wp_strip_all_tags( $html ) );
		$out   = array();
		if ( $words < 80 ) {
			return $out;
		}
		if ( self::can_write_snippets() ) {
			$t = AIGS_SEO::seo_title( $post->ID );
			$l = function_exists( 'mb_strlen' ) ? mb_strlen( $t ) : strlen( $t );
			if ( $l < 25 || $l > 65 ) {
				$out[] = 'title';
			}
			if ( '' === AIGS_SEO::meta_description( $post->ID ) ) {
				$out[] = 'desc';
			}
		}
		$s = AIGS_Answer::structure( $html );
		if ( $words >= 250 && ! $s['faq_blocks'] && ! get_post_meta( $post->ID, '_aigs_faqs', true ) ) {
			$out[] = 'faq';
		}
		$heads = count( $s['headings'] );
		if ( $heads >= 2 ) {
			$q = 0;
			foreach ( $s['headings'] as $h ) {
				$q += AIGS_Text::is_question( $h['text'] ) ? 1 : 0;
			}
			if ( $q * 2 < $heads ) {
				$out[] = 'questions';
			}
		}
		return $out;
	}

	/**
	 * Pages worth improving, most important first.
	 *
	 * @param int $limit Max pages.
	 * @return array [ [post, needs[]], ... ]
	 */
	public static function candidates( $limit = 30 ) {
		$types = get_post_types( array( 'public' => true ) );
		unset( $types['attachment'] );
		$posts = get_posts(
			array(
				'post_type'      => array_values( $types ),
				'post_status'    => 'publish',
				'posts_per_page' => 150,
				'orderby'        => 'modified',
				'has_password'   => false,
			)
		);
		$rows = array();
		foreach ( $posts as $p ) {
			if ( get_post_meta( $p->ID, self::PROPOSAL, true ) || AIGS_SEO::is_noindex( $p->ID ) ) {
				continue;
			}
			$needs = self::needs( $p );
			if ( $needs ) {
				$rows[] = array( $p, $needs, (int) get_post_meta( $p->ID, '_aigs_importance', true ) );
			}
		}
		usort(
			$rows,
			static function ( $a, $b ) {
				return array( $b[2], count( $b[1] ) ) <=> array( $a[2], count( $a[1] ) );
			}
		);
		return array_slice( $rows, 0, $limit );
	}

	/* ------------------------------------------------------------
	 * Prompt and parsing (pure; tested)
	 * ------------------------------------------------------------ */

	/**
	 * Build the system and user prompt.
	 *
	 * @param string $title    Page title.
	 * @param string $plain    Page text.
	 * @param array  $headings Heading texts.
	 * @param array  $kinds    Requested kinds.
	 * @param string $site     Site name.
	 * @return array [system, prompt]
	 */
	public static function prompt( $title, $plain, $headings, $kinds, $site ) {
		$system = 'You improve web pages so search engines and AI assistants can understand and quote them. Always write in the same language and script as the page text. Use only facts stated in the page text: never invent numbers, prices, dates, names, features or claims. Return only valid JSON, no markdown.';
		$want   = array();
		if ( in_array( 'title', $kinds, true ) ) {
			$want[] = '"title": a search result title of 30 to 60 characters that names the main topic first and is specific to this page. Do not add the site name.';
		}
		if ( in_array( 'desc', $kinds, true ) ) {
			$want[] = '"description": a meta description of 120 to 155 characters that directly answers the main question of the page.';
		}
		if ( in_array( 'faq', $kinds, true ) ) {
			$want[] = '"faq_heading": a short heading meaning "Frequently asked questions" in the page language. "faq": 3 to 5 objects {"q": a question a customer would ask, "a": a 25 to 60 word answer that uses only facts from the page}. Only include questions the page text really answers; return an empty list if there are none.';
		}
		if ( in_array( 'questions', $kinds, true ) && $headings ) {
			$want[] = '"headings": a list of {"from": the exact original heading text, "to": the same heading rewritten as the natural question a person would ask, which the text under it answers}. Only include headings that are not questions already and where the question is clearer. Keep "from" exactly as given.';
		}
		$prompt  = "Site: {$site}\nPage title: {$title}\n";
		if ( $headings ) {
			$prompt .= "Headings (H2/H3):\n- " . implode( "\n- ", $headings ) . "\n";
		}
		$prompt .= "\nPage text:\n{$plain}\n\nReturn one JSON object with these keys:\n" . implode( "\n", $want );
		return array( $system, $prompt );
	}

	/**
	 * Parse and validate the model's JSON against the page.
	 *
	 * @param string $text     Model output.
	 * @param array  $headings Existing heading texts.
	 * @param array  $kinds    Requested kinds.
	 * @return array|WP_Error Proposal items (kind => value).
	 */
	public static function parse( $text, $headings, $kinds ) {
		$text = trim( (string) $text );
		$text = preg_replace( '/^```(?:json)?\s*|\s*```$/i', '', $text );
		$a    = strpos( $text, '{' );
		$b    = strrpos( $text, '}' );
		$data = ( false !== $a && false !== $b ) ? json_decode( substr( $text, $a, $b - $a + 1 ), true ) : null;
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'aigs_parse', __( 'The AI reply could not be read. Try again, or pick a stronger model in Settings.', 'omnigeo-ai-seo' ) );
		}
		$clean = static function ( $s ) {
			return trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $s ) ), " \t\n\r\"“”«»" );
		};
		$len   = static function ( $s ) {
			return function_exists( 'mb_strlen' ) ? mb_strlen( $s ) : strlen( $s );
		};
		$out = array();
		if ( in_array( 'title', $kinds, true ) && isset( $data['title'] ) ) {
			$t = $clean( $data['title'] );
			if ( $len( $t ) >= 10 && $len( $t ) <= 70 ) {
				$out['title'] = $t;
			}
		}
		if ( in_array( 'desc', $kinds, true ) && isset( $data['description'] ) ) {
			$d = $clean( $data['description'] );
			if ( $len( $d ) >= 50 ) {
				$out['desc'] = AIGS_Head::trim_desc( $d );
			}
		}
		if ( in_array( 'faq', $kinds, true ) && isset( $data['faq'] ) && is_array( $data['faq'] ) ) {
			$faq = array();
			foreach ( $data['faq'] as $f ) {
				if ( ! is_array( $f ) || empty( $f['q'] ) || empty( $f['a'] ) ) {
					continue;
				}
				$q = $clean( $f['q'] );
				$ans = $clean( $f['a'] );
				$w = AIGS_Text::word_count( $ans );
				if ( '' !== $q && $w >= 5 && $w <= 120 ) {
					$faq[] = array(
						'q' => $q,
						'a' => $ans,
					);
				}
				if ( count( $faq ) >= 6 ) {
					break;
				}
			}
			if ( count( $faq ) >= 2 ) {
				$out['faq'] = array(
					'heading' => isset( $data['faq_heading'] ) && '' !== $clean( $data['faq_heading'] ) ? $clean( $data['faq_heading'] ) : __( 'Frequently asked questions', 'omnigeo-ai-seo' ),
					'items'   => $faq,
				);
			}
		}
		if ( in_array( 'questions', $kinds, true ) && isset( $data['headings'] ) && is_array( $data['headings'] ) ) {
			$known = array();
			foreach ( $headings as $h ) {
				$known[ self::norm( $h ) ] = $h;
			}
			$map = array();
			foreach ( $data['headings'] as $h ) {
				if ( ! is_array( $h ) || ! isset( $h['from'], $h['to'] ) ) {
					continue;
				}
				$from = self::norm( $h['from'] );
				$to   = $clean( $h['to'] );
				// Only headings that really exist on the page, and real changes.
				if ( isset( $known[ $from ] ) && '' !== $to && self::norm( $to ) !== $from && $len( $to ) <= 140 ) {
					$map[] = array(
						'from' => $known[ $from ],
						'to'   => $to,
					);
				}
			}
			if ( $map ) {
				$out['questions'] = $map;
			}
		}
		if ( ! $out ) {
			return new WP_Error( 'aigs_empty', __( 'No useful suggestion for this page. It may already be in good shape, or its text is too thin to build from.', 'omnigeo-ai-seo' ) );
		}
		return $out;
	}

	/**
	 * Normalise heading text for comparison.
	 *
	 * @param string $s Text.
	 * @return string
	 */
	private static function norm( $s ) {
		$s = html_entity_decode( wp_strip_all_tags( (string) $s ), ENT_QUOTES, 'UTF-8' );
		$s = trim( preg_replace( '/\s+/u', ' ', $s ) );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $s, 'UTF-8' ) : strtolower( $s );
	}

	/**
	 * Replace heading texts in post content (pure; tested). Only the text of
	 * matching H2/H3 elements changes; attributes and block comments stay.
	 *
	 * @param string $content Raw post content.
	 * @param array  $map     [ [from, to], ... ].
	 * @return string
	 */
	public static function rewrite_headings( $content, $map ) {
		$lookup = array();
		foreach ( $map as $m ) {
			$lookup[ self::norm( $m['from'] ) ] = $m['to'];
		}
		return preg_replace_callback(
			'#<(h[23])(\s[^>]*)?>(.*?)</\1>#is',
			static function ( $m ) use ( &$lookup ) {
				$key = self::norm( $m[3] );
				if ( ! isset( $lookup[ $key ] ) ) {
					return $m[0];
				}
				$to = $lookup[ $key ];
				unset( $lookup[ $key ] ); // Only the first matching heading.
				return '<' . $m[1] . $m[2] . '>' . esc_html( $to ) . '</' . $m[1] . '>';
			},
			(string) $content
		);
	}

	/**
	 * Visible FAQ markup to append to the content (blocks or classic HTML).
	 *
	 * @param string $heading Section heading.
	 * @param array  $items   [ [q, a], ... ].
	 * @param bool   $blocks  Block editor content?
	 * @return string
	 */
	public static function faq_markup( $heading, $items, $blocks ) {
		if ( $blocks ) {
			$out = "\n\n<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">" . esc_html( $heading ) . "</h2>\n<!-- /wp:heading -->";
			foreach ( $items as $f ) {
				$out .= "\n\n<!-- wp:heading {\"level\":3} -->\n<h3 class=\"wp-block-heading\">" . esc_html( $f['q'] ) . "</h3>\n<!-- /wp:heading -->";
				$out .= "\n\n<!-- wp:paragraph -->\n<p>" . esc_html( $f['a'] ) . "</p>\n<!-- /wp:paragraph -->";
			}
			return $out;
		}
		$out = "\n\n<h2>" . esc_html( $heading ) . '</h2>';
		foreach ( $items as $f ) {
			$out .= "\n<h3>" . esc_html( $f['q'] ) . "</h3>\n<p>" . esc_html( $f['a'] ) . '</p>';
		}
		return $out;
	}

	/* ------------------------------------------------------------
	 * Generate, apply, undo
	 * ------------------------------------------------------------ */

	/**
	 * Ask the AI for one page and store the proposal.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $kinds   Kinds.
	 * @return array|WP_Error
	 */
	public static function generate( $post_id, $kinds ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'aigs_post', __( 'Page not found.', 'omnigeo-ai-seo' ) );
		}
		if ( ! self::can_write_snippets() ) {
			$kinds = array_diff( $kinds, array( 'title', 'desc' ) );
		}
		$kinds = array_values( array_intersect( $kinds, array_keys( self::kinds() ) ) );
		if ( ! $kinds ) {
			return new WP_Error( 'aigs_kinds', __( 'Pick at least one improvement.', 'omnigeo-ai-seo' ) );
		}
		$html  = self::html( $post );
		$plain = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $html ) ) );
		if ( AIGS_Text::word_count( $plain ) < 80 ) {
			return new WP_Error( 'aigs_thin', __( 'This page has too little text to build suggestions from. Add the facts first.', 'omnigeo-ai-seo' ) );
		}
		$plain    = wp_trim_words( $plain, 1800, '' );
		$s        = AIGS_Answer::structure( $html );
		$headings = array_slice( wp_list_pluck( $s['headings'], 'text' ), 0, 25 );
		list( $system, $prompt ) = self::prompt( get_the_title( $post ), $plain, $headings, $kinds, aigs_business_name() );

		$res = AIGS_AI_Client::ask(
			$prompt,
			array(
				'system'     => $system,
				'max_tokens' => 1500,
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$items = self::parse( $res['text'], $headings, $kinds );
		if ( is_wp_error( $items ) ) {
			return $items;
		}
		$proposal = array(
			'time'  => time(),
			'model' => $res['model'],
			'items' => $items,
		);
		update_post_meta( $post_id, self::PROPOSAL, $proposal );
		return $proposal;
	}

	/**
	 * Meta keys for the snippet title/description in the active SEO plugin.
	 *
	 * @return array [title key, desc key] or empty when ours are used.
	 */
	public static function snippet_keys() {
		switch ( AIGS_SEO::active() ) {
			case 'yoast':
				return array( '_yoast_wpseo_title', '_yoast_wpseo_metadesc' );
			case 'rankmath':
				return array( 'rank_math_title', 'rank_math_description' );
			case 'seopress':
				return array( '_seopress_titles_title', '_seopress_titles_desc' );
		}
		return array( '_aigs_seo_title', '_aigs_seo_desc' );
	}

	/**
	 * Apply approved items, saving everything needed to undo.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $items   Approved (possibly edited) items.
	 * @return array|WP_Error What was applied.
	 */
	public static function apply( $post_id, $items ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'aigs_post', __( 'Page not found.', 'omnigeo-ai-seo' ) );
		}
		$keys = self::snippet_keys();
		$undo = array(
			'time'    => time(),
			'content' => null,
			'meta'    => array(),
		);
		$done = array();

		foreach ( array( 'title' => $keys[0], 'desc' => $keys[1] ) as $kind => $key ) {
			if ( isset( $items[ $kind ] ) && self::can_write_snippets() ) {
				$v = sanitize_text_field( $items[ $kind ] );
				if ( '' !== $v ) {
					$undo['meta'][ $key ] = get_post_meta( $post_id, $key, true );
					update_post_meta( $post_id, $key, $v );
					$done[] = $kind;
				}
			}
		}

		$content = (string) $post->post_content;
		$new     = $content;
		if ( ! empty( $items['questions'] ) && is_array( $items['questions'] ) ) {
			$map = array();
			foreach ( $items['questions'] as $m ) {
				if ( isset( $m['from'], $m['to'] ) && '' !== trim( (string) $m['to'] ) ) {
					$map[] = array(
						'from' => (string) $m['from'],
						'to'   => sanitize_text_field( $m['to'] ),
					);
				}
			}
			$rewritten = self::rewrite_headings( $new, $map );
			if ( $rewritten !== $new ) {
				$new    = $rewritten;
				$done[] = 'questions';
			}
		}
		if ( ! empty( $items['faq']['items'] ) && is_array( $items['faq']['items'] ) ) {
			$faq = array();
			foreach ( $items['faq']['items'] as $f ) {
				$q = isset( $f['q'] ) ? sanitize_text_field( $f['q'] ) : '';
				$a = isset( $f['a'] ) ? sanitize_textarea_field( $f['a'] ) : '';
				if ( '' !== $q && '' !== $a ) {
					$faq[] = array(
						'q' => $q,
						'a' => $a,
					);
				}
			}
			if ( $faq ) {
				$heading = isset( $items['faq']['heading'] ) ? sanitize_text_field( $items['faq']['heading'] ) : '';
				$heading = '' !== $heading ? $heading : __( 'Frequently asked questions', 'omnigeo-ai-seo' );
				// FAQ schema must match text people can see, so the section is added to the page too.
				$new .= self::faq_markup( $heading, $faq, function_exists( 'has_blocks' ) && has_blocks( $content ) );
				foreach ( array( '_aigs_faqs', '_aigs_schema_type' ) as $k ) {
					$undo['meta'][ $k ] = get_post_meta( $post_id, $k, true );
				}
				update_post_meta( $post_id, '_aigs_faqs', $faq );
				$type = (string) get_post_meta( $post_id, '_aigs_schema_type', true );
				if ( '' === $type || 'FAQPage' === $type ) {
					update_post_meta( $post_id, '_aigs_schema_type', 'FAQPage' );
				}
				$done[] = 'faq';
			}
		}
		if ( $new !== $content ) {
			$undo['content'] = $content;
			$r               = wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => wp_slash( $new ),
				),
				true
			);
			if ( is_wp_error( $r ) ) {
				return $r;
			}
		}
		if ( ! $done ) {
			return new WP_Error( 'aigs_nothing', __( 'Nothing was selected to apply.', 'omnigeo-ai-seo' ) );
		}
		$undo['done']  = $done;
		$undo['after'] = md5( $new );
		update_post_meta( $post_id, self::UNDO, $undo );
		delete_post_meta( $post_id, self::PROPOSAL );
		if ( class_exists( 'AIGS_Markdown' ) ) {
			AIGS_Markdown::flush();
		}
		return $done;
	}

	/**
	 * Undo the last applied batch on a page.
	 *
	 * @param int $post_id Post ID.
	 * @return bool|WP_Error
	 */
	public static function undo( $post_id ) {
		$undo = get_post_meta( $post_id, self::UNDO, true );
		if ( ! is_array( $undo ) ) {
			return new WP_Error( 'aigs_undo', __( 'Nothing to undo for this page.', 'omnigeo-ai-seo' ) );
		}
		$post = get_post( $post_id );
		// Never throw away edits made after the change: send people to revisions instead.
		if ( null !== $undo['content'] && $post && isset( $undo['after'] ) && md5( (string) $post->post_content ) !== $undo['after'] ) {
			return new WP_Error( 'aigs_edited', __( 'This page was edited after the change, so undo is not safe. Use Revisions in the editor to restore an older version.', 'omnigeo-ai-seo' ) );
		}
		foreach ( (array) $undo['meta'] as $k => $v ) {
			if ( '' === $v || null === $v || false === $v ) {
				delete_post_meta( $post_id, $k );
			} else {
				update_post_meta( $post_id, $k, $v );
			}
		}
		if ( null !== $undo['content'] ) {
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => wp_slash( $undo['content'] ),
				)
			);
		}
		delete_post_meta( $post_id, self::UNDO );
		if ( class_exists( 'AIGS_Markdown' ) ) {
			AIGS_Markdown::flush();
		}
		return true;
	}

	/**
	 * Pages with a pending proposal.
	 *
	 * @return WP_Post[]
	 */
	public static function pending() {
		return get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => 50,
				'meta_key'       => self::PROPOSAL, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- admin-only, small set.
			)
		);
	}

	/**
	 * Pages changed recently (undo available).
	 *
	 * @return WP_Post[]
	 */
	public static function applied() {
		return get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => 20,
				'meta_key'       => self::UNDO, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- admin-only, small set.
				'orderby'        => 'modified',
			)
		);
	}

	/* ------------------------------------------------------------
	 * AJAX
	 * ------------------------------------------------------------ */

	/**
	 * Check nonce and edit rights for the posted page.
	 *
	 * @return int Post ID.
	 */
	private static function guard() {
		check_ajax_referer( 'aigs_admin', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'omnigeo-ai-seo' ) ) );
		}
		return $post_id;
	}

	/**
	 * AJAX: generate suggestions for one page.
	 */
	public function ajax_generate() {
		$post_id = self::guard();
		$kinds   = isset( $_POST['kinds'] ) && is_array( $_POST['kinds'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['kinds'] ) ) : array();
		$res     = self::generate( $post_id, $kinds );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error(
				array(
					'message' => $res->get_error_message(),
					'stop'    => in_array( $res->get_error_code(), array( 'aigs_no_key', 'aigs_cap' ), true ),
				)
			);
		}
		wp_send_json_success( array( 'left' => AIGS_AI_Client::remaining() ) );
	}

	/**
	 * AJAX: apply approved items for one page.
	 */
	public function ajax_apply() {
		$post_id = self::guard();
		$raw     = isset( $_POST['items'] ) ? json_decode( wp_unslash( $_POST['items'] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON; every field is sanitized in apply().
		if ( ! is_array( $raw ) ) {
			wp_send_json_error( array( 'message' => __( 'Nothing was selected to apply.', 'omnigeo-ai-seo' ) ) );
		}
		$res = self::apply( $post_id, $raw );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}
		wp_send_json_success( array( 'message' => __( 'Applied. You can undo it below at any time.', 'omnigeo-ai-seo' ) ) );
	}

	/**
	 * AJAX: drop a proposal.
	 */
	public function ajax_reject() {
		$post_id = self::guard();
		delete_post_meta( $post_id, self::PROPOSAL );
		wp_send_json_success();
	}

	/**
	 * AJAX: undo.
	 */
	public function ajax_undo() {
		$post_id = self::guard();
		$res     = self::undo( $post_id );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}
		wp_send_json_success( array( 'message' => __( 'Restored the previous version.', 'omnigeo-ai-seo' ) ) );
	}
}
