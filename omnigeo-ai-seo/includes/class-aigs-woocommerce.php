<?php
/**
 * WooCommerce integration.
 *
 * - Prevents schema conflicts on product pages (WooCommerce already emits rich
 *   Product schema, so we suppress our Article/Product nodes there).
 * - Optionally enriches WooCommerce's Product schema with the brand entity.
 * - Adds products & product categories to the generated llms.txt.
 *
 * Everything here is inert unless WooCommerce is active.
 *
 * @package OmniGEO_Ai_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bridges WooCommerce.
 */
class AIGS_WooCommerce {

	/**
	 * Is WooCommerce active?
	 *
	 * @return bool
	 */
	public static function active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Are we currently rendering a single product page?
	 *
	 * @return bool
	 */
	public static function is_product() {
		return self::active() && function_exists( 'is_product' ) && is_product();
	}

	/**
	 * Is the given post a product? (Works in AJAX where is_product() does not.)
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_product_id( $post_id ) {
		return self::active() && 'product' === get_post_type( $post_id );
	}

	/**
	 * Register hooks (no-op when WooCommerce is inactive).
	 */
	public function hooks() {
		if ( ! self::active() ) {
			return;
		}
		if ( 'off' !== aigs_get( 'enhance_woo', 'on' ) ) {
			add_filter( 'woocommerce_structured_data_product', array( $this, 'enrich_product' ), 20, 2 );
		}
		add_filter( 'aigs_llms_sections', array( $this, 'llms_sections' ) );
	}

	/**
	 * Add the brand to WooCommerce's Product structured data when missing.
	 *
	 * @param array      $markup  Product schema array.
	 * @param WC_Product $product Product (unused but provided by the filter).
	 * @return array
	 */
	public function enrich_product( $markup, $product ) {
		unset( $product );
		if ( empty( $markup['brand'] ) ) {
			$brand = aigs_business_name();
			if ( $brand ) {
				$markup['brand'] = array(
					'@type' => 'Brand',
					'name'  => $brand,
				);
			}
		}
		return $markup;
	}

	/**
	 * Append product & category sections to llms.txt.
	 *
	 * @param array $sections Existing extra sections.
	 * @return array
	 */
	public function llms_sections( $sections ) {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return $sections;
		}

		// Recent published products.
		$products = wc_get_products(
			array(
				'status'  => 'publish',
				'limit'   => 20,
				'orderby' => 'date',
				'order'   => 'DESC',
			)
		);
		if ( $products ) {
			$block = "## Products\n\n";
			foreach ( $products as $product ) {
				$block .= '- [' . wp_strip_all_tags( $product->get_name() ) . '](' . get_permalink( $product->get_id() ) . ")\n";
			}
			$sections[] = rtrim( $block );
		}

		// Product categories.
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'number'     => 20,
				'hide_empty' => true,
			)
		);
		if ( $terms && ! is_wp_error( $terms ) ) {
			$block = "## Product categories\n\n";
			foreach ( $terms as $term ) {
				$link = get_term_link( $term );
				if ( is_wp_error( $link ) ) {
					continue;
				}
				$block .= '- [' . wp_strip_all_tags( $term->name ) . '](' . $link . ")\n";
			}
			$sections[] = rtrim( $block );
		}

		return $sections;
	}

	/**
	 * Product-specific extractability checks merged into the analyzer report.
	 *
	 * @param int $post_id Product ID.
	 * @return array Check rows.
	 */
	public function product_checks( $post_id ) {
		$checks = array();
		if ( ! function_exists( 'wc_get_product' ) ) {
			return $checks;
		}
		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			return $checks;
		}

		// 1) Short description (the concise summary AI lifts).
		$sw = self::wc( $product->get_short_description() );
		if ( 0 === $sw ) {
			$checks[] = self::row( __( 'Short description', 'omnigeo-ai-seo' ), 0, __( 'Add a short description, AI uses it as the product’s one-line summary.', 'omnigeo-ai-seo' ), 2 );
		} elseif ( $sw >= 15 && $sw <= 80 ) {
			/* translators: %d is a value inserted into the message. */
			$checks[] = self::row( __( 'Short description', 'omnigeo-ai-seo' ), 2, sprintf( __( 'Concise summary present (%d words).', 'omnigeo-ai-seo' ), $sw ), 2 );
		} else {
			/* translators: %d is a value inserted into the message. */
			$checks[] = self::row( __( 'Short description', 'omnigeo-ai-seo' ), 1, sprintf( __( 'Short description is %d words, aim for a tight 20–60 word summary.', 'omnigeo-ai-seo' ), $sw ), 2 );
		}

		// 2) Full description depth.
		$lw = self::wc( $product->get_description() );
		if ( $lw >= 80 ) {
			/* translators: %d is a value inserted into the message. */
			$checks[] = self::row( __( 'Full description', 'omnigeo-ai-seo' ), 2, sprintf( __( '~%d words of product detail.', 'omnigeo-ai-seo' ), $lw ), 2 );
		} elseif ( $lw > 0 ) {
			/* translators: %d is a value inserted into the message. */
			$checks[] = self::row( __( 'Full description', 'omnigeo-ai-seo' ), 1, sprintf( __( 'Only ~%d words, expand benefits, use-cases and specs.', 'omnigeo-ai-seo' ), $lw ), 2 );
		} else {
			$checks[] = self::row( __( 'Full description', 'omnigeo-ai-seo' ), 0, __( 'No full description, AI has almost nothing to cite about this product.', 'omnigeo-ai-seo' ), 2 );
		}

		// 3) Attributes / specs.
		$attrs = $product->get_attributes();
		$na    = is_array( $attrs ) ? count( $attrs ) : 0;
		if ( $na >= 2 ) {
			/* translators: %d is a value inserted into the message. */
			$checks[] = self::row( __( 'Specs / attributes', 'omnigeo-ai-seo' ), 2, sprintf( __( '%d product attributes set, ideal for AI comparison.', 'omnigeo-ai-seo' ), $na ), 2 );
		} elseif ( 1 === $na ) {
			$checks[] = self::row( __( 'Specs / attributes', 'omnigeo-ai-seo' ), 1, __( 'Only one attribute. Add specs (material, size, compatibility…) AI can match to queries.', 'omnigeo-ai-seo' ), 2 );
		} else {
			$checks[] = self::row( __( 'Specs / attributes', 'omnigeo-ai-seo' ), 0, __( 'No product attributes. Add specs so AI can match buyer queries and comparisons.', 'omnigeo-ai-seo' ), 2 );
		}

		// 4) Reviews / social proof.
		$rc = (int) $product->get_review_count();
		if ( $rc >= 1 ) {
			/* translators: %d is a value inserted into the message. */
			$checks[] = self::row( __( 'Reviews / ratings', 'omnigeo-ai-seo' ), 2, sprintf( __( '%d review(s), social proof AI favours.', 'omnigeo-ai-seo' ), $rc ), 1 );
		} else {
			$checks[] = self::row( __( 'Reviews / ratings', 'omnigeo-ai-seo' ), 1, __( 'No reviews yet. AI tends to recommend products with social proof.', 'omnigeo-ai-seo' ), 1 );
		}

		// 5) Featured image alt text.
		$thumb_id = $product->get_image_id();
		if ( ! $thumb_id ) {
			$checks[] = self::row( __( 'Product image', 'omnigeo-ai-seo' ), 0, __( 'No product image. Add one with descriptive alt text.', 'omnigeo-ai-seo' ), 1 );
		} else {
			$alt = trim( (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) );
			if ( '' !== $alt ) {
				$checks[] = self::row( __( 'Product image', 'omnigeo-ai-seo' ), 2, __( 'Featured image has alt text.', 'omnigeo-ai-seo' ), 1 );
			} else {
				$checks[] = self::row( __( 'Product image', 'omnigeo-ai-seo' ), 1, __( 'Featured image is missing alt text.', 'omnigeo-ai-seo' ), 1 );
			}
		}

		// 6) Categorized.
		$cats = wp_get_post_terms( $post_id, 'product_cat', array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $cats ) && count( $cats ) >= 1 ) {
			$checks[] = self::row( __( 'Categorized', 'omnigeo-ai-seo' ), 2, __( 'Assigned to a product category.', 'omnigeo-ai-seo' ), 1 );
		} else {
			$checks[] = self::row( __( 'Categorized', 'omnigeo-ai-seo' ), 1, __( 'Not categorized, categories help AI place the product.', 'omnigeo-ai-seo' ), 1 );
		}

		return $checks;
	}

	/**
	 * Multibyte-aware word count.
	 *
	 * @param string $text Text.
	 * @return int
	 */
	private static function wc( $text ) {
		$text = trim( wp_strip_all_tags( (string) $text ) );
		if ( '' === $text ) {
			return 0;
		}
		$parts = preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		return is_array( $parts ) ? count( $parts ) : 0;
	}

	/**
	 * Build a check row.
	 *
	 * @param string $label  Label.
	 * @param int    $status 0|1|2.
	 * @param string $remark Remark.
	 * @param int    $weight Weight.
	 * @return array
	 */
	private static function row( $label, $status, $remark, $weight = 1 ) {
		return array(
			'label'  => $label,
			'status' => $status,
			'remark' => $remark,
			'weight' => $weight,
		);
	}
}
