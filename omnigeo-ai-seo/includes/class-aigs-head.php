<?php
/**
 * Search and sharing basics for sites without an SEO plugin.
 *
 * Snippet title and description per post (with titles and descriptions left
 * behind by a removed SEO plugin used automatically), Open Graph and X cards,
 * webmaster verification tags, Google Analytics 4 or Tag Manager, external
 * link handling and last-modified dates in the WordPress sitemap.
 *
 * Anything an active SEO plugin already prints is skipped, so the two never
 * duplicate tags.
 *
 * @package OmniGEO_Ai_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Head tags, snippet editor and link tweaks.
 */
class AIGS_Head {

	const NONCE = 'aigs_snippet_nonce';

	/**
	 * Register hooks.
	 */
	public function hooks() {
		add_action( 'wp_head', aigs_guard( array( $this, 'print_head' ) ), 1 );
		add_filter( 'pre_get_document_title', aigs_guard( array( $this, 'document_title' ), true ), 20 );
		add_action( 'wp_enqueue_scripts', aigs_guard( array( $this, 'analytics' ) ) );
		add_action( 'wp_body_open', aigs_guard( array( $this, 'gtm_noscript' ) ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', aigs_guard( array( $this, 'save' ) ), 10, 2 );
		add_action( 'wp_ajax_aigs_snippet_save', array( $this, 'ajax_save' ) );
		if ( 'off' !== aigs_get( 'tb_ext_links', 'off' ) ) {
			add_filter( 'the_content', aigs_guard( array( $this, 'filter_links' ), true ), 99 );
		}
		if ( aigs_get( 'tb_lastmod', 1 ) ) {
			add_filter( 'wp_sitemaps_posts_entry', aigs_guard( array( $this, 'sitemap_lastmod' ), true ), 10, 2 );
		}
	}

	/* ------------------------------------------------------------
	 * Snippet data
	 * ------------------------------------------------------------ */

	/**
	 * Is our snippet editor in charge (no SEO plugin active and turned on)?
	 *
	 * @return bool
	 */
	public static function snippets_on() {
		return '' === AIGS_SEO::active() && (bool) aigs_get( 'tb_snippets', 1 );
	}

	/**
	 * Meta keys other SEO plugins leave behind after they are removed.
	 *
	 * @return array field => meta keys
	 */
	public static function leftover_keys() {
		return array(
			'title' => array( '_yoast_wpseo_title', 'rank_math_title', '_seopress_titles_title', '_aioseo_title' ),
			'desc'  => array( '_yoast_wpseo_metadesc', 'rank_math_description', '_seopress_titles_desc', '_aioseo_description' ),
		);
	}

	/**
	 * A value with template variables (%%title%%, %title%, #post_title) means
	 * "use the default" in those plugins, so it is not usable as plain text.
	 *
	 * @param string $v Value.
	 * @return bool
	 */
	public static function is_plain( $v ) {
		$v = trim( (string) $v );
		return '' !== $v && ! preg_match( '/%%?[a-z_]+%%?|#[a-z_]+/i', $v );
	}

	/**
	 * Snippet title or description saved for a post: ours first, then one left
	 * by a removed SEO plugin. '' when none.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $field   title|desc.
	 * @param bool   $own     Only our own value.
	 * @return string
	 */
	public static function snippet( $post_id, $field, $own = false ) {
		$v = trim( (string) get_post_meta( $post_id, 'title' === $field ? '_aigs_seo_title' : '_aigs_seo_desc', true ) );
		if ( '' !== $v || $own ) {
			return $v;
		}
		$keys = self::leftover_keys();
		foreach ( $keys[ 'title' === $field ? 'title' : 'desc' ] as $k ) {
			$l = (string) get_post_meta( $post_id, $k, true );
			if ( self::is_plain( $l ) ) {
				return trim( wp_strip_all_tags( $l ) );
			}
		}
		return '';
	}

	/**
	 * Automatic description: the excerpt, else the opening text, ~155 chars.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public static function auto_description( $post ) {
		$text = trim( (string) $post->post_excerpt );
		if ( '' === $text ) {
			$text = (string) $post->post_content;
			if ( function_exists( 'has_blocks' ) && has_blocks( $text ) ) {
				$text = excerpt_remove_blocks( $text );
			}
			$text = strip_shortcodes( $text );
		}
		return self::trim_desc( $text );
	}

	/**
	 * Clean text and cut it at a word boundary near 155 characters.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function trim_desc( $text ) {
		$text = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' ) ) );
		if ( mb_strlen( $text ) > 158 ) {
			$text = mb_substr( $text, 0, 155 );
			$sp   = mb_strrpos( $text, ' ' );
			$text = ( $sp ? mb_substr( $text, 0, $sp ) : $text ) . '…';
		}
		return $text;
	}

	/**
	 * The description a post shows to search engines and assistants.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public static function description( $post ) {
		$d = AIGS_SEO::meta_description( $post->ID );
		return '' !== $d ? $d : self::auto_description( $post );
	}

	/* ------------------------------------------------------------
	 * Front end
	 * ------------------------------------------------------------ */

	/**
	 * Replace the document title with the snippet title.
	 *
	 * @param string $title Title.
	 * @return string
	 */
	public function document_title( $title ) {
		if ( ! self::snippets_on() || ! is_singular() ) {
			return $title;
		}
		$t = self::snippet( get_queried_object_id(), 'title' );
		return '' !== $t ? $t : $title;
	}

	/**
	 * Print description, verification and social tags.
	 */
	public function print_head() {
		$seo = '' !== AIGS_SEO::active();
		$out = '';

		// Description (only when no SEO plugin prints one).
		if ( ! $seo && is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof WP_Post && ! post_password_required( $post ) ) {
				$d = aigs_get( 'tb_snippets', 1 ) ? self::snippet( $post->ID, 'desc' ) : '';
				if ( '' === $d && aigs_get( 'meta_fallback', 0 ) ) {
					$d = self::auto_description( $post );
				}
				if ( '' !== $d ) {
					$out .= '<meta name="description" content="' . esc_attr( $d ) . '" />' . "\n";
				}
			}
		}

		// Verification codes are safe to print next to any SEO plugin only if it has none of its own.
		if ( is_front_page() ) {
			foreach ( self::verification_tags() as $name => $setting ) {
				$code = self::verification_code( aigs_get( $setting, '' ) );
				if ( '' !== $code ) {
					$out .= '<meta name="' . esc_attr( $name ) . '" content="' . esc_attr( $code ) . '" />' . "\n";
				}
			}
		}

		if ( ! $seo && aigs_get( 'tb_social', 0 ) && ! self::other_og() ) {
			foreach ( self::social_tags() as $tag ) {
				$attr = 0 === strpos( $tag[0], 'twitter:' ) ? 'name' : 'property';
				$out .= '<meta ' . $attr . '="' . esc_attr( $tag[0] ) . '" content="' . esc_attr( $tag[1] ) . '" />' . "\n";
			}
		}
		if ( '' !== $out ) {
			echo "<!-- OmniGEO Ai SEO -->\n" . $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every attribute above is escaped with esc_attr.
		}
	}

	/**
	 * Does another plugin already print Open Graph tags?
	 *
	 * @return bool
	 */
	public static function other_og() {
		// Jetpack prints Open Graph through this filter when its module is on.
		return (bool) apply_filters( 'jetpack_enable_open_graph', false ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- reading Jetpack's own filter.
	}

	/**
	 * Verification meta names mapped to their setting.
	 *
	 * @return array
	 */
	public static function verification_tags() {
		return array(
			'google-site-verification' => 'verify_google',
			'msvalidate.01'            => 'verify_bing',
			'yandex-verification'      => 'verify_yandex',
			'baidu-site-verification'  => 'verify_baidu',
			'p:domain_verify'          => 'verify_pinterest',
		);
	}

	/**
	 * Accept either the bare code or the whole <meta> tag the service shows.
	 *
	 * @param string $raw Input.
	 * @return string
	 */
	public static function verification_code( $raw ) {
		$raw = trim( (string) $raw );
		if ( preg_match( '/content\s*=\s*["\']([^"\']+)["\']/i', $raw, $m ) ) {
			$raw = $m[1];
		}
		return preg_match( '/^[A-Za-z0-9_\-.:=+\/]{4,120}$/', $raw ) ? $raw : '';
	}

	/**
	 * Open Graph and X card values for the current view.
	 *
	 * @return array [ [property, content], ... ]
	 */
	public static function social_tags() {
		$tags  = array();
		$site  = aigs_business_name();
		$image = '';
		$type  = 'website';
		if ( is_singular() ) {
			$post = get_queried_object();
			if ( ! $post instanceof WP_Post || post_password_required( $post ) ) {
				return array();
			}
			$title = self::snippet( $post->ID, 'title' );
			$title = '' !== $title ? $title : wp_strip_all_tags( get_the_title( $post ) );
			$desc  = self::description( $post );
			$url   = get_permalink( $post );
			$type  = is_front_page() ? 'website' : ( ( class_exists( 'AIGS_WooCommerce' ) && AIGS_WooCommerce::is_product() ) ? 'product' : 'article' );
			if ( has_post_thumbnail( $post ) ) {
				$image = (string) get_the_post_thumbnail_url( $post, 'large' );
			}
		} elseif ( is_front_page() || is_home() ) {
			$title = $site;
			$desc  = (string) get_bloginfo( 'description' );
			$url   = home_url( '/' );
		} else {
			return array();
		}
		if ( '' === $image ) {
			$image = (string) aigs_get( 'og_image', '' );
		}
		$tags[] = array( 'og:locale', get_locale() );
		$tags[] = array( 'og:type', $type );
		$tags[] = array( 'og:title', html_entity_decode( $title, ENT_QUOTES, 'UTF-8' ) );
		if ( '' !== trim( $desc ) ) {
			$tags[] = array( 'og:description', $desc );
		}
		$tags[] = array( 'og:url', $url );
		$tags[] = array( 'og:site_name', $site );
		if ( '' !== $image ) {
			$tags[] = array( 'og:image', $image );
		}
		if ( 'article' === $type && isset( $post ) ) {
			$tags[] = array( 'article:published_time', get_post_time( 'c', true, $post ) );
			$tags[] = array( 'article:modified_time', get_post_modified_time( 'c', true, $post ) );
		}
		$tags[] = array( 'twitter:card', '' !== $image ? 'summary_large_image' : 'summary' );
		$handle = ltrim( trim( (string) aigs_get( 'twitter_site', '' ) ), '@' );
		if ( '' !== $handle ) {
			$tags[] = array( 'twitter:site', '@' . $handle );
		}
		return $tags;
	}

	/**
	 * Load GA4 or Google Tag Manager (skipped when Site Kit is active, and for
	 * logged-in editors so your own visits do not count).
	 */
	public function analytics() {
		if ( defined( 'GOOGLESITEKIT_VERSION' ) || current_user_can( 'edit_posts' ) ) {
			return;
		}
		$gtm = self::tag_id( aigs_get( 'gtm_id', '' ), 'GTM' );
		$ga4 = self::tag_id( aigs_get( 'ga4_id', '' ), 'G' );
		if ( '' !== $gtm ) {
			wp_register_script( 'aigs-gtm', false, array(), AIGS_VERSION, false );
			wp_enqueue_script( 'aigs-gtm' );
			wp_add_inline_script( 'aigs-gtm', "(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer'," . wp_json_encode( $gtm ) . ');' );
		} elseif ( '' !== $ga4 ) {
			wp_enqueue_script( 'aigs-gtag', 'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( $ga4 ), array(), null, false ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google serves the current version; a version query would break caching.
			wp_add_inline_script( 'aigs-gtag', 'window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config",' . wp_json_encode( $ga4 ) . ');' );
		}
	}

	/**
	 * GTM fallback for visitors without JavaScript.
	 */
	public function gtm_noscript() {
		if ( defined( 'GOOGLESITEKIT_VERSION' ) || current_user_can( 'edit_posts' ) ) {
			return;
		}
		$gtm = self::tag_id( aigs_get( 'gtm_id', '' ), 'GTM' );
		if ( '' !== $gtm ) {
			echo '<noscript><iframe src="' . esc_url( 'https://www.googletagmanager.com/ns.html?id=' . $gtm ) . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n";
		}
	}

	/**
	 * Validate a Google tag ID (G-XXXX or GTM-XXXX).
	 *
	 * @param string $raw    Input.
	 * @param string $prefix G|GTM.
	 * @return string
	 */
	public static function tag_id( $raw, $prefix ) {
		$raw = strtoupper( trim( (string) $raw ) );
		return preg_match( '/^' . $prefix . '-[A-Z0-9]{4,15}$/', $raw ) ? $raw : '';
	}

	/**
	 * Add target/rel to external links in post content.
	 *
	 * @param string $html Content.
	 * @return string
	 */
	public function filter_links( $html ) {
		return self::external_links( $html, aigs_get( 'tb_ext_links', 'off' ), (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	}

	/**
	 * Pure link rewriter (tested).
	 *
	 * @param string $html Content.
	 * @param string $mode off|blank|blank_nofollow|nofollow.
	 * @param string $home Site host.
	 * @return string
	 */
	public static function external_links( $html, $mode, $home ) {
		if ( 'off' === $mode || false === stripos( (string) $html, '<a ' ) ) {
			return $html;
		}
		$home = strtolower( preg_replace( '/^www\./i', '', $home ) );
		return preg_replace_callback(
			'/<a\s[^>]*>/i',
			static function ( $m ) use ( $mode, $home ) {
				$tag = $m[0];
				if ( ! preg_match( '/\shref\s*=\s*(["\'])(https?:)?\/\/([^\/"\'?#:]+)/i', $tag, $h ) ) {
					return $tag;
				}
				$host = strtolower( preg_replace( '/^www\./i', '', $h[3] ) );
				if ( $host === $home || '.' . $home === substr( $host, -strlen( '.' . $home ) ) ) {
					return $tag;
				}
				$rel = array();
				if ( preg_match( '/\srel\s*=\s*(["\'])(.*?)\1/i', $tag, $r ) ) {
					$rel = preg_split( '/\s+/', strtolower( trim( $r[2] ) ) );
					$tag = str_replace( $r[0], '', $tag );
				}
				if ( 'nofollow' !== $mode && ! preg_match( '/\starget\s*=/i', $tag ) ) {
					$tag = substr( $tag, 0, -1 ) . ' target="_blank">';
				}
				if ( preg_match( '/\starget\s*=\s*(["\'])_blank\1/i', $tag ) ) {
					$rel[] = 'noopener';
				}
				if ( 'blank_nofollow' === $mode || 'nofollow' === $mode ) {
					// Leave sponsored/ugc alone: they already tell crawlers the same thing.
					if ( ! array_intersect( $rel, array( 'sponsored', 'ugc' ) ) ) {
						$rel[] = 'nofollow';
					}
				}
				$rel = array_values( array_unique( array_filter( $rel ) ) );
				if ( $rel ) {
					$tag = substr( $tag, 0, -1 ) . ' rel="' . esc_attr( implode( ' ', $rel ) ) . '">';
				}
				return $tag;
			},
			$html
		);
	}

	/**
	 * Add last-modified dates to the WordPress core sitemap.
	 *
	 * @param array   $entry Entry.
	 * @param WP_Post $post  Post.
	 * @return array
	 */
	public function sitemap_lastmod( $entry, $post ) {
		if ( $post instanceof WP_Post && empty( $entry['lastmod'] ) ) {
			$entry['lastmod'] = get_post_modified_time( 'c', true, $post );
		}
		return $entry;
	}

	/* ------------------------------------------------------------
	 * Editor
	 * ------------------------------------------------------------ */

	/**
	 * Add the snippet box (only when no SEO plugin is active).
	 */
	public function add_meta_box() {
		if ( ! self::snippets_on() ) {
			return;
		}
		foreach ( get_post_types( array( 'public' => true ) ) as $type ) {
			if ( 'attachment' === $type ) {
				continue;
			}
			add_meta_box( 'aigs-snippet', __( 'Search & AI snippet', 'omnigeo-ai-seo' ), array( $this, 'render' ), $type, 'normal', 'high' );
		}
	}

	/**
	 * Render the snippet box with a live search preview.
	 *
	 * @param WP_Post $post Post.
	 */
	public function render( $post ) {
		wp_nonce_field( self::NONCE, self::NONCE );
		$title    = self::snippet( $post->ID, 'title', true );
		$desc     = self::snippet( $post->ID, 'desc', true );
		$lt       = '' === $title ? self::snippet( $post->ID, 'title' ) : '';
		$ld       = '' === $desc ? self::snippet( $post->ID, 'desc' ) : '';
		$fallback = self::auto_description( $post );
		self::snippet_fields( $post->ID, '' !== $title ? $title : $lt, '' !== $desc ? $desc : $ld, $fallback, 'aigs_snippet' );
		if ( '' !== $lt || '' !== $ld ) {
			echo '<p class="description">' . esc_html__( 'Filled in from the SEO plugin you used before. Nothing to import: save to keep it, or edit it.', 'omnigeo-ai-seo' ) . '</p>';
		}
	}

	/**
	 * Shared snippet fields with a Google-style preview (editor and bulk table).
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $title    Title value.
	 * @param string $desc     Description value.
	 * @param string $fallback Automatic description used when empty.
	 * @param string $name     Field name prefix.
	 */
	public static function snippet_fields( $post_id, $title, $desc, $fallback, $name ) {
		$url = get_permalink( $post_id );
		?>
		<div class="aigs-snippet" data-site="<?php echo esc_attr( aigs_business_name() ); ?>">
			<div class="aigs-serp" aria-hidden="true">
				<div class="aigs-serp__url"><?php echo esc_html( preg_replace( '#^https?://#', '', (string) $url ) ); ?></div>
				<div class="aigs-serp__title" data-default="<?php echo esc_attr( wp_strip_all_tags( get_the_title( $post_id ) ) ); ?>"></div>
				<div class="aigs-serp__desc" data-default="<?php echo esc_attr( $fallback ); ?>"></div>
			</div>
			<p>
				<label><?php esc_html_e( 'Title in search results', 'omnigeo-ai-seo' ); ?></label>
				<input type="text" class="widefat aigs-snippet__title" name="<?php echo esc_attr( $name ); ?>[title]" value="<?php echo esc_attr( $title ); ?>" placeholder="<?php echo esc_attr( wp_strip_all_tags( get_the_title( $post_id ) ) ); ?>" maxlength="200" />
				<span class="aigs-count" data-min="30" data-max="60"></span>
			</p>
			<p>
				<label><?php esc_html_e( 'Description', 'omnigeo-ai-seo' ); ?></label>
				<textarea class="widefat aigs-snippet__desc" rows="2" name="<?php echo esc_attr( $name ); ?>[desc]" placeholder="<?php echo esc_attr( $fallback ); ?>" maxlength="400"><?php echo esc_textarea( $desc ); ?></textarea>
				<span class="aigs-count" data-min="70" data-max="160"></span>
			</p>
		</div>
		<?php
	}

	/**
	 * Save the snippet box.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public function save( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$in = isset( $_POST['aigs_snippet'] ) && is_array( $_POST['aigs_snippet'] ) ? wp_unslash( $_POST['aigs_snippet'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field is sanitized in store().
		self::store( $post_id, isset( $in['title'] ) ? $in['title'] : '', isset( $in['desc'] ) ? $in['desc'] : '' );
	}

	/**
	 * Save or clear the two snippet fields.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $title   Title.
	 * @param string $desc    Description.
	 */
	public static function store( $post_id, $title, $desc ) {
		foreach ( array( '_aigs_seo_title' => $title, '_aigs_seo_desc' => $desc ) as $key => $v ) {
			$v = trim( sanitize_text_field( (string) $v ) );
			if ( '' === $v ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $v );
			}
		}
	}

	/**
	 * AJAX: save one row of the bulk snippet editor.
	 */
	public function ajax_save() {
		check_ajax_referer( 'aigs_admin', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'omnigeo-ai-seo' ) ) );
		}
		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$desc  = isset( $_POST['desc'] ) ? sanitize_text_field( wp_unslash( $_POST['desc'] ) ) : '';
		self::store( $post_id, $title, $desc );
		if ( class_exists( 'AIGS_Markdown' ) ) {
			AIGS_Markdown::flush();
		}
		wp_send_json_success( array( 'message' => __( 'Saved', 'omnigeo-ai-seo' ) ) );
	}
}
