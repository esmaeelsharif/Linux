<?php
/**
 * Action Center.
 *
 * Turns findings from every module (site scan, products, AI visibility, bot
 * logs) into a short, prioritised to-do list: what to fix, where, why it
 * matters, and a way to confirm it is fixed.
 *
 * @package OmniGEO_Ai_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Task aggregation + actions.
 */
class AIGS_Actions {

	/**
	 * Register hooks.
	 */
	public function hooks() {
		add_action( 'wp_ajax_aigs_finding_status', array( $this, 'ajax_status' ) );
		add_action( 'wp_ajax_aigs_rule_ignore', array( $this, 'ajax_rule_ignore' ) );
		add_action( 'wp_ajax_aigs_autofix', array( $this, 'ajax_autofix' ) );
		add_action( 'wp_ajax_aigs_important', array( $this, 'ajax_important' ) );
		add_action( 'wp_ajax_aigs_onboarding_dismiss', array( $this, 'ajax_onboarding_dismiss' ) );
		add_action( 'aigs_scan_finalized', aigs_guard( array( $this, 'maybe_send_digest' ) ), 10, 3 );
	}

	/**
	 * Task groups (one per rule), highest priority first.
	 *
	 * @param int $limit Max groups.
	 * @return array
	 */
	public static function tasks( $limit = 50 ) {
		$out = array();
		foreach ( AIGS_Findings::grouped() as $rule => $g ) {
			$m = AIGS_Rules::meta( $rule );
			if ( ! $m ) {
				continue;
			}
			// AI visibility gaps are one task per question; group them for the list.
			$key = 'vis' === $m['grp'] ? ( 0 === strpos( $rule, 'vis_fact_' ) ? 'vis_fact' : 'vis_gap' ) : $rule;
			if ( ! isset( $out[ $key ] ) ) {
				$out[ $key ] = array_merge(
					$m,
					array(
						'rule'     => $key,
						'count'    => 0,
						'priority' => 0,
						'severity' => 0,
					)
				);
			}
			$out[ $key ]['count']    += $g['count'];
			$out[ $key ]['priority'] += $g['priority'];
			$out[ $key ]['severity']  = max( $out[ $key ]['severity'], $g['severity'] );
		}
		uasort(
			$out,
			static function ( $a, $b ) {
				if ( $a['severity'] !== $b['severity'] && ( 4 === $a['severity'] || 4 === $b['severity'] ) ) {
					return $b['severity'] - $a['severity'];
				}
				return $b['priority'] - $a['priority'];
			}
		);
		return array_slice( $out, 0, $limit, true );
	}

	/**
	 * Rows behind a task group.
	 *
	 * @param string $rule  Rule or 'vis_gap'.
	 * @param int    $limit Max.
	 * @return array
	 */
	public static function task_rows( $rule, $limit = 100 ) {
		if ( 'vis_gap' === $rule || 'vis_fact' === $rule ) {
			$rows = array();
			foreach ( AIGS_Findings::query( array( 'post_id' => 0, 'limit' => 200 ) ) as $r ) {
				if ( 0 === strpos( $r['rule'], $rule . '_' ) ) {
					$rows[] = $r;
				}
			}
			return array_slice( $rows, 0, $limit );
		}
		return AIGS_Findings::query(
			array(
				'rule'  => $rule,
				'limit' => $limit,
			)
		);
	}

	/**
	 * Human-readable evidence for a finding.
	 *
	 * @param string $rule     Rule.
	 * @param array  $evidence Evidence.
	 * @return string
	 */
	public static function evidence_text( $rule, $evidence ) {
		$e = (array) $evidence;
		switch ( $rule ) {
			case 'thin_content':
			case 'no_structure':
				/* translators: %d: word count. */
				return sprintf( __( '%d words', 'omnigeo-ai-seo' ), isset( $e['words'] ) ? (int) $e['words'] : 0 );
			case 'title_length':
			case 'meta_desc_length':
				/* translators: %d: characters. */
				return sprintf( __( '%d characters', 'omnigeo-ai-seo' ), isset( $e['chars'] ) ? (int) $e['chars'] : 0 );
			case 'wall_of_text':
				/* translators: %d: paragraphs. */
				return sprintf( __( '%d long paragraphs', 'omnigeo-ai-seo' ), isset( $e['count'] ) ? (int) $e['count'] : 0 );
			case 'img_alt_missing':
				/* translators: %d: images. */
				return sprintf( __( '%d images without alt text', 'omnigeo-ai-seo' ), isset( $e['count'] ) ? (int) $e['count'] : 0 );
			case 'outdated_year':
				/* translators: %d: year. */
				return sprintf( __( 'Title mentions %d', 'omnigeo-ai-seo' ), isset( $e['year'] ) ? (int) $e['year'] : 0 );
			case 'stale_content':
				/* translators: %d: days. */
				return sprintf( __( 'Last updated %d days ago', 'omnigeo-ai-seo' ), isset( $e['days'] ) ? (int) $e['days'] : 0 );
			case 'filler_opening':
				return isset( $e['lead'] ) ? '“' . $e['lead'] . '”' : '';
			case 'no_direct_answer':
				/* translators: %s: question. */
				return isset( $e['question'] ) ? sprintf( __( 'Main question: %s', 'omnigeo-ai-seo' ), $e['question'] ) : '';
			case 'unanswered_questions':
				return isset( $e['questions'] ) ? implode( ' · ', (array) $e['questions'] ) : '';
			case 'canonical_elsewhere':
				return isset( $e['canonical'] ) ? '→ ' . $e['canonical'] : '';
			case 'product_attributes':
				/* translators: %d: attributes. */
				return sprintf( __( '%d attributes', 'omnigeo-ai-seo' ), isset( $e['count'] ) ? (int) $e['count'] : 0 );
			case 'product_thin_desc':
				/* translators: %d: words. */
				return sprintf( __( '%d words', 'omnigeo-ai-seo' ), isset( $e['words'] ) ? (int) $e['words'] : 0 );
			case 'ai_read_weak':
				/* translators: %d: reads. */
				return sprintf( _n( 'Read %d time by AI assistants in 30 days', 'Read %d times by AI assistants in 30 days', isset( $e['reads'] ) ? (int) $e['reads'] : 0, 'omnigeo-ai-seo' ), isset( $e['reads'] ) ? (int) $e['reads'] : 0 );
			case 'weak_passages':
				$parts = array();
				foreach ( isset( $e['sections'] ) ? (array) $e['sections'] : array() as $sec ) {
					$parts[] = '“' . $sec[0] . '” (' . (int) $sec[1] . ')';
				}
				/* translators: 1: weak sections, 2: total sections, 3: list. */
				return sprintf( __( '%1$d of %2$d sections are weak: %3$s', 'omnigeo-ai-seo' ), isset( $e['weak'] ) ? (int) $e['weak'] : 0, isset( $e['total'] ) ? (int) $e['total'] : 0, implode( ', ', $parts ) );
			case 'no_evidence':
				/* translators: %d: words. */
				return sprintf( __( '%d words without a figure or a source', 'omnigeo-ai-seo' ), isset( $e['words'] ) ? (int) $e['words'] : 0 );
			case 'edge_blocks_ai':
				$who = ! empty( $e['cdn'] ) ? $e['cdn'] : __( 'an unknown edge network', 'omnigeo-ai-seo' );
				/* translators: 1: CDN name, 2: bot names. */
				return sprintf( __( 'Answered by %1$s. Blocked: %2$s.', 'omnigeo-ai-seo' ), $who, implode( ', ', isset( $e['blocked'] ) ? (array) $e['blocked'] : array() ) ) . ' ' . AIGS_Visibility::edge_fix_hint( isset( $e['cdn'] ) ? $e['cdn'] : '' );
			case 'site_local_incomplete':
				/* translators: %s: missing items. */
				return sprintf( __( 'Missing: %s', 'omnigeo-ai-seo' ), implode( ', ', isset( $e['missing'] ) ? (array) $e['missing'] : array() ) );
			case 'ai_bots_absent':
				return AIGS_Visibility::edge_fix_hint( isset( $e['cdn'] ) ? $e['cdn'] : '' );
			case 'site_entity_incomplete':
				$miss = array();
				if ( empty( $e['logo'] ) ) {
					$miss[] = __( 'logo', 'omnigeo-ai-seo' );
				}
				if ( empty( $e['sameas'] ) ) {
					$miss[] = __( 'sameAs profile links', 'omnigeo-ai-seo' );
				}
				/* translators: %s: missing items. */
				return sprintf( __( 'Missing: %s', 'omnigeo-ai-seo' ), implode( ', ', $miss ) );
		}
		if ( 0 === strpos( $rule, 'vis_fact_' ) && isset( $e['issues'] ) ) {
			return implode( ' · ', array_map( array( 'AIGS_AI_Visibility', 'issue_text' ), (array) $e['issues'] ) );
		}
		if ( 0 === strpos( $rule, 'vis_gap_' ) && isset( $e['competitors'] ) ) {
			/* translators: %s: competitor names. */
			return sprintf( __( 'Named instead: %s', 'omnigeo-ai-seo' ), implode( ', ', (array) $e['competitors'] ) );
		}
		return '';
	}

	/* ============================================================
	 * AJAX
	 * ============================================================ */

	/**
	 * AJAX: ignore / reopen one finding.
	 */
	public function ajax_status() {
		check_ajax_referer( 'aigs_admin', 'nonce' );
		$id     = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		$row    = AIGS_Findings::get( $id );
		if ( ! $row ) {
			wp_send_json_error( array( 'message' => __( 'Not found.', 'omnigeo-ai-seo' ) ) );
		}
		$pid = (int) $row['post_id'];
		if ( ( $pid && ! current_user_can( 'edit_post', $pid ) ) || ( ! $pid && ! current_user_can( 'manage_options' ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'omnigeo-ai-seo' ) ) );
		}
		if ( ! AIGS_Findings::set_status( $id, $status ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not update.', 'omnigeo-ai-seo' ) ) );
		}
		if ( $pid ) {
			AIGS_Scanner::refresh_score( $pid );
		}
		wp_send_json_success();
	}

	/**
	 * AJAX: ignore every open finding of one rule (e.g. "we never use featured images").
	 */
	public function ajax_rule_ignore() {
		check_ajax_referer( 'aigs_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'omnigeo-ai-seo' ) ) );
		}
		$rule = isset( $_POST['rule'] ) ? sanitize_key( wp_unslash( $_POST['rule'] ) ) : '';
		if ( ! AIGS_Rules::meta( $rule ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown rule.', 'omnigeo-ai-seo' ) ) );
		}
		$n = 0;
		foreach ( AIGS_Findings::query( array( 'rule' => $rule, 'limit' => 5000 ) ) as $r ) {
			AIGS_Findings::set_status( (int) $r['id'], 'ignored' );
			if ( (int) $r['post_id'] ) {
				AIGS_Scanner::refresh_score( (int) $r['post_id'] );
			}
			$n++;
		}
		wp_send_json_success( array( 'ignored' => $n ) );
	}

	/**
	 * Safe one-click fixes.
	 *
	 * @return array id => label
	 */
	public static function autofixes() {
		return array(
			'enable_meta_fallback' => __( 'Turn on automatic meta descriptions', 'omnigeo-ai-seo' ),
			'ai_search_only'       => __( 'Allow AI search, block AI training', 'omnigeo-ai-seo' ),
		);
	}

	/**
	 * AJAX: apply a safe one-click fix (each is reversible in Settings).
	 */
	public function ajax_autofix() {
		check_ajax_referer( 'aigs_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'omnigeo-ai-seo' ) ) );
		}
		$fix      = isset( $_POST['fix'] ) ? sanitize_key( wp_unslash( $_POST['fix'] ) ) : '';
		$settings = get_option( 'aigs_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();
		switch ( $fix ) {
			case 'enable_meta_fallback':
				$settings['meta_fallback'] = 1;
				break;
			case 'ai_search_only':
				$settings['ai_access'] = 'search_only';
				break;
			default:
				wp_send_json_error( array( 'message' => __( 'Unknown fix.', 'omnigeo-ai-seo' ) ) );
		}
		// Write directly: the Settings API sanitize callback only handles form posts.
		remove_all_filters( 'sanitize_option_aigs_settings' );
		update_option( 'aigs_settings', $settings );
		AIGS_Findings::sync( 0, AIGS_Rules::ids( 'site' ), AIGS_Scanner::site_rules() );
		wp_send_json_success();
	}

	/**
	 * AJAX: flag / unflag a page as important (raises its priority).
	 */
	public function ajax_important() {
		check_ajax_referer( 'aigs_admin', 'nonce' );
		$pid = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $pid || ! current_user_can( 'edit_post', $pid ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'omnigeo-ai-seo' ) ) );
		}
		$on = ! get_post_meta( $pid, '_aigs_important', true );
		if ( $on ) {
			update_post_meta( $pid, '_aigs_important', 1 );
		} else {
			delete_post_meta( $pid, '_aigs_important' );
		}
		$imp = (int) get_post_meta( $pid, '_aigs_importance', true );
		$imp = max( 0, min( 100, $imp + ( $on ? 30 : -30 ) ) );
		update_post_meta( $pid, '_aigs_importance', $imp );
		AIGS_Findings::reprioritize( $pid, $imp );
		wp_send_json_success( array( 'important' => $on ) );
	}

	/**
	 * AJAX: hide the getting-started checklist.
	 */
	public function ajax_onboarding_dismiss() {
		check_ajax_referer( 'aigs_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}
		update_option( 'aigs_onboarding_done', 1, false );
		wp_send_json_success();
	}

	/* ============================================================
	 * Weekly digest (opt-in)
	 * ============================================================ */

	/**
	 * Email a short summary after the weekly background scan.
	 *
	 * @param array  $summary Summary.
	 * @param array  $prev    Previous summary.
	 * @param string $mode    manual|cron.
	 */
	public function maybe_send_digest( $summary, $prev, $mode ) {
		if ( 'cron' !== $mode || ! aigs_get( 'digest_email', 0 ) ) {
			return;
		}
		$to = sanitize_email( (string) aigs_get( 'digest_to', '' ) );
		if ( '' === $to ) {
			$to = get_option( 'admin_email' );
		}
		$counts  = AIGS_Findings::counts();
		$tracker = new AIGS_Tracker();
		$delta   = isset( $prev['score'] ) ? (int) $summary['score'] - (int) $prev['score'] : 0;
		$site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		$lines   = array();
		/* translators: 1: score, 2: change. */
		$lines[] = sprintf( __( 'AI answer readiness: %1$d/100 (%2$s since last week)', 'omnigeo-ai-seo' ), (int) $summary['score'], ( $delta >= 0 ? '+' : '' ) . $delta );
		/* translators: %d: count. */
		$lines[] = sprintf( __( 'Issues fixed this week: %d', 'omnigeo-ai-seo' ), $counts['fixed_7d'] );
		/* translators: 1: open, 2: critical/high. */
		$lines[] = sprintf( __( 'Open issues: %1$d (%2$d high priority)', 'omnigeo-ai-seo' ), $counts['open'], $counts['critical'] + $counts['high'] );
		/* translators: %d: count. */
		$lines[] = sprintf( __( 'Pages read inside AI answers (7 days): %d', 'omnigeo-ai-seo' ), $tracker->count( 'fetch', 7 ) );
		$lines[] = '';
		$lines[] = __( 'Top tasks this week:', 'omnigeo-ai-seo' );
		$i       = 0;
		foreach ( self::tasks( 3 ) as $t ) {
			$i++;
			$lines[] = $i . '. ' . $t['title'] . ' (' . (int) $t['count'] . ')';
		}
		$lines[] = '';
		$lines[] = admin_url( 'admin.php?page=aigs-actions' );

		/* translators: %s: site name. */
		wp_mail( $to, sprintf( __( '[%s] Your weekly AI visibility report', 'omnigeo-ai-seo' ), $site ), implode( "\n", $lines ) );
	}
}
