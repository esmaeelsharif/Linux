<?php
/**
 * AI Visibility monitoring.
 *
 * Runs a fixed set of buyer questions against the assistant you choose (with
 * your own API key), stores every raw answer as evidence, and measures:
 *  - mention    : your brand (or an alias) is named in the answer
 *  - recommended: it is named inside a list / recommendation sentence (heuristic)
 *  - cited      : your domain is linked or listed as a source
 *  - position   : order of first appearance among you and your competitors
 *  - sources    : which domains the assistant cited (web-grounded providers)
 *
 * Every number in the report can be traced back to a stored answer. Answers
 * from providers without live web search are labelled as model knowledge, not
 * as web citations.
 *
 * @package OmniGEO_Ai_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Brand visibility in AI answers.
 */
class AIGS_AI_Visibility {

	const STATE_OPTION = 'aigs_vis_state';
	const MAX_PROMPTS  = 25;

	/**
	 * Register hooks.
	 */
	public function hooks() {
		add_action( 'wp_ajax_aigs_ai_scan', array( $this, 'ajax_scan' ) );
		add_action( 'wp_ajax_aigs_prompt_suggest', array( $this, 'ajax_prompts' ) );
		add_action( 'wp_ajax_aigs_vis_start', array( $this, 'ajax_batch_start' ) );
		add_action( 'wp_ajax_aigs_vis_step', array( $this, 'ajax_batch_step' ) );
		add_action( 'wp_ajax_aigs_vis_answer', array( $this, 'ajax_answer' ) );
		add_action( 'wp_ajax_aigs_vis_draft', array( $this, 'ajax_create_draft' ) );
		add_action( 'aigs_weekly', array( $this, 'cron_schedule' ) );
		add_action( 'aigs_visibility_continue', array( $this, 'cron_continue' ) );
	}

	/* ============================================================
	 * Configuration
	 * ============================================================ */

	/**
	 * Split a textarea into clean, unique lines.
	 *
	 * @param string $raw Raw.
	 * @param int    $max Max lines.
	 * @return array
	 */
	private static function lines( $raw, $max = 50 ) {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $l ) {
			$l = trim( $l );
			if ( '' !== $l ) {
				$out[ $l ] = true;
			}
		}
		return array_slice( array_keys( $out ), 0, $max );
	}

	/**
	 * Brand name + aliases (e.g. Persian and Latin spellings).
	 *
	 * @return array
	 */
	public static function brand_terms() {
		$terms = array( aigs_business_name() );
		foreach ( self::lines( aigs_get( 'brand_aliases', '' ), 10 ) as $a ) {
			$terms[] = $a;
		}
		return array_values( array_unique( array_filter( $terms ) ) );
	}

	/**
	 * Own domain without www.
	 *
	 * @return string
	 */
	public static function own_domain() {
		return AIGS_AI_Client::host( home_url() );
	}

	/**
	 * Competitors from "Name | domain.com" lines.
	 *
	 * @return array [ [name, domain, terms[]] ]
	 */
	public static function competitors() {
		$out = array();
		foreach ( self::lines( aigs_get( 'competitors', '' ), 10 ) as $l ) {
			$parts  = array_map( 'trim', explode( '|', $l ) );
			$name   = $parts[0];
			$domain = isset( $parts[1] ) ? AIGS_AI_Client::host( $parts[1] ) : '';
			if ( '' === $domain && preg_match( '/^[a-z0-9.-]+\.[a-z]{2,}$/i', $name ) ) {
				$domain = AIGS_AI_Client::host( $name );
			}
			$terms = array( $name );
			for ( $i = 2; $i < count( $parts ); $i++ ) {
				if ( '' !== $parts[ $i ] ) {
					$terms[] = $parts[ $i ];
				}
			}
			$out[] = array(
				'name'   => $name,
				'domain' => $domain,
				'terms'  => array_values( array_unique( array_filter( $terms ) ) ),
			);
		}
		return $out;
	}

	/**
	 * Tracked prompts.
	 *
	 * @return array
	 */
	public static function prompts() {
		return self::lines( aigs_get( 'vis_prompts', '' ), self::MAX_PROMPTS );
	}

	/**
	 * AJAX: suggest prompts a real user might ask.
	 */
	public function ajax_prompts() {
		check_ajax_referer( 'aigs_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}
		wp_send_json_success( array( 'prompts' => $this->suggested_prompts() ) );
	}

	/**
	 * Build suggested prompts from the business profile, in the site language.
	 *
	 * @return array
	 */
	public function suggested_prompts() {
		$industry = trim( (string) aigs_get( 'industry', '' ) );
		$location = trim( (string) aigs_get( 'location', '' ) );
		$name     = aigs_business_name();
		$fa       = AIGS_Text::is_rtl_script( $industry . $location . $name ) || 0 === strpos( get_locale(), 'fa' );

		if ( $fa ) {
			$ind = '' !== $industry ? $industry : 'این حوزه';
			$loc = '' !== $location ? ' در ' . $location : '';
			return array(
				'بهترین ' . $ind . $loc . ' کدام است؟',
				'یک ' . $ind . ' معتبر و قابل اعتماد' . $loc . ' معرفی کن.',
				'برای ' . $ind . $loc . ' به کجا مراجعه کنم؟',
				'مقایسه بهترین گزینه‌های ' . $ind . ' برای کسب‌وکارهای کوچک',
				'آیا ' . $name . ' انتخاب خوبی است و چه جایگزین‌هایی دارد؟',
				'قیمت ' . $ind . $loc . ' چقدر است و کدام ارزش خرید دارد؟',
			);
		}
		$ind = '' !== $industry ? $industry : __( 'this industry', 'omnigeo-ai-seo' );
		$loc = '' !== $location ? ' in ' . $location : '';
		return array(
			sprintf( 'What are the best %1$s providers%2$s?', $ind, $loc ),
			sprintf( 'Recommend a trustworthy %1$s company%2$s.', $ind, $loc ),
			sprintf( 'Who should I contact for %1$s services%2$s?', $ind, $loc ),
			sprintf( 'Compare top %s options for a small business.', $ind ),
			sprintf( 'Is %s a good choice and what are the alternatives?', $name ),
			sprintf( 'How much does %1$s cost%2$s and which option is worth it?', $ind, $loc ),
		);
	}

	/* ============================================================
	 * Analysis
	 * ============================================================ */

	/**
	 * First offset of any term (or -1).
	 *
	 * @param string $text  Answer.
	 * @param array  $terms Terms.
	 * @return int
	 */
	private static function first_offset( $text, $terms ) {
		$best = -1;
		foreach ( $terms as $t ) {
			$o = AIGS_Text::term_offset( $text, $t );
			if ( $o >= 0 && ( $best < 0 || $o < $best ) ) {
				$best = $o;
			}
		}
		return $best;
	}

	/**
	 * Is a brand named in a list item or recommendation sentence?
	 *
	 * @param string $text  Answer.
	 * @param array  $terms Brand terms.
	 * @return bool
	 */
	private static function is_recommended( $text, $terms ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $text );
		foreach ( $lines as $line ) {
			$hit = false;
			foreach ( $terms as $t ) {
				if ( AIGS_Text::contains_term( $line, $t ) ) {
					$hit = true;
					break;
				}
			}
			if ( ! $hit ) {
				continue;
			}
			if ( preg_match( '/^\s*(\d+[.)]|[-*•]|#+)\s*/u', $line ) ) {
				return true;
			}
			if ( preg_match( '/(recommend|best|top choice|great option|good choice|worth|پیشنهاد|توصیه|بهترین|گزینه خوب|انتخاب خوب|ارزش)/iu', AIGS_Text::normalize( $line ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Analyse one answer.
	 *
	 * @param string $text      Answer text.
	 * @param array  $citations Source URLs returned by the provider.
	 * @return array
	 */
	public static function analyze( $text, $citations = array() ) {
		$brand   = self::brand_terms();
		$own     = self::own_domain();
		$sources = array_values( array_unique( array_merge( (array) $citations, AIGS_AI_Client::urls_in_text( $text ) ) ) );

		$mentioned = self::first_offset( $text, $brand ) >= 0;
		$cited     = false;
		foreach ( $sources as $u ) {
			if ( AIGS_AI_Client::host_matches( $u, $own ) ) {
				$cited = true;
				break;
			}
		}
		if ( ! $cited && '' !== $own && false !== stripos( $text, $own ) ) {
			$cited = true;
		}
		if ( $cited && ! $mentioned ) {
			// Linking your domain is a mention of you.
			$mentioned = true;
		}

		$offsets = array();
		$b_off   = self::first_offset( $text, $brand );
		if ( $b_off >= 0 ) {
			$offsets['__brand'] = $b_off;
		}
		$comp = array();
		foreach ( self::competitors() as $c ) {
			$terms = $c['terms'];
			if ( '' !== $c['domain'] ) {
				$terms[] = $c['domain'];
			}
			$o   = self::first_offset( $text, $terms );
			$hit = $o >= 0;
			if ( ! $hit && '' !== $c['domain'] ) {
				foreach ( $sources as $u ) {
					if ( AIGS_AI_Client::host_matches( $u, $c['domain'] ) ) {
						$hit = true;
						break;
					}
				}
			}
			$comp[ $c['name'] ] = $hit;
			if ( $o >= 0 ) {
				$offsets[ $c['name'] ] = $o;
			}
		}
		asort( $offsets );
		$position = 0;
		$rank     = 0;
		foreach ( array_keys( $offsets ) as $k ) {
			$rank++;
			if ( '__brand' === $k ) {
				$position = $rank;
				break;
			}
		}

		return array(
			'mentioned'   => $mentioned,
			'recommended' => $mentioned && self::is_recommended( $text, $brand ),
			'cited'       => $cited,
			'position'    => $position,
			'competitors' => $comp,
			'citations'   => array_slice( $sources, 0, 30 ),
		);
	}

	/**
	 * Ask one prompt, analyse and store it.
	 *
	 * @param string $prompt Prompt.
	 * @param string $batch  Batch id ('' for a one-off test).
	 * @return array|WP_Error Stored row.
	 */
	public static function run_prompt( $prompt, $batch = '' ) {
		$res = AIGS_AI_Client::ask(
			$prompt,
			array(
				'max_tokens' => 900,
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$a   = self::analyze( $res['text'], $res['citations'] );
		$row = array(
			'batch_id'    => '' !== $batch ? $batch : 'single',
			'prompt_hash' => md5( AIGS_Text::normalize( $prompt ) ),
			'prompt'      => $prompt,
			'provider'    => $res['provider'],
			'model'       => substr( (string) $res['model'], 0, 80 ),
			'grounded'    => $res['grounded'] ? 1 : 0,
			'mentioned'   => $a['mentioned'] ? 1 : 0,
			'recommended' => $a['recommended'] ? 1 : 0,
			'cited'       => $a['cited'] ? 1 : 0,
			'position'    => (int) $a['position'],
			'competitors' => wp_json_encode( $a['competitors'] ),
			'citations'   => wp_json_encode( $a['citations'] ),
			'answer'      => wp_kses_post( $res['text'] ),
			'created_at'  => AIGS_DB::now(),
		);
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom table.
		$wpdb->insert( AIGS_DB::table( 'ai_runs' ), $row );
		$row['id'] = (int) $wpdb->insert_id;
		return $row;
	}

	/* ============================================================
	 * Batches (all tracked prompts)
	 * ============================================================ */

	/**
	 * Start a batch.
	 *
	 * @param string $mode manual|cron.
	 * @return array|WP_Error State.
	 */
	public static function batch_start( $mode = 'manual' ) {
		$prompts = self::prompts();
		if ( ! $prompts ) {
			return new WP_Error( 'aigs_no_prompts', __( 'Add at least one tracked question first.', 'omnigeo-ai-seo' ) );
		}
		if ( ! AIGS_AI_Client::ready() ) {
			return new WP_Error( 'aigs_no_key', __( 'Add your AI API key in Settings first.', 'omnigeo-ai-seo' ) );
		}
		if ( AIGS_AI_Client::remaining() < count( $prompts ) ) {
			/* translators: 1: requests needed, 2: requests left this month. */
			return new WP_Error( 'aigs_cap', sprintf( __( 'This check needs %1$d AI requests but only %2$d are left this month. Remove some questions or raise the monthly limit in Settings.', 'omnigeo-ai-seo' ), count( $prompts ), AIGS_AI_Client::remaining() ) );
		}
		$state = array(
			'batch'   => gmdate( 'ymdHis' ) . wp_rand( 10, 99 ),
			'prompts' => $prompts,
			'index'   => 0,
			'mode'    => $mode,
			'started' => time(),
			'errors'  => 0,
			'status'  => 'running',
		);
		update_option( self::STATE_OPTION, $state, false );
		return $state;
	}

	/**
	 * Current batch state.
	 *
	 * @return array
	 */
	public static function batch_state() {
		$s = get_option( self::STATE_OPTION, array() );
		return is_array( $s ) ? $s : array();
	}

	/**
	 * Run the next prompt of the batch.
	 *
	 * @return array{state:array,row:array|null,error:string}
	 */
	public static function batch_step() {
		$s = self::batch_state();
		if ( empty( $s['status'] ) || 'running' !== $s['status'] ) {
			return array(
				'state' => $s,
				'row'   => null,
				'error' => '',
			);
		}
		$prompt = $s['prompts'][ $s['index'] ];
		$row    = self::run_prompt( $prompt, $s['batch'] );
		$error  = '';
		if ( is_wp_error( $row ) ) {
			$code  = $row->get_error_code();
			$error = $row->get_error_message();
			$row   = null;
			$s['errors']++;
			if ( in_array( $code, array( 'aigs_cap', 'aigs_no_key' ), true ) || $s['errors'] >= 3 ) {
				$s['status'] = 'failed';
			}
		}
		$s['index']++;
		if ( 'running' === $s['status'] && $s['index'] >= count( $s['prompts'] ) ) {
			$s['status'] = 'done';
			update_option( self::STATE_OPTION, $s, false );
			self::sync_gaps( $s['batch'] );
			update_option( 'aigs_vis_last_batch', $s['batch'], false );
		} else {
			update_option( self::STATE_OPTION, $s, false );
		}
		return array(
			'state' => $s,
			'row'   => $row ? self::public_row( $row ) : null,
			'error' => $error,
		);
	}

	/**
	 * Turn prompts where competitors appear but you do not into Action Center tasks.
	 *
	 * @param string $batch Batch id.
	 */
	public static function sync_gaps( $batch ) {
		global $wpdb;
		$found = array();
		foreach ( self::batch_rows( $batch ) as $r ) {
			if ( (int) $r['mentioned'] ) {
				continue;
			}
			$comps = array_keys( array_filter( (array) json_decode( (string) $r['competitors'], true ) ) );
			if ( ! $comps ) {
				continue;
			}
			$found[ 'vis_gap_' . substr( $r['prompt_hash'], 0, 16 ) ] = array(
				'prompt'      => $r['prompt'],
				'competitors' => $comps,
				'provider'    => $r['provider'],
				'run'         => (int) $r['id'],
			);
		}
		$table = AIGS_DB::table( 'findings' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
		$existing = (array) $wpdb->get_col( "SELECT rule FROM {$table} WHERE post_id = 0 AND grp = 'vis'" );
		$rules    = array_values( array_unique( array_merge( $existing, array_keys( $found ) ) ) );
		AIGS_Findings::sync( 0, $rules, $found );
	}

	/**
	 * Rows of a batch.
	 *
	 * @param string $batch Batch id.
	 * @return array
	 */
	public static function batch_rows( $batch ) {
		global $wpdb;
		$table = AIGS_DB::table( 'ai_runs' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, batch_id, prompt_hash, prompt, provider, model, grounded, mentioned, recommended, cited, position, competitors, citations, created_at FROM {$table} WHERE batch_id = %s ORDER BY id ASC", $batch ), ARRAY_A );
	}

	/**
	 * Completed batch ids, newest first.
	 *
	 * @param int $limit Count.
	 * @return array
	 */
	public static function batches( $limit = 12 ) {
		global $wpdb;
		$table = AIGS_DB::table( 'ai_runs' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT batch_id, MIN(created_at) AS t, COUNT(*) AS n, SUM(mentioned) AS m, SUM(cited) AS c FROM {$table} WHERE batch_id <> 'single' GROUP BY batch_id ORDER BY t DESC LIMIT %d", absint( $limit ) ), ARRAY_A );
	}

	/**
	 * Summary metrics for a batch.
	 *
	 * @param array $rows Batch rows.
	 * @return array
	 */
	public static function summarize( $rows ) {
		$n     = count( $rows );
		$m     = 0;
		$c     = 0;
		$rec   = 0;
		$voice = array( '__brand' => 0 );
		foreach ( $rows as $r ) {
			$m   += (int) $r['mentioned'];
			$c   += (int) $r['cited'];
			$rec += (int) $r['recommended'];
			if ( (int) $r['mentioned'] ) {
				$voice['__brand']++;
			}
			foreach ( (array) json_decode( (string) $r['competitors'], true ) as $name => $hit ) {
				if ( ! isset( $voice[ $name ] ) ) {
					$voice[ $name ] = 0;
				}
				if ( $hit ) {
					$voice[ $name ]++;
				}
			}
		}
		$total = array_sum( $voice );
		$share = array();
		foreach ( $voice as $k => $v ) {
			$share[ $k ] = $total ? (int) round( 100 * $v / $total ) : 0;
		}
		arsort( $voice );
		return array(
			'prompts'     => $n,
			'mention'     => $n ? (int) round( 100 * $m / $n ) : 0,
			'cited'       => $n ? (int) round( 100 * $c / $n ) : 0,
			'recommended' => $n ? (int) round( 100 * $rec / $n ) : 0,
			'voice'       => $voice,
			'share'       => $share,
		);
	}

	/**
	 * Domains cited across recent runs, labelled own / competitor / other.
	 *
	 * @param int $days Window.
	 * @return array domain => [count, kind]
	 */
	public static function cited_domains( $days = 90 ) {
		global $wpdb;
		$table = AIGS_DB::table( 'ai_runs' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
		$rows  = (array) $wpdb->get_col( $wpdb->prepare( "SELECT citations FROM {$table} WHERE created_at >= %s", AIGS_DB::since( $days ) ) );
		$own   = self::own_domain();
		$comps = array();
		foreach ( self::competitors() as $c ) {
			if ( '' !== $c['domain'] ) {
				$comps[ $c['domain'] ] = $c['name'];
			}
		}
		$out = array();
		foreach ( $rows as $json ) {
			$seen = array();
			foreach ( (array) json_decode( (string) $json, true ) as $u ) {
				$h = AIGS_AI_Client::host( $u );
				if ( '' === $h || isset( $seen[ $h ] ) ) {
					continue;
				}
				$seen[ $h ] = true;
				if ( ! isset( $out[ $h ] ) ) {
					$kind = 'other';
					if ( AIGS_AI_Client::host_matches( $h, $own ) ) {
						$kind = 'own';
					} else {
						foreach ( $comps as $d => $name ) {
							if ( AIGS_AI_Client::host_matches( $h, $d ) ) {
								$kind = 'competitor';
								break;
							}
						}
					}
					$out[ $h ] = array(
						'count' => 0,
						'kind'  => $kind,
					);
				}
				$out[ $h ]['count']++;
			}
		}
		uasort(
			$out,
			static function ( $a, $b ) {
				return $b['count'] - $a['count'];
			}
		);
		return array_slice( $out, 0, 25, true );
	}

	/**
	 * Strip a row down for the browser.
	 *
	 * @param array $r Row.
	 * @return array
	 */
	public static function public_row( $r ) {
		return array(
			'id'          => (int) $r['id'],
			'prompt'      => $r['prompt'],
			'provider'    => $r['provider'],
			'model'       => $r['model'],
			'grounded'    => (int) $r['grounded'],
			'mentioned'   => (int) $r['mentioned'],
			'recommended' => (int) $r['recommended'],
			'cited'       => (int) $r['cited'],
			'position'    => (int) $r['position'],
			'competitors' => (array) json_decode( (string) $r['competitors'], true ),
			'citations'   => (array) json_decode( (string) $r['citations'], true ),
			'answer'      => isset( $r['answer'] ) ? wp_trim_words( wp_strip_all_tags( $r['answer'] ), 120 ) : '',
		);
	}

	/* ============================================================
	 * AJAX
	 * ============================================================ */

	/**
	 * Admin gate.
	 */
	private function guard() {
		check_ajax_referer( 'aigs_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'omnigeo-ai-seo' ) ) );
		}
	}

	/**
	 * AJAX: one-off test of a single prompt.
	 */
	public function ajax_scan() {
		$this->guard();
		$prompt = isset( $_POST['prompt'] ) ? sanitize_text_field( wp_unslash( $_POST['prompt'] ) ) : '';
		if ( '' === $prompt ) {
			wp_send_json_error( array( 'message' => __( 'Empty prompt.', 'omnigeo-ai-seo' ) ) );
		}
		$row = self::run_prompt( $prompt );
		if ( is_wp_error( $row ) ) {
			wp_send_json_error( array( 'message' => $row->get_error_message() ) );
		}
		$out          = self::public_row( $row );
		$out['left']  = AIGS_AI_Client::remaining();
		$out['label'] = $row['grounded'] ? __( 'Live web search answer', 'omnigeo-ai-seo' ) : __( 'Model knowledge only (no live web search)', 'omnigeo-ai-seo' );
		wp_send_json_success( $out );
	}

	/**
	 * AJAX: start a batch over all tracked prompts.
	 */
	public function ajax_batch_start() {
		$this->guard();
		$s = self::batch_start( 'manual' );
		if ( is_wp_error( $s ) ) {
			wp_send_json_error( array( 'message' => $s->get_error_message() ) );
		}
		wp_send_json_success(
			array(
				'total' => count( $s['prompts'] ),
				'batch' => $s['batch'],
			)
		);
	}

	/**
	 * AJAX: run the next prompt.
	 */
	public function ajax_batch_step() {
		$this->guard();
		$r = self::batch_step();
		wp_send_json_success(
			array(
				'status' => isset( $r['state']['status'] ) ? $r['state']['status'] : 'idle',
				'index'  => isset( $r['state']['index'] ) ? (int) $r['state']['index'] : 0,
				'total'  => isset( $r['state']['prompts'] ) ? count( $r['state']['prompts'] ) : 0,
				'row'    => $r['row'],
				'error'  => $r['error'],
			)
		);
	}

	/**
	 * AJAX: full stored answer (evidence).
	 */
	public function ajax_answer() {
		$this->guard();
		global $wpdb;
		$id    = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$table = AIGS_DB::table( 'ai_runs' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		if ( ! $row ) {
			wp_send_json_error( array( 'message' => __( 'Not found.', 'omnigeo-ai-seo' ) ) );
		}
		$out           = self::public_row( $row );
		$out['answer'] = wp_strip_all_tags( (string) $row['answer'] );
		$out['date']   = $row['created_at'];
		wp_send_json_success( $out );
	}

	/**
	 * AJAX: create a draft page from a visibility gap (never published automatically).
	 */
	public function ajax_create_draft() {
		check_ajax_referer( 'aigs_admin', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'omnigeo-ai-seo' ) ) );
		}
		$id  = isset( $_POST['finding'] ) ? absint( $_POST['finding'] ) : 0;
		$row = AIGS_Findings::get( $id );
		if ( ! $row || 0 !== strpos( $row['rule'], 'vis_gap_' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not found.', 'omnigeo-ai-seo' ) ) );
		}
		$ev     = (array) json_decode( (string) $row['evidence'], true );
		$prompt = isset( $ev['prompt'] ) ? (string) $ev['prompt'] : '';
		if ( '' === $prompt ) {
			wp_send_json_error( array( 'message' => __( 'Not found.', 'omnigeo-ai-seo' ) ) );
		}
		$fa    = AIGS_Text::is_rtl_script( $prompt );
		$brand = aigs_business_name();
		$title = trim( preg_replace( '/[?؟]+\s*$/u', '', $prompt ) );

		$h = static function ( $text ) {
			return "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">" . esc_html( $text ) . "</h2>\n<!-- /wp:heading -->\n\n";
		};
		$p = static function ( $text ) {
			return "<!-- wp:paragraph -->\n<p>" . esc_html( $text ) . "</p>\n<!-- /wp:paragraph -->\n\n";
		};
		if ( $fa ) {
			$content = $p( '[پاسخ مستقیم: در ۴۰ تا ۶۰ کلمه به «' . $prompt . '» پاسخ دهید. جمله اول خود پاسخ باشد.]' )
				. $h( 'معیارهای انتخاب' ) . $p( '[معیارهایی که خریدار بر اساس آن تصمیم می‌گیرد را فهرست کنید.]' )
				. $h( 'مقایسه گزینه‌ها' ) . $p( '[یک جدول مقایسه با اطلاعات واقعی و قابل بررسی اضافه کنید.]' )
				. $h( 'چرا ' . $brand . '؟' ) . $p( '[شواهد مشخص: تجربه، آمار، نظر مشتریان، گواهی‌ها.]' )
				. $h( 'سؤالات متداول' ) . $p( '[۳ تا ۵ سؤال واقعی مشتریان با پاسخ کوتاه.]' );
		} else {
			$content = $p( '[Direct answer: answer “' . $prompt . '” in 40–60 words. Make the first sentence the answer.]' )
				. $h( 'How to choose' ) . $p( '[List the criteria buyers actually decide on.]' )
				. $h( 'Options compared' ) . $p( '[Add a comparison table with real, checkable facts.]' )
				. $h( 'Why ' . $brand ) . $p( '[Concrete proof: experience, numbers, reviews, certifications.]' )
				. $h( 'Frequently asked questions' ) . $p( '[3–5 real customer questions with short answers.]' );
		}

		$post_id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_content' => $content,
				'post_status'  => 'draft',
				'post_type'    => 'post',
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( array( 'message' => $post_id->get_error_message() ) );
		}
		wp_send_json_success( array( 'edit' => get_edit_post_link( $post_id, 'raw' ) ) );
	}

	/* ============================================================
	 * Schedule
	 * ============================================================ */

	/**
	 * Weekly tick: start a batch when the schedule says so.
	 */
	public function cron_schedule() {
		$sched = aigs_get( 'vis_schedule', 'off' );
		if ( 'off' === $sched || ! self::prompts() || ! AIGS_AI_Client::ready() ) {
			return;
		}
		$last = (string) get_option( 'aigs_vis_last_run', '' );
		if ( 'monthly' === $sched && $last && gmdate( 'Y-m' ) === gmdate( 'Y-m', (int) $last ) ) {
			return;
		}
		$s = self::batch_start( 'cron' );
		if ( is_wp_error( $s ) ) {
			return;
		}
		update_option( 'aigs_vis_last_run', time(), false );
		$this->cron_continue();
	}

	/**
	 * Run prompts for ~25 seconds, then continue in a new event.
	 */
	public function cron_continue() {
		$deadline = microtime( true ) + 25;
		do {
			$r = self::batch_step();
		} while ( isset( $r['state']['status'] ) && 'running' === $r['state']['status'] && microtime( true ) < $deadline );
		if ( isset( $r['state']['status'] ) && 'running' === $r['state']['status'] && ! wp_next_scheduled( 'aigs_visibility_continue' ) ) {
			wp_schedule_single_event( time() + 60, 'aigs_visibility_continue' );
		}
	}
}
