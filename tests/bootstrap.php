<?php
/**
 * Minimal WordPress stand-ins so the plugin's pure logic can be unit-tested
 * with plain PHP (no database, no WordPress install).
 *
 * Only what the tested code paths call is stubbed; behaviour mirrors core
 * closely enough for the logic under test.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'WEEK_IN_SECONDS', 604800 );
define( 'KB_IN_BYTES', 1024 );
define( 'MB_IN_BYTES', 1048576 );
define( 'ARRAY_A', 'ARRAY_A' );

$GLOBALS['__options'] = array();
$GLOBALS['__home']    = 'https://example.ir';

// AIGS_LOCALE=fa_IR renders through the bundled .mo (used for screenshots).
$GLOBALS['__mo'] = array();
if ( getenv( 'AIGS_LOCALE' ) ) {
	$d = file_get_contents( dirname( __DIR__ ) . '/omnigeo-ai-seo/languages/omnigeo-ai-seo-' . getenv( 'AIGS_LOCALE' ) . '.mo' );
	$h = unpack( 'Vmagic/Vrev/Vn/Vo/Vt', substr( $d, 0, 20 ) );
	for ( $i = 0; $i < $h['n']; $i++ ) {
		$a = unpack( 'Vl/Vp', substr( $d, $h['o'] + 8 * $i, 8 ) );
		$b = unpack( 'Vl/Vp', substr( $d, $h['t'] + 8 * $i, 8 ) );
		$GLOBALS['__mo'][ substr( $d, $a['p'], $a['l'] ) ] = substr( $d, $b['p'], $b['l'] );
	}
}
function __( $s ) { return isset( $GLOBALS['__mo'][ $s ] ) ? $GLOBALS['__mo'][ $s ] : $s; }
function _n( $a, $b, $n ) { $k = $a . "\0" . $b; return isset( $GLOBALS['__mo'][ $k ] ) ? $GLOBALS['__mo'][ $k ] : ( 1 === (int) $n ? $a : $b ); }
function esc_html__( $s ) { return __( $s ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url_raw( $u ) { return filter_var( trim( (string) $u ), FILTER_SANITIZE_URL ); }
function esc_url( $u ) { return esc_url_raw( $u ); }
function wp_strip_all_tags( $s ) { return trim( strip_tags( preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $s ) ) ); }
function sanitize_text_field( $s ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', wp_strip_all_tags( (string) $s ) ) ); }
function sanitize_textarea_field( $s ) { return trim( wp_strip_all_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_email( $s ) { return filter_var( $s, FILTER_VALIDATE_EMAIL ) ? $s : ''; }
function absint( $n ) { return abs( (int) $n ); }
function wp_parse_args( $a, $d = array() ) { return array_merge( $d, (array) $a ); }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function wp_json_encode( $d ) { return json_encode( $d ); }
function wp_kses_post( $s ) { return (string) $s; }
function home_url( $p = '' ) { return rtrim( $GLOBALS['__home'], '/' ) . '/' . ltrim( $p, '/' ); }
function get_bloginfo( $k ) { return 'Test Shop'; }
function get_locale() { return 'fa_IR'; }
function apply_filters( $h, $v ) { return $v; }
function add_action() {}
function add_filter() {}
function current_time( $t ) { return 'mysql' === $t ? gmdate( 'Y-m-d H:i:s' ) : time(); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['__options'] ) ? $GLOBALS['__options'][ $k ] : $d; }
function update_option( $k, $v ) { $GLOBALS['__options'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['__options'][ $k ] ); }
function wp_http_validate_url( $u ) { return (bool) preg_match( '#^https?://[^\s/]+#i', (string) $u ) ? $u : false; }
function wp_trim_words( $text, $n = 55, $more = '…' ) {
	$w = preg_split( '/\s+/u', trim( wp_strip_all_tags( $text ) ), -1, PREG_SPLIT_NO_EMPTY );
	return count( $w ) > $n ? implode( ' ', array_slice( $w, 0, $n ) ) . $more : implode( ' ', $w );
}
function wp_generate_password( $n ) { return str_repeat( 'k', $n ); }
$GLOBALS['__is_admin'] = false;
function is_admin() { return $GLOBALS['__is_admin']; }
function wp_doing_cron() { return false; }
function add_option( $k, $v ) { if ( array_key_exists( $k, $GLOBALS['__options'] ) ) { return false; } $GLOBALS['__options'][ $k ] = $v; return true; }
function remove_accents( $s ) { return iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $s ); }
function number_format_i18n( $n, $d = 0 ) { return number_format( $n, $d ); }
// Captures outgoing HTTP calls; tests set $GLOBALS['__http_reply'] to the fake response body.
$GLOBALS['__http'] = array();
function wp_remote_post( $url, $args ) {
	$GLOBALS['__http'][] = array( 'url' => $url, 'args' => $args );
	return array( 'code' => 200, 'body' => isset( $GLOBALS['__http_reply'] ) ? $GLOBALS['__http_reply'] : '{}' );
}
function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) && isset( $r['code'] ) ? $r['code'] : 0; }
function wp_remote_retrieve_body( $r ) { return is_array( $r ) && isset( $r['body'] ) ? $r['body'] : ''; }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
class WP_Error {
	private $c;
	private $m;
	public function __construct( $c = '', $m = '' ) { $this->c = $c; $this->m = $m; }
	public function get_error_code() { return $this->c; }
	public function get_error_message() { return $this->m; }
}

/**
 * Settings helper (copied from the main plugin file).
 */
function aigs_get( $key, $default = '' ) {
	$s = get_option( 'aigs_settings', array() );
	return isset( $s[ $key ] ) ? $s[ $key ] : $default;
}
function aigs_business_name() {
	$n = trim( (string) aigs_get( 'business_name', '' ) );
	return '' !== $n ? $n : 'Test Shop';
}

$dir = dirname( __DIR__ ) . '/omnigeo-ai-seo/includes/';
foreach ( array( 'text', 'db', 'ai-client', 'tracker', 'seo', 'answer', 'rules', 'findings', 'ai-visibility', 'llms', 'audit', 'woocommerce', 'admin', 'actions', 'scanner', 'entity', 'links', 'visibility' ) as $f ) {
	require_once $dir . 'class-aigs-' . $f . '.php';
}

/* ---------- tiny assertion helpers ---------- */
$GLOBALS['__pass'] = 0;
$GLOBALS['__fail'] = 0;
function check( $name, $cond, $info = '' ) {
	if ( $cond ) {
		$GLOBALS['__pass']++;
	} else {
		$GLOBALS['__fail']++;
		echo "FAIL: {$name}" . ( '' !== $info ? " -> {$info}" : '' ) . "\n";
	}
}
function same( $name, $expected, $actual ) {
	check( $name, $expected === $actual, 'expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
}
