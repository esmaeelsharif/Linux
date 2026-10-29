<?php
/**
 * llms.txt generation/serving + AI crawler access rules (robots.txt).
 *
 * @package OmniGEO_Ai_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves /llms.txt and injects Allow/Disallow rules for AI bots into robots.txt.
 */
class AIGS_LLMS {

	/**
	 * Register hooks.
	 */
	public function hooks() {
		add_action( 'init', aigs_guard( array( $this, 'add_rewrite' ) ) );
		add_filter( 'query_vars', aigs_guard( array( $this, 'query_vars' ), true ) );
		add_action( 'template_redirect', aigs_guard( array( $this, 'maybe_serve' ) ) );
		add_filter( 'robots_txt', aigs_guard( array( $this, 'robots' ), true ), 20, 1 );
	}

	/**
	 * Add the /llms.txt rewrite rule.
	 */
	public function add_rewrite() {
		add_rewrite_rule( '^llms\.txt$', 'index.php?aigs_llms=1', 'top' );
		add_rewrite_rule( '^llms-full\.txt$', 'index.php?aigs_llms=full', 'top' );
		// New addresses arrive with updates: refresh the rules once per version.
		// Flushed on wp_loaded so rules other plugins add later on init are kept.
		if ( is_admin() && AIGS_VERSION !== get_option( 'aigs_rewrite_ver' ) ) {
			add_action( 'wp_loaded', array( __CLASS__, 'refresh_rewrites' ) );
		}
	}

	/**
	 * Rebuild rewrite rules once after an update.
	 */
	public static function refresh_rewrites() {
		flush_rewrite_rules( false );
		update_option( 'aigs_rewrite_ver', AIGS_VERSION, false );
	}

	/**
	 * Register query var.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function query_vars( $vars ) {
		$vars[] = 'aigs_llms';
		return $vars;
	}

	/**
	 * Serve llms.txt when requested.
	 */
	public function maybe_serve() {
		$which = get_query_var( 'aigs_llms' );
		if ( ! $which ) {
			return;
		}
		$full = 'full' === $which;
		if ( ! aigs_get( 'enable_llms', 1 ) || ( $full && ! aigs_get( 'tb_markdown', 1 ) ) ) {
			status_header( 404 );
			exit;
		}
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		echo $full ? AIGS_Markdown::full( $this ) : $this->get_content(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain-text (text/plain) file body; HTML escaping would corrupt the markdown and URLs.
		exit;
	}

	/**
	 * Get llms.txt content (custom or auto-generated).
	 *
	 * @return string
	 */
	public function get_content() {
		$custom = aigs_get( 'llms_txt', '' );
		if ( trim( $custom ) !== '' ) {
			return $custom;
		}
		return $this->generate();
	}

	/**
	 * Auto-generate an llms.txt from site data and recent content.
	 *
	 * @return string
	 */
	public function generate() {
		// H1: site/brand name with safe fallbacks (never empty).
		$name = trim( (string) aigs_get( 'business_name', '' ) );
		if ( '' === $name ) {
			$name = trim( (string) get_bloginfo( 'name' ) );
		}
		if ( '' === $name ) {
			$name = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		}
		$out = '# ' . $name . "\n\n";

		// Blockquote summary (always present).
		$industry = trim( (string) aigs_get( 'industry', '' ) );
		$location = trim( (string) aigs_get( 'location', '' ) );
		$summary  = trim( (string) get_bloginfo( 'description' ) );
		if ( '' === $summary ) {
			$bits    = array_filter( array( $industry, $location ) );
			$summary = $bits ? $name . ': ' . implode( ', ', $bits ) . '.' : $name . '.';
		}
		$out .= '> ' . $this->one_line( $summary ) . "\n\n";

		// Split pages into key vs low-priority.
		$pages       = get_pages( array( 'sort_column' => 'menu_order', 'number' => 30 ) );
		$privacy_id  = (int) get_option( 'wp_page_for_privacy_policy' );
		$front_id    = (int) get_option( 'page_on_front' );
		$posts_id    = (int) get_option( 'page_for_posts' );
		$key         = array();
		$optional    = array();
		foreach ( (array) $pages as $p ) {
			// Skip the page that IS the front page (the site root represents it),
			// and the blog posts page (its posts are listed below).
			if ( ( $front_id && (int) $p->ID === $front_id ) || ( $posts_id && (int) $p->ID === $posts_id ) ) {
				continue;
			}
			if ( $this->is_low_priority( $p, $privacy_id ) ) {
				$optional[] = $p;
			} else {
				$key[] = $p;
			}
		}

		// Always list the real homepage (the site root), not a page slug.
		$out .= "## Key pages\n\n";
		$home_desc = $this->one_line( $summary );
		$out      .= '- [Home](' . home_url( '/' ) . ')' . ( '' !== $home_desc ? ': ' . $home_desc : '' ) . "\n";
		foreach ( $key as $p ) {
			$out .= $this->link_line( $p );
		}
		$out .= "\n";

		$posts = get_posts( array( 'numberposts' => 20, 'post_status' => 'publish' ) );
		if ( $posts ) {
			$out .= "## Recent posts\n\n";
			foreach ( $posts as $p ) {
				$out .= $this->link_line( $p );
			}
			$out .= "\n";
		}

		/**
		 * Allow modules (e.g. WooCommerce) to append extra llms.txt sections.
		 *
		 * @param array $sections Array of markdown section strings.
		 */
		$sections = apply_filters( 'aigs_llms_sections', array() );
		foreach ( (array) $sections as $section ) {
			$out .= $section . "\n\n";
		}

		// Optional: low-priority pages and the sitemap.
		$out .= "## Optional\n\n";
		foreach ( $optional as $p ) {
			$out .= $this->link_line( $p );
		}
		if ( aigs_get( 'tb_markdown', 1 ) ) {
			$out .= '- [Full text](' . home_url( '/llms-full.txt' ) . "): The main pages as one Markdown file. Add ?format=markdown to any page address for its Markdown copy.\n";
		}
		$out .= '- [Sitemap](' . home_url( '/sitemap.xml' ) . "): Full XML list of site URLs.\n";

		return $out;
	}

	/**
	 * Build a "- [Title](url): description" line for a post or page.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	private function link_line( $post ) {
		$title = wp_strip_all_tags( get_the_title( $post ) );
		$line  = '- [' . $title . '](' . get_permalink( $post->ID ) . ')';
		$desc  = $this->describe( $post );
		if ( '' !== $desc ) {
			$line .= ': ' . $desc;
		}
		return $line . "\n";
	}

	/**
	 * Best-effort one-line description: SEO meta, then excerpt, then content.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	private function describe( $post ) {
		if ( class_exists( 'AIGS_SEO' ) ) {
			$meta = AIGS_SEO::meta_description( $post->ID );
			if ( '' !== trim( (string) $meta ) ) {
				return $this->one_line( $meta );
			}
		}
		$text = trim( (string) $post->post_excerpt );
		if ( '' === $text ) {
			$text = (string) $post->post_content;
		}
		return $this->one_line( strip_shortcodes( $text ) );
	}

	/**
	 * Collapse to a single trimmed line, capped at ~160 chars.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private function one_line( $text ) {
		$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $text ) ) );
		if ( '' === $text ) {
			return '';
		}
		if ( mb_strlen( $text ) > 160 ) {
			$text = mb_substr( $text, 0, 157 );
			$sp   = mb_strrpos( $text, ' ' );
			if ( $sp ) {
				$text = mb_substr( $text, 0, $sp );
			}
			$text .= '...';
		}
		return $text;
	}

	/**
	 * Is this page low-priority (boilerplate) for AI?
	 *
	 * @param WP_Post $post       Page.
	 * @param int     $privacy_id Privacy policy page ID.
	 * @return bool
	 */
	private function is_low_priority( $post, $privacy_id ) {
		if ( $privacy_id && (int) $post->ID === $privacy_id ) {
			return true;
		}
		$needles = array( 'privacy', 'terms', 'sample-page', 'sample page', 'contact', 'advertise', 'cookie', 'disclaimer', 'dmca', 'refund', 'shipping', 'returns', 'legal', 'checkout', 'cart', 'my-account' );
		$hay     = strtolower( $post->post_name . ' ' . $post->post_title );
		foreach ( $needles as $n ) {
			if ( false !== strpos( $hay, $n ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Inject AI bot rules into robots.txt.
	 *
	 * @param string $output Existing robots.txt.
	 * @return string
	 */
	public function robots( $output ) {
		// Never loosen a site that asked search engines to stay away.
		if ( '0' === (string) get_option( 'blog_public' ) ) {
			return $output;
		}
		$mode   = aigs_get( 'ai_access', 'allow' );
		$blocks = 'custom' === $mode ? self::custom_blocks( aigs_get( 'ai_blocked', array() ) ) : self::blocked_tokens( $mode );

		$signal = self::content_signal_line();
		if ( '' !== $signal ) {
			$output = self::add_to_star_group( $output, $signal );
		}

		$output .= "\n# AI crawler rules (OmniGEO Ai SEO)\n";
		if ( $blocks ) {
			// One group with several User-agent lines (RFC 9309), so bots not listed
			// keep following the default "User-agent: *" rules above.
			foreach ( $blocks as $bot ) {
				$output .= 'User-agent: ' . $bot . "\n";
			}
			$output .= "Disallow: /\n";
		} else {
			$output .= "# All AI crawlers follow the default rules above.\n";
		}
		$extra = trim( (string) aigs_get( 'robots_extra', '' ) );
		if ( '' !== $extra ) {
			$output .= "\n# Custom rules (OmniGEO Ai SEO)\n" . $extra . "\n";
		}
		if ( aigs_get( 'enable_llms', 1 ) ) {
			$output .= "\n# llms.txt: " . home_url( '/llms.txt' ) . "\n";
		}
		return $output;
	}

	/**
	 * Content Signals line (contentsignals.org) from the settings, or ''.
	 *
	 * @return string
	 */
	public static function content_signal_line() {
		if ( ! aigs_get( 'cs_enabled', 0 ) ) {
			return '';
		}
		$parts = array();
		foreach ( array( 'search' => 'cs_search', 'ai-input' => 'cs_ai_input', 'ai-train' => 'cs_ai_train' ) as $signal => $key ) {
			$parts[] = $signal . '=' . ( aigs_get( $key, 'ai-train' === $signal ? 'no' : 'yes' ) === 'no' ? 'no' : 'yes' );
		}
		return 'Content-Signal: ' . implode( ', ', $parts );
	}

	/**
	 * Put a line inside the first "User-agent: *" group, or add that group.
	 *
	 * @param string $output robots.txt.
	 * @param string $line   Line to add.
	 * @return string
	 */
	public static function add_to_star_group( $output, $line ) {
		if ( preg_match( '/^User-agent:\s*\*\s*$/mi', $output, $m, PREG_OFFSET_CAPTURE ) ) {
			$at = $m[0][1] + strlen( $m[0][0] );
			return substr( $output, 0, $at ) . "\n" . $line . substr( $output, $at );
		}
		return rtrim( $output ) . "\n\nUser-agent: *\n" . $line . "\nAllow: /\n";
	}

	/**
	 * Tokens to block in "choose per crawler" mode (only known tokens survive).
	 *
	 * @param array $chosen Tokens the user switched off.
	 * @return array
	 */
	public static function custom_blocks( $chosen ) {
		$known = array_merge( array_keys( AIGS_Tracker::bot_map() ), array_keys( AIGS_Tracker::robots_only_tokens() ) );
		return array_values( array_intersect( $known, (array) $chosen ) );
	}

	/**
	 * User-agent tokens to block for an access mode.
	 *
	 * allow       : none (bots use the site's default rules)
	 * search_only : block model-training crawlers, keep AI search and user fetches
	 * disallow    : block every known AI crawler
	 *
	 * @param string $mode Mode.
	 * @return array
	 */
	public static function blocked_tokens( $mode ) {
		$out = array();
		if ( 'search_only' === $mode || 'disallow' === $mode ) {
			foreach ( AIGS_Tracker::bot_map() as $bot => $info ) {
				// Never block classic search engines through this setting.
				if ( in_array( $bot, array( 'bingbot', 'Applebot' ), true ) ) {
					continue;
				}
				if ( 'disallow' === $mode || 'training' === $info[2] ) {
					$out[] = $bot;
				}
			}
			foreach ( AIGS_Tracker::robots_only_tokens() as $token => $class ) {
				if ( 'disallow' === $mode || 'training' === $class ) {
					$out[] = $token;
				}
			}
		}
		return array_values( array_unique( $out ) );
	}
}
