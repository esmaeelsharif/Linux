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

	/* ============================================================
	 * Menu
	 * ============================================================ */

	/**
	 * Admin menu + subpages.
	 */
	public function menu() {
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
			'omnigeo-ai-seo'       => array( __( 'Dashboard', 'omnigeo-ai-seo' ), 'page_dashboard' ),
			'aigs-traffic'       => array( __( 'AI Traffic', 'omnigeo-ai-seo' ), 'page_traffic' ),
			'aigs-audit'         => array( __( 'GEO Audit', 'omnigeo-ai-seo' ), 'page_audit' ),
			'aigs-ai-visibility' => array( __( 'AI Visibility', 'omnigeo-ai-seo' ), 'page_ai_visibility' ),
			'aigs-visibility'    => array( __( 'Site Health', 'omnigeo-ai-seo' ), 'page_visibility' ),
			'aigs-llms'          => array( __( 'llms.txt', 'omnigeo-ai-seo' ), 'page_llms' ),
			'aigs-settings'      => array( __( 'Settings', 'omnigeo-ai-seo' ), 'page_settings' ),
			'aigs-help'          => array( __( 'Guide', 'omnigeo-ai-seo' ), 'page_help' ),
		);
		foreach ( $pages as $slug => $p ) {
			add_submenu_page( 'omnigeo-ai-seo', $p[0], $p[0], 'manage_options', $slug, array( $this, $p[1] ) );
		}
	}

	/**
	 * Enqueue assets only on plugin pages.
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
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'aigs_admin' ),
				'home'  => home_url( '/' ),
				'i18n'  => array(
					'question' => __( 'Question', 'omnigeo-ai-seo' ),
					'answer'   => __( 'Answer', 'omnigeo-ai-seo' ),
					'remove'   => __( 'Remove', 'omnigeo-ai-seo' ),
				),
			)
		);
	}

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
	 * Sanitize settings on save.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$old = get_option( 'aigs_settings', array() );
		$out = is_array( $old ) ? $old : array();

		// Main Settings page fields. Only touched when that form was submitted,
		// so saving another page's form never resets these.
		if ( isset( $input['section_settings'] ) ) {
			$out['enable_schema']   = empty( $input['enable_schema'] ) ? 0 : 1;
			$out['enable_tracking'] = empty( $input['enable_tracking'] ) ? 0 : 1;
			$out['business_name']   = isset( $input['business_name'] ) ? sanitize_text_field( $input['business_name'] ) : '';
			$out['business_type']   = isset( $input['business_type'] ) ? sanitize_text_field( $input['business_type'] ) : 'Organization';
			$out['industry']        = isset( $input['industry'] ) ? sanitize_text_field( $input['industry'] ) : '';
			$out['location']        = isset( $input['location'] ) ? sanitize_text_field( $input['location'] ) : '';
			$out['org_logo']        = isset( $input['org_logo'] ) ? esc_url_raw( $input['org_logo'] ) : '';
			$out['org_founded']     = isset( $input['org_founded'] ) ? sanitize_text_field( $input['org_founded'] ) : '';
			$out['org_sameas']      = isset( $input['org_sameas'] ) ? sanitize_textarea_field( $input['org_sameas'] ) : '';
			$out['dedupe_schema']   = ( isset( $input['dedupe_schema'] ) && 'off' === $input['dedupe_schema'] ) ? 'off' : 'auto';
			$out['ai_access']       = ( isset( $input['ai_access'] ) && 'disallow' === $input['ai_access'] ) ? 'disallow' : 'allow';
			$out['ai_provider']     = isset( $input['ai_provider'] ) ? sanitize_text_field( $input['ai_provider'] ) : 'openai';
			$out['ai_api_key']      = isset( $input['ai_api_key'] ) ? trim( sanitize_text_field( $input['ai_api_key'] ) ) : '';
			if ( isset( $input['woo_present'] ) ) {
				$out['enhance_woo'] = empty( $input['enhance_woo'] ) ? 'off' : 'on';
			}
		}

		// llms.txt page fields.
		if ( isset( $input['section_llms'] ) ) {
			$out['enable_llms'] = empty( $input['enable_llms'] ) ? 0 : 1;
			$out['llms_txt']    = isset( $input['llms_txt'] ) ? sanitize_textarea_field( $input['llms_txt'] ) : '';
		}

		// Always ensure an IndexNow key exists.
		if ( empty( $out['indexnow_key'] ) ) {
			$out['indexnow_key'] = wp_generate_password( 32, false, false );
		}

		// Never persist the transient section markers.
		unset( $out['section_settings'], $out['section_llms'] );

		return $out;
	}

	/* ============================================================
	 * Page chrome
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
	}

	/**
	 * Close the wrapper.
	 */
	private function close() {
		echo '</div>';
	}

	/* ============================================================
	 * Dashboard
	 * ============================================================ */

	/**
	 * Dashboard page.
	 */
	public function page_dashboard() {
		$t      = $this->modules['tracker'];
		$fetch  = $t->count( 'fetch', 30 );
		$crawls = $t->count( 'crawl', 30 );
		$refs   = $t->count( 'referral', 30 );
		$byc    = $t->by_platform( 'crawl', 30 );
		$series = $t->daily_series( 'crawl', 14 );
		$top    = $t->top_urls( 8, 30 );

		$action = '<a class="aigs-btn aigs-btn--primary" href="' . esc_url( admin_url( 'admin.php?page=aigs-audit' ) ) . '">' . wp_kses( self::icon( 'gauge', 16 ), self::svg_kses() ) . esc_html__( 'Run audit', 'omnigeo-ai-seo' ) . '</a>';
		$this->open( __( 'Overview', 'omnigeo-ai-seo' ), __( 'How AI engines crawl, cite and reach your site.', 'omnigeo-ai-seo' ), $action );
		?>
		<div class="aigs-stats">
			<div class="aigs-stat">
				<div class="aigs-stat__top"><span class="aigs-stat__ico"><?php echo wp_kses( self::icon( 'quote', 18 ), self::svg_kses() ); ?></span></div>
				<div class="aigs-stat__val"><?php echo (int) $fetch; ?></div>
				<div class="aigs-stat__label"><?php esc_html_e( 'Read inside AI answers · 30d', 'omnigeo-ai-seo' ); ?></div>
			</div>
			<div class="aigs-stat">
				<div class="aigs-stat__top"><span class="aigs-stat__ico"><?php echo wp_kses( self::icon( 'bot', 18 ), self::svg_kses() ); ?></span></div>
				<div class="aigs-stat__val"><?php echo (int) $crawls; ?></div>
				<div class="aigs-stat__label"><?php esc_html_e( 'Index / training crawls · 30d', 'omnigeo-ai-seo' ); ?></div>
			</div>
			<div class="aigs-stat">
				<div class="aigs-stat__top"><span class="aigs-stat__ico"><?php echo wp_kses( self::icon( 'arrow', 18 ), self::svg_kses() ); ?></span></div>
				<div class="aigs-stat__val"><?php echo (int) $refs; ?></div>
				<div class="aigs-stat__label"><?php esc_html_e( 'AI referral clicks · 30d', 'omnigeo-ai-seo' ); ?></div>
			</div>
		</div>

		<div class="aigs-card">
			<div class="aigs-card__head"><?php echo wp_kses( self::icon( 'activity', 17 ), self::svg_kses() ); ?><h2><?php esc_html_e( 'Crawl activity · last 14 days', 'omnigeo-ai-seo' ); ?></h2></div>
			<p class="aigs-card__desc"><?php esc_html_e( 'Daily AI crawler hits across all engines.', 'omnigeo-ai-seo' ); ?></p>
			<?php
			$total = array_sum( $series );
			if ( $total > 0 ) :
				$maxv = max( 1, max( $series ) );
				?>
				<div class="aigs-bars">
					<?php foreach ( $series as $day => $val ) : ?>
						<span class="aigs-bar" style="height:<?php echo (int) max( 3, round( ( $val / $maxv ) * 90 ) ); ?>px" title="<?php echo esc_attr( $day . ', ' . $val ); ?>"></span>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<div class="aigs-empty"><?php echo wp_kses( self::icon( 'bot', 30 ), self::svg_kses() ); ?><p><?php esc_html_e( 'No AI crawler activity yet, it appears here as bots like GPTBot and ClaudeBot visit.', 'omnigeo-ai-seo' ); ?></p></div>
			<?php endif; ?>
		</div>

		<div class="aigs-grid2">
			<div class="aigs-card">
				<div class="aigs-card__head"><?php echo wp_kses( self::icon( 'radar', 17 ), self::svg_kses() ); ?><h2><?php esc_html_e( 'By platform', 'omnigeo-ai-seo' ); ?></h2></div>
				<?php if ( $byc ) : ?>
					<table class="aigs-table"><tbody>
					<?php foreach ( $byc as $plat => $n ) : ?>
						<tr><td><?php echo esc_html( $plat ); ?></td><td class="num"><?php echo (int) $n; ?></td></tr>
					<?php endforeach; ?>
					</tbody></table>
				<?php else : ?>
					<div class="aigs-empty"><?php echo wp_kses( self::icon( 'radar', 30 ), self::svg_kses() ); ?><p><?php esc_html_e( 'Nothing detected yet.', 'omnigeo-ai-seo' ); ?></p></div>
				<?php endif; ?>
			</div>
			<div class="aigs-card">
				<div class="aigs-card__head"><?php echo wp_kses( self::icon( 'link', 17 ), self::svg_kses() ); ?><h2><?php esc_html_e( 'Most-crawled URLs', 'omnigeo-ai-seo' ); ?></h2></div>
				<?php if ( $top ) : ?>
					<table class="aigs-table"><tbody>
					<?php foreach ( $top as $row ) : ?>
						<tr><td class="url"><?php echo esc_html( $row['url'] ); ?></td><td class="num"><?php echo (int) $row['c']; ?></td></tr>
					<?php endforeach; ?>
					</tbody></table>
				<?php else : ?>
					<div class="aigs-empty"><?php echo wp_kses( self::icon( 'link', 30 ), self::svg_kses() ); ?><p><?php esc_html_e( 'Nothing yet.', 'omnigeo-ai-seo' ); ?></p></div>
				<?php endif; ?>
			</div>
		</div>
		<?php
		$this->close();
	}

	/* ============================================================
	 * AI Traffic
	 * ============================================================ */

	/**
	 * AI Traffic page, fetch vs crawl breakdown + GA4 channel-group helper.
	 */
	public function page_traffic() {
		$t      = $this->modules['tracker'];
		$fetch  = $t->count( 'fetch', 30 );
		$crawl  = $t->count( 'crawl', 30 );
		$ref    = $t->count( 'referral', 30 );
		$fbyp   = $t->by_platform( 'fetch', 30 );
		$ftop   = $t->top_urls( 8, 30, 'fetch' );
		$rbyp   = $t->by_platform( 'referral', 30 );
		$regex  = AIGS_Tracker::ga4_regex();

		$this->open( __( 'AI Traffic', 'omnigeo-ai-seo' ), __( 'Measure the AI visibility most analytics tools miss.', 'omnigeo-ai-seo' ) );
		?>
		<div class="aigs-callout"><?php echo wp_kses( self::icon( 'info', 18 ), self::svg_kses() ); ?>
			<p><?php esc_html_e( 'Three tiers of AI visibility: user-triggered fetches (a person is reading you inside an answer right now), index crawls (training/search), and referral clicks. Server-side fetch tracking catches what GA4 cannot, most AI exposure is zero-click.', 'omnigeo-ai-seo' ); ?></p>
		</div>

		<div class="aigs-stats">
			<div class="aigs-stat">
				<div class="aigs-stat__top"><span class="aigs-stat__ico"><?php echo wp_kses( self::icon( 'quote', 18 ), self::svg_kses() ); ?></span></div>
				<div class="aigs-stat__val"><?php echo (int) $fetch; ?></div>
				<div class="aigs-stat__label"><?php esc_html_e( 'Read inside AI answers · 30d', 'omnigeo-ai-seo' ); ?></div>
			</div>
			<div class="aigs-stat">
				<div class="aigs-stat__top"><span class="aigs-stat__ico"><?php echo wp_kses( self::icon( 'bot', 18 ), self::svg_kses() ); ?></span></div>
				<div class="aigs-stat__val"><?php echo (int) $crawl; ?></div>
				<div class="aigs-stat__label"><?php esc_html_e( 'Index / training crawls · 30d', 'omnigeo-ai-seo' ); ?></div>
			</div>
			<div class="aigs-stat">
				<div class="aigs-stat__top"><span class="aigs-stat__ico"><?php echo wp_kses( self::icon( 'arrow', 18 ), self::svg_kses() ); ?></span></div>
				<div class="aigs-stat__val"><?php echo (int) $ref; ?></div>
				<div class="aigs-stat__label"><?php esc_html_e( 'AI referral clicks · 30d', 'omnigeo-ai-seo' ); ?></div>
			</div>
		</div>

		<div class="aigs-grid2">
			<div class="aigs-card">
				<div class="aigs-card__head"><?php echo wp_kses( self::icon( 'quote', 17 ), self::svg_kses() ); ?><h2><?php esc_html_e( 'Read inside AI answers, by platform', 'omnigeo-ai-seo' ); ?></h2></div>
				<p class="aigs-card__desc"><?php esc_html_e( 'User-triggered fetches, a real person prompted the AI and it pulled your page.', 'omnigeo-ai-seo' ); ?></p>
				<?php if ( $fbyp ) : ?>
					<table class="aigs-table"><tbody>
					<?php foreach ( $fbyp as $plat => $n ) : ?>
						<tr><td><?php echo esc_html( $plat ); ?></td><td class="num"><?php echo (int) $n; ?></td></tr>
					<?php endforeach; ?>
					</tbody></table>
				<?php else : ?>
					<div class="aigs-empty"><?php echo wp_kses( self::icon( 'quote', 30 ), self::svg_kses() ); ?><p><?php esc_html_e( 'No user-triggered fetches yet. They appear when someone is shown your content inside ChatGPT, Perplexity, etc.', 'omnigeo-ai-seo' ); ?></p></div>
				<?php endif; ?>
			</div>
			<div class="aigs-card">
				<div class="aigs-card__head"><?php echo wp_kses( self::icon( 'link', 17 ), self::svg_kses() ); ?><h2><?php esc_html_e( 'Pages pulled into AI answers', 'omnigeo-ai-seo' ); ?></h2></div>
				<?php if ( $ftop ) : ?>
					<table class="aigs-table"><tbody>
					<?php foreach ( $ftop as $row ) : ?>
						<tr><td class="url"><?php echo esc_html( $row['url'] ); ?></td><td class="num"><?php echo (int) $row['c']; ?></td></tr>
					<?php endforeach; ?>
					</tbody></table>
				<?php else : ?>
					<div class="aigs-empty"><?php echo wp_kses( self::icon( 'link', 30 ), self::svg_kses() ); ?><p><?php esc_html_e( 'Nothing yet.', 'omnigeo-ai-seo' ); ?></p></div>
				<?php endif; ?>
			</div>
		</div>

		<div class="aigs-card">
			<div class="aigs-card__head"><?php echo wp_kses( self::icon( 'activity', 17 ), self::svg_kses() ); ?><h2><?php esc_html_e( 'Track AI clicks in GA4', 'omnigeo-ai-seo' ); ?></h2></div>
			<p class="aigs-card__desc"><?php esc_html_e( 'GA4 has no built-in AI channel, so AI clicks get mislabelled as Referral or Direct. Create a custom channel group with this source regex to isolate them.', 'omnigeo-ai-seo' ); ?></p>
			<p><code class="aigs-mono" id="aigs-regex" style="display:block;white-space:pre-wrap;word-break:break-all;padding:12px"><?php echo esc_html( $regex ); ?></code></p>
			<p><button type="button" class="aigs-btn" id="aigs-copy-regex" data-aigs-copy="#aigs-regex"><?php echo wp_kses( self::icon( 'arrow', 16 ), self::svg_kses() ); ?><?php esc_html_e( 'Copy regex', 'omnigeo-ai-seo' ); ?></button></p>
			<ol class="aigs-steps">
				<li><?php esc_html_e( 'In GA4, open Admin → Data display → Channel groups.', 'omnigeo-ai-seo' ); ?></li>
				<li><?php esc_html_e( 'Create a new channel group and add a channel named “AI”.', 'omnigeo-ai-seo' ); ?></li>
				<li><?php esc_html_e( 'Set the condition: Source matches regex → paste the regex above.', 'omnigeo-ai-seo' ); ?></li>
				<li><?php esc_html_e( 'Add a second condition (OR): Source matches regex AND Medium = (not set), to catch app hand-offs.', 'omnigeo-ai-seo' ); ?></li>
				<li><?php esc_html_e( 'Save. New sessions are now grouped under “AI”.', 'omnigeo-ai-seo' ); ?></li>
			</ol>
			<p class="aigs-muted" style="font-size:12.5px"><?php esc_html_e( 'Note: Google AI Mode strips referrers, and most AI mentions are zero-click, so GA4 shows only the floor. The fetch metric above captures the rest.', 'omnigeo-ai-seo' ); ?></p>
		</div>
		<?php
		$this->close();
	}

	/* ============================================================
	 * GEO Audit
	 * ============================================================ */

	/**
	 * GEO Audit page.
	 */
	public function page_audit() {
		$this->open( __( 'GEO / AEO Audit', 'omnigeo-ai-seo' ), __( 'Score any URL across 15 AI-readiness checks.', 'omnigeo-ai-seo' ) );
		?>
		<div class="aigs-card">
			<div class="aigs-inputgroup">
				<input type="url" id="aigs-audit-url" class="aigs-input" value="<?php echo esc_url( home_url( '/' ) ); ?>" placeholder="https://example.com/page" />
				<button class="aigs-btn aigs-btn--primary" id="aigs-run-audit"><?php echo wp_kses( self::icon( 'gauge', 16 ), self::svg_kses() ); ?><?php esc_html_e( 'Run audit', 'omnigeo-ai-seo' ); ?></button>
			</div>
		</div>
		<div id="aigs-audit-result"></div>
		<?php
		$this->close();
	}

	/* ============================================================
	 * AI Visibility
	 * ============================================================ */

	/**
	 * AI Visibility page.
	 */
	public function page_ai_visibility() {
		$has_key = (bool) aigs_get( 'ai_api_key', '' );
		$this->open( __( 'AI Visibility', 'omnigeo-ai-seo' ), __( 'Do assistants mention your brand for buyer-intent prompts?', 'omnigeo-ai-seo' ) );

		if ( ! $has_key ) {
			echo '<div class="aigs-callout">' . wp_kses( self::icon( 'info', 18 ), self::svg_kses() );
			echo '<p>' . wp_kses_post(
				sprintf(
					/* translators: %s settings link */
					__( 'Add your own AI API key in <a href="%s">Settings</a> to run live scans. It uses your OpenAI / Anthropic / Perplexity key, no third-party subscription.', 'omnigeo-ai-seo' ),
					esc_url( admin_url( 'admin.php?page=aigs-settings' ) )
				)
			) . '</p></div>';
		}
		?>
		<div class="aigs-card">
			<div class="aigs-card__head"><?php echo wp_kses( self::icon( 'quote', 17 ), self::svg_kses() ); ?><h2><?php esc_html_e( 'Test a prompt', 'omnigeo-ai-seo' ); ?></h2></div>
			<p class="aigs-card__desc"><?php esc_html_e( 'Pick a suggestion or write your own, then scan it.', 'omnigeo-ai-seo' ); ?></p>
			<p><button class="aigs-btn aigs-btn--ghost" id="aigs-suggest-prompts"><?php echo wp_kses( self::icon( 'sparkles', 16 ), self::svg_kses() ); ?><?php esc_html_e( 'Suggest prompts', 'omnigeo-ai-seo' ); ?></button></p>
			<div id="aigs-prompt-list" class="aigs-chips"></div>
			<div class="aigs-inputgroup" style="margin-top:12px">
				<input type="text" id="aigs-prompt" class="aigs-input" placeholder="<?php esc_attr_e( 'e.g. What are the best providers for…', 'omnigeo-ai-seo' ); ?>" />
				<button class="aigs-btn aigs-btn--primary" id="aigs-run-scan" <?php disabled( ! $has_key ); ?>><?php echo wp_kses( self::icon( 'radar', 16 ), self::svg_kses() ); ?><?php esc_html_e( 'Scan', 'omnigeo-ai-seo' ); ?></button>
			</div>
		</div>
		<div id="aigs-scan-result"></div>
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
		$this->open( __( 'Site Health', 'omnigeo-ai-seo' ), __( 'Technical signals that decide whether AI can read you.', 'omnigeo-ai-seo' ), $action );
		?>
		<div id="aigs-health-result">
			<div class="aigs-card"><div class="aigs-empty"><?php echo wp_kses( self::icon( 'shield', 30 ), self::svg_kses() ); ?><p><?php esc_html_e( 'Run the checks to see HTTPS, robots, sitemap, llms.txt, indexing and freshness status.', 'omnigeo-ai-seo' ); ?></p></div></div>
		</div>

		<div class="aigs-card">
			<div class="aigs-card__head"><?php echo wp_kses( self::icon( 'zap', 17 ), self::svg_kses() ); ?><h2><?php esc_html_e( 'IndexNow', 'omnigeo-ai-seo' ); ?></h2></div>
			<p class="aigs-card__desc"><?php esc_html_e( 'Instantly notify Bing, Copilot and other IndexNow engines when a URL changes.', 'omnigeo-ai-seo' ); ?></p>
			<p><span class="aigs-mono"><?php echo esc_html( home_url( '/' . $key . '.txt' ) ); ?></span></p>
			<div class="aigs-inputgroup" style="margin-top:10px">
				<input type="url" id="aigs-indexnow-url" class="aigs-input" value="<?php echo esc_url( home_url( '/' ) ); ?>" />
				<button class="aigs-btn" id="aigs-submit-indexnow"><?php echo wp_kses( self::icon( 'arrow', 16 ), self::svg_kses() ); ?><?php esc_html_e( 'Submit URL', 'omnigeo-ai-seo' ); ?></button>
			</div>
		</div>
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
	 * Settings
	 * ============================================================ */

	/**
	 * Settings page.
	 */
	public function page_settings() {
		$this->open( __( 'Settings', 'omnigeo-ai-seo' ), __( 'Brand profile, AI access rules and your visibility provider.', 'omnigeo-ai-seo' ) );
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'aigs_settings_group' ); ?>
			<input type="hidden" name="aigs_settings[section_settings]" value="1" />

			<div class="aigs-card">
				<div class="aigs-card__head"><?php echo wp_kses( self::icon( 'sliders', 17 ), self::svg_kses() ); ?><h2><?php esc_html_e( 'Modules', 'omnigeo-ai-seo' ); ?></h2></div>
				<div class="aigs-fieldrow"><label><input type="checkbox" name="aigs_settings[enable_schema]" value="1" <?php checked( aigs_get( 'enable_schema', 1 ), 1 ); ?> /> <?php esc_html_e( 'Output Schema / JSON-LD on the front end', 'omnigeo-ai-seo' ); ?></label></div>
				<div class="aigs-fieldrow"><label><input type="checkbox" name="aigs_settings[enable_tracking]" value="1" <?php checked( aigs_get( 'enable_tracking', 1 ), 1 ); ?> /> <?php esc_html_e( 'Track AI bots & referral visits', 'omnigeo-ai-seo' ); ?></label></div>
			</div>

			<div class="aigs-card">
				<div class="aigs-card__head"><?php echo wp_kses( self::icon( 'info', 17 ), self::svg_kses() ); ?><h2><?php esc_html_e( 'Brand profile', 'omnigeo-ai-seo' ); ?></h2></div>
				<table class="form-table" role="presentation">
					<tr><th><label for="aigs_bn"><?php esc_html_e( 'Business / brand name', 'omnigeo-ai-seo' ); ?></label></th>
						<td><input type="text" id="aigs_bn" class="regular-text" name="aigs_settings[business_name]" value="<?php echo esc_attr( aigs_get( 'business_name', '' ) ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" /></td></tr>
					<tr><th><label for="aigs_ind"><?php esc_html_e( 'Industry', 'omnigeo-ai-seo' ); ?></label></th>
						<td><input type="text" id="aigs_ind" class="regular-text" name="aigs_settings[industry]" value="<?php echo esc_attr( aigs_get( 'industry', '' ) ); ?>" placeholder="e.g. dental clinic" /></td></tr>
					<tr><th><label for="aigs_loc"><?php esc_html_e( 'Location', 'omnigeo-ai-seo' ); ?></label></th>
						<td><input type="text" id="aigs_loc" class="regular-text" name="aigs_settings[location]" value="<?php echo esc_attr( aigs_get( 'location', '' ) ); ?>" placeholder="e.g. Tehran" /></td></tr>
				</table>
			</div>

			<div class="aigs-card">
				<div class="aigs-card__head"><?php echo wp_kses( self::icon( 'globe', 17 ), self::svg_kses() ); ?><h2><?php esc_html_e( 'Organization entity (Schema)', 'omnigeo-ai-seo' ); ?></h2></div>
				<p class="aigs-card__desc"><?php esc_html_e( 'Output site-wide Organization schema so AI engines can resolve your brand identity. The sameAs links are the most valuable part.', 'omnigeo-ai-seo' ); ?></p>
				<table class="form-table" role="presentation">
					<tr><th><label for="aigs_logo"><?php esc_html_e( 'Logo URL', 'omnigeo-ai-seo' ); ?></label></th>
						<td><input type="url" id="aigs_logo" class="regular-text" name="aigs_settings[org_logo]" value="<?php echo esc_attr( aigs_get( 'org_logo', '' ) ); ?>" placeholder="https://example.com/logo.png" /></td></tr>
					<tr><th><label for="aigs_founded"><?php esc_html_e( 'Founding year', 'omnigeo-ai-seo' ); ?></label></th>
						<td><input type="text" id="aigs_founded" class="regular-text" name="aigs_settings[org_founded]" value="<?php echo esc_attr( aigs_get( 'org_founded', '' ) ); ?>" placeholder="2019" /></td></tr>
					<tr><th><label for="aigs_sameas"><?php esc_html_e( 'sameAs profile URLs', 'omnigeo-ai-seo' ); ?></label></th>
						<td><textarea id="aigs_sameas" name="aigs_settings[org_sameas]" rows="4" class="large-text aigs-code" placeholder="https://en.wikipedia.org/wiki/Your_Brand&#10;https://www.wikidata.org/wiki/Q…&#10;https://www.linkedin.com/company/…&#10;https://www.instagram.com/…"><?php echo esc_textarea( aigs_get( 'org_sameas', '' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One URL per line: Wikipedia, Wikidata, LinkedIn, social profiles, Crunchbase, etc.', 'omnigeo-ai-seo' ); ?></p></td></tr>
				</table>
			</div>

			<div class="aigs-card">
				<div class="aigs-card__head"><?php echo wp_kses( self::icon( 'link', 17 ), self::svg_kses() ); ?><h2><?php esc_html_e( 'SEO plugin integration', 'omnigeo-ai-seo' ); ?></h2></div>
				<?php $seo = class_exists( 'AIGS_SEO' ) ? AIGS_SEO::name() : ''; ?>
				<?php if ( $seo ) : ?>
					/* translators: %s is a value inserted into the message. */
					<p class="aigs-card__desc"><?php printf( /* translators: %s plugin */ esc_html__( 'Detected: %s. Its focus keyword is used in the Content Extractability analysis.', 'omnigeo-ai-seo' ), '<strong>' . esc_html( $seo ) . '</strong>' ); ?></p>
				<?php else : ?>
					<p class="aigs-card__desc"><?php esc_html_e( 'No supported SEO plugin detected (Yoast, Rank Math, SEOPress, All in One SEO).', 'omnigeo-ai-seo' ); ?></p>
				<?php endif; ?>
				<table class="form-table" role="presentation">
					<tr><th><?php esc_html_e( 'Duplicate schema', 'omnigeo-ai-seo' ); ?></th><td>
						<select name="aigs_settings[dedupe_schema]">
							<option value="auto" <?php selected( aigs_get( 'dedupe_schema', 'auto' ), 'auto' ); ?>><?php esc_html_e( 'Auto, skip our entity schema when an SEO plugin provides it (recommended)', 'omnigeo-ai-seo' ); ?></option>
							<option value="off" <?php selected( aigs_get( 'dedupe_schema', 'auto' ), 'off' ); ?>><?php esc_html_e( 'Always output our entity schema', 'omnigeo-ai-seo' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Yoast and Rank Math already emit Organization/Article schema. Auto prevents a duplicate graph on the page.', 'omnigeo-ai-seo' ); ?></p>
					</td></tr>
				</table>
			</div>

			<?php if ( class_exists( 'AIGS_WooCommerce' ) && AIGS_WooCommerce::active() ) : ?>
			<div class="aigs-card">
				<div class="aigs-card__head"><?php echo wp_kses( self::icon( 'bot', 17 ), self::svg_kses() ); ?><h2><?php esc_html_e( 'WooCommerce', 'omnigeo-ai-seo' ); ?></h2></div>
				<p class="aigs-card__desc"><?php esc_html_e( 'WooCommerce detected. On product pages we defer to its native Product schema to avoid conflicts, and add products & categories to llms.txt.', 'omnigeo-ai-seo' ); ?></p>
				<input type="hidden" name="aigs_settings[woo_present]" value="1" />
				<div class="aigs-fieldrow"><label><input type="checkbox" name="aigs_settings[enhance_woo]" value="1" <?php checked( aigs_get( 'enhance_woo', 'on' ), 'on' ); ?> /> <?php esc_html_e( 'Add brand entity to WooCommerce Product schema', 'omnigeo-ai-seo' ); ?></label></div>
			</div>
			<?php endif; ?>

			<div class="aigs-card">
				<div class="aigs-card__head"><?php echo wp_kses( self::icon( 'shield', 17 ), self::svg_kses() ); ?><h2><?php esc_html_e( 'AI crawler access', 'omnigeo-ai-seo' ); ?></h2></div>
				<table class="form-table" role="presentation">
					<tr><th><?php esc_html_e( 'robots.txt rule', 'omnigeo-ai-seo' ); ?></th><td>
						<select name="aigs_settings[ai_access]">
							<option value="allow" <?php selected( aigs_get( 'ai_access', 'allow' ), 'allow' ); ?>><?php esc_html_e( 'Allow AI crawlers', 'omnigeo-ai-seo' ); ?></option>
							<option value="disallow" <?php selected( aigs_get( 'ai_access', 'allow' ), 'disallow' ); ?>><?php esc_html_e( 'Disallow AI crawlers', 'omnigeo-ai-seo' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Adds Allow/Disallow rules for known AI bots to robots.txt.', 'omnigeo-ai-seo' ); ?></p>
					</td></tr>
				</table>
			</div>

			<div class="aigs-card">
				<div class="aigs-card__head"><?php echo wp_kses( self::icon( 'radar', 17 ), self::svg_kses() ); ?><h2><?php esc_html_e( 'AI Visibility provider', 'omnigeo-ai-seo' ); ?></h2></div>
				<table class="form-table" role="presentation">
					<tr><th><label for="aigs_prov"><?php esc_html_e( 'Provider', 'omnigeo-ai-seo' ); ?></label></th><td>
						<select id="aigs_prov" name="aigs_settings[ai_provider]">
							<option value="openai" <?php selected( aigs_get( 'ai_provider', 'openai' ), 'openai' ); ?>>OpenAI (ChatGPT)</option>
							<option value="anthropic" <?php selected( aigs_get( 'ai_provider', 'openai' ), 'anthropic' ); ?>>Anthropic (Claude)</option>
							<option value="perplexity" <?php selected( aigs_get( 'ai_provider', 'openai' ), 'perplexity' ); ?>>Perplexity</option>
						</select>
					</td></tr>
					<tr><th><label for="aigs_key"><?php esc_html_e( 'Your API key', 'omnigeo-ai-seo' ); ?></label></th>
						<td><input type="password" id="aigs_key" class="regular-text" name="aigs_settings[ai_api_key]" value="<?php echo esc_attr( aigs_get( 'ai_api_key', '' ) ); ?>" autocomplete="off" />
						<p class="description"><?php esc_html_e( 'Stored in your database and used only for live scans. You are billed on your own API account.', 'omnigeo-ai-seo' ); ?></p></td></tr>
				</table>
			</div>

			<p><button type="submit" class="aigs-btn aigs-btn--primary"><?php echo wp_kses( self::icon( 'check', 16 ), self::svg_kses() ); ?><?php esc_html_e( 'Save settings', 'omnigeo-ai-seo' ); ?></button></p>
		</form>
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
					<li><?php esc_html_e( 'Open Settings and fill in your brand name, industry and location. Add your social and Wikipedia links in the sameAs box. This is the part AI cares about most, so it is worth five minutes.', 'omnigeo-ai-seo' ); ?></li>
					<li><?php esc_html_e( 'Open any post or product. Look at the Content Extractability box under the editor and follow the tips it gives you. Aim for green.', 'omnigeo-ai-seo' ); ?></li>
					<li><?php esc_html_e( 'Come back to the AI Traffic page in a week or two to see which AI bots are reading your site.', 'omnigeo-ai-seo' ); ?></li>
				</ol>
				<p><?php esc_html_e( 'That is really all you need to start. The rest is here when you feel like going deeper.', 'omnigeo-ai-seo' ); ?></p>
			</div>
		</div>

		<div class="aigs-card">
			<div class="aigs-card__head"><?php echo wp_kses( self::icon( 'dashboard', 17 ), self::svg_kses() ); ?><h2><?php esc_html_e( 'What each part does', 'omnigeo-ai-seo' ); ?></h2></div>
			<div class="aigs-prose">
				<p><strong><?php esc_html_e( 'Dashboard.', 'omnigeo-ai-seo' ); ?></strong> <?php esc_html_e( 'A quick snapshot of how AI engines interact with your site over the last 30 days. If the numbers look small at first, that is normal. They grow as AI tools find your content.', 'omnigeo-ai-seo' ); ?></p>
				<p><strong><?php esc_html_e( 'AI Traffic.', 'omnigeo-ai-seo' ); ?></strong> <?php esc_html_e( 'This shows two different things. One is bots crawling you for training. The other, the one that really matters, is when a real person is reading your page inside an AI answer. You also get a ready made GA4 setup so you can track AI clicks the right way.', 'omnigeo-ai-seo' ); ?></p>
				<p><strong><?php esc_html_e( 'GEO Audit.', 'omnigeo-ai-seo' ); ?></strong> <?php esc_html_e( 'Paste any page from your site and get a score out of 100 with plain advice on what to fix. Handy for a quick check of a single page.', 'omnigeo-ai-seo' ); ?></p>
				<p><strong><?php esc_html_e( 'Content Extractability.', 'omnigeo-ai-seo' ); ?></strong> <?php esc_html_e( 'This is the box under the editor when you write a post or product. It scores how easily AI can pull a clean answer from your text. Turn on auto update and it refreshes as you type. The tips tell you exactly what to change, such as opening a section with a short, direct answer.', 'omnigeo-ai-seo' ); ?></p>
				<p><strong><?php esc_html_e( 'AI Score column.', 'omnigeo-ai-seo' ); ?></strong> <?php esc_html_e( 'On your Posts and Products lists you will see a score next to each item. Sort by it and fix the red ones first.', 'omnigeo-ai-seo' ); ?></p>
				<p><strong><?php esc_html_e( 'AI Visibility.', 'omnigeo-ai-seo' ); ?></strong> <?php esc_html_e( 'Want to know if AI actually mentions your brand? Add your own API key in Settings, then ask a buyer style question here. The plugin checks whether your name or website shows up in the answer. You pay your AI provider directly, so there is no extra subscription.', 'omnigeo-ai-seo' ); ?></p>
				<p><strong><?php esc_html_e( 'Site Health.', 'omnigeo-ai-seo' ); ?></strong> <?php esc_html_e( 'One click checks for the technical basics that decide whether AI can read you at all, like HTTPS, your sitemap and your robots file. You can also ping IndexNow when you publish something new.', 'omnigeo-ai-seo' ); ?></p>
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
		wp_add_dashboard_widget( 'aigs_widget', __( 'AI SEO Activity', 'omnigeo-ai-seo' ), array( $this, 'render_widget' ) );
	}

	/**
	 * Render the dashboard widget.
	 */
	public function render_widget() {
		$t = $this->modules['tracker'];
		echo '<p style="font-size:13px;line-height:1.7"><strong style="font-size:18px">' . (int) $t->count( 'fetch', 7 ) . '</strong> ' . esc_html__( 'reads inside AI answers · last 7 days', 'omnigeo-ai-seo' ) . '<br>';
		echo '<strong style="font-size:18px">' . (int) $t->count( 'crawl', 7 ) . '</strong> ' . esc_html__( 'AI index crawls · last 7 days', 'omnigeo-ai-seo' ) . '</p>';
		echo '<p><a class="button button-small" href="' . esc_url( admin_url( 'admin.php?page=omnigeo-ai-seo' ) ) . '">' . esc_html__( 'Open dashboard', 'omnigeo-ai-seo' ) . '</a></p>';
	}
}
