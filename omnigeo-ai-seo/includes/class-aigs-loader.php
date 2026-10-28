<?php
/**
 * Core loader.
 *
 * @package OmniGEO_Ai_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires all modules together.
 */
class AIGS_Loader {

	/**
	 * Singleton instance.
	 *
	 * @var AIGS_Loader|null
	 */
	private static $instance = null;

	/**
	 * Module handles.
	 *
	 * @var array
	 */
	public $modules = array();

	/**
	 * Get instance.
	 *
	 * @return AIGS_Loader
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->modules['db']            = new AIGS_DB();
		$this->modules['tracker']       = new AIGS_Tracker();
		$this->modules['schema']        = new AIGS_Schema();
		$this->modules['entity']        = new AIGS_Entity();
		$this->modules['llms']          = new AIGS_LLMS();
		$this->modules['audit']         = new AIGS_Audit();
		$this->modules['answer']        = new AIGS_Answer();
		$this->modules['extract']       = new AIGS_Extract();
		$this->modules['scanner']       = new AIGS_Scanner();
		$this->modules['actions']       = new AIGS_Actions();
		$this->modules['visibility']    = new AIGS_Visibility();
		$this->modules['ai_visibility'] = new AIGS_AI_Visibility();
		$this->modules['woocommerce']   = new AIGS_WooCommerce();
		$this->modules['admin']         = new AIGS_Admin( $this->modules );

		foreach ( $this->modules as $module ) {
			if ( method_exists( $module, 'hooks' ) ) {
				$module->hooks();
			}
		}

		add_filter( 'plugin_action_links_' . AIGS_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Add settings link on plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function action_links( $links ) {
		$url  = admin_url( 'admin.php?page=omnigeo-ai-seo' );
		$link = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Dashboard', 'omnigeo-ai-seo' ) . '</a>';
		array_unshift( $links, $link );
		return $links;
	}
}
