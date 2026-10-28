<?php
/**
 * Findings store.
 *
 * One row per (post, rule). A finding keeps its history: when it was first
 * seen, when it was resolved, whether it came back (regression) and whether
 * the user marked it as intentional. Re-scanning a page is what "verifies" a
 * fix: if the rule no longer fires, the row moves to "fixed".
 *
 * @package OmniGEO_Ai_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data access for the findings table.
 */
class AIGS_Findings {

	/**
	 * Priority from severity and page importance (0-100). Range 12-150.
	 *
	 * @param int $sev        Severity 1-4.
	 * @param int $importance Importance 0-100.
	 * @return int
	 */
	public static function priority( $sev, $importance ) {
		return (int) round( $sev * 25 * ( 0.5 + max( 0, min( 100, (int) $importance ) ) / 100 ) );
	}

	/**
	 * Importance stored for a post (site-level rows count as 100).
	 *
	 * @param int $post_id Post ID.
	 * @return int
	 */
	public static function importance_of( $post_id ) {
		if ( ! $post_id ) {
			return 100;
		}
		$v = get_post_meta( $post_id, '_aigs_importance', true );
		return '' === $v ? 30 : (int) $v;
	}

	/**
	 * Synchronise the findings of one post (or post 0) for a set of rules.
	 *
	 * @param int   $post_id Post ID (0 = site).
	 * @param array $rules   Rule ids this evaluation was responsible for.
	 * @param array $found   rule => evidence for rules that fired.
	 * @return array{new:int,fixed:int}
	 */
	public static function sync( $post_id, $rules, $found ) {
		global $wpdb;
		$table = AIGS_DB::table( 'findings' );
		$now   = AIGS_DB::now();
		$imp   = self::importance_of( $post_id );
		$stats = array(
			'new'   => 0,
			'fixed' => 0,
		);
		if ( ! $rules ) {
			return $stats;
		}

		$in = implode( ',', array_fill( 0, count( $rules ), '%s' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table; placeholders built for the IN list.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, rule, status FROM {$table} WHERE post_id = %d AND rule IN ({$in})", array_merge( array( (int) $post_id ), $rules ) ), ARRAY_A );
		$have = array();
		foreach ( (array) $rows as $r ) {
			$have[ $r['rule'] ] = $r;
		}

		foreach ( $found as $rule => $evidence ) {
			$m = AIGS_Rules::meta( $rule );
			if ( ! $m ) {
				continue;
			}
			$data = array(
				'severity'  => $m['sev'],
				'priority'  => self::priority( $m['sev'], $imp ),
				'evidence'  => wp_json_encode( (array) $evidence ),
				'last_seen' => $now,
			);
			if ( isset( $have[ $rule ] ) ) {
				if ( 'fixed' === $have[ $rule ]['status'] ) {
					// Regression: the problem came back.
					$data['status']      = 'open';
					$data['resolved_at'] = null;
					$stats['new']++;
				}
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
				$wpdb->update( $table, $data, array( 'id' => (int) $have[ $rule ]['id'] ) );
			} else {
				$data['post_id']    = (int) $post_id;
				$data['rule']       = substr( $rule, 0, 64 );
				$data['grp']        = $m['grp'];
				$data['status']     = 'open';
				$data['first_seen'] = $now;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom table.
				$wpdb->insert( $table, $data );
				$stats['new']++;
			}
		}

		foreach ( $have as $rule => $r ) {
			if ( ! isset( $found[ $rule ] ) && 'open' === $r['status'] ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
				$wpdb->update(
					$table,
					array(
						'status'      => 'fixed',
						'resolved_at' => $now,
					),
					array( 'id' => (int) $r['id'] )
				);
				$stats['fixed']++;
			}
		}
		return $stats;
	}

	/**
	 * Synchronise a whole group across many posts with one read.
	 *
	 * @param string $grp      Group.
	 * @param array  $rules    Rule ids of the group.
	 * @param array  $found    post_id => ( rule => evidence ).
	 * @param array  $post_ids Posts in scope (rows outside scope are left alone).
	 * @return array{new:int,fixed:int}
	 */
	public static function sync_group( $grp, $rules, $found, $post_ids ) {
		global $wpdb;
		$table = AIGS_DB::table( 'findings' );
		$scope = array_flip( array_map( 'intval', $post_ids ) );
		$stats = array(
			'new'   => 0,
			'fixed' => 0,
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, rule, status FROM {$table} WHERE grp = %s", $grp ), ARRAY_A );
		$touched = array();
		foreach ( (array) $rows as $r ) {
			$pid = (int) $r['post_id'];
			if ( isset( $scope[ $pid ] ) ) {
				$touched[ $pid ] = true;
			}
		}
		foreach ( $found as $pid => $f ) {
			$touched[ (int) $pid ] = true;
		}
		foreach ( array_keys( $touched ) as $pid ) {
			$s = self::sync( $pid, $rules, isset( $found[ $pid ] ) ? $found[ $pid ] : array() );
			$stats['new']   += $s['new'];
			$stats['fixed'] += $s['fixed'];
		}
		return $stats;
	}

	/**
	 * Remove rows of posts that were deleted or unpublished.
	 */
	public static function prune_missing() {
		global $wpdb;
		$table = AIGS_DB::table( 'findings' );
		$posts = $wpdb->posts;
		// Delete rows whose post no longer exists or is no longer published.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table, no user input.
		$wpdb->query( "DELETE f FROM {$table} f LEFT JOIN {$posts} p ON p.ID = f.post_id WHERE f.post_id > 0 AND ( p.ID IS NULL OR p.post_status <> 'publish' )" );
	}

	/**
	 * Set a finding's status (ignore / reopen).
	 *
	 * @param int    $id     Row id.
	 * @param string $status open|ignored.
	 * @return bool
	 */
	public static function set_status( $id, $status ) {
		global $wpdb;
		if ( ! in_array( $status, array( 'open', 'ignored' ), true ) ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
		return false !== $wpdb->update(
			AIGS_DB::table( 'findings' ),
			array(
				'status'      => $status,
				'resolved_at' => 'ignored' === $status ? AIGS_DB::now() : null,
			),
			array( 'id' => absint( $id ) )
		);
	}

	/**
	 * Get one row.
	 *
	 * @param int $id Row id.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = AIGS_DB::table( 'findings' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return $row ? $row : null;
	}

	/**
	 * Open findings, highest priority first.
	 *
	 * @param array $args rule, status, post_id, cat, limit.
	 * @return array
	 */
	public static function query( $args = array() ) {
		global $wpdb;
		$args  = wp_parse_args(
			$args,
			array(
				'status'  => 'open',
				'rule'    => '',
				'post_id' => null,
				'limit'   => 500,
			)
		);
		$table = AIGS_DB::table( 'findings' );
		$where = array( 'status = %s' );
		$vals  = array( $args['status'] );
		if ( '' !== $args['rule'] ) {
			$where[] = 'rule = %s';
			$vals[]  = $args['rule'];
		}
		if ( null !== $args['post_id'] ) {
			$where[] = 'post_id = %d';
			$vals[]  = (int) $args['post_id'];
		}
		$vals[] = absint( $args['limit'] );
		$order  = 'fixed' === $args['status'] ? 'resolved_at DESC' : 'priority DESC, id ASC';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table; clauses are fixed strings.
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . " ORDER BY {$order} LIMIT %d", $vals ), ARRAY_A );
	}

	/**
	 * Open counts grouped by rule: rule => [count, priority_sum, max_sev].
	 *
	 * @return array
	 */
	public static function grouped() {
		global $wpdb;
		$table = AIGS_DB::table( 'findings' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table, no user input.
		$rows = $wpdb->get_results( "SELECT rule, COUNT(*) AS n, SUM(priority) AS p, MAX(severity) AS s FROM {$table} WHERE status = 'open' GROUP BY rule ORDER BY p DESC", ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[ $r['rule'] ] = array(
				'count'    => (int) $r['n'],
				'priority' => (int) $r['p'],
				'severity' => (int) $r['s'],
			);
		}
		return $out;
	}

	/**
	 * Count by status/severity.
	 *
	 * @return array{open:int,critical:int,high:int,medium:int,low:int,ignored:int,fixed_7d:int,fixed_total:int}
	 */
	public static function counts() {
		global $wpdb;
		$table = AIGS_DB::table( 'findings' );
		$out   = array(
			'open'        => 0,
			'critical'    => 0,
			'high'        => 0,
			'medium'      => 0,
			'low'         => 0,
			'ignored'     => 0,
			'fixed_7d'    => 0,
			'fixed_total' => 0,
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table, no user input.
		$rows = $wpdb->get_results( "SELECT status, severity, COUNT(*) AS n FROM {$table} GROUP BY status, severity", ARRAY_A );
		$map  = array(
			4 => 'critical',
			3 => 'high',
			2 => 'medium',
			1 => 'low',
		);
		foreach ( (array) $rows as $r ) {
			$n = (int) $r['n'];
			if ( 'open' === $r['status'] ) {
				$out['open'] += $n;
				$sev          = (int) $r['severity'];
				if ( isset( $map[ $sev ] ) ) {
					$out[ $map[ $sev ] ] += $n;
				}
			} elseif ( 'ignored' === $r['status'] ) {
				$out['ignored'] += $n;
			} elseif ( 'fixed' === $r['status'] ) {
				$out['fixed_total'] += $n;
			}
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
		$out['fixed_7d'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = 'fixed' AND resolved_at >= %s", AIGS_DB::since( 7 ) ) );
		return $out;
	}

	/**
	 * Update the stored priority of a post's open findings after importance changed.
	 *
	 * @param int $post_id    Post ID.
	 * @param int $importance Importance.
	 */
	public static function reprioritize( $post_id, $importance ) {
		global $wpdb;
		$table  = AIGS_DB::table( 'findings' );
		$factor = 25 * ( 0.5 + max( 0, min( 100, (int) $importance ) ) / 100 );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET priority = ROUND(severity * %f) WHERE post_id = %d", $factor, $post_id ) );
	}

	/**
	 * Open rule ids for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public static function open_rules( $post_id ) {
		global $wpdb;
		$table = AIGS_DB::table( 'findings' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
		return (array) $wpdb->get_col( $wpdb->prepare( "SELECT rule FROM {$table} WHERE post_id = %d AND status = 'open'", $post_id ) );
	}
}
