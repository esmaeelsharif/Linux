<?php
/**
 * Admin UI: menus, pages, settings, dashboard.
 *
 * Visual language: Vercel (hairline borders, monochrome, black primary action)
 * blended with Apple (SF Pro system type, generous radius, calm motion).
 *
 * @package OmniGEO_Ai_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the wp-admin experience.
 */
class AIGS_Admin {

	use AIGS_Admin_Toolbox;

	/**
	 * Module references.
	 *
	 * @var array
	 */
	private $modules;

	/**
	 * Constructor.
	 *
	 * @param array $modules Modules.
	 */
	public function __construct( $modules ) {
		$this->modules = $modules;
	}

	/**
	 * Register hooks.
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'dashboard_widget' ) );
	}

	/* ============================================================
	 * Icon system, thin-stroke Lucide/Geist-style inline SVGs.
	 * ============================================================ */

	/**
	 * Return an inline SVG icon.
	 *
	 * @param string $name Icon name.
	 * @param int    $size Pixel size.
	 * @return string
	 */
	public static function icon( $name, $size = 20 ) {
		$paths = array(
			'sparkles'  => '<path d="M12 3v4M12 17v4M5 12H1M23 12h-4"/><path d="M12 7l1.6 3.4L17 12l-3.4 1.6L12 17l-1.6-3.4L7 12l3.4-1.6z"/>',
			'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
			'gauge'     => '<path d="M12 14l4-4"/><path d="M3.5 14a8.5 8.5 0 1 1 17 0"/><circle cx="12" cy="14" r="1.2" fill="currentColor" stroke="none"/>',
			'radar'     => '<path d="M19.07 4.93A10 10 0 1 0 22 12"/><path d="M13.5 8.5A4 4 0 1 0 16 12"/><path d="M12 12l8.5-8.5"/>',
			'shield'    => '<path d="M12 3l7 3v5c0 4.4-3 8.3-7 9.5C8 19.3 5 15.4 5 11V6z"/><path d="M9.2 12l2 2 3.6-3.6"/>',
			'file'      => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h6"/>',
			'sliders'   => '<path d="M4 6h10M18 6h2M4 12h2M10 12h10M4 18h7M15 18h5"/><circle cx="16" cy="6" r="2"/><circle cx="8" cy="12" r="2"/><circle cx="13" cy="18" r="2"/>',
			'bot'       => '<rect x="4" y="8" width="16" height="11" rx="3"/><path d="M12 4v4M8 13h.01M16 13h.01"/><path d="M2 14v1M22 14v1"/>',
			'link'      => '<path d="M9 15l6-6"/><path d="M11 6l1-1a4 4 0 0 1 6 6l-1 1"/><path d="M13 18l-1 1a4 4 0 0 1-6-6l1-1"/>',
			'globe'     => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3c2.5 2.5 3.8 5.7 3.8 9s-1.3 6.5-3.8 9c-2.5-2.5-3.8-5.7-3.8-9S9.5 5.5 12 3z"/>',
			'zap'       => '<path d="M13 2L4 14h7l-1 8 9-12h-7z"/>',
			'info'      => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
			'search'    => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
			'arrow'     => '<path d="M5 12h14M13 6l6 6-6 6"/>',
			'check'     => '<path d="M20 6L9 17l-5-5"/>',
			'x'         => '<path d="M18 6L6 18M6 6l12 12"/>',
			'activity'  => '<path d="M3 12h4l3 8 4-16 3 8h4"/>',
			'quote'     => '<path d="M7 7h4v6c0 2-1.5 3.5-3.5 4M14 7h4v6c0 2-1.5 3.5-3.5 4"/>',
			'list'      => '<path d="M9 6h11M9 12h11M9 18h11"/><path d="M4 6h.01M4 12h.01M4 18h.01"/>',
			'cart'      => '<circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2 3h3l2.6 12.2a1.5 1.5 0 0 0 1.5 1.2h8.6a1.5 1.5 0 0 0 1.5-1.2L21 7H6"/>',
			'trend'     => '<path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/>',
			'flag'      => '<path d="M5 21V4"/><path d="M5 4h11l-2 4 2 4H5"/>',
			'refresh'   => '<path d="M20 11a8 8 0 1 0-2.3 5.7"/><path d="M20 4v7h-7"/>',
			'undo'      => '<path d="M9 14L4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-3"/>',
			'eye'       => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
			'edit'      => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="M14 6l4 4"/>',
			'star'      => '<path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/>',
			'trophy'    => '<path d="M8 4h8v5a4 4 0 0 1-8 0z"/><path d="M8 6H5a3 3 0 0 0 3 4M16 6h3a3 3 0 0 1-3 4"/><path d="M12 13v4M8 21h8M9 17h6"/>',
		);
		$inner = isset( $paths[ $name ] ) ? $paths[ $name ] : '';
		return sprintf(
			'<svg width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%2$s</svg>',
			(int) $size,
			$inner
		);
	}

	/**
	 * Allowed tags/attributes for escaping inline SVG icons with wp_kses().
	 * Attribute keys are lowercase for matching; original casing (e.g. viewBox)
	 * is preserved in output by wp_kses.
	 *
	 * @return array
	 */
	private static function svg_kses() {
		$attr = array(
			'width'           => true,
			'height'          => true,
			'viewbox'         => true,
			'fill'            => true,
			'stroke'          => true,
			'stroke-width'    => true,
			'stroke-linecap'  => true,
			'stroke-linejoin' => true,
			'aria-hidden'     => true,
			'xmlns'           => true,
			'class'           => true,
			'd'               => true,
			'x'               => true,
			'y'               => true,
			'rx'              => true,
			'ry'              => true,
			'cx'              => true,
			'cy'              => true,
			'r'               => true,
			'points'          => true,
		);
		return array(
			'svg'      => $attr,
			'path'     => $attr,
			'rect'     => $attr,
			'circle'   => $attr,
			'line'     => $attr,
			'polyline' => $attr,
			'polygon'  => $attr,
			'g'        => $attr,
		);
	}

	/**
	 * SVG data URI for the admin-menu icon.
	 *
	 * @return string
	 */
	private function menu_icon() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#a7aaad" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v4M12 17v4M5 12H1M23 12h-4"/><path d="M12 7l1.6 3.4L17 12l-3.4 1.6L12 17l-1.6-3.4L7 12l3.4-1.6z"/></svg>';
		return 'data:image/svg+xml;charset=utf-8,' . rawurlencode( $svg );
	}

	/**
	 * Echo an icon (escaped).
	 *
	 * @param string $name Icon.
	 * @param int    $size Size.
	 */
	private static function i( $name, $size = 17 ) {
		echo wp_kses( self::icon( $name, $size ), self::svg_kses() );
	}

	/* ============================================================
	 * Menu
	 * ============================================================ */

	/**
	 * Admin menu + subpages.
	 */
	public function menu() {
		$counts = AIGS_Findings::counts();
		$badge  = $counts['critical'] + $counts['high'];
		$label  = __( 'Action Center', 'omnigeo-ai-seo' );
		if ( $badge > 0 ) {
			$label .= ' <span class="awaiting-mod"><span class="pending-count">' . (int) $badge . '</span></span>';
		}

		add_menu_page(
			__( 'OmniGEO Ai SEO', 'omnigeo-ai-seo' ),
			__( 'OmniGEO Ai SEO', 'omnigeo-ai-seo' ),
			'manage_options',
			'omnigeo-ai-seo',
			array( $this, 'page_dashboard' ),
			$this->menu_icon(),
			58
		);
		$pages = array(
			'omnigeo-ai-seo'     => array( __( 'Growth Center', 'omnigeo-ai-seo' ), 'page_dashboard', __( 'Growth Center', 'omnigeo-ai-seo' ) ),
			'aigs-actions'       => array( __( 'Action Center', 'omnigeo-ai-seo' ), 'page_actions', $label ),
		);
		if ( AIGS_WooCommerce::active() ) {
			$pages['aigs-products'] = array( __( 'Products', 'omnigeo-ai-seo' ), 'page_products', __( 'Products', 'omnigeo-ai-seo' ) );
		}
		$pages += array(
			'aigs-ai-visibility' => array( __( 'AI Visibility', 'omnigeo-ai-seo' ), 'page_ai_visibility', __( 'AI Visibility', 'omnigeo-ai-seo' ) ),
			'aigs-traffic'       => array( __( 'AI Traffic', 'omnigeo-ai-seo' ), 'page_traffic', __( 'AI Traffic', 'omnigeo-ai-seo' ) ),
			'aigs-audit'         => array( __( 'URL Audit', 'omnigeo-ai-seo' ), 'page_audit', __( 'URL Audit', 'omnigeo-ai-seo' ) ),
			'aigs-visibility'    => array( __( 'Site Health', 'omnigeo-ai-seo' ), 'page_visibility', __( 'Site Health', 'omnigeo-ai-seo' ) ),
			'aigs-llms'          => array( __( 'llms.txt', 'omnigeo-ai-seo' ), 'page_llms', __( 'llms.txt', 'omnigeo-ai-seo' ) ),
			'aigs-toolbox'       => array( __( 'Toolbox', 'omnigeo-ai-seo' ), 'page_toolbox', __( 'Toolbox', 'omnigeo-ai-seo' ) ),
			'aigs-settings'      => array( __( 'Settings', 'omnigeo-ai-seo' ), 'page_settings', __( 'Settings', 'omnigeo-ai-seo' ) ),
			'aigs-help'          => array( __( 'Guide', 'omnigeo-ai-seo' ), 'page_help', __( 'Guide', 'omnigeo-ai-seo' ) ),
		);
		foreach ( $pages as $slug => $p ) {
			add_submenu_page( 'omnigeo-ai-seo', $p[0], $p[2], 'manage_options', $slug, array( $this, $p[1] ) );
		}
	}

	/**
	 * Enqueue assets only on plugin pages and the editor.
	 *
	 * @param string $hook Hook suffix.
	 */
	public function assets( $hook ) {
		$is_plugin_page = ( false !== strpos( $hook, 'omnigeo-ai-seo' ) ) || ( false !== strpos( $hook, 'aigs-' ) );
		$is_editor      = in_array( $hook, array( 'post.php', 'post-new.php' ), true );
		if ( ! $is_plugin_page && ! $is_editor ) {
			return;
		}
		wp_enqueue_style( 'aigs-admin', AIGS_URL . 'assets/admin.css', array(), AIGS_VERSION );
		wp_enqueue_script( 'aigs-admin', AIGS_URL . 'assets/admin.js', array( 'jquery' ), AIGS_VERSION, true );
		wp_localize_script(
			'aigs-admin',
			'AIGS',
			array(
				'ajax'    => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'aigs_admin' ),
				'home'    => home_url( '/' ),
				'actions' => admin_url( 'admin.php?page=aigs-actions' ),
				'i18n'    => array(
					'question'      => __( 'Question', 'omnigeo-ai-seo' ),
					'answer'        => __( 'Answer', 'omnigeo-ai-seo' ),
					'remove'        => __( 'Remove', 'omnigeo-ai-seo' ),
					'pass'          => __( 'Pass', 'omnigeo-ai-seo' ),
					'partial'       => __( 'Partial', 'omnigeo-ai-seo' ),
					'fail'          => __( 'Fail', 'omnigeo-ai-seo' ),
					'optional'      => __( 'Optional', 'omnigeo-ai-seo' ),
					'ok'            => __( 'OK', 'omnigeo-ai-seo' ),
					'fix'           => __( 'Fix', 'omnigeo-ai-seo' ),
					'failed'        => __( 'Something went wrong. Please try again.', 'omnigeo-ai-seo' ),
					'requestFailed' => __( 'Request failed. Check your connection and try again.', 'omnigeo-ai-seo' ),
					'copied'        => __( 'Copied to clipboard', 'omnigeo-ai-seo' ),
					'copyFailed'    => __( 'Copy failed. Select the text and press Ctrl+C or Cmd+C.', 'omnigeo-ai-seo' ),
					'scanning'      => __( 'Scanning your site…', 'omnigeo-ai-seo' ),
					/* translators: 1: done, 2: total. */
					'scanProgress'  => __( 'Scanned %1$d of %2$d pages', 'omnigeo-ai-seo' ),
					'finalizing'    => __( 'Connecting the dots: links, duplicates, priorities…', 'omnigeo-ai-seo' ),
					'scanDone'      => __( 'Scan complete. Your Action Center is up to date.', 'omnigeo-ai-seo' ),
					'rechecking'    => __( 'Re-checking…', 'omnigeo-ai-seo' ),
					'fixedNow'      => __( 'Fixed and verified', 'omnigeo-ai-seo' ),
					'stillOpen'     => __( 'Still open. Save your changes in the editor, then re-check.', 'omnigeo-ai-seo' ),
					'ignored'       => __( 'Marked as intentional', 'omnigeo-ai-seo' ),
					'reopened'      => __( 'Moved back to open tasks', 'omnigeo-ai-seo' ),
					'confirmIgnore' => __( 'Mark every item of this type as intentional? You can reopen them later.', 'omnigeo-ai-seo' ),
					'saved'         => __( 'Saved', 'omnigeo-ai-seo' ),
					'mentioned'     => __( 'Brand mentioned', 'omnigeo-ai-seo' ),
					'notMentioned'  => __( 'Not mentioned', 'omnigeo-ai-seo' ),
					'cited'         => __( 'Domain cited', 'omnigeo-ai-seo' ),
					'notCited'      => __( 'Not cited', 'omnigeo-ai-seo' ),
					'recommended'   => __( 'Recommended', 'omnigeo-ai-seo' ),
					'position'      => __( 'Position', 'omnigeo-ai-seo' ),
					'via'           => __( 'via', 'omnigeo-ai-seo' ),
					'sources'       => __( 'Sources', 'omnigeo-ai-seo' ),
					'competitors'   => __( 'Competitors named', 'omnigeo-ai-seo' ),
					'writePrompt'   => __( 'Write or pick a prompt first.', 'omnigeo-ai-seo' ),
					/* translators: 1: done, 2: total. */
					'visProgress'   => __( 'Asked %1$d of %2$d questions', 'omnigeo-ai-seo' ),
					'visDone'       => __( 'Check complete. Reloading results…', 'omnigeo-ai-seo' ),
					'draftCreated'  => __( 'Draft created. Opening the editor…', 'omnigeo-ai-seo' ),
					'noContent'     => __( 'Add some content first.', 'omnigeo-ai-seo' ),
					'analysisFail'  => __( 'Analysis failed.', 'omnigeo-ai-seo' ),
					'mainQuestion'  => __( 'Main question', 'omnigeo-ai-seo' ),
					'inferred'      => __( 'inferred from the title', 'omnigeo-ai-seo' ),
					'fromTitle'     => __( 'from the title', 'omnigeo-ai-seo' ),
					'opening'       => __( 'Your opening', 'omnigeo-ai-seo' ),
					'answerOk'      => __( 'Answers the question directly', 'omnigeo-ai-seo' ),
					'answerWork'    => __( 'Needs work', 'omnigeo-ai-seo' ),
					'answerMissing' => __( 'No direct answer', 'omnigeo-ai-seo' ),
					'unanswered'    => __( 'Question headings without a direct answer', 'omnigeo-ai-seo' ),
					'faqFound'      => __( 'Q&A pairs found in your content', 'omnigeo-ai-seo' ),
					'addFaq'        => __( 'Add to FAQ schema', 'omnigeo-ai-seo' ),
					'faqAdded'      => __( 'Added to the FAQ list in the Schema box. Save the post to publish it.', 'omnigeo-ai-seo' ),
					'draftAnswer'   => __( 'Draft a direct answer with AI', 'omnigeo-ai-seo' ),
					'drafting'      => __( 'Drafting from your own text…', 'omnigeo-ai-seo' ),
					'insertTop'     => __( 'Insert at the top', 'omnigeo-ai-seo' ),
					'copy'          => __( 'Copy', 'omnigeo-ai-seo' ),
					'inserted'      => __( 'Inserted as the first paragraph. Review it, then save. Revisions keep your previous version.', 'omnigeo-ai-seo' ),
					'reviewDraft'   => __( 'Review before using: the draft only uses facts from your page, but you know your topic best.', 'omnigeo-ai-seo' ),
					'tips'          => __( 'What to do next', 'omnigeo-ai-seo' ),
					'openTasks'     => __( 'Open tasks for this page', 'omnigeo-ai-seo' ),
					'aiLeft'        => __( 'AI requests left this month:', 'omnigeo-ai-seo' ),
					'noItems'       => __( 'Nothing to change: every matching product is already complete, or a site scan has not run yet.', 'omnigeo-ai-seo' ),
					/* translators: %d: number of products. */
					'applied'       => __( 'Updated %d products. You can undo this below.', 'omnigeo-ai-seo' ),
					/* translators: %d: number of products. */
					'restored'      => __( 'Restored %d products.', 'omnigeo-ai-seo' ),
					'applySelected' => __( 'Apply selected', 'omnigeo-ai-seo' ),
					'cancel'        => __( 'Cancel', 'omnigeo-ai-seo' ),
					'product'       => __( 'Product', 'omnigeo-ai-seo' ),
					'proposed'      => __( 'Proposed value (you can edit it)', 'omnigeo-ai-seo' ),
					'important'     => __( 'Marked as important', 'omnigeo-ai-seo' ),
					'notImportant'  => __( 'No longer marked as important', 'omnigeo-ai-seo' ),
					'auditFailed'   => __( 'Audit failed.', 'omnigeo-ai-seo' ),
					'geoScore'      => __( 'GEO score', 'omnigeo-ai-seo' ),
					'extractScore'  => __( 'Answer readiness score', 'omnigeo-ai-seo' ),
					'submitted'     => __( 'Submitted to IndexNow', 'omnigeo-ai-seo' ),
					'responded'     => __( 'IndexNow responded', 'omnigeo-ai-seo' ),
					'answerExcerpt' => __( 'Answer excerpt', 'omnigeo-ai-seo' ),
					'webSearch'     => __( 'Live web search answer', 'omnigeo-ai-seo' ),
					'modelOnly'     => __( 'Model knowledge only (no live web search)', 'omnigeo-ai-seo' ),
					'close'         => __( 'Close', 'omnigeo-ai-seo' ),
					'findingLinks'  => __( 'Looking for sentences that already mention this topic…', 'omnigeo-ai-seo' ),
					'noLinkSpots'   => __( 'No natural link spot found. Add a sentence that mentions the topic on a related page, or use the suggested pages above.', 'omnigeo-ai-seo' ),
					'linkFrom'      => __( 'On page', 'omnigeo-ai-seo' ),
					'linkTo'        => __( 'links to', 'omnigeo-ai-seo' ),
					'insertLinks'   => __( 'Insert selected links', 'omnigeo-ai-seo' ),
					/* translators: %d: number of links. */
					'linksAdded'    => __( '%d links added and verified.', 'omnigeo-ai-seo' ),
					'undo'          => __( 'Undo', 'omnigeo-ai-seo' ),
					'undone'        => __( 'Restored the previous version.', 'omnigeo-ai-seo' ),
					'wrongFacts'    => __( 'Wrong facts about you in this answer:', 'omnigeo-ai-seo' ),
					'passages'      => __( 'Passage citability', 'omnigeo-ai-seo' ),
					'passagesHelp'  => __( 'Assistants quote single sections. Each section under an H2/H3 is scored on whether it stands alone, its length, data and sources, sentence clarity, and answering first.', 'omnigeo-ai-seo' ),
					'passagesNone'  => __( 'Add H2 or H3 headings to split the page into sections that can be quoted on their own.', 'omnigeo-ai-seo' ),
					/* translators: 1: page score, 2: weak sections, 3: total sections. */
					'passagesSum'   => __( 'Page %1$d/100 · %2$d of %3$d sections need work', 'omnigeo-ai-seo' ),
				),
			)
		);
	}

	/* ============================================================
	 * Settings
	 * ============================================================ */

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting(
			'aigs_settings_group',
			'aigs_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Sanitize settings on save. Each admin form posts a section marker and only
	 * the fields of that section are touched, so saving one page never resets
	 * another. Internal updates (no marker) are merged as-is.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$old   = get_option( 'aigs_settings', array() );
		$out   = is_array( $old ) ? $old : array();
		$input = is_array( $input ) ? $input : array();

		if ( ! isset( $input['section_settings'] ) && ! isset( $input['section_llms'] ) && ! isset( $input['section_visibility'] ) && ! isset( $input['section_toolbox'] ) ) {
			return array_merge( $out, $input );
		}

		if ( isset( $input['section_settings'] ) ) {
			$out['enable_schema']   = empty( $input['enable_schema'] ) ? 0 : 1;
			$out['enable_tracking'] = empty( $input['enable_tracking'] ) ? 0 : 1;
			$out['indexnow_auto']   = empty( $input['indexnow_auto'] ) ? 0 : 1;
			$out['digest_email']    = empty( $input['digest_email'] ) ? 0 : 1;
			$out['digest_to']       = isset( $input['digest_to'] ) ? sanitize_email( $input['digest_to'] ) : '';
			$out['verify_bots']     = empty( $input['verify_bots'] ) ? 0 : 1;
			$out['business_name']   = isset( $input['business_name'] ) ? sanitize_text_field( $input['business_name'] ) : '';
			$types                  = AIGS_Entity::business_types();
			$btype                  = isset( $input['business_type'] ) ? sanitize_text_field( $input['business_type'] ) : 'Organization';
			$out['business_type']   = ( isset( $types['org'][ $btype ] ) || isset( $types['local'][ $btype ] ) ) ? $btype : 'Organization';
			$out['biz_phone']       = isset( $input['biz_phone'] ) ? preg_replace( '/[^0-9+()\-.\s]/', '', sanitize_text_field( $input['biz_phone'] ) ) : '';
			$out['biz_email']       = isset( $input['biz_email'] ) ? sanitize_email( $input['biz_email'] ) : '';
			foreach ( array( 'biz_street', 'biz_city', 'biz_region', 'biz_postal', 'biz_price', 'biz_area' ) as $k ) {
				$out[ $k ] = isset( $input[ $k ] ) ? sanitize_text_field( $input[ $k ] ) : '';
			}
			$country            = isset( $input['biz_country'] ) ? strtoupper( sanitize_text_field( $input['biz_country'] ) ) : '';
			$out['biz_country'] = preg_match( '/^[A-Z]{2}$/', $country ) ? $country : sanitize_text_field( isset( $input['biz_country'] ) ? $input['biz_country'] : '' );
			$out['biz_map']     = isset( $input['biz_map'] ) ? esc_url_raw( $input['biz_map'], array( 'http', 'https' ) ) : '';
			$lat                = isset( $input['biz_lat'] ) ? trim( (string) $input['biz_lat'] ) : '';
			$lng                = isset( $input['biz_lng'] ) ? trim( (string) $input['biz_lng'] ) : '';
			$valid_geo          = is_numeric( $lat ) && is_numeric( $lng ) && abs( (float) $lat ) <= 90 && abs( (float) $lng ) <= 180;
			$out['biz_lat']     = $valid_geo ? (string) (float) $lat : '';
			$out['biz_lng']     = $valid_geo ? (string) (float) $lng : '';
			$out['biz_hours']   = array();
			foreach ( array_keys( AIGS_Entity::days() ) as $d ) {
				$raw = isset( $input['biz_hours'][ $d ] ) ? sanitize_text_field( $input['biz_hours'][ $d ] ) : '';
				// Keep what the user typed only if it parses, so mistakes are visible as empty.
				$out['biz_hours'][ $d ] = ( '' === $raw || AIGS_Entity::parse_ranges( $raw ) ) ? $raw : '';
			}
			$out['industry']        = isset( $input['industry'] ) ? sanitize_text_field( $input['industry'] ) : '';
			$out['location']        = isset( $input['location'] ) ? sanitize_text_field( $input['location'] ) : '';
			$out['org_logo']        = isset( $input['org_logo'] ) ? esc_url_raw( $input['org_logo'] ) : '';
			$out['org_founded']     = isset( $input['org_founded'] ) ? sanitize_text_field( $input['org_founded'] ) : '';
			$out['org_sameas']      = isset( $input['org_sameas'] ) ? sanitize_textarea_field( $input['org_sameas'] ) : '';
			$out['dedupe_schema']   = ( isset( $input['dedupe_schema'] ) && 'off' === $input['dedupe_schema'] ) ? 'off' : 'auto';
			$prov                   = isset( $input['ai_provider'] ) ? sanitize_key( $input['ai_provider'] ) : 'openai';
			$out['ai_provider']     = array_key_exists( $prov, AIGS_AI_Client::providers() ) ? $prov : 'openai';
			$out['ai_model']        = isset( $input['ai_model'] ) ? preg_replace( '/[^A-Za-z0-9._:\/-]/', '', (string) $input['ai_model'] ) : '';
			$base                   = isset( $input['ai_base_url'] ) ? esc_url_raw( trim( (string) $input['ai_base_url'] ), array( 'http', 'https' ) ) : '';
			$out['ai_base_url']     = '' !== AIGS_AI_Client::normalize_endpoint( $base ) ? $base : '';
			$out['ai_grounded']     = empty( $input['ai_grounded'] ) ? 0 : 1;
			$out['ai_monthly_cap']  = isset( $input['ai_monthly_cap'] ) ? min( 5000, absint( $input['ai_monthly_cap'] ) ) : 60;
			$sched                  = isset( $input['scan_schedule'] ) ? sanitize_key( $input['scan_schedule'] ) : 'weekly';
			$out['scan_schedule']   = 'off' === $sched ? 'off' : 'weekly';
			$out['retention_days']  = isset( $input['retention_days'] ) ? max( 7, min( 730, absint( $input['retention_days'] ) ) ) : 90;
			$header                 = isset( $input['ip_header'] ) ? sanitize_text_field( $input['ip_header'] ) : 'REMOTE_ADDR';
			$out['ip_header']       = in_array( $header, array( 'REMOTE_ADDR', 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP' ), true ) ? $header : 'REMOTE_ADDR';

			// The key field is never pre-filled: empty means "keep the saved key".
			$new_key = isset( $input['ai_api_key'] ) ? trim( sanitize_text_field( $input['ai_api_key'] ) ) : '';
			if ( ! empty( $input['ai_remove_key'] ) ) {
				$out['ai_api_key'] = '';
			} elseif ( '' !== $new_key ) {
				$out['ai_api_key'] = $new_key;
			}
			if ( isset( $input['woo_present'] ) ) {
				$out['enhance_woo'] = empty( $input['enhance_woo'] ) ? 'off' : 'on';
			}
		}

		if ( isset( $input['section_llms'] ) ) {
			$out['enable_llms'] = empty( $input['enable_llms'] ) ? 0 : 1;
			$out['llms_txt']    = isset( $input['llms_txt'] ) ? sanitize_textarea_field( $input['llms_txt'] ) : '';
		}

		if ( isset( $input['section_visibility'] ) ) {
			$out['brand_aliases'] = isset( $input['brand_aliases'] ) ? sanitize_textarea_field( $input['brand_aliases'] ) : '';
			$out['competitors']   = isset( $input['competitors'] ) ? sanitize_textarea_field( $input['competitors'] ) : '';
			$out['vis_prompts']   = isset( $input['vis_prompts'] ) ? sanitize_textarea_field( $input['vis_prompts'] ) : '';
			foreach ( array( 'fact_phones', 'fact_emails', 'fact_domains', 'fact_outdated', 'fact_notes' ) as $k ) {
				$out[ $k ] = isset( $input[ $k ] ) ? sanitize_textarea_field( $input[ $k ] ) : '';
			}
			$fy                  = isset( $input['fact_founded'] ) ? trim( sanitize_text_field( $input['fact_founded'] ) ) : '';
			$out['fact_founded'] = preg_match( '/^(1[89]\d{2}|20\d{2})$/', $fy ) ? $fy : '';
			$vs                   = isset( $input['vis_schedule'] ) ? sanitize_key( $input['vis_schedule'] ) : 'off';
			$out['vis_schedule']  = in_array( $vs, array( 'off', 'weekly', 'monthly' ), true ) ? $vs : 'off';
		}

		if ( isset( $input['section_toolbox'] ) ) {
			$out = self::sanitize_toolbox( $out, $input, sanitize_key( $input['section_toolbox'] ) );
		}

		if ( empty( $out['indexnow_key'] ) ) {
			$out['indexnow_key'] = wp_generate_password( 32, false, false );
		}
		unset( $out['section_settings'], $out['section_llms'], $out['section_visibility'], $out['section_toolbox'], $out['ai_remove_key'] );
		return $out;
	}

	/**
	 * Sanitize one Toolbox tab (pure; tested).
	 *
	 * @param array  $out   Settings so far.
	 * @param array  $input Raw input.
	 * @param string $tab   ai|redirects|search|tools.
	 * @return array
	 */
	public static function sanitize_toolbox( $out, $input, $tab ) {
		$flag = static function ( $k ) use ( $input ) {
			return empty( $input[ $k ] ) ? 0 : 1;
		};
		if ( 'ai' === $tab ) {
			$access           = isset( $input['ai_access'] ) ? sanitize_key( $input['ai_access'] ) : 'allow';
			$out['ai_access'] = in_array( $access, array( 'allow', 'search_only', 'disallow', 'custom' ), true ) ? $access : 'allow';
			// The switches say "allowed"; everything known and not switched on is stored as blocked.
			$allowed           = isset( $input['ai_allowed'] ) && is_array( $input['ai_allowed'] ) ? array_map( 'sanitize_text_field', $input['ai_allowed'] ) : array();
			$known             = array_merge( array_keys( AIGS_Tracker::bot_map() ), array_keys( AIGS_Tracker::robots_only_tokens() ) );
			$out['ai_blocked'] = AIGS_LLMS::custom_blocks( array_diff( $known, $allowed, array( 'bingbot', 'Applebot' ) ) );
			$out['cs_enabled'] = $flag( 'cs_enabled' );
			foreach ( array( 'cs_search', 'cs_ai_input', 'cs_ai_train' ) as $k ) {
				$out[ $k ] = ( isset( $input[ $k ] ) && 'no' === $input[ $k ] ) ? 'no' : 'yes';
			}
			$out['tb_markdown'] = $flag( 'tb_markdown' );
			$lines              = array();
			$raw                = isset( $input['robots_extra'] ) ? sanitize_textarea_field( $input['robots_extra'] ) : '';
			foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
				$line = trim( $line );
				// Only real robots.txt lines survive, so a typo cannot break the file.
				if ( ! preg_match( '/^(#.*|(user-agent|allow|disallow|crawl-delay|sitemap|content-signal)\s*:.*)$/i', $line ) ) {
					continue;
				}
				// Keep one blank line before each new group for readability.
				if ( $lines && preg_match( '/^user-agent\s*:/i', $line ) && ! preg_match( '/^user-agent\s*:/i', end( $lines ) ) ) {
					$lines[] = '';
				}
				$lines[] = $line;
			}
			$out['robots_extra'] = trim( implode( "\n", array_slice( $lines, 0, 100 ) ) );
		}
		if ( 'redirects' === $tab ) {
			$out['tb_redirects']     = $flag( 'tb_redirects' );
			$out['tb_auto_redirect'] = $flag( 'tb_auto_redirect' );
		}
		if ( 'search' === $tab ) {
			$out['tb_snippets']   = $flag( 'tb_snippets' );
			$out['meta_fallback'] = $flag( 'meta_fallback' );
			$out['tb_social']     = $flag( 'tb_social' );
			$out['tb_lastmod']    = $flag( 'tb_lastmod' );
			$out['og_image']      = isset( $input['og_image'] ) ? esc_url_raw( trim( (string) $input['og_image'] ), array( 'http', 'https' ) ) : '';
			$handle               = isset( $input['twitter_site'] ) ? ltrim( trim( sanitize_text_field( $input['twitter_site'] ) ), '@' ) : '';
			$out['twitter_site']  = preg_match( '/^[A-Za-z0-9_]{1,15}$/', $handle ) ? $handle : '';
			foreach ( AIGS_Head::verification_tags() as $setting ) {
				$out[ $setting ] = isset( $input[ $setting ] ) ? AIGS_Head::verification_code( $input[ $setting ] ) : '';
			}
			$out['ga4_id'] = isset( $input['ga4_id'] ) ? AIGS_Head::tag_id( $input['ga4_id'], 'G' ) : '';
			$out['gtm_id'] = isset( $input['gtm_id'] ) ? AIGS_Head::tag_id( $input['gtm_id'], 'GTM' ) : '';
		}
		if ( 'tools' === $tab ) {
			$mode               = isset( $input['tb_ext_links'] ) ? sanitize_key( $input['tb_ext_links'] ) : 'off';
			$out['tb_ext_links'] = in_array( $mode, array( 'off', 'blank', 'blank_nofollow', 'nofollow' ), true ) ? $mode : 'off';
			$out['tb_duplicate'] = $flag( 'tb_duplicate' );
		}
		return $out;
	}

	/* ============================================================
	 * Page chrome + small render helpers
	 * ============================================================ */

	/**
	 * Open the page wrapper and render the header.
	 *
	 * @param string $title   Title.
	 * @param string $sub     Subtitle.
	 * @param string $actions HTML for the actions slot.
	 */
	private function open( $title, $sub = '', $actions = '' ) {
		echo '<div class="wrap aigs-app">';
		echo '<div class="aigs-head"><div class="aigs-head__l">';
		echo '<span class="aigs-head__mark">' . wp_kses( self::icon( 'sparkles', 22 ), self::svg_kses() ) . '</span>';
		echo '<div><h1 class="aigs-h1">' . esc_html( $title ) . '</h1>';
		if ( $sub ) {
			echo '<p class="aigs-head__sub">' . esc_html( $sub ) . '</p>';
		}
		echo '</div></div>';
		if ( $actions ) {
			echo '<div class="aigs-head__actions">' . $actions . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $actions is assembled from esc_url(), wp_kses() and esc_html__() parts by the caller.
		}
		echo '</div>';
		// WordPress prints "Settings saved." only on options pages; show it here too.
		if ( isset( $_GET['settings-updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
			echo '<div class="aigs-callout aigs-callout--ok">' . wp_kses( self::icon( 'check', 18 ), self::svg_kses() ) . '<p>' . esc_html__( 'Settings saved.', 'omnigeo-ai-seo' ) . '</p></div>';
		}
	}

	/**
	 * Close the wrapper.
	 */
	private function close() {
		echo '</div>';
	}

	/**
	 * Scan button + progress bar markup.
	 *
	 * @param bool $primary Primary style.
	 * @return string
	 */
	private function scan_button( $primary = true ) {
		return '<button type="button" class="aigs-btn' . ( $primary ? ' aigs-btn--primary' : '' ) . '" id="aigs-scan-start">' . wp_kses( self::icon( 'refresh', 16 ), self::svg_kses() ) . esc_html__( 'Scan site', 'omnigeo-ai-seo' ) . '</button>';
	}

	/**
	 * Progress area shown while a scan runs.
	 */
	private function scan_progress() {
		$s = AIGS_Scanner::state();
		echo '<div class="aigs-progress" id="aigs-scan-progress" data-running="' . ( 'running' === $s['status'] ? '1' : '0' ) . '" hidden>';
		echo '<div class="aigs-progress__bar"><span style="width:0%"></span></div>';
		echo '<p class="aigs-progress__txt" aria-live="polite"></p></div>';
	}

	/**
	 * Severity pill.
	 *
	 * @param int $sev Severity.
	 * @return string
	 */
	private static function sev_pill( $sev ) {
		$labels = AIGS_Rules::severities();
		$cls    = array(
			4 => 'crit',
			3 => 'high',
			2 => 'med',
			1 => 'low',
		);
		$sev = max( 1, min( 4, (int) $sev ) );
		return '<span class="aigs-sev aigs-sev--' . esc_attr( $cls[ $sev ] ) . '">' . esc_html( $labels[ $sev ] ) . '</span>';
	}

	/**
	 * Score color class.
	 *
	 * @param int $score Score.
	 * @return string
	 */
	private static function score_class( $score ) {
		if ( $score >= 75 ) {
			return 'good';
		}
		return $score >= 50 ? 'ok' : 'bad';
	}

	/**
	 * Static score ring (server-rendered).
	 *
	 * @param int    $score Score.
	 * @param string $label Small label under the number.
	 */
	private static function ring( $score, $label ) {
		$r   = 46;
		$c   = 2 * M_PI * $r;
		$off = $c * ( 1 - max( 0, min( 100, $score ) ) / 100 );
		$col = $score >= 75 ? '#18794e' : ( $score >= 50 ? '#9a6700' : '#c4332e' );
		printf(
			'<div class="aigs-ring"><svg width="104" height="104" viewBox="0 0 104 104" aria-hidden="true"><circle class="track" cx="52" cy="52" r="%1$d" fill="none" stroke-width="8"/><circle class="meter" cx="52" cy="52" r="%1$d" fill="none" stroke="%2$s" stroke-width="8" stroke-dasharray="%3$.2f" stroke-dashoffset="%4$.2f"/></svg><div class="aigs-ring__txt"><span class="aigs-ring__num">%5$d</span><span class="aigs-ring__grade">%6$s</span></div></div>',
			(int) $r,
			esc_attr( $col ),
			(float) $c,
			(float) $off,
			(int) $score,
			esc_html( $label )
		);
	}

	/**
	 * Inline SVG sparkline from score history.
	 *
	 * @param array $hist History rows.
	 */
	private static function sparkline( $hist ) {
		$vals = array();
		foreach ( (array) $hist as $h ) {
			$vals[] = (int) $h['score'];
		}
		if ( count( $vals ) < 2 ) {
			return;
		}
		$w   = 180;
		$h   = 40;
		$n   = count( $vals );
		$pts = array();
		foreach ( $vals as $i => $v ) {
			$pts[] = round( $i * ( $w / ( $n - 1 ) ), 1 ) . ',' . round( $h - 2 - ( $v / 100 ) * ( $h - 4 ), 1 );
		}
		printf(
			'<svg class="aigs-spark" width="%1$d" height="%2$d" viewBox="0 0 %1$d %2$d" role="img" aria-label="%3$s"><polyline fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" points="%4$s"/></svg>',
			(int) $w,
			(int) $h,
			esc_attr__( 'Score trend', 'omnigeo-ai-seo' ),
			esc_attr( implode( ' ', $pts ) )
		);
	}

	/**
	 * Human time difference ("3 hours ago").
	 *
	 * @param int $ts Timestamp.
	 * @return string
	 */
	private static function ago( $ts ) {
		/* translators: %s: human time difference. */
		return $ts ? sprintf( __( '%s ago', 'omnigeo-ai-seo' ), human_time_diff( (int) $ts, time() ) ) : __( 'never', 'omnigeo-ai-seo' );
	}

	/**
	 * Link that fixes a site-level finding.
	 *
	 * @param string $act Action descriptor.
	 * @return string HTML
	 */
	private static function site_action( $act ) {
		if ( 0 === strpos( $act, 'auto:' ) ) {
			$fix   = substr( $act, 5 );
			$fixes = AIGS_Actions::autofixes();
			if ( isset( $fixes[ $fix ] ) ) {
				return '<button type="button" class="aigs-btn aigs-btn--primary aigs-btn--sm" data-aigs-autofix="' . esc_attr( $fix ) . '">' . wp_kses( self::icon( 'zap', 15 ), self::svg_kses() ) . esc_html( $fixes[ $fix ] ) . '</button>';
			}
		}
		if ( 0 === strpos( $act, 'settings:' ) ) {
			$target = substr( $act, 9 );
			$url    = 0 === strpos( $target, 'aigs-' ) ? admin_url( 'admin.php?page=' . $target ) : admin_url( $target );
			return '<a class="aigs-btn aigs-btn--sm" href="' . esc_url( $url ) . '">' . esc_html__( 'Open setting', 'omnigeo-ai-seo' ) . '</a>';
		}
		return '';
	}

	/* ============================================================
	 * Growth Center (dashboard)
	 * ============================================================ */

	/**
	 * Getting-started steps with completion state.
	 *
	 * @return array
	 */
	private function onboarding_steps() {
		$summary = get_option( 'aigs_scan_summary', array() );
		$counts  = AIGS_Findings::counts();
		return array(
			array(
				'done'  => '' !== trim( (string) aigs_get( 'industry', '' ) ) && '' !== trim( (string) aigs_get( 'business_name', '' ) ),
				'title' => __( 'Tell OmniGEO about your brand', 'omnigeo-ai-seo' ),
				'desc'  => __( 'Brand name, industry and location shape every suggestion.', 'omnigeo-ai-seo' ),
				'url'   => admin_url( 'admin.php?page=aigs-settings' ),
				'cta'   => __( 'Open Settings', 'omnigeo-ai-seo' ),
			),
			array(
				'done'  => ! empty( $summary['scanned_at'] ),
				'title' => __( 'Run your first site scan', 'omnigeo-ai-seo' ),
				'desc'  => __( 'Reads your pages from WordPress itself, in small batches. Safe on shared hosting.', 'omnigeo-ai-seo' ),
				'url'   => '#aigs-scan-start',
				'cta'   => __( 'Scan now', 'omnigeo-ai-seo' ),
			),
			array(
				'done'  => $counts['fixed_total'] > 0,
				'title' => __( 'Fix your first task', 'omnigeo-ai-seo' ),
				'desc'  => __( 'Start with the top recommendation below, then re-check to confirm it is fixed.', 'omnigeo-ai-seo' ),
				'url'   => admin_url( 'admin.php?page=aigs-actions' ),
				'cta'   => __( 'Open Action Center', 'omnigeo-ai-seo' ),
			),
			array(
				'done'  => '' !== trim( (string) aigs_get( 'org_sameas', '' ) ) && '' !== trim( (string) aigs_get( 'org_logo', '' ) ),
				'title' => __( 'Add your logo and official profiles', 'omnigeo-ai-seo' ),
				'desc'  => __( 'Helps assistants recognise your brand as one consistent entity.', 'omnigeo-ai-seo' ),
				'url'   => admin_url( 'admin.php?page=aigs-settings#aigs-entity' ),
				'cta'   => __( 'Add profiles', 'omnigeo-ai-seo' ),
			),
			array(
				'done'  => (bool) AIGS_AI_Visibility::prompts(),
				'title' => __( 'Choose the questions buyers ask AI (optional)', 'omnigeo-ai-seo' ),
				'desc'  => __( 'Then check whether assistants mention you or your competitors.', 'omnigeo-ai-seo' ),
				'url'   => admin_url( 'admin.php?page=aigs-ai-visibility' ),
				'cta'   => __( 'Set up', 'omnigeo-ai-seo' ),
			),
		);
	}

	/**
	 * Growth Center page.
	 */
	public function page_dashboard() {
		$summary = get_option( 'aigs_scan_summary', array() );
		$summary = is_array( $summary ) ? $summary : array();
		$hist    = get_option( 'aigs_score_history', array() );
		$counts  = AIGS_Findings::counts();
		$t       = $this->modules['tracker'];
		$fetch   = $t->count( 'fetch', 30 );
		$tasks   = AIGS_Actions::tasks( 3 );

		$this->open( __( 'Growth Center', 'omnigeo-ai-seo' ), __( 'What to fix today so search engines and AI assistants understand, trust and quote your site.', 'omnigeo-ai-seo' ), $this->scan_button() );
		$this->scan_progress();

		// Getting started.
		$steps = $this->onboarding_steps();
		$done  = count( array_filter( wp_list_pluck( $steps, 'done' ) ) );
		if ( ! get_option( 'aigs_onboarding_done' ) && $done < count( $steps ) ) :
			?>
			<div class="aigs-card aigs-onboard" id="aigs-onboard">
				<div class="aigs-card__head"><?php self::i( 'flag' ); ?><h2><?php esc_html_e( 'Get started', 'omnigeo-ai-seo' ); ?></h2>
					<span class="aigs-muted aigs-onboard__count">
					<?php
					/* translators: 1: done steps, 2: total steps. */
					printf( esc_html__( '%1$d of %2$d done', 'omnigeo-ai-seo' ), (int) $done, count( $steps ) );
					?>
					</span>
					<button type="button" class="aigs-btn aigs-btn--ghost aigs-btn--sm" id="aigs-onboard-dismiss"><?php esc_html_e( 'Hide', 'omnigeo-ai-seo' ); ?></button>
				</div>
				<div class="aigs-meter"><span style="width:<?php echo (int) round( 100 * $done / count( $steps ) ); ?>%"></span></div>
				<ol class="aigs-onboard__list">
					<?php foreach ( $steps as $s ) : ?>
						<li class="<?php echo $s['done'] ? 'is-done' : ''; ?>">
							<span class="aigs-onboard__tick"><?php self::i( $s['done'] ? 'check' : 'arrow', 14 ); ?></span>
							<div><strong><?php echo esc_html( $s['title'] ); ?></strong><span><?php echo esc_html( $s['desc'] ); ?></span></div>
							<?php if ( ! $s['done'] ) : ?>
								<?php if ( '#aigs-scan-start' === $s['url'] ) : ?>
									<button type="button" class="aigs-btn aigs-btn--sm" data-aigs-trigger="#aigs-scan-start"><?php echo esc_html( $s['cta'] ); ?></button>
								<?php else : ?>
									<a class="aigs-btn aigs-btn--sm" href="<?php echo esc_url( $s['url'] ); ?>"><?php echo esc_html( $s['cta'] ); ?></a>
								<?php endif; ?>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ol>
			</div>
			<?php
		endif;

		if ( empty( $summary['scanned_at'] ) ) :
			?>
			<div class="aigs-card aigs-hero aigs-hero--empty">
				<div>
					<h2><?php esc_html_e( 'Find out what is holding your site back', 'omnigeo-ai-seo' ); ?></h2>
					<p class="aigs-muted"><?php esc_html_e( 'The scan checks every published page and product for answer readiness, indexability, internal links, schema and trust signals, then turns the results into a short, prioritised to-do list. Nothing on your site is changed.', 'omnigeo-ai-seo' ); ?></p>
					<p><button type="button" class="aigs-btn aigs-btn--primary" data-aigs-trigger="#aigs-scan-start"><?php self::i( 'refresh', 16 ); ?><?php esc_html_e( 'Run the first scan', 'omnigeo-ai-seo' ); ?></button></p>
				</div>
			</div>
			<?php
		else :
			$score = (int) $summary['score'];
			$prev  = ( is_array( $hist ) && count( $hist ) > 1 ) ? (int) $hist[ count( $hist ) - 2 ]['score'] : $score;
			$delta = $score - $prev;
			?>
			<div class="aigs-card aigs-hero">
				<?php self::ring( $score, __( 'of 100', 'omnigeo-ai-seo' ) ); ?>
				<div class="aigs-hero__meta">
					<strong><?php esc_html_e( 'AI answer readiness', 'omnigeo-ai-seo' ); ?></strong>
					<p>
						<?php
						/* translators: 1: number of pages, 2: time since scan. */
						printf( esc_html__( '%1$d pages checked · last scan %2$s', 'omnigeo-ai-seo' ), (int) $summary['pages'], esc_html( self::ago( (int) $summary['scanned_at'] ) ) );
						?>
					</p>
					<?php if ( 0 !== $delta ) : ?>
						<p class="aigs-delta aigs-delta--<?php echo $delta > 0 ? 'up' : 'down'; ?>">
							<?php
							/* translators: %s: signed change in points. */
							printf( esc_html__( '%s points since the previous scan', 'omnigeo-ai-seo' ), esc_html( ( $delta > 0 ? '+' : '' ) . $delta ) );
							?>
						</p>
					<?php endif; ?>
					<p class="aigs-muted aigs-small"><?php esc_html_e( 'Weighted by page importance. It measures how ready your pages are to be understood and quoted, not a ranking or a guarantee.', 'omnigeo-ai-seo' ); ?></p>
				</div>
				<div class="aigs-hero__spark"><?php self::sparkline( $hist ); ?></div>
			</div>
			<?php
		endif;
		?>

		<div class="aigs-stats aigs-stats--4">
			<a class="aigs-stat" href="<?php echo esc_url( admin_url( 'admin.php?page=aigs-actions' ) ); ?>">
				<div class="aigs-stat__top"><span class="aigs-stat__ico"><?php self::i( 'flag', 18 ); ?></span></div>
				<div class="aigs-stat__val"><?php echo (int) ( $counts['critical'] + $counts['high'] ); ?></div>
				<div class="aigs-stat__label"><?php esc_html_e( 'High-priority issues', 'omnigeo-ai-seo' ); ?></div>
			</a>
			<a class="aigs-stat" href="<?php echo esc_url( admin_url( 'admin.php?page=aigs-actions' ) ); ?>">
				<div class="aigs-stat__top"><span class="aigs-stat__ico"><?php self::i( 'list', 18 ); ?></span></div>
				<div class="aigs-stat__val"><?php echo (int) $counts['open']; ?></div>
				<div class="aigs-stat__label"><?php esc_html_e( 'Open improvements', 'omnigeo-ai-seo' ); ?></div>
			</a>
			<div class="aigs-stat">
				<div class="aigs-stat__top"><span class="aigs-stat__ico"><?php self::i( 'trophy', 18 ); ?></span></div>
				<div class="aigs-stat__val"><?php echo (int) $counts['fixed_7d']; ?></div>
				<div class="aigs-stat__label"><?php esc_html_e( 'Fixed in the last 7 days', 'omnigeo-ai-seo' ); ?></div>
			</div>
			<a class="aigs-stat" href="<?php echo esc_url( admin_url( 'admin.php?page=aigs-traffic' ) ); ?>">
				<div class="aigs-stat__top"><span class="aigs-stat__ico"><?php self::i( 'quote', 18 ); ?></span></div>
				<div class="aigs-stat__val"><?php echo (int) $fetch; ?></div>
				<div class="aigs-stat__label"><?php esc_html_e( 'Reads inside AI answers · 30d', 'omnigeo-ai-seo' ); ?></div>
			</a>
		</div>

		<?php if ( $tasks ) : ?>
			<div class="aigs-card">
				<div class="aigs-card__head"><?php self::i( 'zap' ); ?><h2><?php esc_html_e( 'Recommended for today', 'omnigeo-ai-seo' ); ?></h2></div>
				<p class="aigs-card__desc"><?php esc_html_e( 'Ordered by impact: how serious the issue is and how important the affected pages are.', 'omnigeo-ai-seo' ); ?></p>
				<div class="aigs-today">
					<?php
					$n = 0;
					foreach ( $tasks as $task ) :
						$n++;
						?>
						<a class="aigs-today__item" href="<?php echo esc_url( add_query_arg( 'rule', $task['rule'], admin_url( 'admin.php?page=aigs-actions' ) ) ); ?>">
							<span class="aigs-today__n"><?php echo (int) $n; ?></span>
							<span class="aigs-today__b">
								<strong><?php echo esc_html( $task['title'] ); ?></strong>
								<span><?php echo esc_html( $task['why'] ); ?></span>
							</span>
							<span class="aigs-today__c"><?php echo wp_kses_post( self::sev_pill( $task['severity'] ) ); ?><em>
							<?php
							/* translators: %d: number of affected items. */
							printf( esc_html( _n( '%d item', '%d items', (int) $task['count'], 'omnigeo-ai-seo' ) ), (int) $task['count'] );
							?>
							</em></span>
						</a>
					<?php endforeach; ?>
				</div>
				<p><a class="aigs-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=aigs-actions' ) ); ?>"><?php esc_html_e( 'See all tasks', 'omnigeo-ai-seo' ); ?><?php self::i( 'arrow', 16 ); ?></a></p>
			</div>
		<?php elseif ( ! empty( $summary['scanned_at'] ) ) : ?>
			<div class="aigs-card"><div class="aigs-empty"><?php self::i( 'trophy', 30 ); ?><p><?php esc_html_e( 'No open tasks. Your pages pass every check. The weekly scan will tell you if anything changes.', 'omnigeo-ai-seo' ); ?></p></div></div>
		<?php endif; ?>

		<div class="aigs-grid2">
			<?php $this->render_wins(); ?>
			<?php $this->render_visibility_snapshot(); ?>
		</div>
		<?php
		if ( 'ok' === AIGS_Revenue::status() ) {
			$this->render_revenue( true );
		}
		?>

		<?php
		$series = $t->daily_series( 'crawl', 14 );
		$byc    = $t->by_platform( 'crawl', 30 );
		?>
		<div class="aigs-card">
			<div class="aigs-card__head"><?php self::i( 'activity' ); ?><h2><?php esc_html_e( 'AI crawler activity · last 14 days', 'omnigeo-ai-seo' ); ?></h2></div>
			<p class="aigs-card__desc"><?php esc_html_e( 'Requests from bots that identify themselves as AI crawlers.', 'omnigeo-ai-seo' ); ?></p>
			<?php
			if ( array_sum( $series ) > 0 ) :
				$maxv = max( 1, max( $series ) );
				?>
				<div class="aigs-bars">
					<?php foreach ( $series as $day => $val ) : ?>
						<span class="aigs-bar" style="height:<?php echo (int) max( 3, round( ( $val / $maxv ) * 90 ) ); ?>px" title="<?php echo esc_attr( $day . ': ' . $val ); ?>"></span>
					<?php endforeach; ?>
				</div>
				<?php if ( $byc ) : ?>
					<p class="aigs-muted aigs-small">
					<?php
					$bits = array();
					foreach ( array_slice( $byc, 0, 5, true ) as $plat => $num ) {
						$bits[] = $plat . ' ' . (int) $num;
					}
					echo esc_html( implode( ' · ', $bits ) );
					?>
					</p>
				<?php endif; ?>
			<?php else : ?>
				<div class="aigs-empty"><?php self::i( 'bot', 30 ); ?><p><?php esc_html_e( 'No AI crawler activity yet. It appears here as bots like GPTBot and ClaudeBot visit.', 'omnigeo-ai-seo' ); ?></p></div>
			<?php endif; ?>
		</div>
		<?php
		$this->close();
	}

	/**
	 * Recently verified fixes.
	 */
	private function render_wins() {
		$rows = AIGS_Findings::query(
			array(
				'status' => 'fixed',
				'limit'  => 6,
			)
		);
		?>
		<div class="aigs-card">
			<div class="aigs-card__head"><?php self::i( 'trophy' ); ?><h2><?php esc_html_e( 'Recent wins', 'omnigeo-ai-seo' ); ?></h2></div>
			<p class="aigs-card__desc"><?php esc_html_e( 'Issues the scanner confirmed as fixed.', 'omnigeo-ai-seo' ); ?></p>
			<?php if ( $rows ) : ?>
				<ul class="aigs-wins">
					<?php
					foreach ( $rows as $r ) :
						$m = AIGS_Rules::meta( $r['rule'] );
						if ( ! $m ) {
							continue;
						}
						$title = $r['post_id'] ? get_the_title( (int) $r['post_id'] ) : __( 'Whole site', 'omnigeo-ai-seo' );
						?>
						<li><?php self::i( 'check', 14 ); ?><span><strong><?php echo esc_html( $m['title'] ); ?></strong> <em dir="auto"><?php echo esc_html( $title ); ?></em></span><time><?php echo esc_html( self::ago( strtotime( (string) $r['resolved_at'] ) - (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ) ); ?></time></li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<div class="aigs-empty"><?php self::i( 'trophy', 30 ); ?><p><?php esc_html_e( 'Your fixes will show up here once a re-check confirms them.', 'omnigeo-ai-seo' ); ?></p></div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Latest AI visibility numbers.
	 */
	private function render_visibility_snapshot() {
		$batches = AIGS_AI_Visibility::batches( 1 );
		?>
		<div class="aigs-card">
			<div class="aigs-card__head"><?php self::i( 'radar' ); ?><h2><?php esc_html_e( 'AI visibility', 'omnigeo-ai-seo' ); ?></h2></div>
			<?php
			if ( $batches ) :
				$s = AIGS_AI_Visibility::summarize( AIGS_AI_Visibility::batch_rows( $batches[0]['batch_id'] ) );
				?>
				<p class="aigs-card__desc">
				<?php
				/* translators: 1: number of questions, 2: time ago. */
				printf( esc_html__( 'Latest check: %1$d questions, %2$s.', 'omnigeo-ai-seo' ), (int) $s['prompts'], esc_html( self::ago( strtotime( $batches[0]['t'] ) - (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ) ) );
				?>
				</p>
				<div class="aigs-kpis">
					<div><span><?php echo (int) $s['mention']; ?>%</span><em><?php esc_html_e( 'mentioned you', 'omnigeo-ai-seo' ); ?></em></div>
					<div><span><?php echo (int) $s['cited']; ?>%</span><em><?php esc_html_e( 'cited your site', 'omnigeo-ai-seo' ); ?></em></div>
					<div><span><?php echo (int) $s['share']['__brand']; ?>%</span><em><?php esc_html_e( 'share of voice', 'omnigeo-ai-seo' ); ?></em></div>
				</div>
				<p><a class="aigs-btn aigs-btn--sm" href="<?php echo esc_url( admin_url( 'admin.php?page=aigs-ai-visibility' ) ); ?>"><?php esc_html_e( 'Open report', 'omnigeo-ai-seo' ); ?></a></p>
			<?php else : ?>
				<div class="aigs-empty"><?php self::i( 'radar', 30 ); ?><p><?php esc_html_e( 'Track whether ChatGPT, Claude or Perplexity mention you for the questions your buyers ask.', 'omnigeo-ai-seo' ); ?></p>
					<p style="margin-top:12px"><a class="aigs-btn aigs-btn--sm" href="<?php echo esc_url( admin_url( 'admin.php?page=aigs-ai-visibility' ) ); ?>"><?php esc_html_e( 'Set up tracking', 'omnigeo-ai-seo' ); ?></a></p></div>
			<?php endif; ?>
		</div>
		<?php
	}

	/* ============================================================
	 * Action Center
	 * ============================================================ */

	/**
	 * Action Center page (task list, or one task's detail).
	 */
	public function page_actions() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view filters.
		$rule   = isset( $_GET['rule'] ) ? sanitize_key( wp_unslash( $_GET['rule'] ) ) : '';
		$cat    = isset( $_GET['cat'] ) ? sanitize_key( wp_unslash( $_GET['cat'] ) ) : '';
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'open';
		// phpcs:enable

		$this->open( __( 'Action Center', 'omnigeo-ai-seo' ), __( 'Every improvement in one list: what to fix, where, why it matters, and proof when it is done.', 'omnigeo-ai-seo' ), $this->scan_button( false ) );
		$this->scan_progress();

		$summary = get_option( 'aigs_scan_summary', array() );
		if ( empty( $summary['scanned_at'] ) ) {
			echo '<div class="aigs-card"><div class="aigs-empty">' . wp_kses( self::icon( 'list', 30 ), self::svg_kses() ) . '<p>' . esc_html__( 'Run a site scan to build your task list. It takes a minute or two and changes nothing on your site.', 'omnigeo-ai-seo' ) . '</p><p style="margin-top:12px"><button type="button" class="aigs-btn aigs-btn--primary" data-aigs-trigger="#aigs-scan-start">' . esc_html__( 'Scan site', 'omnigeo-ai-seo' ) . '</button></p></div></div>';
			$this->close();
			return;
		}

		if ( 'ignored' === $status ) {
			$this->render_ignored();
		} elseif ( '' !== $rule ) {
			$this->render_task( $rule );
		} else {
			$this->render_task_list( $cat );
		}
		$this->close();
	}

	/**
	 * Grouped task cards.
	 *
	 * @param string $cat Category filter.
	 */
	private function render_task_list( $cat ) {
		$tasks  = AIGS_Actions::tasks( 100 );
		$cats   = AIGS_Rules::categories();
		$counts = array();
		foreach ( $tasks as $t ) {
			$counts[ $t['cat'] ] = ( isset( $counts[ $t['cat'] ] ) ? $counts[ $t['cat'] ] : 0 ) + $t['count'];
		}
		$base = admin_url( 'admin.php?page=aigs-actions' );
		echo '<nav class="aigs-tabs" aria-label="' . esc_attr__( 'Filter tasks', 'omnigeo-ai-seo' ) . '">';
		echo '<a class="' . ( '' === $cat ? 'is-active' : '' ) . '" href="' . esc_url( $base ) . '">' . esc_html__( 'All', 'omnigeo-ai-seo' ) . ' <span>' . (int) array_sum( $counts ) . '</span></a>';
		foreach ( $cats as $key => $label ) {
			if ( empty( $counts[ $key ] ) ) {
				continue;
			}
			echo '<a class="' . ( $cat === $key ? 'is-active' : '' ) . '" href="' . esc_url( add_query_arg( 'cat', $key, $base ) ) . '">' . esc_html( $label ) . ' <span>' . (int) $counts[ $key ] . '</span></a>';
		}
		$ignored = AIGS_Findings::counts();
		if ( $ignored['ignored'] ) {
			echo '<a class="aigs-tabs__right" href="' . esc_url( add_query_arg( 'status', 'ignored', $base ) ) . '">' . esc_html__( 'Intentional', 'omnigeo-ai-seo' ) . ' <span>' . (int) $ignored['ignored'] . '</span></a>';
		}
		echo '</nav>';

		$shown = 0;
		echo '<div class="aigs-tasks">';
		foreach ( $tasks as $t ) {
			if ( '' !== $cat && $t['cat'] !== $cat ) {
				continue;
			}
			$shown++;
			$url = add_query_arg( 'rule', $t['rule'], $base );
			?>
			<a class="aigs-task" href="<?php echo esc_url( $url ); ?>">
				<span class="aigs-task__top"><?php echo wp_kses_post( self::sev_pill( $t['severity'] ) ); ?><span class="aigs-task__cat"><?php echo esc_html( isset( $cats[ $t['cat'] ] ) ? $cats[ $t['cat'] ] : '' ); ?></span></span>
				<strong><?php echo esc_html( $t['title'] ); ?></strong>
				<span class="aigs-task__why"><?php echo esc_html( $t['why'] ); ?></span>
				<span class="aigs-task__foot">
				<?php
				/* translators: %d: number of affected items. */
				printf( esc_html( _n( '%d item', '%d items', (int) $t['count'], 'omnigeo-ai-seo' ) ), (int) $t['count'] );
				?>
				<?php self::i( 'arrow', 15 ); ?></span>
			</a>
			<?php
		}
		echo '</div>';
		if ( ! $shown ) {
			echo '<div class="aigs-card"><div class="aigs-empty">' . wp_kses( self::icon( 'trophy', 30 ), self::svg_kses() ) . '<p>' . esc_html__( 'Nothing to fix here. Nice work.', 'omnigeo-ai-seo' ) . '</p></div></div>';
		}
	}

	/**
	 * One task's detail: explanation + affected items with actions.
	 *
	 * @param string $rule Rule or vis_gap.
	 */
	private function render_task( $rule ) {
		$meta = in_array( $rule, array( 'vis_gap', 'vis_fact' ), true ) ? AIGS_Rules::meta( $rule . '_x' ) : AIGS_Rules::meta( $rule );
		$back = admin_url( 'admin.php?page=aigs-actions' );
		echo '<p><a class="aigs-back" href="' . esc_url( $back ) . '">' . ( is_rtl() ? '→ ' : '← ' ) . esc_html__( 'All tasks', 'omnigeo-ai-seo' ) . '</a></p>';
		if ( ! $meta ) {
			echo '<div class="aigs-card"><p>' . esc_html__( 'Unknown task.', 'omnigeo-ai-seo' ) . '</p></div>';
			return;
		}
		$rows = AIGS_Actions::task_rows( $rule, 200 );
		?>
		<div class="aigs-card aigs-taskhead">
			<div class="aigs-taskhead__l">
				<?php echo wp_kses_post( self::sev_pill( $meta['sev'] ) ); ?>
				<h2><?php echo esc_html( $meta['title'] ); ?></h2>
				<p><strong><?php esc_html_e( 'Why it matters:', 'omnigeo-ai-seo' ); ?></strong> <?php echo esc_html( $meta['why'] ); ?></p>
				<p><strong><?php esc_html_e( 'How to fix:', 'omnigeo-ai-seo' ); ?></strong> <?php echo esc_html( $meta['fix'] ); ?></p>
			</div>
			<?php if ( 'site' !== $meta['grp'] && 'vis' !== $meta['grp'] && $rows ) : ?>
				<div class="aigs-taskhead__r">
					<button type="button" class="aigs-btn aigs-btn--ghost aigs-btn--sm" data-aigs-ignore-rule="<?php echo esc_attr( $rule ); ?>"><?php esc_html_e( 'This is intentional for all pages', 'omnigeo-ai-seo' ); ?></button>
				</div>
			<?php endif; ?>
		</div>
		<?php
		if ( ! $rows ) {
			echo '<div class="aigs-card"><div class="aigs-empty">' . wp_kses( self::icon( 'trophy', 30 ), self::svg_kses() ) . '<p>' . esc_html__( 'All done. Every item of this task is fixed or marked as intentional.', 'omnigeo-ai-seo' ) . '</p></div></div>';
			return;
		}
		$ids = array_filter( array_map( 'intval', wp_list_pluck( $rows, 'post_id' ) ) );
		if ( $ids ) {
			_prime_post_caches( $ids, false, true );
		}
		echo '<div class="aigs-card aigs-rows">';
		foreach ( $rows as $r ) {
			$this->render_row( $r, $meta );
		}
		echo '</div>';
		if ( in_array( $rule, array( 'orphan', 'no_internal_links' ), true ) ) {
			$this->render_link_log();
		}
	}

	/**
	 * Recent one-click link insertions with undo.
	 */
	private function render_link_log() {
		$ops = get_option( AIGS_Links::LOG_OPTION, array() );
		if ( ! is_array( $ops ) || ! $ops ) {
			return;
		}
		echo '<div class="aigs-card"><div class="aigs-card__head">' . wp_kses( self::icon( 'undo', 17 ), self::svg_kses() ) . '<h2>' . esc_html__( 'Recent link changes', 'omnigeo-ai-seo' ) . '</h2></div>';
		echo '<p class="aigs-card__desc">' . esc_html__( 'Undo restores the page exactly as it was before the links were added. WordPress revisions also keep every version.', 'omnigeo-ai-seo' ) . '</p>';
		echo '<table class="aigs-table"><tbody>';
		foreach ( array_slice( $ops, 0, 10 ) as $op ) {
			$n     = 0;
			$names = array();
			foreach ( $op['items'] as $it ) {
				$n      += count( $it['targets'] );
				$names[] = get_the_title( (int) $it['source'] );
			}
			echo '<tr><td dir="auto">' . esc_html( implode( ', ', array_slice( $names, 0, 3 ) ) ) . '</td>';
			/* translators: %d: number of links. */
			echo '<td class="aigs-muted">' . esc_html( sprintf( _n( '%d link', '%d links', $n, 'omnigeo-ai-seo' ), $n ) . ' · ' . self::ago( (int) $op['time'] ) ) . '</td>';
			echo '<td class="act"><button type="button" class="aigs-btn aigs-btn--sm" data-aigs-links-undo="' . esc_attr( $op['id'] ) . '">' . wp_kses( self::icon( 'undo', 15 ), self::svg_kses() ) . esc_html__( 'Undo', 'omnigeo-ai-seo' ) . '</button></td></tr>';
		}
		echo '</tbody></table></div>';
	}

	/**
	 * One affected item.
	 *
	 * @param array $r    Finding row.
	 * @param array $meta Rule meta.
	 */
	private function render_row( $r, $meta ) {
		$pid  = (int) $r['post_id'];
		$ev   = (array) json_decode( (string) $r['evidence'], true );
		$text = AIGS_Actions::evidence_text( $r['rule'], $ev );
		echo '<div class="aigs-row" data-finding="' . (int) $r['id'] . '">';
		echo '<div class="aigs-row__main">';
		if ( $pid ) {
			$post = get_post( $pid );
			$name = $post ? get_the_title( $post ) : '#' . $pid;
			$imp  = (int) get_post_meta( $pid, '_aigs_importance', true );
			$star = (bool) get_post_meta( $pid, '_aigs_important', true );
			echo '<strong dir="auto">' . esc_html( '' !== $name ? $name : __( '(no title)', 'omnigeo-ai-seo' ) ) . '</strong>';
			echo '<span class="aigs-row__meta">';
			if ( $post ) {
				echo '<span class="aigs-badge">' . esc_html( get_post_type_object( $post->post_type ) ? get_post_type_object( $post->post_type )->labels->singular_name : $post->post_type ) . '</span>';
			}
			if ( $imp >= 60 ) {
				echo '<span class="aigs-badge aigs-badge--imp">' . esc_html__( 'Important page', 'omnigeo-ai-seo' ) . '</span>';
			}
			$score = get_post_meta( $pid, '_aigs_page_score', true );
			if ( '' !== $score ) {
				/* translators: %d: page score. */
				echo '<span class="aigs-badge aigs-badge--' . esc_attr( self::score_class( (int) $score ) ) . '">' . esc_html( sprintf( __( 'Score %d', 'omnigeo-ai-seo' ), (int) $score ) ) . '</span>';
			}
			echo '</span>';
			if ( '' !== $text ) {
				echo '<span class="aigs-row__ev">' . esc_html( $text ) . '</span>';
			}
			if ( ! empty( $ev['suggest'] ) ) {
				$links = array();
				foreach ( array_slice( (array) $ev['suggest'], 0, 3 ) as $sid ) {
					$links[] = '<a href="' . esc_url( (string) get_edit_post_link( (int) $sid ) ) . '">' . esc_html( get_the_title( (int) $sid ) ) . '</a>';
				}
				if ( $links ) {
					$label = 'orphan' === $r['rule'] ? __( 'Link to it from:', 'omnigeo-ai-seo' ) : __( 'Related pages to link to:', 'omnigeo-ai-seo' );
					echo '<span class="aigs-row__ev">' . esc_html( $label ) . ' ' . wp_kses_post( implode( ', ', $links ) ) . '</span>';
				}
			}
			if ( ! empty( $ev['others'] ) ) {
				$links = array();
				foreach ( array_slice( (array) $ev['others'], 0, 3 ) as $oid ) {
					$links[] = '<a href="' . esc_url( (string) get_edit_post_link( (int) $oid ) ) . '">' . esc_html( get_the_title( (int) $oid ) ) . '</a>';
				}
				echo '<span class="aigs-row__ev">' . esc_html__( 'Same as:', 'omnigeo-ai-seo' ) . ' ' . wp_kses_post( implode( ', ', $links ) ) . '</span>';
			}
			echo '</div><div class="aigs-row__acts">';
			if ( 'author' === $meta['act'] && ! empty( $ev['author_id'] ) ) {
				echo '<a class="aigs-btn aigs-btn--sm" href="' . esc_url( get_edit_user_link( (int) $ev['author_id'] ) ) . '">' . wp_kses( self::icon( 'edit', 15 ), self::svg_kses() ) . esc_html__( 'Edit profile', 'omnigeo-ai-seo' ) . '</a>';
			} elseif ( 'products' === $meta['act'] && AIGS_WooCommerce::active() ) {
				echo '<a class="aigs-btn aigs-btn--sm" href="' . esc_url( admin_url( 'admin.php?page=aigs-products' ) ) . '">' . wp_kses( self::icon( 'zap', 15 ), self::svg_kses() ) . esc_html__( 'Bulk fix', 'omnigeo-ai-seo' ) . '</a>';
				echo '<a class="aigs-btn aigs-btn--sm" href="' . esc_url( (string) get_edit_post_link( $pid ) ) . '">' . wp_kses( self::icon( 'edit', 15 ), self::svg_kses() ) . esc_html__( 'Edit', 'omnigeo-ai-seo' ) . '</a>';
			} else {
				echo '<a class="aigs-btn aigs-btn--sm" href="' . esc_url( (string) get_edit_post_link( $pid ) ) . '">' . wp_kses( self::icon( 'edit', 15 ), self::svg_kses() ) . esc_html__( 'Edit', 'omnigeo-ai-seo' ) . '</a>';
			}
			echo '<a class="aigs-btn aigs-btn--ghost aigs-btn--sm" href="' . esc_url( (string) get_permalink( $pid ) ) . '" target="_blank" rel="noopener" title="' . esc_attr__( 'View', 'omnigeo-ai-seo' ) . '">' . wp_kses( self::icon( 'eye', 15 ), self::svg_kses() ) . '<span class="screen-reader-text">' . esc_html__( 'View', 'omnigeo-ai-seo' ) . '</span></a>';
			if ( in_array( $r['rule'], array( 'orphan', 'no_internal_links' ), true ) ) {
				echo '<button type="button" class="aigs-btn aigs-btn--primary aigs-btn--sm" data-aigs-links="' . ( 'orphan' === $r['rule'] ? 'to' : 'from' ) . '" data-post="' . (int) $pid . '">' . wp_kses( self::icon( 'link', 15 ), self::svg_kses() ) . esc_html__( 'Find link spots', 'omnigeo-ai-seo' ) . '</button>';
			}
			echo '<button type="button" class="aigs-btn aigs-btn--sm" data-aigs-recheck="' . (int) $pid . '">' . wp_kses( self::icon( 'refresh', 15 ), self::svg_kses() ) . esc_html__( 'Re-check', 'omnigeo-ai-seo' ) . '</button>';
			echo '<button type="button" class="aigs-btn aigs-btn--ghost aigs-btn--sm' . ( $star ? ' is-on' : '' ) . '" data-aigs-important="' . (int) $pid . '" title="' . esc_attr__( 'Mark as important page (raises priority)', 'omnigeo-ai-seo' ) . '">' . wp_kses( self::icon( 'star', 15 ), self::svg_kses() ) . '<span class="screen-reader-text">' . esc_html__( 'Important', 'omnigeo-ai-seo' ) . '</span></button>';
		} else {
			// Site-level or AI visibility finding.
			if ( 0 === strpos( $r['rule'], 'vis_fact_' ) ) {
				echo '<strong dir="auto">' . esc_html( isset( $ev['prompt'] ) ? $ev['prompt'] : '' ) . '</strong>';
				echo '<ul class="aigs-row__urls">';
				foreach ( isset( $ev['issues'] ) ? (array) $ev['issues'] : array() as $iss ) {
					echo '<li dir="auto">' . esc_html( AIGS_AI_Visibility::issue_text( $iss ) ) . '</li>';
				}
				echo '</ul>';
				echo '</div><div class="aigs-row__acts">';
				echo '<a class="aigs-btn aigs-btn--sm" href="' . esc_url( admin_url( 'admin.php?page=aigs-ai-visibility' ) ) . '">' . wp_kses( self::icon( 'eye', 15 ), self::svg_kses() ) . esc_html__( 'See the answer', 'omnigeo-ai-seo' ) . '</a>';
				echo '<button type="button" class="aigs-btn aigs-btn--primary aigs-btn--sm" data-aigs-trigger-facts="1">' . wp_kses( self::icon( 'file', 15 ), self::svg_kses() ) . esc_html__( 'Create facts page', 'omnigeo-ai-seo' ) . '</button>';
			} elseif ( 0 === strpos( $r['rule'], 'vis_gap_' ) ) {
				echo '<strong dir="auto">' . esc_html( isset( $ev['prompt'] ) ? $ev['prompt'] : '' ) . '</strong>';
				if ( '' !== $text ) {
					echo '<span class="aigs-row__ev">' . esc_html( $text ) . '</span>';
				}
				echo '</div><div class="aigs-row__acts">';
				echo '<button type="button" class="aigs-btn aigs-btn--primary aigs-btn--sm" data-aigs-vis-draft="' . (int) $r['id'] . '">' . wp_kses( self::icon( 'edit', 15 ), self::svg_kses() ) . esc_html__( 'Create draft page', 'omnigeo-ai-seo' ) . '</button>';
			} else {
				$m = AIGS_Rules::meta( $r['rule'] );
				echo '<strong>' . esc_html( $m ? $m['title'] : $r['rule'] ) . '</strong>';
				if ( '' !== $text ) {
					echo '<span class="aigs-row__ev">' . esc_html( $text ) . '</span>';
				}
				if ( ! empty( $ev['urls'] ) ) {
					echo '<ul class="aigs-row__urls">';
					foreach ( (array) $ev['urls'] as $u => $c ) {
						/* translators: %d: number of requests. */
						echo '<li><code>' . esc_html( $u ) . '</code> <em>' . esc_html( sprintf( _n( '%d request', '%d requests', (int) $c, 'omnigeo-ai-seo' ), (int) $c ) ) . '</em></li>';
					}
					echo '</ul>';
				}
				echo '</div><div class="aigs-row__acts">';
				echo self::site_action( $m ? $m['act'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_* helpers.
				if ( 'site_ai_blocked' === $r['rule'] ) {
					echo self::site_action( 'auto:ai_search_only' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_* helpers.
				}
			}
		}
		echo '<button type="button" class="aigs-btn aigs-btn--ghost aigs-btn--sm" data-aigs-status="ignored" data-id="' . (int) $r['id'] . '">' . esc_html__( 'Intentional', 'omnigeo-ai-seo' ) . '</button>';
		echo '</div>';
		if ( $pid && in_array( $r['rule'], array( 'orphan', 'no_internal_links' ), true ) ) {
			echo '<div class="aigs-linkbox" hidden></div>';
		}
		echo '</div>';
	}

	/**
	 * Items marked as intentional (can be reopened).
	 */
	private function render_ignored() {
		echo '<p><a class="aigs-back" href="' . esc_url( admin_url( 'admin.php?page=aigs-actions' ) ) . '">' . ( is_rtl() ? '→ ' : '← ' ) . esc_html__( 'All tasks', 'omnigeo-ai-seo' ) . '</a></p>';
		$rows = AIGS_Findings::query(
			array(
				'status' => 'ignored',
				'limit'  => 300,
			)
		);
		echo '<div class="aigs-card aigs-rows"><div class="aigs-card__head">' . wp_kses( self::icon( 'flag', 17 ), self::svg_kses() ) . '<h2>' . esc_html__( 'Marked as intentional', 'omnigeo-ai-seo' ) . '</h2></div>';
		echo '<p class="aigs-card__desc">' . esc_html__( 'These do not count against your score. Reopen any item to track it again.', 'omnigeo-ai-seo' ) . '</p>';
		foreach ( $rows as $r ) {
			$m    = AIGS_Rules::meta( $r['rule'] );
			$name = $r['post_id'] ? get_the_title( (int) $r['post_id'] ) : __( 'Whole site', 'omnigeo-ai-seo' );
			echo '<div class="aigs-row" data-finding="' . (int) $r['id'] . '"><div class="aigs-row__main"><strong>' . esc_html( $m ? $m['title'] : $r['rule'] ) . '</strong><span class="aigs-row__ev">' . esc_html( $name ) . '</span></div>';
			echo '<div class="aigs-row__acts"><button type="button" class="aigs-btn aigs-btn--sm" data-aigs-status="open" data-id="' . (int) $r['id'] . '">' . esc_html__( 'Reopen', 'omnigeo-ai-seo' ) . '</button></div></div>';
		}
		if ( ! $rows ) {
			echo '<div class="aigs-empty"><p>' . esc_html__( 'Nothing here.', 'omnigeo-ai-seo' ) . '</p></div>';
		}
		echo '</div>';
	}

	/* ============================================================
	 * Products (WooCommerce Catalog Copilot)
	 * ============================================================ */

	/**
	 * Products page.
	 */
	public function page_products() {
		$this->open( __( 'Products', 'omnigeo-ai-seo' ), __( 'Make every product easy for shopping assistants to describe, compare and recommend.', 'omnigeo-ai-seo' ), $this->scan_button( false ) );
		$this->scan_progress();

		$grouped = AIGS_Findings::grouped();
		$rules   = array();
		foreach ( AIGS_Rules::catalog() as $id => $r ) {
			if ( 'products' === $r[2] ) {
				$rules[ $id ] = $r;
			}
		}
		$total = 0;
		foreach ( array_keys( $rules ) as $id ) {
			$total += isset( $grouped[ $id ] ) ? $grouped[ $id ]['count'] : 0;
		}
		$summary = get_option( 'aigs_scan_summary', array() );
		?>
		<div class="aigs-callout"><?php self::i( 'info', 18 ); ?>
			<p><?php esc_html_e( 'All products are checked with simple rules first (free and instant). AI drafts are only used for the products you choose, with your own key, and nothing is saved until you approve it. Every bulk change can be undone.', 'omnigeo-ai-seo' ); ?></p>
		</div>

		<div class="aigs-card">
			<div class="aigs-card__head"><?php self::i( 'cart' ); ?><h2><?php esc_html_e( 'Catalog health', 'omnigeo-ai-seo' ); ?></h2></div>
			<?php if ( empty( $summary['scanned_at'] ) ) : ?>
				<div class="aigs-empty"><p><?php esc_html_e( 'Run a site scan to check your products.', 'omnigeo-ai-seo' ); ?></p></div>
			<?php elseif ( ! $total ) : ?>
				<div class="aigs-empty"><?php self::i( 'trophy', 30 ); ?><p><?php esc_html_e( 'Every product passes the catalog checks.', 'omnigeo-ai-seo' ); ?></p></div>
			<?php else : ?>
				<table class="aigs-table aigs-table--rules"><tbody>
				<?php
				foreach ( $rules as $id => $r ) :
					$n = isset( $grouped[ $id ] ) ? $grouped[ $id ]['count'] : 0;
					if ( ! $n ) {
						continue;
					}
					?>
					<tr>
						<td><?php echo wp_kses_post( self::sev_pill( $r[1] ) ); ?></td>
						<td><strong><?php echo esc_html( $r[3] ); ?></strong><br><span class="aigs-muted aigs-small"><?php echo esc_html( $r[4] ); ?></span></td>
						<td class="num"><?php echo (int) $n; ?></td>
						<td class="act"><a class="aigs-btn aigs-btn--sm" href="<?php echo esc_url( add_query_arg( 'rule', $id, admin_url( 'admin.php?page=aigs-actions' ) ) ); ?>"><?php esc_html_e( 'View', 'omnigeo-ai-seo' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
				</tbody></table>
			<?php endif; ?>
		</div>

		<div class="aigs-card">
			<div class="aigs-card__head"><?php self::i( 'zap' ); ?><h2><?php esc_html_e( 'Bulk fixes', 'omnigeo-ai-seo' ); ?></h2></div>
			<p class="aigs-card__desc"><?php esc_html_e( 'Preview first. Untick anything you do not want, edit the text if needed, then apply. Existing values are never overwritten.', 'omnigeo-ai-seo' ); ?></p>
			<div class="aigs-fixes">
				<?php
				foreach ( AIGS_WooCommerce::fixes() as $id => $fx ) :
					$n        = isset( $grouped[ $fx['rule'] ] ) ? $grouped[ $fx['rule'] ]['count'] : 0;
					$disabled = $fx['ai'] && ! AIGS_AI_Client::ready();
					?>
					<div class="aigs-fix">
						<strong><?php echo esc_html( $fx['label'] ); ?><?php echo $fx['ai'] ? ' <span class="aigs-badge">AI</span>' : ''; ?></strong>
						<span class="aigs-muted aigs-small"><?php echo esc_html( $fx['desc'] ); ?></span>
						<span class="aigs-small">
						<?php
						/* translators: %d: number of products. */
						printf( esc_html( _n( '%d product needs this', '%d products need this', (int) $n, 'omnigeo-ai-seo' ) ), (int) $n );
						?>
						</span>
						<button type="button" class="aigs-btn aigs-btn--sm" data-aigs-woo-preview="<?php echo esc_attr( $id ); ?>" <?php disabled( $disabled || ! $n ); ?>><?php self::i( 'eye', 15 ); ?><?php esc_html_e( 'Preview', 'omnigeo-ai-seo' ); ?></button>
						<?php if ( $disabled ) : ?>
							<span class="aigs-muted aigs-small"><?php esc_html_e( 'Needs an AI API key in Settings.', 'omnigeo-ai-seo' ); ?></span>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
			<div id="aigs-woo-preview"></div>
		</div>

		<?php
		$ops = get_option( 'aigs_bulk_log', array() );
		if ( is_array( $ops ) && $ops ) :
			$fixes = AIGS_WooCommerce::fixes();
			?>
			<div class="aigs-card">
				<div class="aigs-card__head"><?php self::i( 'undo' ); ?><h2><?php esc_html_e( 'Recent bulk changes', 'omnigeo-ai-seo' ); ?></h2></div>
				<table class="aigs-table"><tbody>
				<?php foreach ( $ops as $op ) : ?>
					<tr data-op="<?php echo esc_attr( $op['op'] ); ?>">
						<td><?php echo esc_html( isset( $fixes[ $op['fix'] ] ) ? $fixes[ $op['fix'] ]['label'] : $op['fix'] ); ?></td>
						<td class="aigs-muted">
						<?php
						/* translators: %d: number of products. */
						echo esc_html( sprintf( _n( '%d product', '%d products', count( $op['items'] ), 'omnigeo-ai-seo' ), count( $op['items'] ) ) . ' · ' . self::ago( (int) $op['time'] ) );
						?>
						</td>
						<td class="act"><button type="button" class="aigs-btn aigs-btn--sm" data-aigs-woo-undo="<?php echo esc_attr( $op['op'] ); ?>"><?php self::i( 'undo', 15 ); ?><?php esc_html_e( 'Undo', 'omnigeo-ai-seo' ); ?></button></td>
					</tr>
				<?php endforeach; ?>
				</tbody></table>
			</div>
			<?php
		endif;
		$this->close();
	}

	/* ============================================================
	 * AI Visibility
	 * ============================================================ */

	/**
	 * AI Visibility page.
	 */
	public function page_ai_visibility() {
		$has_key  = AIGS_AI_Client::ready();
		$prompts  = AIGS_AI_Visibility::prompts();
		$provs    = AIGS_AI_Client::providers();
		$provider = AIGS_AI_Client::provider();
		$batches  = AIGS_AI_Visibility::batches( 12 );

		$this->open( __( 'AI Visibility', 'omnigeo-ai-seo' ), __( 'Do assistants mention, recommend and cite you for the questions your buyers ask?', 'omnigeo-ai-seo' ) );

		if ( ! $has_key ) {
			echo '<div class="aigs-callout">' . wp_kses( self::icon( 'info', 18 ), self::svg_kses() ) . '<p>' . wp_kses_post(
				sprintf(
					/* translators: %s settings link */
					__( 'Add your own AI API key in <a href="%s">Settings</a> to run checks. You pay your provider directly; OmniGEO adds no subscription and enforces a monthly request limit you control.', 'omnigeo-ai-seo' ),
					esc_url( admin_url( 'admin.php?page=aigs-settings#aigs-ai' ) )
				)
			) . '</p></div>';
		} else {
			echo '<div class="aigs-callout">' . wp_kses( self::icon( 'info', 18 ), self::svg_kses() ) . '<p>';
			echo esc_html(
				sprintf(
					/* translators: 1: provider, 2: model, 3: requests left, 4: monthly cap. */
					__( 'Provider: %1$s (%2$s). AI requests left this month: %3$d of %4$d.', 'omnigeo-ai-seo' ),
					$provs[ $provider ]['label'],
					AIGS_AI_Client::model(),
					AIGS_AI_Client::remaining(),
					AIGS_AI_Client::monthly_cap()
				)
			);
			echo ' ';
			echo AIGS_AI_Client::grounded() ? esc_html__( 'Answers come from a live web search, so cited sources are real.', 'omnigeo-ai-seo' ) : esc_html__( 'This provider answers from model knowledge without a live web search, so results show what the model “remembers”, not live web citations. Perplexity gives web-grounded answers with sources.', 'omnigeo-ai-seo' );
			echo '</p></div>';
		}

		// Results.
		if ( $batches ) {
			$this->render_visibility_results( $batches );
		}
		?>
		<div class="aigs-grid2 aigs-grid2--wide">
			<div class="aigs-card">
				<div class="aigs-card__head"><?php self::i( 'sliders' ); ?><h2><?php esc_html_e( 'What to track', 'omnigeo-ai-seo' ); ?></h2></div>
				<form method="post" action="options.php">
					<?php settings_fields( 'aigs_settings_group' ); ?>
					<input type="hidden" name="aigs_settings[section_visibility]" value="1" />
					<div class="aigs-field">
						<label for="aigs_aliases"><?php esc_html_e( 'Other names for your brand (one per line)', 'omnigeo-ai-seo' ); ?></label>
						<textarea id="aigs_aliases" name="aigs_settings[brand_aliases]" rows="3" placeholder="<?php esc_attr_e( "e.g. your brand in another language or script,\nan abbreviation, or a short name customers use", 'omnigeo-ai-seo' ); ?>"><?php echo esc_textarea( aigs_get( 'brand_aliases', '' ) ); ?></textarea>
						<p class="description">
						<?php
						/* translators: %s: brand name. */
						printf( esc_html__( 'Your main name “%s” is always included. Matching ignores letter case, accents, spacing and hyphens, and handles non-Latin scripts.', 'omnigeo-ai-seo' ), esc_html( aigs_business_name() ) );
						?>
						</p>
					</div>
					<div class="aigs-field">
						<label for="aigs_comp"><?php esc_html_e( 'Competitors (one per line: Name | domain.com)', 'omnigeo-ai-seo' ); ?></label>
						<textarea id="aigs_comp" name="aigs_settings[competitors]" rows="4" class="aigs-code" placeholder="Competitor One | competitor-one.com&#10;Competitor Two | competitor-two.co.uk"><?php echo esc_textarea( aigs_get( 'competitors', '' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Up to 10. Add more spellings after another “|”, e.g. Name | domain.com | Other spelling.', 'omnigeo-ai-seo' ); ?></p>
					</div>
					<div class="aigs-field">
						<label for="aigs_prompts"><?php esc_html_e( 'Questions buyers ask AI (one per line)', 'omnigeo-ai-seo' ); ?></label>
						<textarea id="aigs_prompts" name="aigs_settings[vis_prompts]" rows="6"><?php echo esc_textarea( aigs_get( 'vis_prompts', '' ) ); ?></textarea>
						<p class="description">
						<?php
						/* translators: %d: max prompts. */
						printf( esc_html__( 'Up to %d. Each question uses one AI request per check.', 'omnigeo-ai-seo' ), (int) AIGS_AI_Visibility::MAX_PROMPTS );
						?>
						<button type="button" class="button-link" id="aigs-suggest-prompts"><?php esc_html_e( 'Suggest questions', 'omnigeo-ai-seo' ); ?></button></p>
						<div id="aigs-prompt-list" class="aigs-chips"></div>
					</div>
					<details class="aigs-facts" <?php echo ( '' === trim( (string) aigs_get( 'fact_phones', '' ) . aigs_get( 'fact_outdated', '' ) . aigs_get( 'fact_domains', '' ) ) ) ? '' : 'open'; ?>>
						<summary><strong><?php esc_html_e( 'Brand facts (accuracy check)', 'omnigeo-ai-seo' ); ?></strong> <span class="aigs-muted aigs-small"><?php esc_html_e( 'Get warned when an assistant gives people wrong details about you.', 'omnigeo-ai-seo' ); ?></span></summary>
						<div class="aigs-field">
							<label for="aigs_fphones"><?php esc_html_e( 'Official phone numbers (one per line)', 'omnigeo-ai-seo' ); ?></label>
							<textarea id="aigs_fphones" name="aigs_settings[fact_phones]" rows="2" dir="ltr"><?php echo esc_textarea( aigs_get( 'fact_phones', '' ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Your main phone from Settings is always included.', 'omnigeo-ai-seo' ); ?></p>
						</div>
						<div class="aigs-field">
							<label for="aigs_femails"><?php esc_html_e( 'Official email addresses (one per line)', 'omnigeo-ai-seo' ); ?></label>
							<textarea id="aigs_femails" name="aigs_settings[fact_emails]" rows="2" dir="ltr"><?php echo esc_textarea( aigs_get( 'fact_emails', '' ) ); ?></textarea>
						</div>
						<div class="aigs-field">
							<label for="aigs_fdomains"><?php esc_html_e( 'Other official domains (one per line)', 'omnigeo-ai-seo' ); ?></label>
							<textarea id="aigs_fdomains" name="aigs_settings[fact_domains]" rows="2" dir="ltr" placeholder="shop.example.com&#10;example.co.uk"><?php echo esc_textarea( aigs_get( 'fact_domains', '' ) ); ?></textarea>
							<p class="description">
							<?php
							/* translators: %s: site domain. */
							printf( esc_html__( '%s is always official. Domains that contain your brand name but are not listed here are flagged.', 'omnigeo-ai-seo' ), esc_html( AIGS_AI_Visibility::own_domain() ) );
							?>
							</p>
						</div>
						<div class="aigs-field">
							<label for="aigs_ffounded"><?php esc_html_e( 'Founding year', 'omnigeo-ai-seo' ); ?></label>
							<input type="text" id="aigs_ffounded" class="small-text" style="width:7em" name="aigs_settings[fact_founded]" value="<?php echo esc_attr( aigs_get( 'fact_founded', '' ) ); ?>" placeholder="<?php echo esc_attr( (string) aigs_get( 'org_founded', '' ) ); ?>" dir="ltr" />
						</div>
						<div class="aigs-field">
							<label for="aigs_foutdated"><?php esc_html_e( 'Outdated or wrong statements to watch for (one per line)', 'omnigeo-ai-seo' ); ?></label>
							<textarea id="aigs_foutdated" name="aigs_settings[fact_outdated]" rows="3" placeholder="<?php esc_attr_e( "e.g. an old address, a discontinued product name,
a former company name or an old price", 'omnigeo-ai-seo' ); ?>"><?php echo esc_textarea( aigs_get( 'fact_outdated', '' ) ); ?></textarea>
						</div>
						<div class="aigs-field">
							<label for="aigs_fnotes"><?php esc_html_e( 'Short description of what you offer (used for the facts page)', 'omnigeo-ai-seo' ); ?></label>
							<textarea id="aigs_fnotes" name="aigs_settings[fact_notes]" rows="3"><?php echo esc_textarea( aigs_get( 'fact_notes', '' ) ); ?></textarea>
						</div>
						<p><button type="button" class="aigs-btn aigs-btn--sm" id="aigs-facts-page"><?php self::i( 'file', 15 ); ?><?php esc_html_e( 'Create a “key facts” page draft', 'omnigeo-ai-seo' ); ?></button>
						<span class="aigs-muted aigs-small"><?php esc_html_e( 'Uses the saved facts. Save first.', 'omnigeo-ai-seo' ); ?></span></p>
					</details>
					<div class="aigs-field">
						<label for="aigs_vsched"><?php esc_html_e( 'Automatic checks', 'omnigeo-ai-seo' ); ?></label>
						<select id="aigs_vsched" name="aigs_settings[vis_schedule]">
							<option value="off" <?php selected( aigs_get( 'vis_schedule', 'off' ), 'off' ); ?>><?php esc_html_e( 'Off (run manually)', 'omnigeo-ai-seo' ); ?></option>
							<option value="weekly" <?php selected( aigs_get( 'vis_schedule', 'off' ), 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'omnigeo-ai-seo' ); ?></option>
							<option value="monthly" <?php selected( aigs_get( 'vis_schedule', 'off' ), 'monthly' ); ?>><?php esc_html_e( 'Monthly', 'omnigeo-ai-seo' ); ?></option>
						</select>
					</div>
					<p><button type="submit" class="aigs-btn aigs-btn--primary"><?php self::i( 'check', 16 ); ?><?php esc_html_e( 'Save', 'omnigeo-ai-seo' ); ?></button></p>
				</form>
			</div>

			<div class="aigs-card">
				<div class="aigs-card__head"><?php self::i( 'radar' ); ?><h2><?php esc_html_e( 'Run a check', 'omnigeo-ai-seo' ); ?></h2></div>
				<p class="aigs-card__desc">
				<?php
				/* translators: %d: number of questions. */
				printf( esc_html( _n( 'Ask all %d tracked question now and save the answers as evidence.', 'Ask all %d tracked questions now and save the answers as evidence.', count( $prompts ), 'omnigeo-ai-seo' ) ), count( $prompts ) );
				?>
				</p>
				<p><button type="button" class="aigs-btn aigs-btn--primary" id="aigs-vis-run" <?php disabled( ! $has_key || ! $prompts ); ?>><?php self::i( 'radar', 16 ); ?><?php esc_html_e( 'Run all questions', 'omnigeo-ai-seo' ); ?></button></p>
				<div class="aigs-progress" id="aigs-vis-progress" hidden><div class="aigs-progress__bar"><span style="width:0%"></span></div><p class="aigs-progress__txt" aria-live="polite"></p></div>

				<hr class="aigs-hr" />
				<h3 class="aigs-h3"><?php esc_html_e( 'Quick test of one question', 'omnigeo-ai-seo' ); ?></h3>
				<div class="aigs-inputgroup">
					<input type="text" id="aigs-prompt" class="aigs-input" placeholder="<?php esc_attr_e( 'e.g. What are the best providers for…', 'omnigeo-ai-seo' ); ?>" />
					<button class="aigs-btn" id="aigs-run-scan" <?php disabled( ! $has_key ); ?>><?php self::i( 'search', 16 ); ?><?php esc_html_e( 'Ask', 'omnigeo-ai-seo' ); ?></button>
				</div>
				<div id="aigs-scan-result"></div>
			</div>
		</div>
		<?php
		$this->close();
	}

	/**
	 * Latest batch report.
	 *
	 * @param array $batches Batches (newest first).
	 */
	private function render_visibility_results( $batches ) {
		$latest  = AIGS_AI_Visibility::batch_rows( $batches[0]['batch_id'] );
		$s       = AIGS_AI_Visibility::summarize( $latest );
		$prev    = isset( $batches[1] ) ? AIGS_AI_Visibility::batch_rows( $batches[1]['batch_id'] ) : array();
		$ps      = $prev ? AIGS_AI_Visibility::summarize( $prev ) : null;
		$prevmap = array();
		foreach ( $prev as $r ) {
			$prevmap[ $r['prompt_hash'] ] = $r;
		}
		$brand    = aigs_business_name();
		$grounded = ! empty( $latest[0]['grounded'] );
		$delta    = static function ( $now, $before ) {
			if ( null === $before ) {
				return '';
			}
			$d = (int) $now - (int) $before;
			if ( 0 === $d ) {
				return '';
			}
			return '<i class="aigs-delta aigs-delta--' . ( $d > 0 ? 'up' : 'down' ) . '">' . esc_html( ( $d > 0 ? '+' : '' ) . $d ) . '</i>';
		};
		?>
		<div class="aigs-card">
			<div class="aigs-card__head"><?php self::i( 'trend' ); ?><h2><?php esc_html_e( 'Latest results', 'omnigeo-ai-seo' ); ?></h2>
				<span class="aigs-badge"><?php echo $grounded ? esc_html__( 'Live web search', 'omnigeo-ai-seo' ) : esc_html__( 'Model knowledge only', 'omnigeo-ai-seo' ); ?></span>
			</div>
			<p class="aigs-card__desc">
			<?php
			/* translators: 1: number of questions, 2: date. */
			printf( esc_html__( '%1$d questions · %2$s. Every number links back to a stored answer.', 'omnigeo-ai-seo' ), (int) $s['prompts'], esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $batches[0]['t'] ) ) );
			?>
			</p>
			<?php if ( $s['wrong'] ) : ?>
				<div class="aigs-callout aigs-callout--warn"><?php self::i( 'flag', 18 ); ?><p>
				<?php
				/* translators: %d: number of answers. */
				echo esc_html( sprintf( _n( '%d answer gives people wrong facts about you. See the Facts column and the task in the Action Center.', '%d answers give people wrong facts about you. See the Facts column and the task in the Action Center.', (int) $s['wrong'], 'omnigeo-ai-seo' ), (int) $s['wrong'] ) );
				?>
				</p></div>
			<?php endif; ?>
			<div class="aigs-kpis aigs-kpis--4">
				<div><span><?php echo (int) $s['mention']; ?>%<?php echo wp_kses_post( $delta( $s['mention'], $ps ? $ps['mention'] : null ) ); ?></span><em><?php esc_html_e( 'answers mention you', 'omnigeo-ai-seo' ); ?></em></div>
				<div><span><?php echo (int) $s['recommended']; ?>%<?php echo wp_kses_post( $delta( $s['recommended'], $ps ? $ps['recommended'] : null ) ); ?></span><em><?php esc_html_e( 'recommend you (heuristic)', 'omnigeo-ai-seo' ); ?></em></div>
				<div><span><?php echo (int) $s['cited']; ?>%<?php echo wp_kses_post( $delta( $s['cited'], $ps ? $ps['cited'] : null ) ); ?></span><em><?php esc_html_e( 'cite or link your site', 'omnigeo-ai-seo' ); ?></em></div>
				<div><span><?php echo (int) $s['share']['__brand']; ?>%<?php echo wp_kses_post( $delta( $s['share']['__brand'], $ps ? $ps['share']['__brand'] : null ) ); ?></span><em><?php esc_html_e( 'share of voice vs competitors', 'omnigeo-ai-seo' ); ?></em></div>
			</div>

			<?php if ( count( $s['voice'] ) > 1 ) : ?>
				<h3 class="aigs-h3"><?php esc_html_e( 'Share of voice', 'omnigeo-ai-seo' ); ?></h3>
				<div class="aigs-sov">
					<?php
					$maxv = max( 1, max( $s['voice'] ) );
					foreach ( $s['voice'] as $name => $v ) :
						$label = '__brand' === $name ? $brand : $name;
						?>
						<div class="aigs-sov__row<?php echo '__brand' === $name ? ' is-you' : ''; ?>">
							<span class="aigs-sov__name"><?php echo esc_html( $label ); ?></span>
							<span class="aigs-sov__bar"><span style="width:<?php echo (int) round( 100 * $v / $maxv ); ?>%"></span></span>
							<span class="aigs-sov__n">
							<?php
							/* translators: 1: answers, 2: total. */
							printf( esc_html__( '%1$d of %2$d', 'omnigeo-ai-seo' ), (int) $v, (int) $s['prompts'] );
							?>
							</span>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<h3 class="aigs-h3"><?php esc_html_e( 'By question', 'omnigeo-ai-seo' ); ?></h3>
			<div class="aigs-tablewrap">
			<table class="aigs-table aigs-table--vis">
				<thead><tr>
					<th><?php esc_html_e( 'Question', 'omnigeo-ai-seo' ); ?></th>
					<th><?php esc_html_e( 'You', 'omnigeo-ai-seo' ); ?></th>
					<th><?php esc_html_e( 'Cited', 'omnigeo-ai-seo' ); ?></th>
					<th><?php esc_html_e( 'Position', 'omnigeo-ai-seo' ); ?></th>
					<th><?php esc_html_e( 'Competitors named', 'omnigeo-ai-seo' ); ?></th>
					<th><?php esc_html_e( 'Facts', 'omnigeo-ai-seo' ); ?></th>
					<th></th>
				</tr></thead>
				<tbody>
				<?php
				foreach ( $latest as $r ) :
					$comps = array_keys( array_filter( (array) json_decode( (string) $r['competitors'], true ) ) );
					$was   = isset( $prevmap[ $r['prompt_hash'] ] ) ? (int) $prevmap[ $r['prompt_hash'] ]['mentioned'] : null;
					$chg   = '';
					if ( null !== $was && $was !== (int) $r['mentioned'] ) {
						$chg = (int) $r['mentioned'] ? '<i class="aigs-delta aigs-delta--up">' . esc_html__( 'new', 'omnigeo-ai-seo' ) . '</i>' : '<i class="aigs-delta aigs-delta--down">' . esc_html__( 'lost', 'omnigeo-ai-seo' ) . '</i>';
					}
					?>
					<tr>
						<td dir="auto"><?php echo esc_html( $r['prompt'] ); ?></td>
						<td><?php echo (int) $r['mentioned'] ? '<span class="aigs-tag aigs-tag--yes">' . ( (int) $r['recommended'] ? esc_html__( 'Recommended', 'omnigeo-ai-seo' ) : esc_html__( 'Mentioned', 'omnigeo-ai-seo' ) ) . '</span>' : '<span class="aigs-tag aigs-tag--no">' . esc_html__( 'Absent', 'omnigeo-ai-seo' ) . '</span>'; ?><?php echo wp_kses_post( $chg ); ?></td>
						<td><?php echo (int) $r['cited'] ? '✓' : '—'; ?></td>
						<td><?php echo (int) $r['position'] ? '#' . (int) $r['position'] : '—'; ?></td>
						<td class="aigs-muted"><?php echo esc_html( $comps ? implode( ', ', $comps ) : '—' ); ?></td>
						<td>
						<?php
						$issues = (array) json_decode( isset( $r['issues'] ) ? (string) $r['issues'] : '', true );
						if ( $issues ) {
							$texts = array_map( array( 'AIGS_AI_Visibility', 'issue_text' ), $issues );
							echo '<span class="aigs-tag aigs-tag--warn" title="' . esc_attr( implode( "\n", $texts ) ) . '">';
							/* translators: %d: number of wrong facts. */
							echo esc_html( sprintf( _n( '%d wrong fact', '%d wrong facts', count( $issues ), 'omnigeo-ai-seo' ), count( $issues ) ) ) . '</span>';
						} elseif ( (int) $r['mentioned'] ) {
							echo '<span class="aigs-muted">✓</span>';
						} else {
							echo '<span class="aigs-muted">—</span>';
						}
						?>
						</td>
						<td class="act"><button type="button" class="aigs-btn aigs-btn--ghost aigs-btn--sm" data-aigs-answer="<?php echo (int) $r['id']; ?>"><?php self::i( 'eye', 15 ); ?><?php esc_html_e( 'Answer', 'omnigeo-ai-seo' ); ?></button></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			</div>
			<div id="aigs-answer-view"></div>
		</div>

		<?php
		$domains = AIGS_AI_Visibility::cited_domains( 90 );
		?>
		<div class="aigs-grid2">
			<div class="aigs-card">
				<div class="aigs-card__head"><?php self::i( 'link' ); ?><h2><?php esc_html_e( 'Sources assistants cited · 90 days', 'omnigeo-ai-seo' ); ?></h2></div>
				<p class="aigs-card__desc"><?php esc_html_e( 'Third-party sites that assistants rely on are where reviews, listings, comparisons and PR can make you visible. A citation here is not a promise that a mention there will change AI answers.', 'omnigeo-ai-seo' ); ?></p>
				<?php if ( $domains ) : ?>
					<table class="aigs-table"><tbody>
					<?php
					$kinds = array(
						'own'        => __( 'You', 'omnigeo-ai-seo' ),
						'competitor' => __( 'Competitor', 'omnigeo-ai-seo' ),
						'other'      => __( 'Opportunity', 'omnigeo-ai-seo' ),
					);
					foreach ( $domains as $d => $info ) :
						?>
						<tr><td><?php echo esc_html( $d ); ?></td><td><span class="aigs-badge aigs-badge--<?php echo esc_attr( $info['kind'] ); ?>"><?php echo esc_html( $kinds[ $info['kind'] ] ); ?></span></td><td class="num"><?php echo (int) $info['count']; ?></td></tr>
					<?php endforeach; ?>
					</tbody></table>
				<?php else : ?>
					<div class="aigs-empty"><p><?php esc_html_e( 'No sources yet. Web-grounded providers such as Perplexity return the sources they used.', 'omnigeo-ai-seo' ); ?></p></div>
				<?php endif; ?>
			</div>
			<div class="aigs-card">
				<div class="aigs-card__head"><?php self::i( 'activity' ); ?><h2><?php esc_html_e( 'History', 'omnigeo-ai-seo' ); ?></h2></div>
				<table class="aigs-table"><tbody>
				<?php foreach ( $batches as $b ) : ?>
					<tr>
						<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $b['t'] ) ); ?></td>
						<td class="aigs-muted">
						<?php
						/* translators: 1: mentions, 2: questions. */
						printf( esc_html__( 'mentioned in %1$d of %2$d', 'omnigeo-ai-seo' ), (int) $b['m'], (int) $b['n'] );
						?>
						</td>
						<td class="num"><?php echo (int) round( 100 * (int) $b['m'] / max( 1, (int) $b['n'] ) ); ?>%</td>
					</tr>
				<?php endforeach; ?>
				</tbody></table>
				<?php
				$gaps = 0;
				foreach ( AIGS_Findings::grouped() as $rule => $g ) {
					if ( 0 === strpos( $rule, 'vis_gap_' ) ) {
						$gaps += $g['count'];
					}
				}
				if ( $gaps ) :
					?>
					<p style="margin-top:14px"><a class="aigs-btn aigs-btn--sm" href="<?php echo esc_url( add_query_arg( 'rule', 'vis_gap', admin_url( 'admin.php?page=aigs-actions' ) ) ); ?>">
					<?php
					/* translators: %d: number of questions. */
					printf( esc_html( _n( '%d question where competitors win: see tasks', '%d questions where competitors win: see tasks', $gaps, 'omnigeo-ai-seo' ) ), (int) $gaps );
					?>
					</a></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/* ============================================================
	 * AI Traffic (Bot & Referral Intelligence)
	 * ============================================================ */

	/**
	 * AI Traffic page.
	 */
	public function page_traffic() {
		$t       = $this->modules['tracker'];
		$classes = $t->by_class( 30 );
		$ref     = $t->count( 'referral', 30 );
		$bots    = $t->by_bot( 30 );
		$ftop    = $t->top_urls( 8, 30, 'fetch' );
		$rbyp    = $t->by_platform( 'referral', 30 );
		$rsrc    = $t->referral_sources( 30 );
		$miss    = AIGS_Tracker::bot_404s( 30, 10 );
		$verify  = (bool) aigs_get( 'verify_bots', 0 );
		$labels  = array(
			'fetch'    => __( 'Read inside AI answers', 'omnigeo-ai-seo' ),
			'search'   => __( 'AI search indexing', 'omnigeo-ai-seo' ),
			'training' => __( 'Model training crawls', 'omnigeo-ai-seo' ),
		);

		$this->open( __( 'AI Traffic', 'omnigeo-ai-seo' ), __( 'Which AI systems read your site, and which people arrive from AI answers.', 'omnigeo-ai-seo' ) );
		?>
		<div class="aigs-callout"><?php self::i( 'info', 18 ); ?>
			<p><?php esc_html_e( 'Three separate layers, never added together: requests from bots that say they are AI bots (anyone can fake a user agent), bots whose identity was verified, and human visits that arrived from an AI product. A bot reading a page is not the same as your page being shown in an answer.', 'omnigeo-ai-seo' ); ?></p>
		</div>

		<?php $this->render_revenue(); ?>

		<div class="aigs-stats aigs-stats--4">
			<?php foreach ( array( 'fetch' => 'quote', 'search' => 'search', 'training' => 'bot' ) as $c => $ico ) : ?>
				<div class="aigs-stat">
					<div class="aigs-stat__top"><span class="aigs-stat__ico"><?php self::i( $ico, 18 ); ?></span>
					<?php if ( $verify && $classes[ $c ]['total'] ) : ?>
						<span class="aigs-badge aigs-badge--good">
						<?php
						/* translators: %d: verified percentage. */
						printf( esc_html__( '%d%% verified', 'omnigeo-ai-seo' ), (int) round( 100 * $classes[ $c ]['verified'] / max( 1, $classes[ $c ]['total'] - $classes[ $c ]['unverifiable'] ) ) );
						?>
						</span>
					<?php endif; ?>
					</div>
					<div class="aigs-stat__val"><?php echo (int) $classes[ $c ]['total']; ?></div>
					<div class="aigs-stat__label"><?php echo esc_html( $labels[ $c ] ); ?> · 30d</div>
				</div>
			<?php endforeach; ?>
			<div class="aigs-stat">
				<div class="aigs-stat__top"><span class="aigs-stat__ico"><?php self::i( 'arrow', 18 ); ?></span></div>
				<div class="aigs-stat__val"><?php echo (int) $ref; ?></div>
				<div class="aigs-stat__label"><?php esc_html_e( 'Visitors from AI answers · 30d', 'omnigeo-ai-seo' ); ?></div>
			</div>
		</div>

		<div class="aigs-card">
			<div class="aigs-card__head"><?php self::i( 'shield' ); ?><h2><?php esc_html_e( 'Bots by name', 'omnigeo-ai-seo' ); ?></h2></div>
			<?php if ( ! $verify ) : ?>
				<p class="aigs-card__desc">
				<?php
				printf(
					/* translators: %s: settings link. */
					wp_kses_post( __( 'Identity verification is off, so these are bots that claim the name. <a href="%s">Turn on verification</a> to confirm them with reverse DNS or the IP ranges OpenAI and Perplexity publish.', 'omnigeo-ai-seo' ) ),
					esc_url( admin_url( 'admin.php?page=aigs-settings#aigs-tracking' ) )
				);
				?>
				</p>
			<?php else : ?>
				<p class="aigs-card__desc"><?php esc_html_e( 'Verification runs hourly in the background. “Fake” means the IP does not belong to the company named in the user agent.', 'omnigeo-ai-seo' ); ?></p>
			<?php endif; ?>
			<?php if ( $bots ) : ?>
				<div class="aigs-tablewrap">
				<table class="aigs-table">
					<thead><tr><th><?php esc_html_e( 'Bot', 'omnigeo-ai-seo' ); ?></th><th><?php esc_html_e( 'Purpose', 'omnigeo-ai-seo' ); ?></th><th class="num"><?php esc_html_e( 'Requests', 'omnigeo-ai-seo' ); ?></th><?php if ( $verify ) : ?><th class="num"><?php esc_html_e( 'Verified', 'omnigeo-ai-seo' ); ?></th><th class="num"><?php esc_html_e( 'Fake', 'omnigeo-ai-seo' ); ?></th><?php endif; ?></tr></thead>
					<tbody>
					<?php foreach ( $bots as $b ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $b['bot'] ); ?></strong> <span class="aigs-muted"><?php echo esc_html( $b['platform'] ); ?></span></td>
							<td><?php echo esc_html( isset( $labels[ $b['bot_class'] ] ) ? $labels[ $b['bot_class'] ] : '—' ); ?></td>
							<td class="num"><?php echo (int) $b['c']; ?></td>
							<?php if ( $verify ) : ?>
								<td class="num"><?php echo (int) $b['v']; ?></td>
								<td class="num"><?php echo (int) $b['f'] ? '<span class="aigs-fail">' . (int) $b['f'] . '</span>' : '0'; ?></td>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				</div>
			<?php else : ?>
				<div class="aigs-empty"><?php self::i( 'bot', 30 ); ?><p><?php esc_html_e( 'No AI bots recorded yet.', 'omnigeo-ai-seo' ); ?></p></div>
			<?php endif; ?>
		</div>

		<div class="aigs-grid2">
			<div class="aigs-card">
				<div class="aigs-card__head"><?php self::i( 'quote' ); ?><h2><?php esc_html_e( 'Pages assistants fetched for users', 'omnigeo-ai-seo' ); ?></h2></div>
				<p class="aigs-card__desc"><?php esc_html_e( 'A person asked an assistant something and it opened these pages to answer. Make these pages excellent first.', 'omnigeo-ai-seo' ); ?></p>
				<?php if ( $ftop ) : ?>
					<table class="aigs-table"><tbody>
					<?php foreach ( $ftop as $row ) : ?>
						<tr><td class="url"><?php echo esc_html( $row['url'] ); ?></td><td class="num"><?php echo (int) $row['c']; ?></td></tr>
					<?php endforeach; ?>
					</tbody></table>
				<?php else : ?>
					<div class="aigs-empty"><p><?php esc_html_e( 'Nothing yet.', 'omnigeo-ai-seo' ); ?></p></div>
				<?php endif; ?>
			</div>
			<div class="aigs-card">
				<div class="aigs-card__head"><?php self::i( 'x' ); ?><h2><?php esc_html_e( 'Missing pages AI bots asked for', 'omnigeo-ai-seo' ); ?></h2></div>
				<p class="aigs-card__desc"><?php esc_html_e( 'These URLs returned “not found”. Redirect them to the closest live page.', 'omnigeo-ai-seo' ); ?></p>
				<?php if ( $miss ) : ?>
					<table class="aigs-table"><tbody>
					<?php foreach ( $miss as $u => $c ) : ?>
						<tr><td class="url"><?php echo esc_html( $u ); ?></td><td class="num"><?php echo (int) $c; ?></td></tr>
					<?php endforeach; ?>
					</tbody></table>
				<?php else : ?>
					<div class="aigs-empty"><p><?php esc_html_e( 'No missing pages requested. Good.', 'omnigeo-ai-seo' ); ?></p></div>
				<?php endif; ?>
			</div>
		</div>

		<div class="aigs-card">
			<div class="aigs-card__head"><?php self::i( 'arrow' ); ?><h2><?php esc_html_e( 'Visitors from AI answers', 'omnigeo-ai-seo' ); ?></h2></div>
			<p class="aigs-card__desc"><?php esc_html_e( 'Detected from the referring site or from the utm_source tag some assistants add to links (for example utm_source=chatgpt.com). Apps and Google AI Mode often send no referrer, so this is a floor, not the full number.', 'omnigeo-ai-seo' ); ?></p>
			<?php if ( $rbyp ) : ?>
				<table class="aigs-table"><tbody>
				<?php foreach ( $rbyp as $plat => $n ) : ?>
					<tr><td><?php echo esc_html( $plat ); ?></td><td class="num"><?php echo (int) $n; ?></td></tr>
				<?php endforeach; ?>
				</tbody></table>
				<p class="aigs-muted aigs-small">
				<?php
				/* translators: 1: count via referrer, 2: count via utm. */
				printf( esc_html__( 'Detected by referrer: %1$d · by utm_source: %2$d', 'omnigeo-ai-seo' ), isset( $rsrc['referrer'] ) ? (int) $rsrc['referrer'] : 0, isset( $rsrc['utm'] ) ? (int) $rsrc['utm'] : 0 );
				?>
				</p>
			<?php else : ?>
				<div class="aigs-empty"><p><?php esc_html_e( 'No AI referral visits yet.', 'omnigeo-ai-seo' ); ?></p></div>
			<?php endif; ?>
		</div>

		<div class="aigs-card">
			<div class="aigs-card__head"><?php self::i( 'activity' ); ?><h2><?php esc_html_e( 'Track AI clicks in GA4', 'omnigeo-ai-seo' ); ?></h2></div>
			<p class="aigs-card__desc"><?php esc_html_e( 'GA4 has no built-in AI channel, so AI clicks get mislabelled as Referral or Direct. Create a custom channel group with this source regex to isolate them.', 'omnigeo-ai-seo' ); ?></p>
			<p><code class="aigs-mono" id="aigs-regex" style="display:block;white-space:pre-wrap;word-break:break-all;padding:12px"><?php echo esc_html( AIGS_Tracker::ga4_regex() ); ?></code></p>
			<p><button type="button" class="aigs-btn" data-aigs-copy="#aigs-regex"><?php self::i( 'arrow', 16 ); ?><?php esc_html_e( 'Copy regex', 'omnigeo-ai-seo' ); ?></button></p>
			<ol class="aigs-steps">
				<li><?php esc_html_e( 'In GA4, open Admin → Data display → Channel groups.', 'omnigeo-ai-seo' ); ?></li>
				<li><?php esc_html_e( 'Create a new channel group and add a channel named “AI”.', 'omnigeo-ai-seo' ); ?></li>
				<li><?php esc_html_e( 'Set the condition: Source matches regex → paste the regex above.', 'omnigeo-ai-seo' ); ?></li>
				<li><?php esc_html_e( 'Move the AI channel above Referral so it is matched first, then save.', 'omnigeo-ai-seo' ); ?></li>
			</ol>
		</div>
		<?php
		$this->close();
	}

	/**
	 * Money formatted in the store currency (plain text).
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	private static function money( $amount ) {
		if ( function_exists( 'wc_price' ) ) {
			return html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' );
		}
		return number_format_i18n( $amount, 2 );
	}

	/**
	 * AI revenue attribution card (WooCommerce).
	 *
	 * @param bool $compact Short version for the Growth Center.
	 */
	private function render_revenue( $compact = false ) {
		if ( ! AIGS_WooCommerce::active() ) {
			return;
		}
		$status = AIGS_Revenue::status();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- view switch only.
		$days   = ( ! $compact && isset( $_GET['rev'] ) && '90' === $_GET['rev'] ) ? 90 : 30;
		echo '<div class="aigs-card aigs-revenue" id="aigs-revenue">';
		echo '<div class="aigs-card__head">' . wp_kses( self::icon( 'cart', 17 ), self::svg_kses() ) . '<h2>' . esc_html__( 'Revenue from AI assistants', 'omnigeo-ai-seo' ) . '</h2>';
		if ( ! $compact && 'ok' === $status ) {
			$base = admin_url( 'admin.php?page=aigs-traffic' );
			echo '<span class="aigs-seg">';
			echo '<a class="' . ( 30 === $days ? 'is-active' : '' ) . '" href="' . esc_url( $base . '#aigs-revenue' ) . '">' . esc_html__( '30 days', 'omnigeo-ai-seo' ) . '</a>';
			echo '<a class="' . ( 90 === $days ? 'is-active' : '' ) . '" href="' . esc_url( add_query_arg( 'rev', '90', $base ) . '#aigs-revenue' ) . '">' . esc_html__( '90 days', 'omnigeo-ai-seo' ) . '</a>';
			echo '</span>';
		}
		echo '</div>';

		if ( 'disabled' === $status ) {
			echo '<p class="aigs-card__desc">' . esc_html__( 'WooCommerce Order Attribution is turned off, so orders do not record where the customer came from. Turn it on under WooCommerce → Settings → Advanced → Features to see revenue from ChatGPT, Perplexity, Gemini and other assistants.', 'omnigeo-ai-seo' ) . '</p></div>';
			return;
		}
		if ( 'ok' !== $status ) {
			echo '</div>';
			return;
		}
		$r = AIGS_Revenue::report( $days );
		if ( ! $r ) {
			echo '</div>';
			return;
		}
		if ( ! $r['ai_orders'] ) {
			echo '<div class="aigs-empty">' . wp_kses( self::icon( 'cart', 30 ), self::svg_kses() ) . '<p>';
			/* translators: %d: days. */
			echo esc_html( sprintf( __( 'No orders from AI assistants in the last %d days yet. Orders are attributed from the referrer or utm_source WooCommerce records at checkout.', 'omnigeo-ai-seo' ), $days ) );
			echo '</p></div></div>';
			return;
		}
		?>
		<div class="aigs-kpis aigs-kpis--4">
			<div><span><?php echo esc_html( self::money( $r['ai_revenue'] ) ); ?></span><em>
			<?php
			/* translators: %s: share of total revenue. */
			echo esc_html( sprintf( __( 'AI revenue · %s%% of sales', 'omnigeo-ai-seo' ), number_format_i18n( $r['revenue_share'], 1 ) ) );
			?>
			</em></div>
			<div><span><?php echo (int) $r['ai_orders']; ?></span><em>
			<?php
			/* translators: %s: share of orders. */
			echo esc_html( sprintf( __( 'AI orders · %s%% of orders', 'omnigeo-ai-seo' ), number_format_i18n( $r['share'], 1 ) ) );
			?>
			</em></div>
			<div><span><?php echo esc_html( self::money( $r['ai_aov'] ) ); ?></span><em>
			<?php
			/* translators: %s: average order value of other channels. */
			echo esc_html( sprintf( __( 'AI order value (others: %s)', 'omnigeo-ai-seo' ), self::money( $r['other_aov'] ) ) );
			?>
			</em></div>
			<?php if ( null !== $r['conversion'] && ! $compact ) : ?>
				<div><span><?php echo esc_html( number_format_i18n( $r['conversion'], 1 ) ); ?>%</span><em><?php esc_html_e( 'approx. conversion of AI visits', 'omnigeo-ai-seo' ); ?></em></div>
			<?php endif; ?>
		</div>
		<?php
		if ( $compact ) {
			echo '<p><a class="aigs-btn aigs-btn--sm" href="' . esc_url( admin_url( 'admin.php?page=aigs-traffic#aigs-revenue' ) ) . '">' . esc_html__( 'See the breakdown', 'omnigeo-ai-seo' ) . '</a></p></div>';
			return;
		}
		?>
		<div class="aigs-grid3">
			<div>
				<h3 class="aigs-h3"><?php esc_html_e( 'By assistant', 'omnigeo-ai-seo' ); ?></h3>
				<table class="aigs-table"><tbody>
				<?php foreach ( $r['by_platform'] as $plat => $v ) : ?>
					<tr><td><?php echo esc_html( $plat ); ?></td><td class="aigs-muted"><?php echo (int) $v['orders']; ?></td><td class="num"><?php echo esc_html( self::money( $v['revenue'] ) ); ?></td></tr>
				<?php endforeach; ?>
				</tbody></table>
			</div>
			<div>
				<h3 class="aigs-h3"><?php esc_html_e( 'Landing pages that sold', 'omnigeo-ai-seo' ); ?></h3>
				<table class="aigs-table"><tbody>
				<?php foreach ( $r['landing'] as $path => $n ) : ?>
					<tr><td class="url" dir="ltr"><?php echo esc_html( $path ); ?></td><td class="num"><?php echo (int) $n; ?></td></tr>
				<?php endforeach; ?>
				<?php if ( ! $r['landing'] ) : ?>
					<tr><td class="aigs-muted"><?php esc_html_e( 'Not recorded', 'omnigeo-ai-seo' ); ?></td></tr>
				<?php endif; ?>
				</tbody></table>
			</div>
			<div>
				<h3 class="aigs-h3"><?php esc_html_e( 'Products bought', 'omnigeo-ai-seo' ); ?></h3>
				<table class="aigs-table"><tbody>
				<?php foreach ( $r['products'] as $pid => $qty ) : ?>
					<tr><td dir="auto"><?php echo esc_html( get_the_title( (int) $pid ) ); ?></td><td class="num"><?php echo (int) $qty; ?></td></tr>
				<?php endforeach; ?>
				</tbody></table>
			</div>
		</div>
		<p class="aigs-muted aigs-small">
		<?php
		/* translators: %s: time ago. */
		echo esc_html( sprintf( __( 'Paid orders, refunds subtracted. Based on the traffic source WooCommerce records on each order; apps and some assistants send no referrer, so this is a minimum. Updated %s.', 'omnigeo-ai-seo' ), self::ago( (int) $r['time'] ) ) );
		?>
		<button type="button" class="button-link" id="aigs-revenue-refresh"><?php esc_html_e( 'Refresh now', 'omnigeo-ai-seo' ); ?></button></p>
		</div>
		<?php
	}

	/* ============================================================
	 * URL Audit
	 * ============================================================ */

	/**
	 * URL Audit page.
	 */
	public function page_audit() {
		$this->open( __( 'URL Audit', 'omnigeo-ai-seo' ), __( 'Check the live HTML of any one page: indexability, canonical, metadata, structure and AI crawler access.', 'omnigeo-ai-seo' ) );
		?>
		<div class="aigs-callout"><?php self::i( 'info', 18 ); ?>
			<p>
			<?php
			printf(
				/* translators: %s: Action Center link. */
				wp_kses_post( __( 'For your whole site, use the <a href="%s">Action Center</a>. This tool fetches one URL exactly as a crawler sees it, which is useful for checking theme output and other sites.', 'omnigeo-ai-seo' ) ),
				esc_url( admin_url( 'admin.php?page=aigs-actions' ) )
			);
			?>
			</p>
		</div>
		<div class="aigs-card">
			<div class="aigs-inputgroup">
				<input type="url" id="aigs-audit-url" class="aigs-input" value="<?php echo esc_url( home_url( '/' ) ); ?>" placeholder="https://example.com/page" />
				<button class="aigs-btn aigs-btn--primary" id="aigs-run-audit"><?php self::i( 'gauge', 16 ); ?><?php esc_html_e( 'Run audit', 'omnigeo-ai-seo' ); ?></button>
			</div>
		</div>
		<div id="aigs-audit-result"></div>
		<?php
		$this->close();
	}

	/* ============================================================
	 * Site Health
	 * ============================================================ */

	/**
	 * Site Health page.
	 */
	public function page_visibility() {
		$key    = aigs_get( 'indexnow_key', '' );
		$action = '<button class="aigs-btn aigs-btn--primary" id="aigs-run-health">' . wp_kses( self::icon( 'shield', 16 ), self::svg_kses() ) . esc_html__( 'Run checks', 'omnigeo-ai-seo' ) . '</button>';
		$this->open( __( 'Site Health', 'omnigeo-ai-seo' ), __( 'Technical signals that decide whether search engines and AI can read you.', 'omnigeo-ai-seo' ), $action );
		?>
		<div id="aigs-health-result">
			<div class="aigs-card"><div class="aigs-empty"><?php self::i( 'shield', 30 ); ?><p><?php esc_html_e( 'Run the checks to see HTTPS, robots, sitemap, llms.txt, indexing and freshness status.', 'omnigeo-ai-seo' ); ?></p></div></div>
		</div>

		<div class="aigs-card">
			<div class="aigs-card__head"><?php self::i( 'zap' ); ?><h2><?php esc_html_e( 'IndexNow', 'omnigeo-ai-seo' ); ?></h2></div>
			<p class="aigs-card__desc"><?php esc_html_e( 'Notify Bing and other IndexNow engines when a URL changes. Bing’s index also feeds Microsoft Copilot.', 'omnigeo-ai-seo' ); ?></p>
			<p><span class="aigs-mono"><?php echo esc_html( home_url( '/' . $key . '.txt' ) ); ?></span></p>
			<p class="aigs-muted aigs-small">
				<?php echo aigs_get( 'indexnow_auto', 0 ) ? esc_html__( 'Automatic pings on publish and update are on.', 'omnigeo-ai-seo' ) : esc_html__( 'Tip: turn on automatic pings in Settings so new and updated content is submitted for you.', 'omnigeo-ai-seo' ); ?>
			</p>
			<div class="aigs-inputgroup" style="margin-top:10px">
				<input type="url" id="aigs-indexnow-url" class="aigs-input" value="<?php echo esc_url( home_url( '/' ) ); ?>" />
				<button class="aigs-btn" id="aigs-submit-indexnow"><?php self::i( 'arrow', 16 ); ?><?php esc_html_e( 'Submit URL', 'omnigeo-ai-seo' ); ?></button>
			</div>
		</div>
		<?php
		$this->close();
	}

	/* ============================================================
	 * Settings
	 * ============================================================ */

	/**
	 * Settings page.
	 */
	public function page_settings() {
		$provs     = AIGS_AI_Client::providers();
		$has_key   = '' !== (string) aigs_get( 'ai_api_key', '' );
		$const_key = defined( 'AIGS_AI_API_KEY' ) && '' !== (string) AIGS_AI_API_KEY;
		$this->open( __( 'Settings', 'omnigeo-ai-seo' ), __( 'Brand profile, crawler access, automation and your AI provider.', 'omnigeo-ai-seo' ) );
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'aigs_settings_group' ); ?>
			<input type="hidden" name="aigs_settings[section_settings]" value="1" />

			<div class="aigs-card">
				<div class="aigs-card__head"><?php self::i( 'info' ); ?><h2><?php esc_html_e( 'Brand profile', 'omnigeo-ai-seo' ); ?></h2></div>
				<table class="form-table" role="presentation">
					<tr><th><label for="aigs_bn"><?php esc_html_e( 'Business / brand name', 'omnigeo-ai-seo' ); ?></label></th>
						<td><input type="text" id="aigs_bn" class="regular-text" name="aigs_settings[business_name]" value="<?php echo esc_attr( aigs_get( 'business_name', '' ) ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" /></td></tr>
					<tr><th><label for="aigs_ind"><?php esc_html_e( 'Industry', 'omnigeo-ai-seo' ); ?></label></th>
						<td><input type="text" id="aigs_ind" class="regular-text" name="aigs_settings[industry]" value="<?php echo esc_attr( aigs_get( 'industry', '' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. dental clinic', 'omnigeo-ai-seo' ); ?>" /></td></tr>
					<tr><th><label for="aigs_loc"><?php esc_html_e( 'Location', 'omnigeo-ai-seo' ); ?></label></th>
						<td><input type="text" id="aigs_loc" class="regular-text" name="aigs_settings[location]" value="<?php echo esc_attr( aigs_get( 'location', '' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. Berlin', 'omnigeo-ai-seo' ); ?>" /></td></tr>
					<tr><th><label for="aigs_btype"><?php esc_html_e( 'Type of business', 'omnigeo-ai-seo' ); ?></label></th><td>
						<select id="aigs_btype" name="aigs_settings[business_type]">
							<?php
							$types   = AIGS_Entity::business_types();
							$current = AIGS_Entity::schema_type();
							$groups  = array(
								'org'   => __( 'Organization', 'omnigeo-ai-seo' ),
								'local' => __( 'Local business (has a place customers visit or a service area)', 'omnigeo-ai-seo' ),
							);
							foreach ( $groups as $g => $glabel ) {
								echo '<optgroup label="' . esc_attr( $glabel ) . '">';
								foreach ( $types[ $g ] as $val => $label ) {
									printf( '<option value="%s" data-local="%d" %s>%s</option>', esc_attr( $val ), 'local' === $g ? 1 : 0, selected( $current, $val, false ), esc_html( $label ) );
								}
								echo '</optgroup>';
							}
							?>
						</select>
						<p class="description"><?php esc_html_e( 'Used as the schema.org type of your brand. Local types add address, opening hours and map data.', 'omnigeo-ai-seo' ); ?></p>
					</td></tr>
					<tr><th><label for="aigs_phone"><?php esc_html_e( 'Main phone', 'omnigeo-ai-seo' ); ?></label></th>
						<td><input type="tel" id="aigs_phone" class="regular-text" name="aigs_settings[biz_phone]" value="<?php echo esc_attr( aigs_get( 'biz_phone', '' ) ); ?>" placeholder="+1 555 010 0100" />
						<p class="description"><?php esc_html_e( 'International format with country code works best.', 'omnigeo-ai-seo' ); ?></p></td></tr>
					<tr><th><label for="aigs_email"><?php esc_html_e( 'Public email', 'omnigeo-ai-seo' ); ?></label></th>
						<td><input type="email" id="aigs_email" class="regular-text" name="aigs_settings[biz_email]" value="<?php echo esc_attr( aigs_get( 'biz_email', '' ) ); ?>" /></td></tr>
				</table>
			</div>

			<div class="aigs-card aigs-local-only" id="aigs-local">
				<div class="aigs-card__head"><?php self::i( 'globe' ); ?><h2><?php esc_html_e( 'Local business details', 'omnigeo-ai-seo' ); ?></h2></div>
				<p class="aigs-card__desc"><?php esc_html_e( 'Printed as LocalBusiness schema on your homepage. Use exactly the same name, address and phone as on your Google Business Profile, Apple Maps, Bing Places and directories: consistency is what assistants trust.', 'omnigeo-ai-seo' ); ?></p>
				<?php if ( AIGS_SEO::outputs_schema() ) : ?>
					<p class="aigs-card__desc">
					<?php
					/* translators: %s: SEO plugin name. */
					printf( esc_html__( '%s outputs your main schema graph; OmniGEO adds a separate LocalBusiness node on the homepage only. If you use a local SEO add-on that already does this, leave these fields empty.', 'omnigeo-ai-seo' ), esc_html( AIGS_SEO::name() ) );
					?>
					</p>
				<?php endif; ?>
				<table class="form-table" role="presentation">
					<tr><th><label for="aigs_street"><?php esc_html_e( 'Street address', 'omnigeo-ai-seo' ); ?></label></th>
						<td><input type="text" id="aigs_street" class="regular-text" name="aigs_settings[biz_street]" value="<?php echo esc_attr( aigs_get( 'biz_street', '' ) ); ?>" /></td></tr>
					<tr><th><label for="aigs_city"><?php esc_html_e( 'City', 'omnigeo-ai-seo' ); ?></label></th>
						<td><input type="text" id="aigs_city" class="regular-text" name="aigs_settings[biz_city]" value="<?php echo esc_attr( aigs_get( 'biz_city', '' ) ); ?>" /></td></tr>
					<tr><th><label for="aigs_region"><?php esc_html_e( 'State / region', 'omnigeo-ai-seo' ); ?></label></th>
						<td><input type="text" id="aigs_region" class="regular-text" name="aigs_settings[biz_region]" value="<?php echo esc_attr( aigs_get( 'biz_region', '' ) ); ?>" /></td></tr>
					<tr><th><label for="aigs_postal"><?php esc_html_e( 'Postal code', 'omnigeo-ai-seo' ); ?></label></th>
						<td><input type="text" id="aigs_postal" class="small-text" style="width:10em" name="aigs_settings[biz_postal]" value="<?php echo esc_attr( aigs_get( 'biz_postal', '' ) ); ?>" /></td></tr>
					<tr><th><label for="aigs_country"><?php esc_html_e( 'Country code', 'omnigeo-ai-seo' ); ?></label></th>
						<td><input type="text" id="aigs_country" class="small-text" maxlength="40" name="aigs_settings[biz_country]" value="<?php echo esc_attr( aigs_get( 'biz_country', '' ) ); ?>" placeholder="US" />
						<p class="description"><?php esc_html_e( 'Two-letter ISO code, for example US, GB, DE, AE.', 'omnigeo-ai-seo' ); ?></p></td></tr>
					<tr><th><?php esc_html_e( 'Opening hours', 'omnigeo-ai-seo' ); ?></th><td>
						<div class="aigs-hours">
						<?php
						$hours = (array) aigs_get( 'biz_hours', array() );
						$names = array(
							'mo' => __( 'Monday', 'omnigeo-ai-seo' ),
							'tu' => __( 'Tuesday', 'omnigeo-ai-seo' ),
							'we' => __( 'Wednesday', 'omnigeo-ai-seo' ),
							'th' => __( 'Thursday', 'omnigeo-ai-seo' ),
							'fr' => __( 'Friday', 'omnigeo-ai-seo' ),
							'sa' => __( 'Saturday', 'omnigeo-ai-seo' ),
							'su' => __( 'Sunday', 'omnigeo-ai-seo' ),
						);
						foreach ( $names as $k => $label ) {
							printf(
								'<label><span>%1$s</span><input type="text" name="aigs_settings[biz_hours][%2$s]" value="%3$s" placeholder="09:00-17:00" dir="ltr" /></label>',
								esc_html( $label ),
								esc_attr( $k ),
								esc_attr( isset( $hours[ $k ] ) ? $hours[ $k ] : '' )
							);
						}
						?>
						</div>
						<p class="description"><?php esc_html_e( 'One or more ranges per day, for example 09:00-13:00, 14:00-18:00. Type 24h for always open. Leave empty when closed.', 'omnigeo-ai-seo' ); ?></p>
					</td></tr>
					<tr><th><label for="aigs_area"><?php esc_html_e( 'Service area', 'omnigeo-ai-seo' ); ?></label></th>
						<td><input type="text" id="aigs_area" class="regular-text" name="aigs_settings[biz_area]" value="<?php echo esc_attr( aigs_get( 'biz_area', '' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. Brooklyn, Queens, Manhattan', 'omnigeo-ai-seo' ); ?>" />
						<p class="description"><?php esc_html_e( 'Cities or regions you serve, comma separated. Important for service businesses without a storefront.', 'omnigeo-ai-seo' ); ?></p></td></tr>
					<tr><th><label for="aigs_price"><?php esc_html_e( 'Price range', 'omnigeo-ai-seo' ); ?></label></th>
						<td><input type="text" id="aigs_price" class="small-text" style="width:10em" name="aigs_settings[biz_price]" value="<?php echo esc_attr( aigs_get( 'biz_price', '' ) ); ?>" placeholder="$$" /></td></tr>
					<tr><th><label for="aigs_map"><?php esc_html_e( 'Map link', 'omnigeo-ai-seo' ); ?></label></th>
						<td><input type="url" id="aigs_map" class="regular-text" name="aigs_settings[biz_map]" value="<?php echo esc_attr( aigs_get( 'biz_map', '' ) ); ?>" placeholder="https://maps.google.com/?cid=…" /></td></tr>
					<tr><th><?php esc_html_e( 'Coordinates', 'omnigeo-ai-seo' ); ?></th>
						<td><input type="text" class="small-text" style="width:9em" name="aigs_settings[biz_lat]" value="<?php echo esc_attr( aigs_get( 'biz_lat', '' ) ); ?>" placeholder="<?php esc_attr_e( 'Latitude', 'omnigeo-ai-seo' ); ?>" dir="ltr" />
						<input type="text" class="small-text" style="width:9em" name="aigs_settings[biz_lng]" value="<?php echo esc_attr( aigs_get( 'biz_lng', '' ) ); ?>" placeholder="<?php esc_attr_e( 'Longitude', 'omnigeo-ai-seo' ); ?>" dir="ltr" />
						<p class="description"><?php esc_html_e( 'Optional. Right-click your location in a map app to copy them.', 'omnigeo-ai-seo' ); ?></p></td></tr>
				</table>
			</div>

			<div class="aigs-card" id="aigs-entity">
				<div class="aigs-card__head"><?php self::i( 'globe' ); ?><h2><?php esc_html_e( 'Organization entity (Schema)', 'omnigeo-ai-seo' ); ?></h2></div>
				<p class="aigs-card__desc"><?php esc_html_e( 'Site-wide Organization schema so engines can resolve your brand identity. The sameAs links to official profiles are the most valuable part.', 'omnigeo-ai-seo' ); ?></p>
				<table class="form-table" role="presentation">
					<tr><th><label for="aigs_logo"><?php esc_html_e( 'Logo URL', 'omnigeo-ai-seo' ); ?></label></th>
						<td><input type="url" id="aigs_logo" class="regular-text" name="aigs_settings[org_logo]" value="<?php echo esc_attr( aigs_get( 'org_logo', '' ) ); ?>" placeholder="https://example.com/logo.png" /></td></tr>
					<tr><th><label for="aigs_founded"><?php esc_html_e( 'Founding year', 'omnigeo-ai-seo' ); ?></label></th>
						<td><input type="text" id="aigs_founded" class="regular-text" name="aigs_settings[org_founded]" value="<?php echo esc_attr( aigs_get( 'org_founded', '' ) ); ?>" placeholder="2019" /></td></tr>
					<tr><th><label for="aigs_sameas"><?php esc_html_e( 'sameAs profile URLs', 'omnigeo-ai-seo' ); ?></label></th>
						<td><textarea id="aigs_sameas" name="aigs_settings[org_sameas]" rows="4" class="large-text aigs-code" placeholder="https://www.linkedin.com/company/…&#10;https://www.instagram.com/…&#10;https://www.wikidata.org/wiki/Q…"><?php echo esc_textarea( aigs_get( 'org_sameas', '' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One URL per line: official social profiles, LinkedIn, Wikipedia/Wikidata if you have them, business directories.', 'omnigeo-ai-seo' ); ?></p></td></tr>
				</table>
			</div>

			<div class="aigs-card">
				<div class="aigs-card__head"><?php self::i( 'sliders' ); ?><h2><?php esc_html_e( 'Output & automation', 'omnigeo-ai-seo' ); ?></h2></div>
				<div class="aigs-fieldrow"><label><input type="checkbox" name="aigs_settings[enable_schema]" value="1" <?php checked( aigs_get( 'enable_schema', 1 ), 1 ); ?> /> <?php esc_html_e( 'Output Schema / JSON-LD on the front end', 'omnigeo-ai-seo' ); ?></label></div>
				<div class="aigs-fieldrow"><label><input type="checkbox" name="aigs_settings[indexnow_auto]" value="1" <?php checked( aigs_get( 'indexnow_auto', 0 ), 1 ); ?> /> <?php esc_html_e( 'Ping IndexNow automatically when content is published or updated', 'omnigeo-ai-seo' ); ?></label></div>
				<table class="form-table" role="presentation">
					<tr><th><label for="aigs_ssched"><?php esc_html_e( 'Background site scan', 'omnigeo-ai-seo' ); ?></label></th><td>
						<select id="aigs_ssched" name="aigs_settings[scan_schedule]">
							<option value="weekly" <?php selected( aigs_get( 'scan_schedule', 'weekly' ), 'weekly' ); ?>><?php esc_html_e( 'Weekly (recommended)', 'omnigeo-ai-seo' ); ?></option>
							<option value="off" <?php selected( aigs_get( 'scan_schedule', 'weekly' ), 'off' ); ?>><?php esc_html_e( 'Off, I will scan manually', 'omnigeo-ai-seo' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Runs in small batches and skips unchanged pages, so it stays light on shared hosting.', 'omnigeo-ai-seo' ); ?></p>
					</td></tr>
					<tr><th><?php esc_html_e( 'Weekly email', 'omnigeo-ai-seo' ); ?></th><td>
						<label><input type="checkbox" name="aigs_settings[digest_email]" value="1" <?php checked( aigs_get( 'digest_email', 0 ), 1 ); ?> /> <?php esc_html_e( 'Email me a short progress report after the weekly scan', 'omnigeo-ai-seo' ); ?></label>
						<p><input type="email" class="regular-text" name="aigs_settings[digest_to]" value="<?php echo esc_attr( aigs_get( 'digest_to', '' ) ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" /></p>
					</td></tr>
				</table>
			</div>

			<div class="aigs-card">
				<div class="aigs-card__head"><?php self::i( 'link' ); ?><h2><?php esc_html_e( 'SEO plugin integration', 'omnigeo-ai-seo' ); ?></h2></div>
				<?php $seo = AIGS_SEO::name(); ?>
				<?php if ( $seo ) : ?>
					<p class="aigs-card__desc">
					<?php
					/* translators: %s: plugin name. */
					printf( esc_html__( 'Detected: %s. Its focus keyword, titles, descriptions, noindex and canonical settings are used by the scanner.', 'omnigeo-ai-seo' ), '<strong>' . esc_html( $seo ) . '</strong>' );
					?>
					</p>
				<?php else : ?>
					<p class="aigs-card__desc"><?php esc_html_e( 'No supported SEO plugin detected (Yoast, Rank Math, SEOPress, All in One SEO).', 'omnigeo-ai-seo' ); ?></p>
				<?php endif; ?>
				<table class="form-table" role="presentation">
					<tr><th><?php esc_html_e( 'Duplicate schema', 'omnigeo-ai-seo' ); ?></th><td>
						<select name="aigs_settings[dedupe_schema]">
							<option value="auto" <?php selected( aigs_get( 'dedupe_schema', 'auto' ), 'auto' ); ?>><?php esc_html_e( 'Auto, skip our entity schema when an SEO plugin provides it (recommended)', 'omnigeo-ai-seo' ); ?></option>
							<option value="off" <?php selected( aigs_get( 'dedupe_schema', 'auto' ), 'off' ); ?>><?php esc_html_e( 'Always output our entity schema', 'omnigeo-ai-seo' ); ?></option>
						</select>
					</td></tr>
				</table>
			</div>

			<?php if ( AIGS_WooCommerce::active() ) : ?>
			<div class="aigs-card">
				<div class="aigs-card__head"><?php self::i( 'cart' ); ?><h2><?php esc_html_e( 'WooCommerce', 'omnigeo-ai-seo' ); ?></h2></div>
				<p class="aigs-card__desc"><?php esc_html_e( 'On product pages OmniGEO defers to WooCommerce’s native Product schema to avoid conflicts.', 'omnigeo-ai-seo' ); ?></p>
				<input type="hidden" name="aigs_settings[woo_present]" value="1" />
				<div class="aigs-fieldrow"><label><input type="checkbox" name="aigs_settings[enhance_woo]" value="1" <?php checked( aigs_get( 'enhance_woo', 'on' ), 'on' ); ?> /> <?php esc_html_e( 'Add the brand to WooCommerce Product schema when it has none', 'omnigeo-ai-seo' ); ?></label></div>
			</div>
			<?php endif; ?>

			<div class="aigs-card">
				<div class="aigs-card__head"><?php self::i( 'shield' ); ?><h2><?php esc_html_e( 'AI crawler access (robots.txt)', 'omnigeo-ai-seo' ); ?></h2></div>
				<p class="aigs-card__desc"><?php esc_html_e( 'Which AI crawlers may read your site, per crawler, plus Content Signals and your own robots.txt lines.', 'omnigeo-ai-seo' ); ?></p>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=aigs-toolbox&tab=ai' ) ); ?>"><?php esc_html_e( 'Open in Toolbox', 'omnigeo-ai-seo' ); ?></a>
			</div>

			<div class="aigs-card" id="aigs-tracking">
				<div class="aigs-card__head"><?php self::i( 'bot' ); ?><h2><?php esc_html_e( 'AI traffic tracking', 'omnigeo-ai-seo' ); ?></h2></div>
				<div class="aigs-fieldrow"><label><input type="checkbox" name="aigs_settings[enable_tracking]" value="1" <?php checked( aigs_get( 'enable_tracking', 1 ), 1 ); ?> /> <?php esc_html_e( 'Record AI bot requests and visits from AI products', 'omnigeo-ai-seo' ); ?></label></div>
				<div class="aigs-fieldrow"><label><input type="checkbox" name="aigs_settings[verify_bots]" value="1" <?php checked( aigs_get( 'verify_bots', 0 ), 1 ); ?> /> <?php esc_html_e( 'Verify bot identity in the background (reverse DNS, and the public IP lists of OpenAI and Perplexity)', 'omnigeo-ai-seo' ); ?></label></div>
				<table class="form-table" role="presentation">
					<tr><th><label for="aigs_iph"><?php esc_html_e( 'Visitor IP comes from', 'omnigeo-ai-seo' ); ?></label></th><td>
						<select id="aigs_iph" name="aigs_settings[ip_header]">
							<?php
							$opts = array(
								'REMOTE_ADDR'           => __( 'Direct connection (default)', 'omnigeo-ai-seo' ),
								'HTTP_CF_CONNECTING_IP' => __( 'Cloudflare (CF-Connecting-IP)', 'omnigeo-ai-seo' ),
								'HTTP_X_FORWARDED_FOR'  => __( 'Proxy / load balancer (X-Forwarded-For)', 'omnigeo-ai-seo' ),
								'HTTP_X_REAL_IP'        => __( 'Proxy (X-Real-IP)', 'omnigeo-ai-seo' ),
							);
							foreach ( $opts as $v => $l ) {
								printf( '<option value="%s" %s>%s</option>', esc_attr( $v ), selected( aigs_get( 'ip_header', 'REMOTE_ADDR' ), $v, false ), esc_html( $l ) );
							}
							?>
						</select>
						<p class="description"><?php esc_html_e( 'Only change this if your site is behind a CDN or proxy. IPs are stored only for bot requests, never for human visitors.', 'omnigeo-ai-seo' ); ?></p>
					</td></tr>
					<tr><th><label for="aigs_ret"><?php esc_html_e( 'Keep detailed logs for', 'omnigeo-ai-seo' ); ?></label></th><td>
						<input type="number" min="7" max="730" id="aigs_ret" class="small-text" name="aigs_settings[retention_days]" value="<?php echo (int) aigs_get( 'retention_days', 90 ); ?>" /> <?php esc_html_e( 'days', 'omnigeo-ai-seo' ); ?>
						<p class="description"><?php esc_html_e( 'Older entries are summarised into daily totals and deleted, keeping the database small.', 'omnigeo-ai-seo' ); ?></p>
					</td></tr>
				</table>
			</div>

			<div class="aigs-card" id="aigs-ai">
				<div class="aigs-card__head"><?php self::i( 'radar' ); ?><h2><?php esc_html_e( 'AI provider (your own key)', 'omnigeo-ai-seo' ); ?></h2></div>
				<p class="aigs-card__desc"><?php esc_html_e( 'Used only for features you trigger or schedule: AI visibility checks, answer drafts and product summaries. Pick any provider you already pay for, or a local model.', 'omnigeo-ai-seo' ); ?></p>
				<table class="form-table" role="presentation">
					<tr><th><label for="aigs_prov"><?php esc_html_e( 'Provider', 'omnigeo-ai-seo' ); ?></label></th><td>
						<select id="aigs_prov" name="aigs_settings[ai_provider]">
							<?php foreach ( $provs as $k => $p ) : ?>
								<option value="<?php echo esc_attr( $k ); ?>" data-model="<?php echo esc_attr( $p['model'] ); ?>" data-keys="<?php echo esc_url( $p['keys'] ); ?>" <?php selected( AIGS_AI_Client::provider(), $k ); ?>><?php echo esc_html( $p['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description" id="aigs-keys-link-wrap"><a id="aigs-keys-link" href="<?php echo esc_url( $provs[ AIGS_AI_Client::provider() ]['keys'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Get an API key', 'omnigeo-ai-seo' ); ?> ↗</a></p>
					</td></tr>
					<tr class="aigs-custom-only"><th><label for="aigs_base"><?php esc_html_e( 'Endpoint URL', 'omnigeo-ai-seo' ); ?></label></th><td>
						<input type="url" id="aigs_base" class="regular-text" name="aigs_settings[ai_base_url]" value="<?php echo esc_attr( aigs_get( 'ai_base_url', '' ) ); ?>" placeholder="https://api.example.com/v1" />
						<p class="description"><?php esc_html_e( 'Any OpenAI-compatible base URL, for example a company AI gateway, Groq, Together, Fireworks, or a local Ollama (http://localhost:11434/v1) or LM Studio server. “/chat/completions” is added for you.', 'omnigeo-ai-seo' ); ?></p>
					</td></tr>
					<tr><th><label for="aigs_model"><?php esc_html_e( 'Model', 'omnigeo-ai-seo' ); ?></label></th><td>
						<input type="text" id="aigs_model" class="regular-text" name="aigs_settings[ai_model]" value="<?php echo esc_attr( aigs_get( 'ai_model', '' ) ); ?>" placeholder="<?php echo esc_attr( $provs[ AIGS_AI_Client::provider() ]['model'] ); ?>" />
						<p class="description"><?php esc_html_e( 'Leave empty for the default shown. Defaults are small, low-cost models. Required for “Other OpenAI-compatible endpoint”.', 'omnigeo-ai-seo' ); ?></p>
					</td></tr>
					<tr class="aigs-gateway-only"><th><?php esc_html_e( 'Live web search', 'omnigeo-ai-seo' ); ?></th><td>
						<label><input type="checkbox" name="aigs_settings[ai_grounded]" value="1" <?php checked( aigs_get( 'ai_grounded', 0 ), 1 ); ?> /> <?php esc_html_e( 'This model searches the web and returns sources (for example Perplexity models or “:online” models on OpenRouter)', 'omnigeo-ai-seo' ); ?></label>
						<p class="description"><?php esc_html_e( 'Only changes how AI Visibility results are labelled.', 'omnigeo-ai-seo' ); ?></p>
					</td></tr>
					<tr><th><label for="aigs_key"><?php esc_html_e( 'API key', 'omnigeo-ai-seo' ); ?></label></th>
						<td>
						<?php if ( $const_key ) : ?>
							<p><?php esc_html_e( 'Set in wp-config.php (AIGS_AI_API_KEY). That key is used.', 'omnigeo-ai-seo' ); ?></p>
						<?php else : ?>
							<input type="password" id="aigs_key" class="regular-text" name="aigs_settings[ai_api_key]" value="" autocomplete="new-password" placeholder="<?php echo $has_key ? esc_attr__( '•••••••• saved. Type to replace', 'omnigeo-ai-seo' ) : esc_attr__( 'Paste your key', 'omnigeo-ai-seo' ); ?>" />
							<?php if ( $has_key ) : ?>
								<label style="margin-inline-start:10px"><input type="checkbox" name="aigs_settings[ai_remove_key]" value="1" /> <?php esc_html_e( 'Remove saved key', 'omnigeo-ai-seo' ); ?></label>
							<?php endif; ?>
							<p class="description"><?php esc_html_e( 'Stored in your database and never shown again. For extra safety define AIGS_AI_API_KEY in wp-config.php instead. Local servers usually need no key.', 'omnigeo-ai-seo' ); ?></p>
						<?php endif; ?>
						</td></tr>
					<tr><th><label for="aigs_cap"><?php esc_html_e( 'Monthly request limit', 'omnigeo-ai-seo' ); ?></label></th><td>
						<input type="number" min="0" max="5000" id="aigs_cap" class="small-text" name="aigs_settings[ai_monthly_cap]" value="<?php echo (int) aigs_get( 'ai_monthly_cap', 60 ); ?>" />
						<p class="description">
						<?php
						/* translators: %d: requests used. */
						printf( esc_html__( 'A hard stop across all AI features. Used this month: %d.', 'omnigeo-ai-seo' ), (int) AIGS_AI_Client::used() );
						?>
						</p>
					</td></tr>
					<tr><th><?php esc_html_e( 'Check', 'omnigeo-ai-seo' ); ?></th><td>
						<button type="button" class="aigs-btn aigs-btn--sm" id="aigs-ai-test" <?php disabled( ! AIGS_AI_Client::ready() ); ?>><?php self::i( 'zap', 15 ); ?><?php esc_html_e( 'Test saved connection', 'omnigeo-ai-seo' ); ?></button>
						<span class="aigs-muted aigs-small" id="aigs-ai-test-out"><?php esc_html_e( 'Save first, then test. Uses one request.', 'omnigeo-ai-seo' ); ?></span>
					</td></tr>
				</table>
			</div>

			<p><button type="submit" class="aigs-btn aigs-btn--primary"><?php self::i( 'check', 16 ); ?><?php esc_html_e( 'Save settings', 'omnigeo-ai-seo' ); ?></button></p>
		</form>
		<?php
		$this->close();
	}

	/* ============================================================
	 * llms.txt
	 * ============================================================ */

	/**
	 * llms.txt page.
	 */
	public function page_llms() {
		$settings = get_option( 'aigs_settings', array() );
		$auto     = $this->modules['llms']->generate();
		$this->open( __( 'llms.txt Manager', 'omnigeo-ai-seo' ), __( 'Guide LLMs to your most important content.', 'omnigeo-ai-seo' ) );
		?>
		<div class="aigs-callout"><?php echo wp_kses( self::icon( 'info', 18 ), self::svg_kses() ); ?>
			<p><?php
				printf(
					/* translators: %s url */
					wp_kses_post( __( 'Your file is live at <a href="%1$s" target="_blank" rel="noopener"><code>%2$s</code></a>. Leave the editor empty to auto-generate it from your pages and posts.', 'omnigeo-ai-seo' ) ),
					esc_url( home_url( '/llms.txt' ) ),
					esc_html( home_url( '/llms.txt' ) )
				);
			?></p>
		</div>

		<div class="aigs-card">
			<div class="aigs-card__head"><?php echo wp_kses( self::icon( 'info', 17 ), self::svg_kses() ); ?><h2><?php esc_html_e( 'How this works', 'omnigeo-ai-seo' ); ?></h2></div>
			<div class="aigs-prose">
				<p><?php esc_html_e( 'An llms.txt file is a short, clean map that points AI tools to your best content. Think of it as a menu for assistants like ChatGPT, Perplexity and coding agents, so they do not have to dig through your whole site.', 'omnigeo-ai-seo' ); ?></p>
				<p><strong><?php esc_html_e( 'First steps:', 'omnigeo-ai-seo' ); ?></strong></p>
				<ol class="aigs-help-steps">
					<li><?php
						printf(
							/* translators: %s url */
							wp_kses_post( __( 'Click your file link above, or open <code>%s</code>, to see what AI tools receive.', 'omnigeo-ai-seo' ) ),
							esc_html( home_url( '/llms.txt' ) )
						);
					?></li>
					<li><?php esc_html_e( 'If you get a 404, go to Settings then Permalinks and click Save once. That refreshes the rule that serves the file. You do not need to create any file by hand.', 'omnigeo-ai-seo' ); ?></li>
					<li><?php esc_html_e( 'Leave the editor below empty to let the plugin build and update the file for you. Or paste your own version to take full control.', 'omnigeo-ai-seo' ); ?></li>
				</ol>
				<p><strong><?php esc_html_e( 'Good to know:', 'omnigeo-ai-seo' ); ?></strong> <?php esc_html_e( 'The file is served live by the plugin, so there is no physical file in your hosting folder, and it stays up to date on its own. This is normal and intended.', 'omnigeo-ai-seo' ); ?></p>
			</div>
		</div>

		<div class="aigs-card">
			<div class="aigs-card__head"><?php echo wp_kses( self::icon( 'file', 17 ), self::svg_kses() ); ?><h2><?php esc_html_e( 'Editor', 'omnigeo-ai-seo' ); ?></h2></div>
			<form method="post" action="options.php">
				<?php settings_fields( 'aigs_settings_group' ); ?>
				<input type="hidden" name="aigs_settings[section_llms]" value="1" />
				<textarea name="aigs_settings[llms_txt]" rows="14" class="large-text aigs-code" placeholder="<?php echo esc_attr( $auto ); ?>"><?php echo esc_textarea( isset( $settings['llms_txt'] ) ? $settings['llms_txt'] : '' ); ?></textarea>
				<div class="aigs-fieldrow"><label><input type="checkbox" name="aigs_settings[enable_llms]" value="1" <?php checked( aigs_get( 'enable_llms', 1 ), 1 ); ?> /> <?php esc_html_e( 'Serve llms.txt publicly', 'omnigeo-ai-seo' ); ?></label></div>
				<p><button type="submit" class="aigs-btn aigs-btn--primary"><?php echo wp_kses( self::icon( 'check', 16 ), self::svg_kses() ); ?><?php esc_html_e( 'Save', 'omnigeo-ai-seo' ); ?></button></p>
			</form>
		</div>

		<div class="aigs-card">
			<div class="aigs-card__head"><?php echo wp_kses( self::icon( 'globe', 17 ), self::svg_kses() ); ?><h2><?php esc_html_e( 'Auto-generated preview', 'omnigeo-ai-seo' ); ?></h2></div>
			<pre class="aigs-pre" id="aigs-llms-pre"><?php echo esc_html( $auto ); ?></pre>
			<p><button type="button" class="aigs-btn" data-aigs-copy="#aigs-llms-pre"><?php echo wp_kses( self::icon( 'arrow', 16 ), self::svg_kses() ); ?><?php esc_html_e( 'Copy to clipboard', 'omnigeo-ai-seo' ); ?></button></p>
		</div>
		<?php
		$this->close();
	}

	/* ============================================================
	 * Guide
	 * ============================================================ */

	/**
	 * Friendly help / guide page.
	 */
	public function page_help() {
		$this->open( __( 'Guide', 'omnigeo-ai-seo' ), __( 'Everything you need to get going, in plain language.', 'omnigeo-ai-seo' ) );
		?>
		<div class="aigs-card">
			<div class="aigs-card__head"><?php echo wp_kses( self::icon( 'sparkles', 17 ), self::svg_kses() ); ?><h2><?php esc_html_e( 'Welcome', 'omnigeo-ai-seo' ); ?></h2></div>
			<div class="aigs-prose">
				<p><?php esc_html_e( 'This plugin helps your content get noticed by AI search tools like ChatGPT, Perplexity, Gemini and Google AI Overviews. You do not need to be technical to use it, and you can ignore the advanced parts until you want them.', 'omnigeo-ai-seo' ); ?></p>
				<p><?php esc_html_e( 'Here is the fastest way to get value in your first few minutes:', 'omnigeo-ai-seo' ); ?></p>
				<ol class="aigs-help-steps">
					<li><?php esc_html_e( 'Open Settings and fill in your brand name, industry and location. Add your logo and official profile links in the sameAs box. It takes five minutes and shapes every suggestion.', 'omnigeo-ai-seo' ); ?></li>
					<li><?php esc_html_e( 'On the Growth Center, click Scan site. The scan reads your pages from WordPress itself in small batches, so it is safe on shared hosting and changes nothing.', 'omnigeo-ai-seo' ); ?></li>
					<li><?php esc_html_e( 'Open the Action Center and start with the first task. Fix the page, click Re-check, and the task moves to Recent wins once the fix is confirmed.', 'omnigeo-ai-seo' ); ?></li>
					<li><?php esc_html_e( 'When you write, use the Answer Optimizer under the editor. It shows the question your page should answer and whether your opening answers it.', 'omnigeo-ai-seo' ); ?></li>
				</ol>
				<p><?php esc_html_e( 'That is really all you need to start. The rest is here when you feel like going deeper.', 'omnigeo-ai-seo' ); ?></p>
			</div>
		</div>

		<div class="aigs-card">
			<div class="aigs-card__head"><?php echo wp_kses( self::icon( 'dashboard', 17 ), self::svg_kses() ); ?><h2><?php esc_html_e( 'What each part does', 'omnigeo-ai-seo' ); ?></h2></div>
			<div class="aigs-prose">
				<p><strong><?php esc_html_e( 'Growth Center.', 'omnigeo-ai-seo' ); ?></strong> <?php esc_html_e( 'Your starting point: the answer-readiness score of your site, its trend, today’s three most valuable tasks, recent wins and AI activity.', 'omnigeo-ai-seo' ); ?></p>
				<p><strong><?php esc_html_e( 'Action Center.', 'omnigeo-ai-seo' ); ?></strong> <?php esc_html_e( 'Every issue the scan found, grouped into tasks and ordered by impact. Each task explains why it matters and how to fix it, lists the affected pages with suggestions, and lets you re-check a page to confirm the fix. If something is a deliberate choice, mark it as intentional and it stops counting.', 'omnigeo-ai-seo' ); ?></p>
				<p><strong><?php esc_html_e( 'Answer Optimizer.', 'omnigeo-ai-seo' ); ?></strong> <?php esc_html_e( 'The box under the editor. It detects the main question of the page, checks whether the opening answers it directly, finds question headings without an answer, and can turn answered questions into FAQ schema. With your own AI key it can draft a direct answer from your text for you to review.', 'omnigeo-ai-seo' ); ?></p>
				<p><strong><?php esc_html_e( 'Products.', 'omnigeo-ai-seo' ); ?></strong> <?php esc_html_e( 'For WooCommerce shops: catalog checks (descriptions, specs, images, prices, GTIN) and bulk fixes you preview, approve and can undo.', 'omnigeo-ai-seo' ); ?></p>
				<p><strong><?php esc_html_e( 'AI Visibility.', 'omnigeo-ai-seo' ); ?></strong> <?php esc_html_e( 'Save the questions your buyers ask, your brand spellings and your competitors. Each check asks an assistant with your own key, stores the full answers, and shows mentions, recommendations, citations, share of voice and the sources it used. Questions where competitors appear and you do not become tasks.', 'omnigeo-ai-seo' ); ?></p>
				<p><strong><?php esc_html_e( 'AI Traffic.', 'omnigeo-ai-seo' ); ?></strong> <?php esc_html_e( 'Which AI bots read your site (with optional identity verification), which pages assistants opened for real users, which missing pages they asked for, and how many visitors came from AI answers.', 'omnigeo-ai-seo' ); ?></p>
				<p><strong><?php esc_html_e( 'URL Audit and Site Health.', 'omnigeo-ai-seo' ); ?></strong> <?php esc_html_e( 'Check the live HTML of one page, and the technical basics of the whole site. You can also ping IndexNow, manually or automatically.', 'omnigeo-ai-seo' ); ?></p>
				<p><strong><?php esc_html_e( 'llms.txt.', 'omnigeo-ai-seo' ); ?></strong> <?php esc_html_e( 'A small file that points AI tools to your best content. Let the plugin build it or write your own. To be honest, it is a new idea and not every AI tool reads it yet, so treat it as a nice extra rather than a must.', 'omnigeo-ai-seo' ); ?></p>
				<p><strong><?php esc_html_e( 'WooCommerce.', 'omnigeo-ai-seo' ); ?></strong> <?php esc_html_e( 'If you run a shop, the plugin steps back and lets WooCommerce handle product schema so nothing clashes. Your product pages also get their own checks for the short description, specs, reviews and images.', 'omnigeo-ai-seo' ); ?></p>
			</div>
		</div>

		<div class="aigs-card">
			<div class="aigs-card__head"><?php echo wp_kses( self::icon( 'activity', 17 ), self::svg_kses() ); ?><h2><?php esc_html_e( 'What actually helps you show up', 'omnigeo-ai-seo' ); ?></h2></div>
			<div class="aigs-prose">
				<p><?php esc_html_e( 'A few honest pointers, based on what tends to work in the real world:', 'omnigeo-ai-seo' ); ?></p>
				<ul class="aigs-help-list">
					<li><?php esc_html_e( 'Write the answer first. Put a short, clear answer at the top of a page or section before the long explanation. AI loves lifting these.', 'omnigeo-ai-seo' ); ?></li>
					<li><?php esc_html_e( 'Be a real, findable brand. Links to your Wikipedia, LinkedIn and social profiles help AI trust who you are.', 'omnigeo-ai-seo' ); ?></li>
					<li><?php esc_html_e( 'Use tables for comparisons. If a page is about the best option or the difference between options, a table gets picked up far more often.', 'omnigeo-ai-seo' ); ?></li>
					<li><?php esc_html_e( 'Keep pages fresh. Updating now and then is a small sign that you are still the right source.', 'omnigeo-ai-seo' ); ?></li>
					<li><?php esc_html_e( 'Remember that a lot happens off your site too. Mentions on places like Reddit, and links from other websites, still matter a great deal, and no plugin can do that part for you.', 'omnigeo-ai-seo' ); ?></li>
				</ul>
			</div>
		</div>

		<div class="aigs-card">
			<div class="aigs-card__head"><?php echo wp_kses( self::icon( 'info', 17 ), self::svg_kses() ); ?><h2><?php esc_html_e( 'Good to know', 'omnigeo-ai-seo' ); ?></h2></div>
			<div class="aigs-prose">
				<p><?php esc_html_e( 'This is not a magic switch. AI visibility builds up over weeks, not minutes, and some of it you will never see in any dashboard because people read the answer without ever clicking. Do the basics well, give it time, and check back now and then. Small, steady improvements add up.', 'omnigeo-ai-seo' ); ?></p>
			</div>
		</div>
		<?php
		$this->close();
	}

	/* ============================================================
	 * Dashboard widget
	 * ============================================================ */

	/**
	 * Register the dashboard widget.
	 */
	public function dashboard_widget() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_add_dashboard_widget( 'aigs_widget', __( 'OmniGEO: AI visibility', 'omnigeo-ai-seo' ), array( $this, 'render_widget' ) );
	}

	/**
	 * Render the dashboard widget.
	 */
	public function render_widget() {
		$t       = $this->modules['tracker'];
		$summary = get_option( 'aigs_scan_summary', array() );
		$counts  = AIGS_Findings::counts();
		echo '<p style="font-size:13px;line-height:1.8">';
		if ( ! empty( $summary['scanned_at'] ) ) {
			echo '<strong style="font-size:18px">' . (int) $summary['score'] . '/100</strong> ' . esc_html__( 'AI answer readiness', 'omnigeo-ai-seo' ) . '<br>';
			echo '<strong style="font-size:18px">' . (int) ( $counts['critical'] + $counts['high'] ) . '</strong> ' . esc_html__( 'high-priority issues', 'omnigeo-ai-seo' ) . '<br>';
		}
		echo '<strong style="font-size:18px">' . (int) $t->count( 'fetch', 7 ) . '</strong> ' . esc_html__( 'reads inside AI answers · last 7 days', 'omnigeo-ai-seo' ) . '</p>';
		echo '<p><a class="button button-primary button-small" href="' . esc_url( admin_url( 'admin.php?page=aigs-actions' ) ) . '">' . esc_html__( 'Open Action Center', 'omnigeo-ai-seo' ) . '</a> ';
		echo '<a class="button button-small" href="' . esc_url( admin_url( 'admin.php?page=omnigeo-ai-seo' ) ) . '">' . esc_html__( 'Growth Center', 'omnigeo-ai-seo' ) . '</a></p>';
	}
}
