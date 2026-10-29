<?php
/**
 * Toolbox admin page: AI access, redirects and 404s, search and social
 * basics, and site tools. Four tabs, each card with one switch and a single
 * sentence on what it does, so nothing needs a manual.
 *
 * @package OmniGEO_Ai_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rendering for the Toolbox page (mixed into AIGS_Admin).
 */
trait AIGS_Admin_Toolbox {

	/**
	 * Tabs: slug => [label, icon, one-line purpose].
	 *
	 * @return array
	 */
	private static function toolbox_tabs() {
		return array(
			'ai'        => array( __( 'AI access', 'omnigeo-ai-seo' ), 'bot', __( 'Decide which AI crawlers may read your site and give them clean Markdown copies.', 'omnigeo-ai-seo' ) ),
			'redirects' => array( __( 'Redirects & 404s', 'omnigeo-ai-seo' ), 'link', __( 'Send old and broken addresses to the right page, so people and AI crawlers never hit a dead end.', 'omnigeo-ai-seo' ) ),
			'search'    => array( __( 'Search & social', 'omnigeo-ai-seo' ), 'search', __( 'Titles, descriptions, social cards, verification and analytics.', 'omnigeo-ai-seo' ) ),
			'tools'     => array( __( 'Tools & diagnostics', 'omnigeo-ai-seo' ), 'sliders', __( 'A health check that explains problems in plain words, plus handy extras.', 'omnigeo-ai-seo' ) ),
		);
	}

	/**
	 * A switch row: label + one sentence.
	 *
	 * @param string $key     Setting key.
	 * @param int    $default Default.
	 * @param string $label   Label.
	 * @param string $help    Help sentence.
	 */
	private static function tb_switch( $key, $default, $label, $help ) {
		?>
		<label class="aigs-switchrow">
			<input type="checkbox" class="aigs-switch" name="aigs_settings[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( (int) aigs_get( $key, $default ), 1 ); ?> />
			<span><strong><?php echo esc_html( $label ); ?></strong><em><?php echo esc_html( $help ); ?></em></span>
		</label>
		<?php
	}

	/**
	 * Open a Toolbox settings form for one tab.
	 *
	 * @param string $tab Tab.
	 */
	private static function tb_form_open( $tab ) {
		echo '<form method="post" action="options.php" class="aigs-tbform">';
		settings_fields( 'aigs_settings_group' );
		echo '<input type="hidden" name="aigs_settings[section_toolbox]" value="' . esc_attr( $tab ) . '" />';
		echo '<input type="hidden" name="_wp_http_referer" value="' . esc_attr( admin_url( 'admin.php?page=aigs-toolbox&tab=' . $tab ) ) . '" />';
	}

	/**
	 * Close the form with a sticky save bar.
	 */
	private static function tb_form_close() {
		echo '<div class="aigs-savebar"><button type="submit" class="aigs-btn aigs-btn--primary">' . wp_kses( self::icon( 'check', 16 ), self::svg_kses() ) . esc_html__( 'Save changes', 'omnigeo-ai-seo' ) . '</button></div></form>';
	}

	/**
	 * Toolbox page.
	 */
	public function page_toolbox() {
		$tabs = self::toolbox_tabs();
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'ai'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- tab navigation only.
		$tab  = isset( $tabs[ $tab ] ) ? $tab : 'ai';
		$this->open( __( 'Toolbox', 'omnigeo-ai-seo' ), __( 'Everything else your site needs, in one place. Each switch says what it does; the safe choices are already on.', 'omnigeo-ai-seo' ) );

		echo '<nav class="aigs-tabs" aria-label="' . esc_attr__( 'Toolbox sections', 'omnigeo-ai-seo' ) . '">';
		foreach ( $tabs as $slug => $t ) {
			$url = admin_url( 'admin.php?page=aigs-toolbox&tab=' . $slug );
			echo '<a class="aigs-tab' . ( $slug === $tab ? ' is-active' : '' ) . '" href="' . esc_url( $url ) . '"' . ( $slug === $tab ? ' aria-current="page"' : '' ) . '>' . wp_kses( self::icon( $t[1], 16 ), self::svg_kses() ) . '<span>' . esc_html( $t[0] ) . '</span>';
			if ( 'redirects' === $slug ) {
				$n = count( AIGS_Redirects::log() );
				if ( $n ) {
					echo '<b class="aigs-tab__badge">' . esc_html( number_format_i18n( $n ) ) . '</b>';
				}
			}
			echo '</a>';
		}
		echo '</nav>';
		echo '<p class="aigs-tabintro">' . esc_html( $tabs[ $tab ][2] ) . '</p>';

		if ( isset( $_GET['settings-updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
			echo '<div class="aigs-note is-ok" role="status">' . esc_html__( 'Saved. Changes are live now.', 'omnigeo-ai-seo' ) . '</div>';
		}

		switch ( $tab ) {
			case 'redirects':
				$this->tb_redirects();
				break;
			case 'search':
				$this->tb_search();
				break;
			case 'tools':
				$this->tb_tools();
				break;
			default:
				$this->tb_ai();
		}
		$this->close();
	}

	/**
	 * Tab: AI access.
	 */
	private function tb_ai() {
		$mode    = aigs_get( 'ai_access', 'allow' );
		$blocked = (array) aigs_get( 'ai_blocked', array() );
		if ( 'custom' !== $mode ) {
			$blocked = AIGS_LLMS::blocked_tokens( $mode );
		}
		$classes = array(
			'fetch'    => array( __( 'Answering a person right now', 'omnigeo-ai-seo' ), __( 'Someone asked an assistant a question and it is reading your page to answer. Blocking these removes you from live answers.', 'omnigeo-ai-seo' ) ),
			'search'   => array( __( 'AI search indexes', 'omnigeo-ai-seo' ), __( 'Build the index that AI search products cite. Keep these on to be found.', 'omnigeo-ai-seo' ) ),
			'training' => array( __( 'Model training', 'omnigeo-ai-seo' ), __( 'Collect text to train future models. Blocking them does not remove you from AI search.', 'omnigeo-ai-seo' ) ),
		);
		$groups = array(
			'fetch'    => array(),
			'search'   => array(),
			'training' => array(),
		);
		foreach ( AIGS_Tracker::bot_map() as $bot => $info ) {
			if ( in_array( $bot, array( 'bingbot', 'Applebot' ), true ) ) {
				continue;
			}
			$groups[ $info[2] ][ $bot ] = $info[0];
		}
		foreach ( AIGS_Tracker::robots_only_tokens() as $token => $class ) {
			$groups[ $class ][ $token ] = 'Google-Extended' === $token ? 'Google (Gemini)' : 'Apple Intelligence';
		}
		$seen = array();
		foreach ( ( new AIGS_Tracker() )->by_bot( 30 ) as $row ) {
			$row = (array) $row;
			if ( isset( $row['bot'], $row['c'] ) ) {
				$seen[ $row['bot'] ] = (int) $row['c'];
			}
		}
		self::tb_form_open( 'ai' );
		?>
		<div class="aigs-card">
			<div class="aigs-card__head"><?php self::i( 'shield' ); ?><h2><?php esc_html_e( 'Who may read your site', 'omnigeo-ai-seo' ); ?></h2></div>
			<div class="aigs-presets" role="radiogroup">
				<?php
				$presets = array(
					'allow'       => array( __( 'Open', 'omnigeo-ai-seo' ), __( 'Every AI crawler follows your normal rules.', 'omnigeo-ai-seo' ) ),
					'search_only' => array( __( 'Be found, don\'t train', 'omnigeo-ai-seo' ), __( 'AI search and live answers yes, model training no.', 'omnigeo-ai-seo' ) ),
					'disallow'    => array( __( 'Closed', 'omnigeo-ai-seo' ), __( 'Block every known AI crawler. You will not appear in AI answers.', 'omnigeo-ai-seo' ) ),
					'custom'      => array( __( 'Choose per crawler', 'omnigeo-ai-seo' ), __( 'Pick each crawler yourself below.', 'omnigeo-ai-seo' ) ),
				);
				foreach ( $presets as $val => $p ) :
					?>
					<label class="aigs-preset"><input type="radio" name="aigs_settings[ai_access]" value="<?php echo esc_attr( $val ); ?>" <?php checked( $mode, $val ); ?> /><span><strong><?php echo esc_html( $p[0] ); ?></strong><em><?php echo esc_html( $p[1] ); ?></em></span></label>
				<?php endforeach; ?>
			</div>
			<div class="aigs-botgrid" data-aigs-bots>
				<?php foreach ( $groups as $class => $bots ) : ?>
					<div class="aigs-botcol">
						<h3><?php echo esc_html( $classes[ $class ][0] ); ?></h3>
						<p class="description"><?php echo esc_html( $classes[ $class ][1] ); ?></p>
						<?php foreach ( $bots as $bot => $owner ) : ?>
							<label class="aigs-bot" data-class="<?php echo esc_attr( $class ); ?>">
								<input type="checkbox" class="aigs-switch" name="aigs_settings[ai_allowed][]" value="<?php echo esc_attr( $bot ); ?>" <?php checked( ! in_array( $bot, $blocked, true ) ); ?> />
								<span><strong><?php echo esc_html( $bot ); ?></strong><em><?php echo esc_html( $owner ); ?><?php echo isset( $seen[ $bot ] ) ? ' – ' . esc_html( sprintf( /* translators: %s: number of requests. */ __( '%s visits in 30 days', 'omnigeo-ai-seo' ), number_format_i18n( $seen[ $bot ] ) ) ) : ''; ?></em></span>
							</label>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
			</div>
			<p class="description"><?php esc_html_e( 'Bing and Apple search are never blocked here. If Settings → Reading discourages search engines, no AI rules are added. robots.txt is a request that reputable crawlers honour, not a lock.', 'omnigeo-ai-seo' ); ?></p>
		</div>

		<div class="aigs-card">
			<div class="aigs-card__head"><?php self::i( 'flag' ); ?><h2><?php esc_html_e( 'Content Signals', 'omnigeo-ai-seo' ); ?></h2></div>
			<?php self::tb_switch( 'cs_enabled', 0, __( 'Tell AI companies how your content may be used', 'omnigeo-ai-seo' ), __( 'Adds a Content-Signal line to robots.txt (the open contentsignals.org format). It states your terms; it does not block anyone.', 'omnigeo-ai-seo' ) ); ?>
			<div class="aigs-signals">
				<?php
				$signals = array(
					'cs_search'   => array( __( 'Search results', 'omnigeo-ai-seo' ), 'yes' ),
					'cs_ai_input' => array( __( 'AI answers (quoting and summarising)', 'omnigeo-ai-seo' ), 'yes' ),
					'cs_ai_train' => array( __( 'Training AI models', 'omnigeo-ai-seo' ), 'no' ),
				);
				foreach ( $signals as $k => $sig ) :
					$v = aigs_get( $k, $sig[1] );
					?>
					<div class="aigs-signal"><span><?php echo esc_html( $sig[0] ); ?></span>
						<label><input type="radio" name="aigs_settings[<?php echo esc_attr( $k ); ?>]" value="yes" <?php checked( $v, 'yes' ); ?> /> <?php esc_html_e( 'Yes', 'omnigeo-ai-seo' ); ?></label>
						<label><input type="radio" name="aigs_settings[<?php echo esc_attr( $k ); ?>]" value="no" <?php checked( $v, 'no' ); ?> /> <?php esc_html_e( 'No', 'omnigeo-ai-seo' ); ?></label>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="aigs-card">
			<div class="aigs-card__head"><?php self::i( 'file' ); ?><h2><?php esc_html_e( 'Markdown copies for AI', 'omnigeo-ai-seo' ); ?></h2></div>
			<?php self::tb_switch( 'tb_markdown', 1, __( 'Offer every page as clean Markdown', 'omnigeo-ai-seo' ), __( 'Assistants read text, not themes. Each public page gets a Markdown copy at its address plus ?format=markdown, and /llms-full.txt bundles your main pages. Same words as the page, never indexed, canonical points to the real page.', 'omnigeo-ai-seo' ) ); ?>
			<?php
			$sample = get_posts(
				array(
					'numberposts' => 1,
					'post_status' => 'publish',
				)
			);
			if ( aigs_get( 'tb_markdown', 1 ) ) :
				?>
				<p class="aigs-links">
					<a href="<?php echo esc_url( home_url( '/llms-full.txt' ) ); ?>" target="_blank" rel="noopener"><code>/llms-full.txt</code></a>
					<?php if ( $sample ) : ?>
						<a href="<?php echo esc_url( AIGS_Markdown::url( $sample[0]->ID ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'See an example page as Markdown', 'omnigeo-ai-seo' ); ?></a>
					<?php endif; ?>
				</p>
			<?php endif; ?>
		</div>

		<details class="aigs-card aigs-more">
			<summary><?php self::i( 'edit' ); ?><span><?php esc_html_e( 'Your own robots.txt lines (advanced)', 'omnigeo-ai-seo' ); ?></span></summary>
			<p class="description"><?php esc_html_e( 'Added at the end of robots.txt. Only User-agent, Allow, Disallow, Crawl-delay, Sitemap, Content-Signal and # comment lines are kept, so a typo cannot break the file.', 'omnigeo-ai-seo' ); ?></p>
			<textarea name="aigs_settings[robots_extra]" rows="6" class="large-text aigs-code" placeholder="User-agent: SomeBot&#10;Disallow: /private/"><?php echo esc_textarea( aigs_get( 'robots_extra', '' ) ); ?></textarea>
			<p><a href="<?php echo esc_url( home_url( '/robots.txt' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View your live robots.txt', 'omnigeo-ai-seo' ); ?></a></p>
		</details>
		<?php
		self::tb_form_close();
	}

	/**
	 * Tab: redirects and 404s.
	 */
	private function tb_redirects() {
		$rules = AIGS_Redirects::rules();
		$used  = get_option( AIGS_Redirects::USED, array() );
		$log   = AIGS_Redirects::log();
		if ( isset( $_GET['imported'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
			/* translators: 1: added, 2: skipped. */
			echo '<div class="aigs-note is-ok" role="status">' . esc_html( sprintf( __( 'Imported %1$d redirects, skipped %2$d lines.', 'omnigeo-ai-seo' ), absint( $_GET['imported'] ), isset( $_GET['skipped'] ) ? absint( $_GET['skipped'] ) : 0 ) ) . '</div>'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		?>
		<div class="aigs-card">
			<div class="aigs-card__head"><?php self::i( 'flag' ); ?><h2><?php esc_html_e( 'Broken addresses people and bots tried', 'omnigeo-ai-seo' ); ?></h2>
				<?php if ( $log ) : ?><button type="button" class="aigs-btn aigs-btn--ghost aigs-push" data-aigs-404-clear=""><?php esc_html_e( 'Clear list', 'omnigeo-ai-seo' ); ?></button><?php endif; ?>
			</div>
			<?php if ( ! aigs_get( 'tb_redirects', 1 ) ) : ?>
				<p class="aigs-empty"><?php esc_html_e( 'Turn on the redirect engine below to start the 404 log.', 'omnigeo-ai-seo' ); ?></p>
			<?php elseif ( ! $log ) : ?>
				<p class="aigs-empty"><?php self::i( 'check' ); ?> <?php esc_html_e( 'No broken addresses recorded yet. New ones appear here with a suggested fix.', 'omnigeo-ai-seo' ); ?></p>
			<?php else : ?>
				<table class="aigs-table aigs-404">
					<thead><tr><th><?php esc_html_e( 'Address', 'omnigeo-ai-seo' ); ?></th><th><?php esc_html_e( 'Hits', 'omnigeo-ai-seo' ); ?></th><th><?php esc_html_e( 'Fix', 'omnigeo-ai-seo' ); ?></th></tr></thead>
					<tbody>
					<?php
					$shown = 0;
					foreach ( $log as $key => $row ) :
						if ( ++$shown > 30 ) {
							break;
						}
						$sug = AIGS_Redirects::suggest( $key );
						?>
						<tr data-key="<?php echo esc_attr( $key ); ?>">
							<td><code><?php echo esc_html( $key ); ?></code>
								<div class="aigs-sub">
									<?php echo esc_html( self::ago( $row['last'] ) ); ?>
									<?php if ( ! empty( $row['bot'] ) ) : ?><span class="aigs-chip aigs-chip--ai"><?php echo esc_html( $row['bot'] ); ?></span><?php endif; ?>
									<?php if ( ! empty( $row['ref'] ) ) : ?><span title="<?php echo esc_attr( $row['ref'] ); ?>"><?php esc_html_e( 'linked from', 'omnigeo-ai-seo' ); ?> <?php echo esc_html( (string) wp_parse_url( $row['ref'], PHP_URL_HOST ) ); ?></span><?php endif; ?>
								</div>
							</td>
							<td><?php echo esc_html( number_format_i18n( $row['count'] ) ); ?></td>
							<td class="aigs-404__fix">
								<input type="text" class="aigs-input" value="<?php echo esc_attr( $sug ? $sug[1] : '' ); ?>" placeholder="<?php esc_attr_e( 'Send to… (/page/ or full URL)', 'omnigeo-ai-seo' ); ?>" />
								<button type="button" class="aigs-btn aigs-btn--primary aigs-btn--sm" data-aigs-404-fix><?php esc_html_e( 'Redirect', 'omnigeo-ai-seo' ); ?></button>
								<button type="button" class="aigs-btn aigs-btn--ghost aigs-btn--sm" data-aigs-404-gone title="<?php esc_attr_e( 'Tell crawlers this page is gone for good (410)', 'omnigeo-ai-seo' ); ?>"><?php esc_html_e( 'Gone', 'omnigeo-ai-seo' ); ?></button>
								<button type="button" class="aigs-btn aigs-btn--ghost aigs-btn--sm" data-aigs-404-clear="<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Ignore', 'omnigeo-ai-seo' ); ?></button>
								<?php if ( $sug ) : ?>
									<div class="aigs-sub"><?php self::i( 'sparkles', 13 ); ?> <?php /* translators: %s: page title. */ echo esc_html( sprintf( __( 'Suggested: %s', 'omnigeo-ai-seo' ), $sug[2] ) ); ?></div>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<div class="aigs-card">
			<div class="aigs-card__head"><?php self::i( 'link' ); ?><h2><?php esc_html_e( 'Redirects', 'omnigeo-ai-seo' ); ?></h2><span class="aigs-chip"><?php echo esc_html( number_format_i18n( count( $rules ) ) ); ?></span></div>
			<div class="aigs-redir-add">
				<input type="text" class="aigs-input" id="aigs-r-from" placeholder="<?php esc_attr_e( 'Old address, e.g. /old-page/', 'omnigeo-ai-seo' ); ?>" />
				<span class="aigs-redir-arrow" aria-hidden="true">→</span>
				<input type="text" class="aigs-input" id="aigs-r-to" placeholder="<?php esc_attr_e( 'New address, e.g. /new-page/', 'omnigeo-ai-seo' ); ?>" />
				<select id="aigs-r-code" class="aigs-input">
					<option value="301"><?php esc_html_e( '301 Moved for good', 'omnigeo-ai-seo' ); ?></option>
					<option value="302"><?php esc_html_e( '302 Temporary', 'omnigeo-ai-seo' ); ?></option>
					<option value="307"><?php esc_html_e( '307 Temporary (keep method)', 'omnigeo-ai-seo' ); ?></option>
					<option value="410"><?php esc_html_e( '410 Gone for good', 'omnigeo-ai-seo' ); ?></option>
				</select>
				<button type="button" class="aigs-btn aigs-btn--primary" id="aigs-r-add"><?php esc_html_e( 'Add redirect', 'omnigeo-ai-seo' ); ?></button>
			</div>
			<p class="description"><?php esc_html_e( 'Chains are flattened automatically (A→B and B→C become A→C) and loops are refused. Campaign parameters such as utm_source are kept.', 'omnigeo-ai-seo' ); ?></p>
			<?php if ( $rules ) : ?>
				<input type="search" class="aigs-input aigs-filter" data-aigs-filter=".aigs-redirs tbody tr" placeholder="<?php esc_attr_e( 'Filter…', 'omnigeo-ai-seo' ); ?>" />
				<table class="aigs-table aigs-redirs">
					<thead><tr><th><?php esc_html_e( 'From', 'omnigeo-ai-seo' ); ?></th><th><?php esc_html_e( 'To', 'omnigeo-ai-seo' ); ?></th><th><?php esc_html_e( 'Type', 'omnigeo-ai-seo' ); ?></th><th><?php esc_html_e( 'Last used', 'omnigeo-ai-seo' ); ?></th><th></th></tr></thead>
					<tbody>
					<?php
					$src_label = array(
						'auto'   => __( 'automatic', 'omnigeo-ai-seo' ),
						'import' => __( 'imported', 'omnigeo-ai-seo' ),
						'404'    => __( 'from 404 log', 'omnigeo-ai-seo' ),
					);
					foreach ( array_reverse( $rules, true ) as $key => $r ) :
						?>
						<tr data-key="<?php echo esc_attr( $key ); ?>">
							<td><code><?php echo esc_html( $key ); ?></code><?php if ( isset( $src_label[ $r['src'] ] ) ) : ?> <span class="aigs-chip"><?php echo esc_html( $src_label[ $r['src'] ] ); ?></span><?php endif; ?></td>
							<td><?php echo 410 === (int) $r['code'] ? '<em>' . esc_html__( 'Gone', 'omnigeo-ai-seo' ) . '</em>' : '<a href="' . esc_url( $r['to'] ) . '" target="_blank" rel="noopener">' . esc_html( $r['to'] ) . '</a>'; ?></td>
							<td><?php echo esc_html( (int) $r['code'] ); ?></td>
							<td><?php echo isset( $used[ $key ] ) ? esc_html( self::ago( $used[ $key ] ) ) : '<span class="aigs-sub">' . esc_html__( 'not yet', 'omnigeo-ai-seo' ) . '</span>'; ?></td>
							<td><button type="button" class="aigs-btn aigs-btn--ghost aigs-btn--sm" data-aigs-r-del><?php esc_html_e( 'Delete', 'omnigeo-ai-seo' ); ?></button></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<details class="aigs-card aigs-more">
			<summary><?php self::i( 'list' ); ?><span><?php esc_html_e( 'Import or export (CSV)', 'omnigeo-ai-seo' ); ?></span></summary>
			<p><a class="aigs-btn" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=aigs_redirects_csv' ), 'aigs_redirects_csv' ) ); ?>"><?php esc_html_e( 'Download all redirects', 'omnigeo-ai-seo' ); ?></a></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="aigs_redirects_import" />
				<?php wp_nonce_field( 'aigs_redirects_import' ); ?>
				<p class="description"><?php esc_html_e( 'Paste one redirect per line: old address, new address, and optionally the code (301 if empty). Commas or tabs both work, so exports from other redirect plugins and spreadsheets paste straight in.', 'omnigeo-ai-seo' ); ?></p>
				<textarea name="csv" rows="6" class="large-text aigs-code" placeholder="/old-page/,/new-page/,301"></textarea>
				<p><button type="submit" class="aigs-btn"><?php esc_html_e( 'Import', 'omnigeo-ai-seo' ); ?></button></p>
			</form>
		</details>
		<?php
		self::tb_form_open( 'redirects' );
		echo '<div class="aigs-card">';
		self::tb_switch( 'tb_redirects', 1, __( 'Redirect engine and 404 log', 'omnigeo-ai-seo' ), __( 'Runs your redirects and records broken addresses. Scanner noise such as .php and .env probes is ignored.', 'omnigeo-ai-seo' ) );
		self::tb_switch( 'tb_auto_redirect', 1, __( 'Redirect automatically when an address changes', 'omnigeo-ai-seo' ), __( 'Change a slug, parent page or date and the old address keeps working with a 301 to the new one.', 'omnigeo-ai-seo' ) );
		echo '</div>';
		self::tb_form_close();
	}

	/**
	 * Tab: search and social.
	 */
	private function tb_search() {
		$seo = AIGS_SEO::name();
		if ( '' !== $seo ) {
			?>
			<div class="aigs-callout"><?php echo wp_kses( self::icon( 'info', 18 ), self::svg_kses() ); ?>
				<p><?php /* translators: %s: SEO plugin name. */ echo esc_html( sprintf( __( '%s is active, so it keeps handling titles, descriptions, social cards and sitemaps. OmniGEO reads its values and stays out of the way to avoid duplicate tags. Verification and analytics below still work if you have not set them up there.', 'omnigeo-ai-seo' ), $seo ) ); ?></p>
			</div>
			<?php
		}
		self::tb_form_open( 'search' );
		if ( '' === $seo ) :
			?>
			<div class="aigs-card">
				<div class="aigs-card__head"><?php self::i( 'search' ); ?><h2><?php esc_html_e( 'Titles and descriptions', 'omnigeo-ai-seo' ); ?></h2></div>
				<?php
				self::tb_switch( 'tb_snippets', 1, __( 'Search & AI snippet box in the editor', 'omnigeo-ai-seo' ), __( 'Write the title and description shown in Google and quoted by assistants, with a live preview. Values left by a removed SEO plugin are used automatically; nothing to import.', 'omnigeo-ai-seo' ) );
				self::tb_switch( 'meta_fallback', 0, __( 'Automatic description when you leave it empty', 'omnigeo-ai-seo' ), __( 'Builds one from the excerpt or the opening paragraph.', 'omnigeo-ai-seo' ) );
				self::tb_switch( 'tb_lastmod', 1, __( 'Last-updated dates in the WordPress sitemap', 'omnigeo-ai-seo' ), __( 'Tells search engines and AI crawlers which pages changed, so they re-read those first.', 'omnigeo-ai-seo' ) );
				?>
			</div>

			<div class="aigs-card">
				<div class="aigs-card__head"><?php self::i( 'globe' ); ?><h2><?php esc_html_e( 'Social sharing cards', 'omnigeo-ai-seo' ); ?></h2></div>
				<?php self::tb_switch( 'tb_social', 0, __( 'Open Graph and X cards', 'omnigeo-ai-seo' ), __( 'Controls the title, text and image shown when a page is shared on Facebook, LinkedIn, X, WhatsApp or in AI chat apps. Skipped automatically when Jetpack already prints them.', 'omnigeo-ai-seo' ) ); ?>
				<table class="form-table" role="presentation">
					<tr><th><label for="aigs_ogimg"><?php esc_html_e( 'Default image', 'omnigeo-ai-seo' ); ?></label></th>
						<td><input type="url" id="aigs_ogimg" class="regular-text" name="aigs_settings[og_image]" value="<?php echo esc_attr( aigs_get( 'og_image', '' ) ); ?>" placeholder="https://example.com/share.jpg" />
						<p class="description"><?php esc_html_e( 'Used when a page has no featured image. 1200 × 630 pixels works everywhere.', 'omnigeo-ai-seo' ); ?></p></td></tr>
					<tr><th><label for="aigs_tw"><?php esc_html_e( 'X (Twitter) username', 'omnigeo-ai-seo' ); ?></label></th>
						<td><input type="text" id="aigs_tw" class="regular-text" name="aigs_settings[twitter_site]" value="<?php echo esc_attr( aigs_get( 'twitter_site', '' ) ); ?>" placeholder="@yourbrand" /></td></tr>
				</table>
			</div>
		<?php endif; ?>

		<div class="aigs-card">
			<div class="aigs-card__head"><?php self::i( 'check' ); ?><h2><?php esc_html_e( 'Verify your site', 'omnigeo-ai-seo' ); ?></h2></div>
			<p class="aigs-card__desc"><?php esc_html_e( 'Paste the code or the whole <meta> tag each service gives you; the code is picked out for you. Printed on the homepage only.', 'omnigeo-ai-seo' ); ?></p>
			<table class="form-table" role="presentation">
				<?php
				$services = array(
					'verify_google'    => array( 'Google Search Console', 'https://search.google.com/search-console' ),
					'verify_bing'      => array( 'Bing Webmaster Tools', 'https://www.bing.com/webmasters' ),
					'verify_yandex'    => array( 'Yandex Webmaster', 'https://webmaster.yandex.com' ),
					'verify_baidu'     => array( 'Baidu', 'https://ziyuan.baidu.com' ),
					'verify_pinterest' => array( 'Pinterest', 'https://www.pinterest.com/settings/claim' ),
				);
				foreach ( $services as $k => $svc ) :
					?>
					<tr><th><label for="aigs_<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $svc[0] ); ?></label></th>
						<td><input type="text" id="aigs_<?php echo esc_attr( $k ); ?>" class="regular-text" name="aigs_settings[<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( aigs_get( $k, '' ) ); ?>" />
						<a href="<?php echo esc_url( $svc[1] ); ?>" target="_blank" rel="noopener" class="aigs-sub"><?php esc_html_e( 'Get code', 'omnigeo-ai-seo' ); ?></a></td></tr>
				<?php endforeach; ?>
			</table>
			<p class="description"><?php esc_html_e( 'Bing Webmaster Tools also shows how often Copilot cites your pages, so it is worth verifying.', 'omnigeo-ai-seo' ); ?></p>
		</div>

		<div class="aigs-card">
			<div class="aigs-card__head"><?php self::i( 'activity' ); ?><h2><?php esc_html_e( 'Analytics', 'omnigeo-ai-seo' ); ?></h2></div>
			<?php if ( defined( 'GOOGLESITEKIT_VERSION' ) ) : ?>
				<p class="aigs-card__desc"><?php esc_html_e( 'Site Kit by Google is active and already adds Google tags, so nothing is added here to avoid counting visits twice.', 'omnigeo-ai-seo' ); ?></p>
			<?php endif; ?>
			<table class="form-table" role="presentation">
				<tr><th><label for="aigs_ga4"><?php esc_html_e( 'GA4 measurement ID', 'omnigeo-ai-seo' ); ?></label></th>
					<td><input type="text" id="aigs_ga4" class="regular-text" name="aigs_settings[ga4_id]" value="<?php echo esc_attr( aigs_get( 'ga4_id', '' ) ); ?>" placeholder="G-XXXXXXXXXX" /></td></tr>
				<tr><th><label for="aigs_gtm"><?php esc_html_e( 'or Tag Manager container', 'omnigeo-ai-seo' ); ?></label></th>
					<td><input type="text" id="aigs_gtm" class="regular-text" name="aigs_settings[gtm_id]" value="<?php echo esc_attr( aigs_get( 'gtm_id', '' ) ); ?>" placeholder="GTM-XXXXXXX" />
					<p class="description"><?php esc_html_e( 'Use one of the two. Logged-in editors are not counted. The AI Traffic page gives you a ready GA4 channel for AI assistants.', 'omnigeo-ai-seo' ); ?></p></td></tr>
			</table>
		</div>
		<?php
		self::tb_form_close();

		if ( AIGS_Head::snippets_on() ) {
			$this->tb_bulk_snippets();
		}
	}

	/**
	 * Bulk snippet editor: 20 pages at a time, saved row by row.
	 */
	private function tb_bulk_snippets() {
		$paged = isset( $_GET['sp'] ) ? max( 1, absint( $_GET['sp'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- pagination.
		$only  = isset( $_GET['empty'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filter.
		$types = get_post_types( array( 'public' => true ) );
		unset( $types['attachment'] );
		$args = array(
			'post_type'      => array_values( $types ),
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'paged'          => $paged,
			'orderby'        => 'modified',
		);
		if ( $only ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- admin-only listing, 20 rows.
				array(
					'key'     => '_aigs_seo_desc',
					'compare' => 'NOT EXISTS',
				),
			);
		}
		$q    = new WP_Query( $args );
		$base = admin_url( 'admin.php?page=aigs-toolbox&tab=search' );
		?>
		<div class="aigs-card" id="aigs-bulk">
			<div class="aigs-card__head"><?php self::i( 'edit' ); ?><h2><?php esc_html_e( 'Edit many snippets at once', 'omnigeo-ai-seo' ); ?></h2>
				<a class="aigs-btn aigs-btn--ghost aigs-push" href="<?php echo esc_url( $only ? $base . '#aigs-bulk' : $base . '&empty=1#aigs-bulk' ); ?>"><?php echo $only ? esc_html__( 'Show all', 'omnigeo-ai-seo' ) : esc_html__( 'Only pages without a description', 'omnigeo-ai-seo' ); ?></a>
			</div>
			<p class="aigs-card__desc"><?php esc_html_e( 'Each row saves on its own when you click Save. Empty fields fall back to the page title and the automatic description.', 'omnigeo-ai-seo' ); ?></p>
			<?php if ( ! $q->have_posts() ) : ?>
				<p class="aigs-empty"><?php self::i( 'check' ); ?> <?php esc_html_e( 'Nothing here. Every page has a description.', 'omnigeo-ai-seo' ); ?></p>
			<?php else : ?>
				<div class="aigs-bulk">
				<?php foreach ( $q->posts as $p ) : ?>
					<div class="aigs-bulk__row" data-post="<?php echo esc_attr( $p->ID ); ?>">
						<div class="aigs-bulk__name"><a href="<?php echo esc_url( get_edit_post_link( $p->ID ) ); ?>"><?php echo esc_html( get_the_title( $p ) ? get_the_title( $p ) : __( '(no title)', 'omnigeo-ai-seo' ) ); ?></a><span class="aigs-sub"><?php echo esc_html( get_post_type_object( $p->post_type )->labels->singular_name ); ?></span></div>
						<?php AIGS_Head::snippet_fields( $p->ID, AIGS_Head::snippet( $p->ID, 'title' ), AIGS_Head::snippet( $p->ID, 'desc' ), AIGS_Head::auto_description( $p ), 'aigs_bulk_' . $p->ID ); ?>
						<button type="button" class="aigs-btn aigs-btn--sm" data-aigs-snippet-save><?php esc_html_e( 'Save', 'omnigeo-ai-seo' ); ?></button>
					</div>
				<?php endforeach; ?>
				</div>
				<?php
				$pages = (int) $q->max_num_pages;
				if ( $pages > 1 ) {
					echo '<p class="aigs-pager">';
					for ( $i = 1; $i <= min( $pages, 30 ); $i++ ) {
						$url = add_query_arg( array( 'sp' => $i ), $only ? $base . '&empty=1' : $base ) . '#aigs-bulk';
						echo $i === $paged ? '<strong>' . esc_html( number_format_i18n( $i ) ) . '</strong> ' : '<a href="' . esc_url( $url ) . '">' . esc_html( number_format_i18n( $i ) ) . '</a> ';
					}
					echo '</p>';
				}
				?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Tab: tools and diagnostics.
	 */
	private function tb_tools() {
		$labels = array(
			'ok'   => __( 'OK', 'omnigeo-ai-seo' ),
			'warn' => __( 'Check', 'omnigeo-ai-seo' ),
			'fail' => __( 'Fix', 'omnigeo-ai-seo' ),
			'info' => __( 'Info', 'omnigeo-ai-seo' ),
		);
		$fixes  = array(
			'rewrites'    => __( 'Repair now', 'omnigeo-ai-seo' ),
			'cron'        => __( 'Schedule again', 'omnigeo-ai-seo' ),
			'db'          => __( 'Repair tables', 'omnigeo-ai-seo' ),
			'attachments' => __( 'Turn them off', 'omnigeo-ai-seo' ),
		);
		if ( isset( $_GET['repaired'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
			echo '<div class="aigs-note is-ok" role="status">' . esc_html__( 'Done. The check below shows the new state.', 'omnigeo-ai-seo' ) . '</div>';
		}
		if ( isset( $_GET['backup'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
			$ok = 'restored' === $_GET['backup']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="aigs-note ' . ( $ok ? 'is-ok' : 'is-bad' ) . '" role="status">' . ( $ok ? esc_html__( 'Settings restored. Your AI key and IndexNow key were kept as they were.', 'omnigeo-ai-seo' ) : esc_html__( 'This file is not an OmniGEO Ai SEO backup.', 'omnigeo-ai-seo' ) ) . '</div>';
		}
		$diag   = AIGS_Tools::diagnostics();
		$issues = count(
			array_filter(
				$diag,
				static function ( $d ) {
					return in_array( $d[0], array( 'warn', 'fail' ), true );
				}
			)
		);
		?>
		<div class="aigs-card">
			<div class="aigs-card__head"><?php self::i( 'gauge' ); ?><h2><?php esc_html_e( 'Setup check', 'omnigeo-ai-seo' ); ?></h2>
				<span class="aigs-chip <?php echo $issues ? 'aigs-chip--warn' : 'aigs-chip--ok'; ?>"><?php echo $issues ? esc_html( sprintf( /* translators: %d: number of items. */ _n( '%d item to look at', '%d items to look at', $issues, 'omnigeo-ai-seo' ), $issues ) ) : esc_html__( 'All good', 'omnigeo-ai-seo' ); ?></span>
			</div>
			<ul class="aigs-diag">
				<?php foreach ( $diag as $d ) : ?>
					<li class="is-<?php echo esc_attr( $d[0] ); ?>">
						<span class="aigs-diag__pill"><?php echo esc_html( $labels[ $d[0] ] ); ?></span>
						<div><strong><?php echo esc_html( $d[1] ); ?></strong><?php if ( '' !== $d[2] ) : ?><p><?php echo esc_html( $d[2] ); ?></p><?php endif; ?></div>
						<?php if ( '' !== $d[3] && isset( $fixes[ $d[3] ] ) ) : ?>
							<a class="aigs-btn aigs-btn--sm aigs-btn--primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=aigs_repair&what=' . $d[3] ), 'aigs_repair' ) ); ?>"><?php echo esc_html( $fixes[ $d[3] ] ); ?></a>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
		self::tb_form_open( 'tools' );
		?>
		<div class="aigs-card">
			<div class="aigs-card__head"><?php self::i( 'link' ); ?><h2><?php esc_html_e( 'Links to other websites', 'omnigeo-ai-seo' ); ?></h2></div>
			<?php
			$mode  = aigs_get( 'tb_ext_links', 'off' );
			$modes = array(
				'off'            => array( __( 'Leave links as written', 'omnigeo-ai-seo' ), __( 'Default.', 'omnigeo-ai-seo' ) ),
				'blank'          => array( __( 'Open in a new tab', 'omnigeo-ai-seo' ), __( 'Adds target="_blank" and rel="noopener" for safety.', 'omnigeo-ai-seo' ) ),
				'blank_nofollow' => array( __( 'New tab + nofollow', 'omnigeo-ai-seo' ), __( 'Also asks search engines not to pass ranking credit. Links already marked sponsored or ugc are left alone.', 'omnigeo-ai-seo' ) ),
				'nofollow'       => array( __( 'Nofollow only', 'omnigeo-ai-seo' ), __( 'Same tab, no ranking credit.', 'omnigeo-ai-seo' ) ),
			);
			foreach ( $modes as $val => $m ) :
				?>
				<label class="aigs-radio"><input type="radio" name="aigs_settings[tb_ext_links]" value="<?php echo esc_attr( $val ); ?>" <?php checked( $mode, $val ); ?> /> <span><strong><?php echo esc_html( $m[0] ); ?></strong><em><?php echo esc_html( $m[1] ); ?></em></span></label>
			<?php endforeach; ?>
			<p class="description"><?php esc_html_e( 'Applied when pages are shown; your saved content is not changed, so switching back is instant. Links to your own site and sub-domains are never touched.', 'omnigeo-ai-seo' ); ?></p>
		</div>
		<div class="aigs-card">
			<div class="aigs-card__head"><?php self::i( 'file' ); ?><h2><?php esc_html_e( 'Editing helpers', 'omnigeo-ai-seo' ); ?></h2></div>
			<?php self::tb_switch( 'tb_duplicate', 0, __( 'Duplicate as draft', 'omnigeo-ai-seo' ), __( 'Adds a link under each post and page title in the list that copies it, with its categories, schema and FAQs, into a new draft.', 'omnigeo-ai-seo' ) ); ?>
		</div>
		<?php
		self::tb_form_close();
		?>
		<div class="aigs-card">
			<div class="aigs-card__head"><?php self::i( 'refresh' ); ?><h2><?php esc_html_e( 'Back up or move your settings', 'omnigeo-ai-seo' ); ?></h2></div>
			<p class="aigs-card__desc"><?php esc_html_e( 'One file with every OmniGEO setting and redirect. Use it before big changes or to copy your setup to another site. API keys are never included.', 'omnigeo-ai-seo' ); ?></p>
			<p><a class="aigs-btn" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=aigs_export' ), 'aigs_export' ) ); ?>"><?php esc_html_e( 'Download backup', 'omnigeo-ai-seo' ); ?></a></p>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aigs-inline">
				<input type="hidden" name="action" value="aigs_import" />
				<?php wp_nonce_field( 'aigs_import' ); ?>
				<input type="file" name="backup" accept=".json,application/json" required />
				<button type="submit" class="aigs-btn"><?php esc_html_e( 'Restore', 'omnigeo-ai-seo' ); ?></button>
			</form>
		</div>
		<?php
	}
}
