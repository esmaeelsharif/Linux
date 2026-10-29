<?php
/**
 * Plugin Name:       OmniGEO Ai SEO
 * Plugin URI:        https://wordpress.org/plugins/omnigeo-ai-seo/
 * Description:       AEO and GEO for WordPress: scan your site, fix prioritised tasks, optimise answers in the editor, improve WooCommerce products and track AI visibility.
 * Version:           1.5.0
 * Requires at least: 5.8
 * Requires PHP:      7.2
 * Author:            Deepwp
 * Author URI:        https://profiles.wordpress.org/deepwp/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       omnigeo-ai-seo
 * Domain Path:       /languages
 *
 * @package OmniGEO_Ai_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AIGS_VERSION', '1.5.0' );
define( 'AIGS_FILE', __FILE__ );
define( 'AIGS_DIR', plugin_dir_path( __FILE__ ) );
define( 'AIGS_URL', plugin_dir_url( __FILE__ ) );
define( 'AIGS_BASENAME', plugin_basename( __FILE__ ) );

require_once AIGS_DIR . 'includes/class-aigs-text.php';
require_once AIGS_DIR . 'includes/class-aigs-db.php';
require_once AIGS_DIR . 'includes/class-aigs-ai-client.php';
require_once AIGS_DIR . 'includes/class-aigs-tracker.php';
require_once AIGS_DIR . 'includes/class-aigs-seo.php';
require_once AIGS_DIR . 'includes/class-aigs-schema.php';
require_once AIGS_DIR . 'includes/class-aigs-entity.php';
require_once AIGS_DIR . 'includes/class-aigs-llms.php';
require_once AIGS_DIR . 'includes/class-aigs-audit.php';
require_once AIGS_DIR . 'includes/class-aigs-answer.php';
require_once AIGS_DIR . 'includes/class-aigs-citability.php';
require_once AIGS_DIR . 'includes/class-aigs-extract.php';
require_once AIGS_DIR . 'includes/class-aigs-rules.php';
require_once AIGS_DIR . 'includes/class-aigs-findings.php';
require_once AIGS_DIR . 'includes/class-aigs-scanner.php';
require_once AIGS_DIR . 'includes/class-aigs-actions.php';
require_once AIGS_DIR . 'includes/class-aigs-links.php';
require_once AIGS_DIR . 'includes/class-aigs-visibility.php';
require_once AIGS_DIR . 'includes/class-aigs-ai-visibility.php';
require_once AIGS_DIR . 'includes/class-aigs-woocommerce.php';
require_once AIGS_DIR . 'includes/class-aigs-revenue.php';
require_once AIGS_DIR . 'includes/class-aigs-redirects.php';
require_once AIGS_DIR . 'includes/class-aigs-head.php';
require_once AIGS_DIR . 'includes/class-aigs-markdown.php';
require_once AIGS_DIR . 'includes/class-aigs-tools.php';
require_once AIGS_DIR . 'includes/class-aigs-admin-toolbox.php';
require_once AIGS_DIR . 'includes/class-aigs-admin.php';
require_once AIGS_DIR . 'includes/class-aigs-loader.php';

/**
 * Activation: create DB tables, default options and scheduled jobs.
 */
function aigs_activate() {
	AIGS_DB::install();

	$defaults = array(
		'enable_schema'   => 1,
		'enable_tracking' => 1,
		'enable_llms'     => 1,
		'business_name'   => get_bloginfo( 'name' ),
		'business_type'   => 'Organization',
		'industry'        => '',
		'location'        => '',
		'ai_provider'     => 'openai',
		'ai_api_key'      => '',
		'indexnow_key'    => wp_generate_password( 32, false, false ),
		'llms_txt'        => '',
	);
	$existing = get_option( 'aigs_settings', array() );
	update_option( 'aigs_settings', wp_parse_args( is_array( $existing ) ? $existing : array(), $defaults ) );

	AIGS_DB::ensure_events();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'aigs_activate' );

/**
 * Deactivation: stop background jobs (data is kept until uninstall).
 */
function aigs_deactivate() {
	AIGS_DB::clear_events();
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'aigs_deactivate' );

/**
 * Load bundled translations (Persian ships with the plugin). Language packs
 * from translate.wordpress.org in wp-content/languages take priority.
 */
function aigs_load_textdomain() {
	load_plugin_textdomain( 'omnigeo-ai-seo', false, dirname( AIGS_BASENAME ) . '/languages' ); // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- bundled fa_IR until it is published as a language pack.
}
add_action( 'init', 'aigs_load_textdomain' );

/**
 * Record an error from the plugin without breaking the site. Kept to the last
 * 20 entries and shown to admins as a notice.
 *
 * @param string    $where Context.
 * @param Throwable $e     Error.
 */
function aigs_log_error( $where, $e ) {
	$msg = $where . ': ' . $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ')';
	if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
		error_log( 'OmniGEO Ai SEO ' . $msg ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- only when the site owner enabled the debug log.
	}
	$log = get_option( 'aigs_error_log', array() );
	$log = is_array( $log ) ? $log : array();
	array_unshift(
		$log,
		array(
			'time' => time(),
			'msg'  => substr( $msg, 0, 500 ),
		)
	);
	update_option( 'aigs_error_log', array_slice( $log, 0, 20 ), false );
}

/**
 * Wrap a hook callback so an error inside the plugin can never take the site
 * down. Filters get their first argument back unchanged when something fails.
 *
 * @param callable $callback Callback.
 * @param bool     $filter   Is this a filter (return first arg on error)?
 * @return Closure
 */
function aigs_guard( $callback, $filter = false ) {
	return static function () use ( $callback, $filter ) {
		$args = func_get_args();
		try {
			return call_user_func_array( $callback, $args );
		} catch ( Throwable $e ) {
			aigs_log_error( is_array( $callback ) ? ( is_object( $callback[0] ) ? get_class( $callback[0] ) : $callback[0] ) . '::' . $callback[1] : 'callback', $e );
			return ( $filter && $args ) ? $args[0] : null;
		}
	};
}

/**
 * Boot the plugin. If anything fails here, the site keeps running without it.
 */
function aigs_run() {
	try {
		AIGS_Loader::instance();
	} catch ( Throwable $e ) {
		aigs_log_error( 'boot', $e );
	}
}

/**
 * Tell administrators when the plugin caught an error (so it is not silent).
 */
function aigs_error_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$log = get_option( 'aigs_error_log', array() );
	if ( ! is_array( $log ) || ! $log || (int) $log[0]['time'] < time() - DAY_IN_SECONDS ) {
		return;
	}
	echo '<div class="notice notice-warning is-dismissible"><p><strong>OmniGEO Ai SEO:</strong> ';
	echo esc_html__( 'A feature hit an error and was skipped, so your site kept working. Please send this to support:', 'omnigeo-ai-seo' );
	echo ' <code>' . esc_html( $log[0]['msg'] ) . '</code></p></div>';
}
add_action( 'admin_notices', 'aigs_error_notice' );
add_action( 'plugins_loaded', 'aigs_run' );

/**
 * Settings helper.
 *
 * @param string $key     Option key.
 * @param mixed  $default Default value.
 * @return mixed
 */
function aigs_get( $key, $default = '' ) {
	$settings = get_option( 'aigs_settings', array() );
	return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
}

/**
 * Resolve the brand/business name with a safe, never-empty fallback chain:
 * saved business name, then the site name, then the domain host.
 *
 * @return string
 */
function aigs_business_name() {
	$name = trim( (string) aigs_get( 'business_name', '' ) );
	if ( '' === $name ) {
		$name = trim( (string) get_bloginfo( 'name' ) );
	}
	if ( '' === $name ) {
		$name = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	}
	return $name;
}
