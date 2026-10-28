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
		add_action( 'wp_ajax_aigs_woo_preview', array( $this, 'ajax_preview' ) );
		add_action( 'wp_ajax_aigs_woo_apply', array( $this, 'ajax_apply' ) );
		add_action( 'wp_ajax_aigs_woo_undo', array( $this, 'ajax_undo' ) );
	}

	/* ============================================================
	 * Catalog Copilot: bulk fixes with preview, approval and undo
	 * ============================================================ */

	/**
	 * Available bulk fixes.
	 *
	 * @return array id => [label, description, rule, ai]
	 */
	public static function fixes() {
		return array(
			'alt_from_name'   => array(
				'label' => __( 'Alt text from product name', 'omnigeo-ai-seo' ),
				'desc'  => __( 'Sets the main image alt text to the product name where it is empty.', 'omnigeo-ai-seo' ),
				'rule'  => 'product_image_alt',
				'ai'    => false,
			),
			'short_from_long' => array(
				'label' => __( 'Short description from full description', 'omnigeo-ai-seo' ),
				'desc'  => __( 'Uses the first sentences (up to 50 words) of the full description where the short description is empty.', 'omnigeo-ai-seo' ),
				'rule'  => 'product_short_desc',
				'ai'    => false,
			),
			'short_ai'        => array(
				'label' => __( 'Short description drafted by AI', 'omnigeo-ai-seo' ),
				'desc'  => __( 'Drafts a 25–50 word summary from the product’s own data with your AI key. Up to 10 products per run.', 'omnigeo-ai-seo' ),
				'rule'  => 'product_short_desc',
				'ai'    => true,
			),
		);
	}

	/**
	 * Products that a fix applies to (from open findings).
	 *
	 * @param string $fix   Fix id.
	 * @param int    $limit Max.
	 * @return array IDs
	 */
	private static function candidates( $fix, $limit ) {
		$fixes = self::fixes();
		if ( ! isset( $fixes[ $fix ] ) ) {
			return array();
		}
		$rows = AIGS_Findings::query(
			array(
				'rule'  => $fixes[ $fix ]['rule'],
				'limit' => $limit,
			)
		);
		$ids = array();
		foreach ( $rows as $r ) {
			if ( current_user_can( 'edit_post', (int) $r['post_id'] ) ) {
				$ids[] = (int) $r['post_id'];
			}
		}
		return $ids;
	}

	/**
	 * Proposed value for one product.
	 *
	 * @param string     $fix     Fix id.
	 * @param WC_Product $product Product.
	 * @return string|WP_Error Empty string when nothing can be proposed.
	 */
	private static function propose( $fix, $product ) {
		switch ( $fix ) {
			case 'alt_from_name':
				return $product->get_image_id() ? wp_strip_all_tags( $product->get_name() ) : '';
			case 'short_from_long':
				$long = trim( wp_strip_all_tags( strip_shortcodes( $product->get_description() ) ) );
				return AIGS_Text::word_count( $long ) >= 12 ? AIGS_Text::excerpt_sentences( $long, 50 ) : '';
			case 'short_ai':
				$facts = array( 'Name: ' . $product->get_name() );
				$long  = trim( wp_strip_all_tags( strip_shortcodes( $product->get_description() ) ) );
				if ( '' !== $long ) {
					$facts[] = 'Description: ' . wp_trim_words( $long, 400, '' );
				}
				foreach ( $product->get_attributes() as $attr ) {
					if ( is_object( $attr ) && method_exists( $attr, 'get_name' ) ) {
						$vals    = $attr->is_taxonomy() ? wc_get_product_terms( $product->get_id(), $attr->get_name(), array( 'fields' => 'names' ) ) : $attr->get_options();
						$facts[] = wc_attribute_label( $attr->get_name() ) . ': ' . implode( ', ', array_map( 'strval', (array) $vals ) );
					}
				}
				$cats = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) );
				if ( ! is_wp_error( $cats ) && $cats ) {
					$facts[] = 'Category: ' . implode( ', ', $cats );
				}
				if ( count( $facts ) < 2 ) {
					return '';
				}
				$plain = implode( "\n", $facts );
				$lang  = AIGS_Text::is_rtl_script( $plain ) ? 'Persian/Arabic script, the same language as the data' : 'the same language as the data';
				$res   = AIGS_AI_Client::ask(
					"Product data:\n{$plain}\n\nWrite a 25 to 50 word product summary: what it is, who it is for and the most important specification. Return only the summary text.",
					array(
						'system'     => 'You write e-commerce product summaries in ' . $lang . '. Use only the facts provided. Never invent prices, materials, sizes, certifications or claims.',
						'max_tokens' => 300,
					)
				);
				if ( is_wp_error( $res ) ) {
					return $res;
				}
				return trim( wp_strip_all_tags( (string) $res['text'] ), " \t\n\r\"“”«»" );
		}
		return '';
	}

	/**
	 * Guard for catalog endpoints.
	 */
	private function guard() {
		check_ajax_referer( 'aigs_admin', 'nonce' );
		if ( ! current_user_can( 'edit_products' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'omnigeo-ai-seo' ) ) );
		}
	}

	/**
	 * AJAX: preview a fix (nothing is saved).
	 */
	public function ajax_preview() {
		$this->guard();
		$fix   = isset( $_POST['fix'] ) ? sanitize_key( wp_unslash( $_POST['fix'] ) ) : '';
		$fixes = self::fixes();
		if ( ! isset( $fixes[ $fix ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown fix.', 'omnigeo-ai-seo' ) ) );
		}
		if ( $fixes[ $fix ]['ai'] && ! AIGS_AI_Client::ready() ) {
			wp_send_json_error( array( 'message' => __( 'Add your AI API key in Settings first.', 'omnigeo-ai-seo' ) ) );
		}
		$limit = $fixes[ $fix ]['ai'] ? min( 10, max( 1, AIGS_AI_Client::remaining() ) ) : 200;
		$items = array();
		$error = '';
		foreach ( self::candidates( $fix, $limit ) as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product ) {
				continue;
			}
			$value = self::propose( $fix, $product );
			if ( is_wp_error( $value ) ) {
				$error = $value->get_error_message();
				break;
			}
			if ( '' === $value ) {
				continue;
			}
			$items[] = array(
				'id'    => $id,
				'name'  => wp_strip_all_tags( $product->get_name() ),
				'edit'  => get_edit_post_link( $id, 'raw' ),
				'value' => $value,
			);
		}
		wp_send_json_success(
			array(
				'fix'   => $fix,
				'items' => $items,
				'error' => $error,
			)
		);
	}

	/**
	 * AJAX: apply approved items (values come back from the reviewed preview).
	 */
	public function ajax_apply() {
		$this->guard();
		$fix   = isset( $_POST['fix'] ) ? sanitize_key( wp_unslash( $_POST['fix'] ) ) : '';
		$fixes = self::fixes();
		if ( ! isset( $fixes[ $fix ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown fix.', 'omnigeo-ai-seo' ) ) );
		}
		$raw   = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field sanitized below.
		$log   = array();
		$count = 0;
		foreach ( array_slice( $raw, 0, 200 ) as $row ) {
			$id    = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			$value = isset( $row['value'] ) ? sanitize_textarea_field( $row['value'] ) : '';
			if ( ! $id || '' === $value || ! current_user_can( 'edit_post', $id ) ) {
				continue;
			}
			$product = wc_get_product( $id );
			if ( ! $product ) {
				continue;
			}
			if ( 'alt_from_name' === $fix ) {
				$img = (int) $product->get_image_id();
				$old = $img ? (string) get_post_meta( $img, '_wp_attachment_image_alt', true ) : '';
				if ( ! $img || '' !== $old ) {
					continue; // Never overwrite an alt someone already wrote.
				}
				update_post_meta( $img, '_wp_attachment_image_alt', $value );
				$log[] = array(
					'id'    => $id,
					'field' => 'alt',
					'obj'   => $img,
					'old'   => $old,
				);
			} else {
				$old = (string) $product->get_short_description();
				if ( '' !== trim( wp_strip_all_tags( $old ) ) ) {
					continue; // Never overwrite an existing short description.
				}
				$product->set_short_description( wp_kses_post( $value ) );
				$product->save();
				$log[] = array(
					'id'    => $id,
					'field' => 'short',
					'obj'   => $id,
					'old'   => $old,
				);
			}
			AIGS_Scanner::scan_post( $id, true );
			$count++;
		}
		if ( $log ) {
			$ops = get_option( 'aigs_bulk_log', array() );
			$ops = is_array( $ops ) ? $ops : array();
			array_unshift(
				$ops,
				array(
					'op'    => uniqid( 'op', false ),
					'fix'   => $fix,
					'time'  => time(),
					'user'  => get_current_user_id(),
					'items' => $log,
				)
			);
			update_option( 'aigs_bulk_log', array_slice( $ops, 0, 15 ), false );
		}
		wp_send_json_success( array( 'applied' => $count ) );
	}

	/**
	 * AJAX: undo a bulk operation (restores previous values).
	 */
	public function ajax_undo() {
		$this->guard();
		$op  = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';
		$ops = get_option( 'aigs_bulk_log', array() );
		$ops = is_array( $ops ) ? $ops : array();
		foreach ( $ops as $i => $entry ) {
			if ( $entry['op'] !== $op ) {
				continue;
			}
			$n = 0;
			foreach ( $entry['items'] as $it ) {
				if ( ! current_user_can( 'edit_post', (int) $it['id'] ) ) {
					continue;
				}
				if ( 'alt' === $it['field'] ) {
					update_post_meta( (int) $it['obj'], '_wp_attachment_image_alt', $it['old'] );
				} else {
					$product = wc_get_product( (int) $it['id'] );
					if ( $product ) {
						$product->set_short_description( $it['old'] );
						$product->save();
					}
				}
				AIGS_Scanner::scan_post( (int) $it['id'], true );
				$n++;
			}
			unset( $ops[ $i ] );
			update_option( 'aigs_bulk_log', array_values( $ops ), false );
			wp_send_json_success( array( 'restored' => $n ) );
		}
		wp_send_json_error( array( 'message' => __( 'That operation can no longer be undone.', 'omnigeo-ai-seo' ) ) );
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
