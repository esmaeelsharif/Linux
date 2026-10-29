<?php
/**
 * AI Revenue Attribution (WooCommerce).
 *
 * Shows which orders and how much revenue came from AI assistants. It reads
 * the traffic source WooCommerce already stores on each order (Order
 * Attribution, WooCommerce 8.5+) and classifies AI products by referrer or
 * utm_source. No cookies are set and no new visitor data is collected.
 *
 * @package OmniGEO_Ai_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Order-level AI attribution and reporting.
 */
class AIGS_Revenue {

	/**
	 * Max orders read per report (protects very large stores).
	 */
	const MAX_ORDERS = 5000;

	/**
	 * Register hooks.
	 */
	public function hooks() {
		add_action( 'wp_ajax_aigs_revenue_refresh', array( $this, 'ajax_refresh' ) );
	}

	/**
	 * Is WooCommerce Order Attribution available and on?
	 *
	 * @return string ok|no_woo|disabled
	 */
	public static function status() {
		if ( ! AIGS_WooCommerce::active() || ! function_exists( 'wc_get_orders' ) ) {
			return 'no_woo';
		}
		// The feature option exists from WooCommerce 8.5; "no" means it was switched off.
		if ( 'no' === get_option( 'woocommerce_feature_order_attribution_enabled', 'yes' ) ) {
			return 'disabled';
		}
		return 'ok';
	}

	/**
	 * Which AI product (if any) an order came from.
	 *
	 * @param string $referrer    Order attribution referrer URL.
	 * @param string $utm_source  Order attribution utm_source.
	 * @return string Platform label or ''.
	 */
	public static function classify( $referrer, $utm_source ) {
		$m = AIGS_Tracker::match_referral( (string) $referrer, strtolower( trim( (string) $utm_source ) ) );
		return $m ? $m[0] : '';
	}

	/**
	 * Read paid orders of the last N days into plain rows.
	 *
	 * @param int $days Window.
	 * @return array Rows: total, ai (platform|''), landing, products[], device.
	 */
	public static function collect( $days ) {
		$rows  = array();
		$page  = 1;
		$since = time() - absint( $days ) * DAY_IN_SECONDS;
		do {
			$orders = wc_get_orders(
				array(
					'status'       => wc_get_is_paid_statuses(),
					'date_created' => '>' . $since,
					'type'         => 'shop_order',
					'limit'        => 200,
					'paged'        => $page,
					'orderby'      => 'date',
					'order'        => 'DESC',
				)
			);
			foreach ( (array) $orders as $order ) {
				if ( ! is_object( $order ) || ! method_exists( $order, 'get_total' ) ) {
					continue;
				}
				$ai       = self::classify( $order->get_meta( '_wc_order_attribution_referrer' ), $order->get_meta( '_wc_order_attribution_utm_source' ) );
				$products = array();
				if ( $ai ) {
					foreach ( $order->get_items() as $item ) {
						if ( is_object( $item ) && method_exists( $item, 'get_product_id' ) ) {
							$products[] = array( (int) $item->get_product_id(), (int) $item->get_quantity() );
						}
					}
				}
				$rows[] = array(
					'total'    => (float) $order->get_total() - (float) $order->get_total_refunded(),
					'ai'       => $ai,
					'landing'  => $ai ? (string) $order->get_meta( '_wc_order_attribution_session_entry' ) : '',
					'products' => $products,
				);
			}
			$page++;
		} while ( count( (array) $orders ) === 200 && count( $rows ) < self::MAX_ORDERS );
		return $rows;
	}

	/**
	 * Summarise rows (pure; testable).
	 *
	 * @param array $rows      Rows from collect().
	 * @param int   $ai_visits AI referral visits in the same window (for a rough conversion rate).
	 * @return array
	 */
	public static function summarize( $rows, $ai_visits = 0 ) {
		$s = array(
			'orders'       => 0,
			'revenue'      => 0.0,
			'ai_orders'    => 0,
			'ai_revenue'   => 0.0,
			'by_platform'  => array(),
			'landing'      => array(),
			'products'     => array(),
			'ai_aov'       => 0.0,
			'other_aov'    => 0.0,
			'share'        => 0,
			'revenue_share' => 0,
			'conversion'   => null,
		);
		foreach ( $rows as $r ) {
			$s['orders']++;
			$s['revenue'] += $r['total'];
			if ( '' === $r['ai'] ) {
				continue;
			}
			$s['ai_orders']++;
			$s['ai_revenue'] += $r['total'];
			if ( ! isset( $s['by_platform'][ $r['ai'] ] ) ) {
				$s['by_platform'][ $r['ai'] ] = array(
					'orders'  => 0,
					'revenue' => 0.0,
				);
			}
			$s['by_platform'][ $r['ai'] ]['orders']++;
			$s['by_platform'][ $r['ai'] ]['revenue'] += $r['total'];
			if ( '' !== $r['landing'] ) {
				$path = wp_parse_url( $r['landing'], PHP_URL_PATH );
				$path = $path ? $path : '/';
				$s['landing'][ $path ] = ( isset( $s['landing'][ $path ] ) ? $s['landing'][ $path ] : 0 ) + 1;
			}
			foreach ( $r['products'] as $p ) {
				$s['products'][ $p[0] ] = ( isset( $s['products'][ $p[0] ] ) ? $s['products'][ $p[0] ] : 0 ) + $p[1];
			}
		}
		uasort(
			$s['by_platform'],
			static function ( $a, $b ) {
				return $b['revenue'] <=> $a['revenue'];
			}
		);
		arsort( $s['landing'] );
		arsort( $s['products'] );
		$s['landing']  = array_slice( $s['landing'], 0, 8, true );
		$s['products'] = array_slice( $s['products'], 0, 8, true );

		$other_orders     = $s['orders'] - $s['ai_orders'];
		$s['ai_aov']      = $s['ai_orders'] ? $s['ai_revenue'] / $s['ai_orders'] : 0.0;
		$s['other_aov']   = $other_orders ? ( $s['revenue'] - $s['ai_revenue'] ) / $other_orders : 0.0;
		$s['share']       = $s['orders'] ? round( 100 * $s['ai_orders'] / $s['orders'], 1 ) : 0;
		$s['revenue_share'] = $s['revenue'] > 0 ? round( 100 * $s['ai_revenue'] / $s['revenue'], 1 ) : 0;
		if ( $ai_visits > 0 ) {
			$s['conversion'] = round( 100 * min( $s['ai_orders'], $ai_visits ) / $ai_visits, 1 );
		}
		return $s;
	}

	/**
	 * Report for a window, cached for 6 hours.
	 *
	 * @param int  $days  Window (30 or 90).
	 * @param bool $fresh Ignore cache.
	 * @return array|null Null when WooCommerce attribution is not available.
	 */
	public static function report( $days = 30, $fresh = false ) {
		if ( 'ok' !== self::status() ) {
			return null;
		}
		$days = in_array( (int) $days, array( 30, 90 ), true ) ? (int) $days : 30;
		$key  = 'aigs_revenue_' . $days;
		if ( ! $fresh ) {
			$cached = get_transient( $key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}
		$tracker        = new AIGS_Tracker();
		$report         = self::summarize( self::collect( $days ), $tracker->count( 'referral', $days ) );
		$report['days'] = $days;
		$report['time'] = time();
		set_transient( $key, $report, 6 * HOUR_IN_SECONDS );
		return $report;
	}

	/**
	 * AJAX: recompute now.
	 */
	public function ajax_refresh() {
		check_ajax_referer( 'aigs_admin', 'nonce' );
		if ( ! current_user_can( 'view_woocommerce_reports' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'omnigeo-ai-seo' ) ) );
		}
		delete_transient( 'aigs_revenue_30' );
		delete_transient( 'aigs_revenue_90' );
		wp_send_json_success();
	}
}
