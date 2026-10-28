<?php
/**
 * Plugin Name:       OmniGEO Ai SEO
 * Plugin URI:        https://wordpress.org/plugins/omnigeo-ai-seo/
 * Description:       AEO and GEO for WordPress: scan your site, fix prioritised tasks, optimise answers in the editor, improve WooCommerce products and track AI visibility.
 * Version:           1.1.0
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

define( 'AIGS_VERSION', '1.1.0' );
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
require_once AIGS_DIR . 'includes/class-aigs-extract.php';
require_once AIGS_DIR . 'includes/class-aigs-rules.php';
require_once AIGS_DIR . 'includes/class-aigs-findings.php';
require_once AIGS_DIR . 'includes/class-aigs-scanner.php';
require_once AIGS_DIR . 'includes/class-aigs-actions.php';
require_once AIGS_DIR . 'includes/class-aigs-visibility.php';
require_once AIGS_DIR . 'includes/class-aigs-ai-visibility.php';
require_once AIGS_DIR . 'includes/class-aigs-woocommerce.php';
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
 * Boot the plugin.
 */
function aigs_run() {
	AIGS_Loader::instance();
}
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
