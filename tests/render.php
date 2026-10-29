<?php
/**
 * Render smoke test: every admin page renders with sample data and without
 * PHP notices or warnings. Run: php tests/render.php
 */

require __DIR__ . '/bootstrap.php';

set_error_handler(
	static function ( $no, $str, $file, $line ) {
		throw new ErrorException( $str . ' @ ' . basename( $file ) . ':' . $line, 0, $no, $file, $line );
	}
);

define( 'AIGS_VERSION', 'test' );
define( 'AIGS_URL', 'https://example.ir/wp-content/plugins/omnigeo-ai-seo/' );
define( 'M_PI_TEST', M_PI );

/* ---------- more WordPress stand-ins (render-only) ---------- */
function esc_attr__( $s ) { return esc_attr( __( $s ) ); }
function esc_html_e( $s ) { echo esc_html( __( $s ) ); }
function esc_attr_e( $s ) { echo esc_attr( __( $s ) ); }
function esc_textarea( $s ) { return esc_html( $s ); }
function wp_kses( $s ) { return (string) $s; }
function selected( $a, $b, $echo = true ) { $r = (string) $a === (string) $b ? ' selected="selected"' : ''; if ( $echo ) { echo $r; } return $r; }
function checked( $a, $b = true, $echo = true ) { $r = (string) $a === (string) $b ? ' checked="checked"' : ''; if ( $echo ) { echo $r; } return $r; }
function disabled( $a, $b = true, $echo = true ) { $r = (bool) $a === (bool) $b ? ' disabled="disabled"' : ''; if ( $echo ) { echo $r; } return $r; }
function settings_fields() {}
function admin_url( $p = '' ) { return 'https://example.ir/wp-admin/' . $p; }
function add_query_arg( $k, $v, $u ) { return $u . ( false === strpos( $u, '?' ) ? '?' : '&' ) . $k . '=' . rawurlencode( $v ); }
function wp_list_pluck( $list, $field ) { return array_map( static function ( $r ) use ( $field ) { return is_array( $r ) ? $r[ $field ] : $r->$field; }, $list ); }
function get_post( $id ) { return (object) array( 'ID' => $id, 'post_type' => 'post', 'post_title' => 'Post ' . $id, 'post_content' => '<p>x</p>' ); }
function get_post_meta( $id, $k = '', $single = false ) { $m = array( '_aigs_importance' => '70', '_aigs_page_score' => '58', '_aigs_important' => '' ); return isset( $m[ $k ] ) ? $m[ $k ] : ''; }
function get_the_title( $p ) { return 'Title ' . ( is_object( $p ) ? $p->ID : $p ); }
function get_post_type_object( $t ) { return (object) array( 'labels' => (object) array( 'singular_name' => 'Post' ), 'public' => true ); }
function get_edit_post_link( $id ) { return 'https://example.ir/wp-admin/post.php?post=' . $id; }
function get_permalink( $id ) { return 'https://example.ir/?p=' . $id; }
function get_edit_user_link( $id ) { return 'https://example.ir/wp-admin/user-edit.php?user_id=' . $id; }
function _prime_post_caches() {}
function human_time_diff( $a, $b ) { return abs( $b - $a ) . ' s'; }
function mysql2date( $f, $d ) { return $d; }
function wp_create_nonce() { return 'nonce'; }
function get_current_user_id() { return 1; }
function wp_enqueue_style() {}
function wp_enqueue_script() {}
function wp_localize_script( $h, $n, $d ) { $GLOBALS['__l10n'] = $d; }
function add_menu_page() {}
function add_submenu_page( $p, $t, $m ) { $GLOBALS['__menu'][] = $m; }
function wp_add_dashboard_widget() {}
function current_user_can() { return true; }
function register_setting() {}
function get_userdata() { return false; }
function did_action() { return 0; }
function get_pages() { return array(); }
function get_posts() { return array(); }
function wp_specialchars_decode( $s ) { return $s; }
function wp_unslash( $s ) { return $s; }
function is_rtl() { return (bool) getenv( 'AIGS_LOCALE' ); }

/**
 * Fake $wpdb answering by query pattern.
 */
class FakeWPDB {
	public $prefix = 'wp_';
	public $posts  = 'wp_posts';
	public $options = 'wp_options';
	public $insert_id = 1;
	public $last = '';
	public function prepare( $q ) { $args = func_get_args(); array_shift( $args ); if ( isset( $args[0] ) && is_array( $args[0] ) ) { $args = $args[0]; } foreach ( $args as $a ) { $q = preg_replace( '/%[dsf]/', is_numeric( $a ) ? (string) $a : "'" . addslashes( (string) $a ) . "'", $q, 1 ); } return $q; }
	public function get_charset_collate() { return ''; }
	public function suppress_errors() {}
	public function query( $q ) { return 0; }
	public function insert() { return 1; }
	public function update() { return 1; }
	public function get_var( $q ) { return 3; }
	public function get_col( $q ) {
		if ( false !== strpos( $q, 'SELECT citations' ) ) { return array( '["https://www.zoomit.ir/a","https://digikala.com/x","https://torob.com/y"]', '["https://www.zoomit.ir/b"]' ); }
		return array( 'thin_content' );
	}
	public function get_row( $q ) {
		return array( 'id' => 7, 'post_id' => 0, 'rule' => 'vis_gap_x', 'evidence' => '{}', 'prompt' => 'q', 'provider' => 'perplexity', 'model' => 'sonar', 'grounded' => 1, 'mentioned' => 1, 'recommended' => 0, 'cited' => 1, 'position' => 1, 'competitors' => '{}', 'citations' => '[]', 'answer' => 'a', 'created_at' => '2026-10-01 10:00:00' );
	}
	public function get_results( $q ) {
		$this->last = $q;
		if ( false !== strpos( $q, 'GROUP BY rule' ) ) {
			return array(
				array( 'rule' => 'site_noindex', 'n' => 1, 'p' => 150, 's' => 4 ),
				array( 'rule' => 'no_direct_answer', 'n' => 12, 'p' => 600, 's' => 2 ),
				array( 'rule' => 'orphan', 'n' => 4, 'p' => 200, 's' => 2 ),
				array( 'rule' => 'product_short_desc', 'n' => 9, 'p' => 300, 's' => 2 ),
				array( 'rule' => 'vis_gap_0123456789abcdef', 'n' => 1, 'p' => 75, 's' => 2 ),
				array( 'rule' => 'bot_404', 'n' => 1, 'p' => 112, 's' => 3 ),
				array( 'rule' => 'vis_fact_0123456789abcdef', 'n' => 1, 'p' => 112, 's' => 3 ),
				array( 'rule' => 'edge_blocks_ai', 'n' => 1, 'p' => 112, 's' => 3 ),
			);
		}
		if ( false !== strpos( $q, 'GROUP BY status, severity' ) ) {
			return array( array( 'status' => 'open', 'severity' => 4, 'n' => 1 ), array( 'status' => 'open', 'severity' => 2, 'n' => 20 ), array( 'status' => 'ignored', 'severity' => 1, 'n' => 2 ), array( 'status' => 'fixed', 'severity' => 2, 'n' => 5 ) );
		}
		if ( false !== strpos( $q, 'aigs_findings WHERE' ) ) {
			return array(
				array( 'id' => 1, 'post_id' => 11, 'rule' => 'orphan', 'severity' => 2, 'priority' => 50, 'status' => 'open', 'evidence' => '{"suggest":[12,13]}', 'resolved_at' => '2026-10-20 10:00:00' ),
				array( 'id' => 2, 'post_id' => 12, 'rule' => 'no_direct_answer', 'severity' => 2, 'priority' => 40, 'status' => 'open', 'evidence' => '{"question":"What is X?","reason":"filler"}', 'resolved_at' => '2026-10-20 10:00:00' ),
				array( 'id' => 3, 'post_id' => 0, 'rule' => 'bot_404', 'severity' => 3, 'priority' => 100, 'status' => 'open', 'evidence' => '{"urls":{"/old-page/":7}}', 'resolved_at' => null ),
				array( 'id' => 4, 'post_id' => 0, 'rule' => 'vis_gap_0123456789abcdef', 'severity' => 2, 'priority' => 75, 'status' => 'open', 'evidence' => '{"prompt":"بهترین فروشگاه؟","competitors":["Torob"]}', 'resolved_at' => null ),
				array( 'id' => 5, 'post_id' => 0, 'rule' => 'site_meta_fallback', 'severity' => 2, 'priority' => 75, 'status' => 'open', 'evidence' => '{}', 'resolved_at' => null ),
				array( 'id' => 6, 'post_id' => 13, 'rule' => 'author_profile', 'severity' => 1, 'priority' => 20, 'status' => 'open', 'evidence' => '{"author_id":2}', 'resolved_at' => null ),
				array( 'id' => 7, 'post_id' => 0, 'rule' => 'vis_fact_0123456789abcdef', 'severity' => 3, 'priority' => 112, 'status' => 'open', 'evidence' => '{"prompt":"Best plumber?","issues":[["phone","020 1","020 2"],["founded","2012","2009"]]}', 'resolved_at' => null ),
				array( 'id' => 8, 'post_id' => 0, 'rule' => 'edge_blocks_ai', 'severity' => 3, 'priority' => 112, 'status' => 'open', 'evidence' => '{"cdn":"Cloudflare","blocked":["OAI-SearchBot","GPTBot"]}', 'resolved_at' => null ),
				array( 'id' => 9, 'post_id' => 14, 'rule' => 'no_internal_links', 'severity' => 1, 'priority' => 20, 'status' => 'open', 'evidence' => '{"suggest":[]}', 'resolved_at' => null ),
			);
		}
		if ( false !== strpos( $q, 'GROUP BY batch_id' ) ) {
			return array( array( 'batch_id' => 'b2', 't' => '2026-10-20 10:00:00', 'n' => 2, 'm' => 1, 'c' => 1 ), array( 'batch_id' => 'b1', 't' => '2026-10-13 10:00:00', 'n' => 2, 'm' => 0, 'c' => 0 ) );
		}
		if ( false !== strpos( $q, 'FROM wp_aigs_ai_runs WHERE batch_id' ) ) {
			return array(
				array( 'id' => 1, 'batch_id' => 'b', 'prompt_hash' => 'h1', 'prompt' => 'Best shop?', 'provider' => 'perplexity', 'model' => 'sonar', 'grounded' => 1, 'mentioned' => 1, 'recommended' => 1, 'cited' => 1, 'position' => 1, 'competitors' => '{"Torob":true}', 'citations' => '[]', 'issues' => '[["phone","020 1","020 2"]]', 'created_at' => '2026-10-20 10:00:00' ),
				array( 'id' => 2, 'batch_id' => 'b', 'prompt_hash' => 'h2', 'prompt' => 'Cheap phones?', 'provider' => 'perplexity', 'model' => 'sonar', 'grounded' => 1, 'mentioned' => 0, 'recommended' => 0, 'cited' => 0, 'position' => 0, 'competitors' => '{"Torob":true}', 'citations' => '[]', 'created_at' => '2026-10-20 10:00:00' ),
			);
		}
		if ( false !== strpos( $q, 'GROUP BY bot_class, verified' ) ) {
			return array( array( 'bot_class' => 'fetch', 'verified' => 1, 'c' => 5 ), array( 'bot_class' => 'training', 'verified' => 2, 'c' => 3 ), array( 'bot_class' => 'search', 'verified' => 3, 'c' => 4 ) );
		}
		if ( false !== strpos( $q, 'GROUP BY bot, platform' ) ) {
			return array( array( 'bot' => 'GPTBot', 'platform' => 'OpenAI', 'bot_class' => 'training', 'c' => 9, 'v' => 6, 'f' => 3 ) );
		}
		if ( false !== strpos( $q, 'GROUP BY platform' ) ) {
			return array( array( 'platform' => 'OpenAI', 'c' => 9 ) );
		}
		if ( false !== strpos( $q, 'GROUP BY source' ) ) {
			return array( array( 'source' => 'utm', 'c' => 2 ) );
		}
		if ( false !== strpos( $q, 'GROUP BY url' ) ) {
			return array( array( 'url' => '/blog/a/', 'c' => 4 ) );
		}
		if ( false !== strpos( $q, 'GROUP BY DATE' ) ) {
			return array( array( 'd' => gmdate( 'Y-m-d' ), 'c' => 3 ) );
		}
		return array();
	}
}
$GLOBALS['wpdb'] = new FakeWPDB();

// Plugin-side helpers not in bootstrap.
if ( ! class_exists( 'WooCommerce' ) ) {
	class WooCommerce {} // Pretend WooCommerce is active so the Products page renders.
}
update_option( 'aigs_settings', array( 'business_name' => 'دیجی‌کالا', 'industry' => 'فروشگاه آنلاین', 'competitors' => "Torob | torob.com", 'vis_prompts' => "Best shop?\nCheap phones?", 'ai_api_key' => 'k', 'ai_provider' => 'perplexity', 'verify_bots' => 1, 'business_type' => 'Dentist', 'biz_hours' => array( 'mo' => '9-17' ), 'fact_phones' => '+1 555', 'fact_outdated' => 'Old Road' ) );
update_option( AIGS_Links::LOG_OPTION, array( array( 'id' => 'lop1', 'time' => time() - 30, 'user' => 1, 'items' => array( array( 'op' => 'lnk1', 'source' => 12, 'targets' => array( 11, 13 ) ) ) ) ) );
update_option( 'aigs_scan_summary', array( 'score' => 71, 'pages' => 40, 'scanned_at' => time() - 3600, 'mode' => 'manual', 'new' => 2, 'open' => 21 ) );
update_option( 'aigs_score_history', array( array( 't' => 1, 'score' => 60, 'open' => 30 ), array( 't' => 2, 'score' => 71, 'open' => 21 ) ) );
update_option( 'aigs_bulk_log', array( array( 'op' => 'op1', 'fix' => 'alt_from_name', 'time' => time() - 60, 'user' => 1, 'items' => array( array( 'id' => 1 ) ) ) ) );
update_option( 'date_format', 'Y-m-d' );
update_option( 'time_format', 'H:i' );

$tracker = new AIGS_Tracker();
$admin   = new AIGS_Admin(
	array(
		'tracker' => $tracker,
		'llms'    => new AIGS_LLMS(),
	)
);

$pages = array( 'page_dashboard', 'page_actions', 'page_products', 'page_ai_visibility', 'page_traffic', 'page_audit', 'page_settings', 'page_help', 'render_widget' );
$ok    = 0;
foreach ( $pages as $p ) {
	try {
		ob_start();
		$admin->$p();
		$html = ob_get_clean();
		check( "render {$p} produced html", strlen( $html ) > 200, (string) strlen( $html ) );
		// Balanced divs is a cheap structural sanity check.
		same( "render {$p} balanced divs", substr_count( $html, '<div' ), substr_count( $html, '</div>' ) );
		$ok++;
	} catch ( Throwable $e ) {
		ob_end_clean();
		check( "render {$p}", false, $e->getMessage() );
	}
}

// Action Center task detail views.
foreach ( array( 'orphan', 'vis_gap', 'vis_fact', 'bot_404', 'site_meta_fallback', 'author_profile', 'edge_blocks_ai', 'no_internal_links' ) as $rule ) {
	$_GET = array( 'rule' => $rule );
	try {
		ob_start();
		$admin->page_actions();
		$html = ob_get_clean();
		same( "task view {$rule} balanced divs", substr_count( $html, '<div' ), substr_count( $html, '</div>' ) );
		check( "task view {$rule} has rows", false !== strpos( $html, 'aigs-row' ) );
	} catch ( Throwable $e ) {
		ob_end_clean();
		check( "task view {$rule}", false, $e->getMessage() );
	}
}
// 1.2 UI pieces.
$_GET = array( 'rule' => 'orphan' );
ob_start();
$admin->page_actions();
$html = ob_get_clean();
check( 'orphan row has link finder', false !== strpos( $html, 'data-aigs-links="to"' ) && false !== strpos( $html, 'aigs-linkbox' ) );
check( 'link undo log rendered', false !== strpos( $html, 'data-aigs-links-undo="lop1"' ) );
$_GET = array( 'rule' => 'vis_fact' );
ob_start();
$admin->page_actions();
$html = ob_get_clean();
check( 'vis_fact row lists issues', false !== strpos( $html, '020 1' ) && false !== strpos( $html, 'data-aigs-trigger-facts' ) );
ob_start();
$admin->page_settings();
$html = ob_get_clean();
check( 'settings has local card', false !== strpos( $html, 'id="aigs-local"' ) && false !== strpos( $html, 'biz_hours][mo]' ) );
check( 'settings has provider endpoint + test', false !== strpos( $html, 'ai_base_url' ) && false !== strpos( $html, 'id="aigs-ai-test"' ) );
check( 'settings dentist selected', false !== strpos( $html, 'value="Dentist" data-local="1"  selected' ) || false !== strpos( $html, 'value="Dentist" data-local="1" selected' ) );
ob_start();
$admin->page_ai_visibility();
$html = ob_get_clean();
check( 'visibility facts form', false !== strpos( $html, 'fact_outdated' ) && false !== strpos( $html, 'id="aigs-facts-page"' ) );
check( 'visibility wrong facts column', false !== strpos( $html, 'aigs-tag--warn' ) && false !== strpos( $html, 'aigs-callout--warn' ) );

$_GET = array( 'status' => 'ignored' );
ob_start();
$admin->page_actions();
$html = ob_get_clean();
check( 'ignored view renders', false !== strpos( $html, 'data-aigs-status="open"' ) );
$_GET = array();

// Menu + localized strings.
$GLOBALS['__menu'] = array();
$admin->menu();
check( 'menu has Action Center badge', (bool) preg_grep( '/awaiting-mod/', $GLOBALS['__menu'] ) );
$admin->assets( 'toplevel_page_omnigeo-ai-seo' );
check( 'js i18n has scanProgress', isset( $GLOBALS['__l10n']['i18n']['scanProgress'] ) );

// Every i18n key used in admin.js exists in the localized array.
$js = file_get_contents( dirname( __DIR__ ) . '/omnigeo-ai-seo/assets/admin.js' );
preg_match_all( "/\bt\(\s*'([a-zA-Z]+)'/", $js, $m );
foreach ( array_unique( $m[1] ) as $key ) {
	check( "js i18n key {$key}", isset( $GLOBALS['__l10n']['i18n'][ $key ] ) );
}

echo "\n{$GLOBALS['__pass']} passed, {$GLOBALS['__fail']} failed\n";
exit( $GLOBALS['__fail'] ? 1 : 0 );
