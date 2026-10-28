<?php
/**
 * Schema & entity optimization (FAQ / HowTo / Product / Article + About/Keywords/SameAs).
 *
 * @package OmniGEO_Ai_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds a per-post meta box and outputs JSON-LD + Speakable schema in <head>.
 */
class AIGS_Schema {

	const NONCE = 'aigs_schema_nonce';

	/**
	 * Register hooks.
	 */
	public function hooks() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
		if ( aigs_get( 'enable_schema', 1 ) ) {
			add_action( 'wp_head', array( $this, 'output' ), 20 );
		}
		if ( aigs_get( 'meta_fallback', 0 ) ) {
			add_action( 'wp_head', array( $this, 'meta_description' ), 1 );
		}
	}

	/**
	 * Print a meta description when no SEO plugin is active (opt-in).
	 * Built from the excerpt, else the first paragraph; nothing on archives.
	 */
	public function meta_description() {
		if ( '' !== AIGS_SEO::active() || ! is_singular() ) {
			return;
		}
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post || post_password_required( $post ) ) {
			return;
		}
		$text = trim( (string) $post->post_excerpt );
		if ( '' === $text ) {
			$text = (string) $post->post_content;
			if ( function_exists( 'has_blocks' ) && has_blocks( $text ) ) {
				$text = excerpt_remove_blocks( $text );
			}
			$text = strip_shortcodes( $text );
		}
		$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $text ) ) );
		if ( '' === $text ) {
			return;
		}
		if ( mb_strlen( $text ) > 158 ) {
			$text = mb_substr( $text, 0, 155 );
			$sp   = mb_strrpos( $text, ' ' );
			$text = ( $sp ? mb_substr( $text, 0, $sp ) : $text ) . '…';
		}
		echo '<meta name="description" content="' . esc_attr( $text ) . '" />' . "\n";
	}

	/**
	 * Does the content already contain an FAQ block from an SEO plugin?
	 *
	 * @param string $content Raw post content.
	 * @return bool
	 */
	public static function has_seo_faq_block( $content ) {
		return false !== strpos( (string) $content, '<!-- wp:yoast/faq-block' ) || false !== strpos( (string) $content, '<!-- wp:rank-math/faq-block' );
	}

	/**
	 * Add meta box to public post types.
	 */
	public function add_meta_box() {
		$types = get_post_types( array( 'public' => true ), 'names' );
		foreach ( $types as $type ) {
			add_meta_box(
				'aigs_schema',
				__( 'OmniGEO Schema & Entities', 'omnigeo-ai-seo' ),
				array( $this, 'render' ),
				$type,
				'normal',
				'high'
			);
		}
	}

	/**
	 * Render the meta box.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function render( $post ) {
		wp_nonce_field( self::NONCE, self::NONCE );
		$type     = get_post_meta( $post->ID, '_aigs_schema_type', true ) ?: 'none';
		$about    = get_post_meta( $post->ID, '_aigs_about', true );
		$keywords = get_post_meta( $post->ID, '_aigs_keywords', true );
		$sameas   = get_post_meta( $post->ID, '_aigs_sameas', true );
		$speak    = get_post_meta( $post->ID, '_aigs_speakable', true );
		$faqs     = get_post_meta( $post->ID, '_aigs_faqs', true );
		$faqs     = is_array( $faqs ) ? $faqs : array();
		?>
		<div class="aigs-field">
			<label for="aigs_schema_type"><?php esc_html_e( 'Schema type (pick one per page)', 'omnigeo-ai-seo' ); ?></label>
			<select name="aigs_schema_type" id="aigs_schema_type">
				<?php
				$opts = array(
					'none'    => __( 'None', 'omnigeo-ai-seo' ),
					'FAQPage' => __( 'FAQ Page', 'omnigeo-ai-seo' ),
					'HowTo'   => __( 'How-To', 'omnigeo-ai-seo' ),
					'Product' => __( 'Product', 'omnigeo-ai-seo' ),
				);
				foreach ( $opts as $val => $label ) {
					printf( '<option value="%s" %s>%s</option>', esc_attr( $val ), selected( $type, $val, false ), esc_html( $label ) );
				}
				?>
			</select>
			<p class="description" style="margin-top:6px"><?php esc_html_e( 'Article, Author and Organization schema are added automatically and linked together. Use this only to add FAQ, How-To or Product on top.', 'omnigeo-ai-seo' ); ?></p>
			<p class="description"><?php esc_html_e( 'Good to know: Google shows FAQ rich results mainly for authoritative health and government sites and no longer shows HowTo results. The markup still helps machines read your Q&A, but it is optional.', 'omnigeo-ai-seo' ); ?></p>
			<?php if ( self::has_seo_faq_block( $post->post_content ) ) : ?>
				<p class="description" style="color:#9a6700"><?php esc_html_e( 'This post contains an FAQ block from your SEO plugin, which already outputs FAQ schema. OmniGEO will not output a second copy.', 'omnigeo-ai-seo' ); ?></p>
			<?php endif; ?>
		</div>

		<div class="aigs-field">
			<label for="aigs_about"><?php esc_html_e( 'About (main topic / entity)', 'omnigeo-ai-seo' ); ?></label>
			<input type="text" id="aigs_about" name="aigs_about" value="<?php echo esc_attr( $about ); ?>" />
		</div>

		<div class="aigs-field">
			<label for="aigs_keywords"><?php esc_html_e( 'Keywords (comma separated)', 'omnigeo-ai-seo' ); ?></label>
			<input type="text" id="aigs_keywords" name="aigs_keywords" value="<?php echo esc_attr( $keywords ); ?>" />
		</div>

		<div class="aigs-field">
			<label for="aigs_sameas"><?php esc_html_e( 'SameAs URLs (comma separated, Wikipedia, Wikidata, social, etc.)', 'omnigeo-ai-seo' ); ?></label>
			<textarea id="aigs_sameas" name="aigs_sameas" rows="2" placeholder="https://en.wikipedia.org/wiki/..., https://www.wikidata.org/wiki/..."><?php echo esc_textarea( $sameas ); ?></textarea>
		</div>

		<div class="aigs-field">
			<label><input type="checkbox" name="aigs_speakable" value="1" <?php checked( $speak, '1' ); ?> /> <?php esc_html_e( 'Add Speakable markup (voice assistants) for the title & first paragraph', 'omnigeo-ai-seo' ); ?></label>
		</div>

		<div class="aigs-field" id="aigs-faq-wrap" style="<?php echo esc_attr( ( 'FAQPage' !== $type ) ? 'display:none' : '' ); ?>">
			<label><?php esc_html_e( 'FAQ items', 'omnigeo-ai-seo' ); ?></label>
			<div id="aigs-faq-list">
				<?php
				if ( empty( $faqs ) ) {
					$faqs = array( array( 'q' => '', 'a' => '' ) );
				}
				foreach ( $faqs as $i => $faq ) :
					?>
					<div class="aigs-faq-item">
						<input type="text" name="aigs_faqs[<?php echo (int) $i; ?>][q]" value="<?php echo esc_attr( $faq['q'] ); ?>" placeholder="<?php esc_attr_e( 'Question', 'omnigeo-ai-seo' ); ?>" />
						<textarea name="aigs_faqs[<?php echo (int) $i; ?>][a]" rows="2" placeholder="<?php esc_attr_e( 'Answer', 'omnigeo-ai-seo' ); ?>"><?php echo esc_textarea( $faq['a'] ); ?></textarea>
						<button type="button" class="button aigs-remove-faq"><?php esc_html_e( 'Remove', 'omnigeo-ai-seo' ); ?></button>
					</div>
					<?php
				endforeach;
				?>
			</div>
			<button type="button" class="button" id="aigs-add-faq"><?php esc_html_e( 'Add FAQ item', 'omnigeo-ai-seo' ); ?></button>
		</div>
		<?php
	}

	/**
	 * Save meta box data.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public function save( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$allowed = array( 'none', 'FAQPage', 'HowTo', 'Product' );
		$type    = isset( $_POST['aigs_schema_type'] ) ? sanitize_text_field( wp_unslash( $_POST['aigs_schema_type'] ) ) : 'none';
		$type    = in_array( $type, $allowed, true ) ? $type : 'none';
		update_post_meta( $post_id, '_aigs_schema_type', $type );

		update_post_meta( $post_id, '_aigs_about', isset( $_POST['aigs_about'] ) ? sanitize_text_field( wp_unslash( $_POST['aigs_about'] ) ) : '' );
		update_post_meta( $post_id, '_aigs_keywords', isset( $_POST['aigs_keywords'] ) ? sanitize_text_field( wp_unslash( $_POST['aigs_keywords'] ) ) : '' );
		update_post_meta( $post_id, '_aigs_sameas', isset( $_POST['aigs_sameas'] ) ? sanitize_textarea_field( wp_unslash( $_POST['aigs_sameas'] ) ) : '' );
		update_post_meta( $post_id, '_aigs_speakable', isset( $_POST['aigs_speakable'] ) ? '1' : '0' );

		$faqs = array();
		if ( isset( $_POST['aigs_faqs'] ) && is_array( $_POST['aigs_faqs'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each sub-field is sanitized individually below.
			foreach ( wp_unslash( $_POST['aigs_faqs'] ) as $row ) {
				$q = isset( $row['q'] ) ? sanitize_text_field( $row['q'] ) : '';
				$a = isset( $row['a'] ) ? wp_kses_post( $row['a'] ) : '';
				if ( '' !== $q && '' !== $a ) {
					$faqs[] = array( 'q' => $q, 'a' => $a );
				}
			}
		}
		update_post_meta( $post_id, '_aigs_faqs', $faqs );
	}

	/**
	 * Output JSON-LD in head on singular views.
	 */
	public function output() {
		if ( ! is_singular() ) {
			return;
		}
		$post_id = get_queried_object_id();
		$type    = get_post_meta( $post_id, '_aigs_schema_type', true );
		$graph   = array();

		$about    = get_post_meta( $post_id, '_aigs_about', true );
		$keywords = get_post_meta( $post_id, '_aigs_keywords', true );
		$sameas   = $this->sameas_array( get_post_meta( $post_id, '_aigs_sameas', true ) );
		$url      = get_permalink( $post_id );
		$title    = get_the_title( $post_id );

		if ( 'FAQPage' === $type && ! self::has_seo_faq_block( get_post_field( 'post_content', $post_id ) ) ) {
			$faqs = get_post_meta( $post_id, '_aigs_faqs', true );
			$main = array();
			foreach ( (array) $faqs as $faq ) {
				$main[] = array(
					'@type'          => 'Question',
					'name'           => $faq['q'],
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => wp_strip_all_tags( $faq['a'] ),
					),
				);
			}
			if ( $main ) {
				$graph[] = array(
					'@type'      => 'FAQPage',
					'mainEntity' => $main,
				);
			}
		} elseif ( 'Product' === $type ) {
			// HowTo nodes without steps are invalid, so they are no longer printed.
			// Let WooCommerce own Product schema on its product pages.
			$woo_product = ( 'Product' === $type && class_exists( 'AIGS_WooCommerce' ) && AIGS_WooCommerce::is_product() );
			if ( ! $woo_product ) {
				$node = array(
					'@type' => $type,
					'name'  => $title,
					'url'   => $url,
				);
				if ( $keywords ) {
					$node['keywords'] = $keywords;
				}
				if ( $about ) {
					$node['about'] = array( '@type' => 'Thing', 'name' => $about );
				}
				if ( $sameas ) {
					$node['sameAs'] = $sameas;
				}
				$graph[] = $node;
			}
		}

		if ( empty( $graph ) ) {
			return;
		}

		$json = array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);

		echo "\n<!-- OmniGEO Ai SEO schema -->\n";
		echo '<script type="application/ld+json">' . wp_json_encode( $json, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}

	/**
	 * Normalize comma-separated SameAs into a clean URL array.
	 *
	 * @param string $raw Raw string.
	 * @return array
	 */
	private function sameas_array( $raw ) {
		if ( ! $raw ) {
			return array();
		}
		$items = array_map( 'trim', explode( ',', $raw ) );
		$items = array_filter(
			$items,
			static function ( $u ) {
				return filter_var( $u, FILTER_VALIDATE_URL );
			}
		);
		return array_values( $items );
	}
}
