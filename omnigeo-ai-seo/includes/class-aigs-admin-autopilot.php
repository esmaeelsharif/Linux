<?php
/**
 * AI Autopilot page (pick → generate → review → apply) and the Support page.
 *
 * @package OmniGEO_Ai_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rendering for AI Autopilot and Support (mixed into AIGS_Admin).
 */
trait AIGS_Admin_Autopilot {

	/**
	 * AI Autopilot page.
	 */
	public function page_autopilot() {
		$kinds   = AIGS_Optimizer::kinds();
		$ready   = AIGS_AI_Client::ready();
		$provs   = AIGS_AI_Client::providers();
		$prov    = AIGS_AI_Client::provider();
		$snip_ok = AIGS_Optimizer::can_write_snippets();
		$this->open( __( 'AI Autopilot', 'omnigeo-ai-seo' ), __( 'AI writes the improvements, you approve them. Nothing goes live until you say so, and every change can be undone.', 'omnigeo-ai-seo' ) );
		?>
		<div class="aigs-byok<?php echo $ready ? ' is-ready' : ''; ?>">
			<div class="aigs-byok__icon"><?php self::i( 'zap', 22 ); ?></div>
			<div class="aigs-byok__body">
				<strong><?php esc_html_e( 'Your key, your price. No credits, no markup.', 'omnigeo-ai-seo' ); ?></strong>
				<p><?php esc_html_e( 'Other AI SEO plugins sell you AI credits. OmniGEO talks to the AI provider you choose with your own API key, so you pay the provider directly. With small models that is usually less than one cent per page, and a local model costs nothing.', 'omnigeo-ai-seo' ); ?></p>
				<?php if ( $ready ) : ?>
					<p class="aigs-byok__meta">
						<?php
						/* translators: 1: provider, 2: model, 3: requests left, 4: monthly limit. */
						echo esc_html( sprintf( __( 'Using %1$s – %2$s – %3$d of %4$d requests left this month', 'omnigeo-ai-seo' ), $provs[ $prov ]['label'], AIGS_AI_Client::model(), AIGS_AI_Client::remaining(), AIGS_AI_Client::monthly_cap() ) );
						?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=aigs-settings#aigs-ai' ) ); ?>"><?php esc_html_e( 'Change', 'omnigeo-ai-seo' ); ?></a>
					</p>
				<?php else : ?>
					<p class="aigs-byok__keys">
						<?php foreach ( array( 'openai', 'anthropic', 'gemini', 'openrouter' ) as $k ) : ?>
							<?php if ( isset( $provs[ $k ] ) ) : ?>
								<a href="<?php echo esc_url( $provs[ $k ]['keys'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $provs[ $k ]['label'] ); ?> ↗</a>
							<?php endif; ?>
						<?php endforeach; ?>
					</p>
					<a class="aigs-btn aigs-btn--primary" href="<?php echo esc_url( admin_url( 'admin.php?page=aigs-settings#aigs-ai' ) ); ?>"><?php esc_html_e( 'Add your API key (2 minutes)', 'omnigeo-ai-seo' ); ?></a>
				<?php endif; ?>
			</div>
		</div>

		<?php
		$pending = AIGS_Optimizer::pending();
		if ( $pending ) :
			?>
			<div class="aigs-card" id="aigs-review">
				<div class="aigs-card__head"><?php self::i( 'check' ); ?><h2><?php esc_html_e( 'Review and approve', 'omnigeo-ai-seo' ); ?></h2>
					<span class="aigs-chip"><?php echo esc_html( number_format_i18n( count( $pending ) ) ); ?></span>
					<button type="button" class="aigs-btn aigs-btn--primary aigs-push" data-aigs-opt-apply-all><?php esc_html_e( 'Apply all ticked', 'omnigeo-ai-seo' ); ?></button>
				</div>
				<p class="aigs-card__desc"><?php esc_html_e( 'Edit anything you like, untick what you do not want, then apply. AI can make mistakes: check facts, names and numbers.', 'omnigeo-ai-seo' ); ?></p>
				<?php
				foreach ( $pending as $p ) {
					$this->render_proposal( $p );
				}
				?>
			</div>
		<?php endif; ?>

		<div class="aigs-card" id="aigs-pick">
			<div class="aigs-card__head"><?php self::i( 'sparkles' ); ?><h2><?php esc_html_e( 'What should AI improve?', 'omnigeo-ai-seo' ); ?></h2></div>
			<div class="aigs-kinds">
				<?php foreach ( $kinds as $k => $kd ) : ?>
					<?php $off = ! $snip_ok && in_array( $k, array( 'title', 'desc' ), true ); ?>
					<label class="aigs-kind<?php echo $off ? ' is-off' : ''; ?>">
						<input type="checkbox" value="<?php echo esc_attr( $k ); ?>" data-aigs-kind <?php checked( ! $off ); ?> <?php disabled( $off ); ?> />
						<span><strong><?php echo esc_html( $kd[0] ); ?></strong><em><?php echo esc_html( $off ? __( 'All in One SEO keeps these in its own editor.', 'omnigeo-ai-seo' ) : $kd[1] ); ?></em></span>
					</label>
				<?php endforeach; ?>
			</div>

			<?php $rows = AIGS_Optimizer::candidates( 30 ); ?>
			<?php if ( ! $rows ) : ?>
				<p class="aigs-empty"><?php self::i( 'check' ); ?> <?php esc_html_e( 'Every page with enough text already has these basics. Nice work.', 'omnigeo-ai-seo' ); ?></p>
			<?php else : ?>
				<div class="aigs-optlist__head">
					<label><input type="checkbox" data-aigs-opt-all /> <?php esc_html_e( 'Pages that would benefit most', 'omnigeo-ai-seo' ); ?></label>
					<span class="aigs-sub"><?php /* translators: %d: batch size. */ echo esc_html( sprintf( __( 'Up to %d pages per run', 'omnigeo-ai-seo' ), AIGS_Optimizer::BATCH ) ); ?></span>
				</div>
				<ul class="aigs-optlist">
					<?php foreach ( $rows as $r ) : ?>
						<li>
							<label>
								<input type="checkbox" value="<?php echo esc_attr( $r[0]->ID ); ?>" data-aigs-opt-post />
								<span class="aigs-optlist__t"><?php echo esc_html( get_the_title( $r[0] ) ? get_the_title( $r[0] ) : __( '(no title)', 'omnigeo-ai-seo' ) ); ?></span>
							</label>
							<span class="aigs-optlist__needs">
								<?php foreach ( $r[1] as $n ) : ?>
									<span class="aigs-chip aigs-chip--need" data-need="<?php echo esc_attr( $n ); ?>"><?php echo esc_html( $kinds[ $n ][0] ); ?></span>
								<?php endforeach; ?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
				<div class="aigs-optgo">
					<button type="button" class="aigs-btn aigs-btn--primary" data-aigs-opt-generate <?php disabled( ! $ready ); ?>><?php self::i( 'sparkles', 16 ); ?><?php esc_html_e( 'Write suggestions', 'omnigeo-ai-seo' ); ?></button>
					<span class="aigs-optgo__status" aria-live="polite"></span>
				</div>
				<div class="aigs-progress" hidden><span></span></div>
			<?php endif; ?>
		</div>

		<?php
		$applied = AIGS_Optimizer::applied();
		if ( $applied ) :
			?>
			<details class="aigs-card aigs-more">
				<summary><?php self::i( 'undo' ); ?><span><?php esc_html_e( 'Recently applied', 'omnigeo-ai-seo' ); ?></span></summary>
				<table class="aigs-table">
					<tbody>
					<?php foreach ( $applied as $p ) : ?>
						<?php $u = get_post_meta( $p->ID, AIGS_Optimizer::UNDO, true ); ?>
						<tr data-post="<?php echo esc_attr( $p->ID ); ?>">
							<td><a href="<?php echo esc_url( get_permalink( $p ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( get_the_title( $p ) ); ?></a>
								<span class="aigs-sub"><?php echo esc_html( implode( ', ', array_map( static function ( $k ) use ( $kinds ) { return isset( $kinds[ $k ] ) ? $kinds[ $k ][0] : $k; }, (array) ( isset( $u['done'] ) ? $u['done'] : array() ) ) ) ); ?> – <?php echo esc_html( self::ago( isset( $u['time'] ) ? $u['time'] : 0 ) ); ?></span></td>
							<td class="act"><button type="button" class="aigs-btn aigs-btn--ghost aigs-btn--sm" data-aigs-opt-undo><?php self::i( 'undo', 14 ); ?><?php esc_html_e( 'Undo', 'omnigeo-ai-seo' ); ?></button></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</details>
		<?php endif; ?>
		<?php
		$this->close();
	}

	/**
	 * One proposal card: current vs suggested, editable.
	 *
	 * @param WP_Post $p Post.
	 */
	private function render_proposal( $p ) {
		$prop  = get_post_meta( $p->ID, AIGS_Optimizer::PROPOSAL, true );
		$items = is_array( $prop ) && isset( $prop['items'] ) ? $prop['items'] : array();
		$kinds = AIGS_Optimizer::kinds();
		?>
		<div class="aigs-prop" data-post="<?php echo esc_attr( $p->ID ); ?>">
			<div class="aigs-prop__head">
				<a href="<?php echo esc_url( get_permalink( $p ) ); ?>" target="_blank" rel="noopener"><strong><?php echo esc_html( get_the_title( $p ) ); ?></strong></a>
				<span class="aigs-sub"><?php echo esc_html( isset( $prop['model'] ) ? $prop['model'] : '' ); ?></span>
				<span class="aigs-push">
					<button type="button" class="aigs-btn aigs-btn--ghost aigs-btn--sm" data-aigs-opt-reject><?php esc_html_e( 'Discard', 'omnigeo-ai-seo' ); ?></button>
					<button type="button" class="aigs-btn aigs-btn--primary aigs-btn--sm" data-aigs-opt-apply><?php esc_html_e( 'Apply', 'omnigeo-ai-seo' ); ?></button>
				</span>
			</div>
			<?php if ( isset( $items['title'] ) ) : ?>
				<div class="aigs-prop__row" data-kind="title">
					<label class="aigs-prop__tick"><input type="checkbox" checked /> <?php echo esc_html( $kinds['title'][0] ); ?></label>
					<div class="aigs-prop__before"><?php echo esc_html( AIGS_SEO::seo_title( $p->ID ) ); ?></div>
					<input type="text" class="aigs-input aigs-prop__val" value="<?php echo esc_attr( $items['title'] ); ?>" />
				</div>
			<?php endif; ?>
			<?php if ( isset( $items['desc'] ) ) : ?>
				<?php $cur = AIGS_SEO::meta_description( $p->ID ); ?>
				<div class="aigs-prop__row" data-kind="desc">
					<label class="aigs-prop__tick"><input type="checkbox" checked /> <?php echo esc_html( $kinds['desc'][0] ); ?></label>
					<div class="aigs-prop__before"><?php echo '' !== $cur ? esc_html( $cur ) : '<em>' . esc_html__( 'none', 'omnigeo-ai-seo' ) . '</em>'; ?></div>
					<textarea class="aigs-input aigs-prop__val" rows="2"><?php echo esc_textarea( $items['desc'] ); ?></textarea>
				</div>
			<?php endif; ?>
			<?php if ( isset( $items['questions'] ) ) : ?>
				<div class="aigs-prop__row" data-kind="questions">
					<label class="aigs-prop__tick"><input type="checkbox" checked /> <?php echo esc_html( $kinds['questions'][0] ); ?></label>
					<div class="aigs-prop__list">
						<?php foreach ( $items['questions'] as $m ) : ?>
							<div class="aigs-prop__q" data-from="<?php echo esc_attr( $m['from'] ); ?>">
								<input type="checkbox" checked />
								<s><?php echo esc_html( $m['from'] ); ?></s>
								<span aria-hidden="true">→</span>
								<input type="text" class="aigs-input" value="<?php echo esc_attr( $m['to'] ); ?>" />
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
			<?php if ( isset( $items['faq'] ) ) : ?>
				<div class="aigs-prop__row" data-kind="faq">
					<label class="aigs-prop__tick"><input type="checkbox" checked /> <?php echo esc_html( $kinds['faq'][0] ); ?></label>
					<div class="aigs-prop__list">
						<input type="text" class="aigs-input aigs-prop__faqh" value="<?php echo esc_attr( $items['faq']['heading'] ); ?>" />
						<?php foreach ( $items['faq']['items'] as $f ) : ?>
							<div class="aigs-prop__faq">
								<input type="checkbox" checked />
								<div>
									<input type="text" class="aigs-input aigs-prop__fq" value="<?php echo esc_attr( $f['q'] ); ?>" />
									<textarea class="aigs-input aigs-prop__fa" rows="2"><?php echo esc_textarea( $f['a'] ); ?></textarea>
								</div>
							</div>
						<?php endforeach; ?>
						<p class="aigs-sub"><?php esc_html_e( 'Added at the end of the page as a visible section, with FAQ schema.', 'omnigeo-ai-seo' ); ?></p>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Support page.
	 */
	public function page_support() {
		$counts = AIGS_Findings::counts();
		$tr     = new AIGS_Tracker();
		$visits = (int) $tr->count( 'referral', 90 );
		$bots   = (int) $tr->count( 'crawl', 90 ) + (int) $tr->count( 'fetch', 90 );
		$redirs = count( AIGS_Redirects::rules() );
		$this->open( __( 'Support OmniGEO', 'omnigeo-ai-seo' ), '' );
		?>
		<div class="aigs-coffee">
			<div class="aigs-coffee__art" aria-hidden="true">
				<svg viewBox="0 0 120 120" width="120" height="120">
					<path class="aigs-steam" d="M44 30c-6-8 6-12 0-20" />
					<path class="aigs-steam aigs-steam--2" d="M60 30c-6-8 6-12 0-20" />
					<path class="aigs-steam aigs-steam--3" d="M76 30c-6-8 6-12 0-20" />
					<path class="aigs-cup" d="M28 42h64v26a26 26 0 0 1-26 26H54a26 26 0 0 1-26-26z" />
					<path class="aigs-cup__handle" d="M92 50h6a10 10 0 0 1 0 20h-7" />
					<path class="aigs-cup__plate" d="M22 102h76" />
				</svg>
			</div>
			<div class="aigs-coffee__body">
				<h2><?php esc_html_e( 'This plugin runs on coffee.', 'omnigeo-ai-seo' ); ?></h2>
				<p class="aigs-coffee__lead"><?php esc_html_e( 'OmniGEO has no credits, no locked features and no upsells. You bring your own AI key and pay your provider directly. It is built by one independent developer, one late night at a time.', 'omnigeo-ai-seo' ); ?></p>
				<?php if ( $counts['fixed_total'] || $visits || $bots || $redirs ) : ?>
					<ul class="aigs-coffee__stats">
						<?php if ( $counts['fixed_total'] ) : ?><li><b><?php echo esc_html( number_format_i18n( $counts['fixed_total'] ) ); ?></b><span><?php esc_html_e( 'issues fixed on your site', 'omnigeo-ai-seo' ); ?></span></li><?php endif; ?>
						<?php if ( $bots ) : ?><li><b><?php echo esc_html( number_format_i18n( $bots ) ); ?></b><span><?php esc_html_e( 'AI crawler visits tracked (90 days)', 'omnigeo-ai-seo' ); ?></span></li><?php endif; ?>
						<?php if ( $visits ) : ?><li><b><?php echo esc_html( number_format_i18n( $visits ) ); ?></b><span><?php esc_html_e( 'visitors from AI assistants (90 days)', 'omnigeo-ai-seo' ); ?></span></li><?php endif; ?>
						<?php if ( $redirs ) : ?><li><b><?php echo esc_html( number_format_i18n( $redirs ) ); ?></b><span><?php esc_html_e( 'broken links rescued', 'omnigeo-ai-seo' ); ?></span></li><?php endif; ?>
					</ul>
				<?php endif; ?>
				<p><?php esc_html_e( 'If it saved you an hour, an agency invoice or a monthly AI subscription, a coffee keeps the updates coming: new AI crawlers, new answer engines, new fixes.', 'omnigeo-ai-seo' ); ?></p>
				<p class="aigs-coffee__cta">
					<a class="aigs-coffee__btn" href="https://buymeacoffee.com/deepwp" target="_blank" rel="noopener">☕ <?php esc_html_e( 'Buy me a coffee', 'omnigeo-ai-seo' ); ?></a>
					<a class="aigs-coffee__alt" href="https://wordpress.org/support/plugin/omnigeo-ai-seo/reviews/#new-post" target="_blank" rel="noopener"><?php esc_html_e( 'No coffee? A 5-star review helps just as much', 'omnigeo-ai-seo' ); ?> ★</a>
				</p>
				<p class="aigs-sub"><?php esc_html_e( 'Completely optional. Every feature stays free either way.', 'omnigeo-ai-seo' ); ?></p>
			</div>
		</div>
		<?php
		$this->close();
	}
}
