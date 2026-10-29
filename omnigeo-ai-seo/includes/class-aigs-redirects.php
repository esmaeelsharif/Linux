<?php
/**
 * Redirects and 404 monitor.
 *
 * Keeps people and AI crawlers on working pages: manual 301/302/307/410 rules,
 * an automatic 301 when a published URL changes, and a 404 log with a
 * one-click "send it to the best matching page" suggestion.
 *
 * Rules live in one small option keyed by normalised path, so matching is a
 * single array lookup per request. Usage time is stored separately and only
 * written once an hour per rule, so busy redirects never hammer the database.
 *
 * @package OmniGEO_Ai_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Redirect engine, slug-change watcher and 404 log.
 */
class AIGS_Redirects {

	const RULES   = 'aigs_redirects';
	const USED    = 'aigs_redirect_used';
	const LOG     = 'aigs_404_log';
	const MAX_LOG = 200;

	/**
	 * Register hooks.
	 */
	public function hooks() {
		if ( aigs_get( 'tb_redirects', 1 ) ) {
			add_action( 'template_redirect', aigs_guard( array( $this, 'maybe_redirect' ) ), 1 );
			add_action( 'template_redirect', aigs_guard( array( $this, 'maybe_log_404' ) ), 99 );
		}
		if ( aigs_get( 'tb_auto_redirect', 1 ) ) {
			add_action( 'post_updated', aigs_guard( array( $this, 'watch_url_change' ) ), 10, 3 );
		}
		add_action( 'wp_ajax_aigs_redirect_save', array( $this, 'ajax_save' ) );
		add_action( 'wp_ajax_aigs_redirect_delete', array( $this, 'ajax_delete' ) );
		add_action( 'wp_ajax_aigs_404_clear', array( $this, 'ajax_clear_404' ) );
		add_action( 'admin_post_aigs_redirects_csv', array( $this, 'download_csv' ) );
		add_action( 'admin_post_aigs_redirects_import', array( $this, 'import_csv' ) );
	}

	/* ------------------------------------------------------------
	 * Pure helpers (tested)
	 * ------------------------------------------------------------ */

	/**
	 * Normalise a URL or path into a lookup key: decoded path, no query, no
	 * trailing slash, lower case. Returns '' for the site root or another host.
	 *
	 * @param string $url URL or path.
	 * @return string
	 */
	public static function key( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		$parts = wp_parse_url( $url );
		if ( false === $parts ) {
			return '';
		}
		if ( ! empty( $parts['host'] ) ) {
			$home = wp_parse_url( home_url(), PHP_URL_HOST );
			if ( strtolower( preg_replace( '/^www\./i', '', $parts['host'] ) ) !== strtolower( preg_replace( '/^www\./i', '', (string) $home ) ) ) {
				return '';
			}
		}
		$path = isset( $parts['path'] ) ? rawurldecode( $parts['path'] ) : '';
		// Sites installed in a sub folder: compare paths relative to home.
		$base = (string) wp_parse_url( home_url(), PHP_URL_PATH );
		$base = rtrim( $base, '/' );
		if ( '' !== $base && 0 === strpos( $path, $base . '/' ) ) {
			$path = substr( $path, strlen( $base ) );
		}
		$path = '/' . trim( $path, '/' );
		if ( '/' === $path ) {
			return '';
		}
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $path, 'UTF-8' ) : strtolower( $path );
	}

	/**
	 * Turn a key back into a full URL on this site.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	public static function key_url( $key ) {
		return home_url( user_trailingslashit( ltrim( $key, '/' ) ) );
	}

	/**
	 * Add a rule to a rule set: resolves chains (A→B, B→C becomes A→C and
	 * any X→A is pointed at the final target) and refuses loops.
	 *
	 * @param array  $rules Rules (key => rule).
	 * @param string $from  Source URL/path.
	 * @param string $to    Target URL ('' for 410).
	 * @param int    $code  301|302|307|410.
	 * @param string $src   manual|auto|import|404.
	 * @return array|WP_Error New rules.
	 */
	public static function add_rule( $rules, $from, $to, $code, $src = 'manual' ) {
		$rules = is_array( $rules ) ? $rules : array();
		$key   = self::key( $from );
		$code  = in_array( (int) $code, array( 301, 302, 307, 410 ), true ) ? (int) $code : 301;
		if ( '' === $key ) {
			return new WP_Error( 'aigs_from', __( 'Enter a path on this site to redirect, for example /old-page/.', 'omnigeo-ai-seo' ) );
		}
		$to = trim( (string) $to );
		if ( 410 === $code ) {
			$to = '';
		} else {
			if ( '' === $to ) {
				return new WP_Error( 'aigs_to', __( 'Enter where the old address should go.', 'omnigeo-ai-seo' ) );
			}
			if ( '/' === substr( $to, 0, 1 ) && '//' !== substr( $to, 0, 2 ) ) {
				$to = home_url( $to );
			}
			$to = esc_url_raw( $to, array( 'http', 'https' ) );
			if ( '' === $to ) {
				return new WP_Error( 'aigs_to', __( 'The target must be a full web address or a path that starts with /.', 'omnigeo-ai-seo' ) );
			}
			$to_key = self::key( $to );
			// Follow the chain to its end so visitors take one hop, not several.
			$seen = array( $key => true );
			while ( '' !== $to_key && isset( $rules[ $to_key ] ) && 410 !== (int) $rules[ $to_key ]['code'] ) {
				if ( isset( $seen[ $to_key ] ) ) {
					break;
				}
				$seen[ $to_key ] = true;
				$to              = $rules[ $to_key ]['to'];
				$to_key          = self::key( $to );
			}
			if ( '' !== $to_key && isset( $seen[ $to_key ] ) ) {
				return new WP_Error( 'aigs_loop', __( 'This would send the page back to itself. Pick a different target.', 'omnigeo-ai-seo' ) );
			}
		}
		$rules[ $key ] = array(
			'to'      => $to,
			'code'    => $code,
			'src'     => sanitize_key( $src ),
			'created' => time(),
		);
		// Point older rules that ended at the source straight to the new target.
		if ( 410 !== $code ) {
			foreach ( $rules as $k => $r ) {
				if ( $k !== $key && 410 !== (int) $r['code'] && self::key( $r['to'] ) === $key ) {
					$rules[ $k ]['to'] = $to;
				}
			}
		}
		return $rules;
	}

	/**
	 * Is this 404 path worth logging (skips vulnerability-scanner noise)?
	 *
	 * @param string $key Normalised path.
	 * @return bool
	 */
	public static function worth_logging( $key ) {
		if ( '' === $key || strlen( $key ) > 300 ) {
			return false;
		}
		if ( preg_match( '#(^|/)(\.|wp-admin|wp-includes|wp-content/plugins|xmlrpc|cgi-bin|phpmyadmin|vendor/)#', $key ) ) {
			return false;
		}
		return ! preg_match( '/\.(php\d?|asp|aspx|jsp|cgi|env|ini|bak|old|sql|gz|zip|tar|rar|7z|log|yml|yaml|git|ds_store|map|swp)$/', $key );
	}

	/**
	 * Score how well a candidate slug matches a broken one (0..1).
	 *
	 * @param string $broken    Broken slug.
	 * @param string $candidate Candidate slug.
	 * @return float
	 */
	public static function similarity( $broken, $candidate ) {
		$a = array_filter( preg_split( '/[-_\s\/]+/u', strtolower( (string) $broken ) ) );
		$b = array_filter( preg_split( '/[-_\s\/]+/u', strtolower( (string) $candidate ) ) );
		if ( ! $a || ! $b ) {
			return 0.0;
		}
		$common = count( array_intersect( array_unique( $a ), array_unique( $b ) ) );
		return round( $common / max( count( array_unique( $a ) ), count( array_unique( $b ) ) ), 2 );
	}

	/* ------------------------------------------------------------
	 * Storage
	 * ------------------------------------------------------------ */

	/**
	 * All rules.
	 *
	 * @return array
	 */
	public static function rules() {
		$r = get_option( self::RULES, array() );
		return is_array( $r ) ? $r : array();
	}

	/**
	 * Save rules.
	 *
	 * @param array $rules Rules.
	 */
	public static function save_rules( $rules ) {
		update_option( self::RULES, $rules, true );
	}

	/**
	 * 404 log (key => [count, last, ref, bot]), newest first.
	 *
	 * @return array
	 */
	public static function log() {
		$l = get_option( self::LOG, array() );
		$l = is_array( $l ) ? $l : array();
		uasort(
			$l,
			static function ( $a, $b ) {
				return $b['last'] <=> $a['last'];
			}
		);
		return $l;
	}

	/* ------------------------------------------------------------
	 * Front end
	 * ------------------------------------------------------------ */

	/**
	 * Redirect when the request path has a rule.
	 */
	public function maybe_redirect() {
		if ( is_admin() || ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$rules = self::rules();
		if ( ! $rules ) {
			return;
		}
		$key = self::key( wp_unslash( $_SERVER['REQUEST_URI'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only parsed into a lookup key, never printed.
		if ( '' === $key || ! isset( $rules[ $key ] ) ) {
			return;
		}
		$rule = $rules[ $key ];
		$this->touch( $key );
		if ( 410 === (int) $rule['code'] ) {
			global $wp_query;
			if ( $wp_query ) {
				$wp_query->set_404();
			}
			status_header( 410 );
			nocache_headers();
			return;
		}
		if ( self::key( $rule['to'] ) === $key ) {
			return;
		}
		// Keep campaign parameters (utm_source etc.) on the new address.
		$to = $rule['to'];
		if ( ! empty( $_SERVER['QUERY_STRING'] ) && false === strpos( $to, '?' ) ) {
			$to .= '?' . sanitize_text_field( wp_unslash( $_SERVER['QUERY_STRING'] ) );
		}
		wp_redirect( $to, (int) $rule['code'], 'OmniGEO Ai SEO' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- targets are set by an administrator and may be on another domain.
		exit;
	}

	/**
	 * Remember when a rule was last used (at most one write per rule per hour).
	 *
	 * @param string $key Rule key.
	 */
	private function touch( $key ) {
		$used = get_option( self::USED, array() );
		$used = is_array( $used ) ? $used : array();
		if ( isset( $used[ $key ] ) && $used[ $key ] > time() - HOUR_IN_SECONDS ) {
			return;
		}
		$used[ $key ] = time();
		update_option( self::USED, $used, false );
	}

	/**
	 * Log a 404 request.
	 */
	public function maybe_log_404() {
		if ( ! is_404() || is_admin() || ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$key = self::key( wp_unslash( $_SERVER['REQUEST_URI'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- normalised into a path key.
		if ( ! self::worth_logging( $key ) ) {
			return;
		}
		$rules = self::rules();
		if ( isset( $rules[ $key ] ) ) {
			return; // A 410 rule: expected.
		}
		$ua  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$bot = '';
		if ( '' !== $ua ) {
			$m   = AIGS_Tracker::match_bot( $ua );
			$bot = $m ? $m[0] : '';
		}
		$ref = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';

		$log = get_option( self::LOG, array() );
		$log = is_array( $log ) ? $log : array();
		$row = isset( $log[ $key ] ) ? $log[ $key ] : array(
			'count' => 0,
			'first' => time(),
			'ref'   => '',
			'bot'   => '',
		);
		$row['count']++;
		$row['last'] = time();
		if ( '' !== $ref && '' === $row['ref'] ) {
			$row['ref'] = substr( $ref, 0, 300 );
		}
		if ( '' !== $bot ) {
			$row['bot'] = $bot;
		}
		$log[ $key ] = $row;
		if ( count( $log ) > self::MAX_LOG ) {
			// Drop the least useful entries: fewest hits, then oldest.
			uasort(
				$log,
				static function ( $a, $b ) {
					return array( $b['count'], $b['last'] ) <=> array( $a['count'], $a['last'] );
				}
			);
			$log = array_slice( $log, 0, self::MAX_LOG, true );
		}
		update_option( self::LOG, $log, false );
	}

	/**
	 * When a published post's address changes, send the old one to the new one.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $after   New version.
	 * @param WP_Post $before  Old version.
	 */
	public function watch_url_change( $post_id, $after, $before ) {
		if ( 'publish' !== $before->post_status || 'publish' !== $after->post_status ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || ! is_post_type_viewable( $after->post_type ) ) {
			return;
		}
		if ( $before->post_name === $after->post_name && (int) $before->post_parent === (int) $after->post_parent && $before->post_date === $after->post_date ) {
			return;
		}
		$old = get_permalink( $before );
		$new = get_permalink( $post_id );
		if ( ! $old || ! $new ) {
			return;
		}
		$old_key = self::key( $old );
		$new_key = self::key( $new );
		if ( '' === $old_key || $old_key === $new_key ) {
			return;
		}
		$rules = self::rules();
		// The new address may have been redirected before (slug changed back): drop that rule.
		unset( $rules[ $new_key ] );
		$rules = self::add_rule( $rules, $old, $new, 301, 'auto' );
		if ( ! is_wp_error( $rules ) ) {
			self::save_rules( $rules );
		}
	}

	/* ------------------------------------------------------------
	 * Admin
	 * ------------------------------------------------------------ */

	/**
	 * Best existing page for a broken path (for the one-click fix).
	 *
	 * @param string $key Broken path key.
	 * @return array|null [id, url, title, score]
	 */
	public static function suggest( $key ) {
		$slug = basename( $key );
		$slug = preg_replace( '/\.(html?|htm)$/', '', $slug );
		if ( '' === $slug ) {
			return null;
		}
		$types = get_post_types( array( 'public' => true ) );
		unset( $types['attachment'] );
		$found = get_posts(
			array(
				'name'           => sanitize_title( $slug ),
				'post_type'      => array_values( $types ),
				'post_status'    => 'publish',
				'posts_per_page' => 1,
			)
		);
		if ( $found ) {
			return array( (int) $found[0]->ID, get_permalink( $found[0] ), get_the_title( $found[0] ), 1.0 );
		}
		$words = trim( preg_replace( '/[-_]+/', ' ', $slug ) );
		if ( '' === $words || is_numeric( $words ) ) {
			return null;
		}
		$best  = null;
		$cands = get_posts(
			array(
				's'              => $words,
				'post_type'      => array_values( $types ),
				'post_status'    => 'publish',
				'posts_per_page' => 5,
			)
		);
		foreach ( $cands as $p ) {
			$score = self::similarity( $slug, $p->post_name . ' ' . sanitize_title( $p->post_title ) );
			if ( $score >= 0.34 && ( ! $best || $score > $best[3] ) ) {
				$best = array( (int) $p->ID, get_permalink( $p ), get_the_title( $p ), $score );
			}
		}
		return $best;
	}

	/**
	 * Can the current user manage redirects?
	 *
	 * @return bool
	 */
	private static function can() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * AJAX: add or replace a rule (also used by the 404 "fix" buttons).
	 */
	public function ajax_save() {
		check_ajax_referer( 'aigs_admin', 'nonce' );
		if ( ! self::can() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'omnigeo-ai-seo' ) ) );
		}
		$from  = isset( $_POST['from'] ) ? sanitize_text_field( wp_unslash( $_POST['from'] ) ) : '';
		$to    = isset( $_POST['to'] ) ? sanitize_text_field( wp_unslash( $_POST['to'] ) ) : '';
		$code  = isset( $_POST['code'] ) ? absint( $_POST['code'] ) : 301;
		$src   = isset( $_POST['src'] ) && '404' === $_POST['src'] ? '404' : 'manual';
		$rules = self::add_rule( self::rules(), $from, $to, $code, $src );
		if ( is_wp_error( $rules ) ) {
			wp_send_json_error( array( 'message' => $rules->get_error_message() ) );
		}
		self::save_rules( $rules );
		$log = get_option( self::LOG, array() );
		if ( is_array( $log ) && isset( $log[ self::key( $from ) ] ) ) {
			unset( $log[ self::key( $from ) ] );
			update_option( self::LOG, $log, false );
		}
		wp_send_json_success( array( 'message' => __( 'Redirect saved. It works right away.', 'omnigeo-ai-seo' ) ) );
	}

	/**
	 * AJAX: delete a rule.
	 */
	public function ajax_delete() {
		check_ajax_referer( 'aigs_admin', 'nonce' );
		if ( ! self::can() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'omnigeo-ai-seo' ) ) );
		}
		$key   = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
		$rules = self::rules();
		unset( $rules[ $key ] );
		self::save_rules( $rules );
		wp_send_json_success();
	}

	/**
	 * AJAX: clear the 404 log (or one entry).
	 */
	public function ajax_clear_404() {
		check_ajax_referer( 'aigs_admin', 'nonce' );
		if ( ! self::can() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'omnigeo-ai-seo' ) ) );
		}
		$key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
		if ( '' === $key ) {
			delete_option( self::LOG );
		} else {
			$log = get_option( self::LOG, array() );
			if ( is_array( $log ) ) {
				unset( $log[ $key ] );
				update_option( self::LOG, $log, false );
			}
		}
		wp_send_json_success();
	}

	/**
	 * Rules as CSV lines: from,to,code.
	 *
	 * @param array $rules Rules.
	 * @return string
	 */
	public static function to_csv( $rules ) {
		$out = "from,to,code\n";
		foreach ( $rules as $key => $r ) {
			$out .= self::csv_cell( $key ) . ',' . self::csv_cell( $r['to'] ) . ',' . (int) $r['code'] . "\n";
		}
		return $out;
	}

	/**
	 * Quote a CSV cell when needed (and neutralise spreadsheet formulas).
	 *
	 * @param string $v Value.
	 * @return string
	 */
	private static function csv_cell( $v ) {
		$v = (string) $v;
		if ( '' !== $v && in_array( $v[0], array( '=', '+', '-', '@' ), true ) ) {
			$v = "'" . $v;
		}
		return preg_match( '/[",\n]/', $v ) ? '"' . str_replace( '"', '""', $v ) . '"' : $v;
	}

	/**
	 * Merge CSV text (from,to[,code]) into rules. Accepts commas or tabs and an
	 * optional header row, so exports from other redirect plugins paste in.
	 *
	 * @param array  $rules Rules.
	 * @param string $csv   CSV text.
	 * @return array [rules, added, skipped]
	 */
	public static function from_csv( $rules, $csv ) {
		$added   = 0;
		$skipped = 0;
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $csv ) as $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}
			$cells = false !== strpos( $line, "\t" ) ? explode( "\t", $line ) : str_getcsv( $line );
			$cells = array_map( 'trim', $cells );
			if ( count( $cells ) < 2 || in_array( strtolower( $cells[0] ), array( 'from', 'source', 'source url', 'old' ), true ) ) {
				continue;
			}
			$code = isset( $cells[2] ) && '' !== $cells[2] ? (int) $cells[2] : 301;
			$next = self::add_rule( $rules, ltrim( $cells[0], "'" ), $cells[1], $code, 'import' );
			if ( is_wp_error( $next ) ) {
				$skipped++;
				continue;
			}
			$rules = $next;
			$added++;
		}
		return array( $rules, $added, $skipped );
	}

	/**
	 * Download all rules as CSV.
	 */
	public function download_csv() {
		if ( ! self::can() ) {
			wp_die( esc_html__( 'Permission denied.', 'omnigeo-ai-seo' ) );
		}
		check_admin_referer( 'aigs_redirects_csv' );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="redirects-' . gmdate( 'Y-m-d' ) . '.csv"' );
		echo self::to_csv( self::rules() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download, cells are quoted and formula-safe.
		exit;
	}

	/**
	 * Import pasted CSV.
	 */
	public function import_csv() {
		if ( ! self::can() ) {
			wp_die( esc_html__( 'Permission denied.', 'omnigeo-ai-seo' ) );
		}
		check_admin_referer( 'aigs_redirects_import' );
		$csv = isset( $_POST['csv'] ) ? sanitize_textarea_field( wp_unslash( $_POST['csv'] ) ) : '';
		list( $rules, $added, $skipped ) = self::from_csv( self::rules(), $csv );
		self::save_rules( $rules );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'     => 'aigs-toolbox',
					'tab'      => 'redirects',
					'imported' => $added,
					'skipped'  => $skipped,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
