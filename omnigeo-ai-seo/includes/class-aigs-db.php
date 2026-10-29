<?php
/**
 * Database schema, upgrades and scheduled jobs.
 *
 * The activation hook does not run when a plugin is updated in place, so the
 * schema version is compared on every load and dbDelta() runs once per upgrade.
 *
 * @package OmniGEO_Ai_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns custom tables and cron events.
 */
class AIGS_DB {

	/**
	 * Bump whenever a table definition changes.
	 */
	const SCHEMA_VERSION = '4';

	/**
	 * Register hooks.
	 */
	public function hooks() {
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 0 );
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		add_action( 'init', array( __CLASS__, 'ensure_events' ) );
	}

	/**
	 * Table name helper.
	 *
	 * @param string $name Short name (hits|findings|ai_runs|bot_daily).
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'aigs_' . $name;
	}

	/**
	 * Create or update all tables.
	 */
	public static function install() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();

		$hits     = self::table( 'hits' );
		$findings = self::table( 'findings' );
		$runs     = self::table( 'ai_runs' );
		$daily    = self::table( 'bot_daily' );

		$sql = array();

		$sql[] = "CREATE TABLE {$hits} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			hit_type VARCHAR(20) NOT NULL DEFAULT 'crawl',
			bot_class VARCHAR(20) NOT NULL DEFAULT '',
			platform VARCHAR(50) NOT NULL DEFAULT '',
			bot VARCHAR(80) NOT NULL DEFAULT '',
			url VARCHAR(255) NOT NULL DEFAULT '',
			ua VARCHAR(255) NOT NULL DEFAULT '',
			referrer VARCHAR(255) NOT NULL DEFAULT '',
			source VARCHAR(20) NOT NULL DEFAULT '',
			status SMALLINT(5) UNSIGNED NOT NULL DEFAULT 200,
			ip VARCHAR(45) NOT NULL DEFAULT '',
			verified TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY hit_type (hit_type),
			KEY platform (platform),
			KEY created_at (created_at),
			KEY verified (verified)
		) {$charset};";

		$sql[] = "CREATE TABLE {$findings} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			rule VARCHAR(64) NOT NULL DEFAULT '',
			grp VARCHAR(12) NOT NULL DEFAULT 'page',
			severity TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
			priority SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
			status VARCHAR(12) NOT NULL DEFAULT 'open',
			evidence LONGTEXT NULL,
			first_seen DATETIME NOT NULL,
			last_seen DATETIME NOT NULL,
			resolved_at DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY post_rule (post_id,rule),
			KEY status (status),
			KEY rule (rule)
		) {$charset};";

		$sql[] = "CREATE TABLE {$runs} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			batch_id VARCHAR(20) NOT NULL DEFAULT '',
			prompt_hash CHAR(32) NOT NULL DEFAULT '',
			prompt TEXT NOT NULL,
			provider VARCHAR(20) NOT NULL DEFAULT '',
			model VARCHAR(80) NOT NULL DEFAULT '',
			grounded TINYINT(1) NOT NULL DEFAULT 0,
			mentioned TINYINT(1) NOT NULL DEFAULT 0,
			recommended TINYINT(1) NOT NULL DEFAULT 0,
			cited TINYINT(1) NOT NULL DEFAULT 0,
			position SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
			competitors TEXT NULL,
			citations TEXT NULL,
			issues TEXT NULL,
			answer LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY batch_id (batch_id),
			KEY prompt_hash (prompt_hash),
			KEY created_at (created_at)
		) {$charset};";

		$sql[] = "CREATE TABLE {$daily} (
			day DATE NOT NULL,
			hit_type VARCHAR(20) NOT NULL DEFAULT '',
			bot_class VARCHAR(20) NOT NULL DEFAULT '',
			platform VARCHAR(50) NOT NULL DEFAULT '',
			verified TINYINT(1) NOT NULL DEFAULT 0,
			hits INT(10) UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (day,hit_type,bot_class,platform,verified)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( $sql as $q ) {
			dbDelta( $q );
		}

		// Rows logged by 1.0.0 have no class; derive it from the bot map.
		self::backfill_bot_class();

		// When tracking started (used to tell "no AI bots yet" from "blocked").
		if ( ! get_option( 'aigs_tracking_since' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off read of our own table.
			$first = $wpdb->get_var( "SELECT MIN(created_at) FROM {$hits}" );
			$ts    = $first ? strtotime( $first . ' UTC' ) - (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) : time();
			update_option( 'aigs_tracking_since', $ts ? $ts : time(), false );
		}

		update_option( 'aigs_db_version', self::SCHEMA_VERSION );
	}

	/**
	 * Fill bot_class for legacy rows (one-off, cheap: grouped by bot).
	 */
	private static function backfill_bot_class() {
		global $wpdb;
		$hits = self::table( 'hits' );
		foreach ( AIGS_Tracker::bot_map() as $needle => $info ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off migration; table name from $wpdb->prefix.
			$wpdb->query( $wpdb->prepare( "UPDATE {$hits} SET bot_class = %s, hit_type = %s WHERE bot = %s AND bot_class = ''", $info[2], ( 'fetch' === $info[2] ? 'fetch' : 'crawl' ), $needle ) );
		}
	}

	/**
	 * Run install() when the stored schema is older than the code.
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'aigs_db_version' ) !== self::SCHEMA_VERSION ) {
			self::install();
			self::ensure_events();
		}
	}

	/**
	 * Add a weekly schedule (core only ships hourly/twicedaily/daily/weekly since 5.4).
	 *
	 * @param array $s Schedules.
	 * @return array
	 */
	public static function cron_schedules( $s ) {
		if ( ! isset( $s['weekly'] ) ) {
			$s['weekly'] = array(
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Once Weekly', 'omnigeo-ai-seo' ),
			);
		}
		return $s;
	}

	/**
	 * Make sure recurring events exist (idempotent, cheap).
	 */
	public static function ensure_events() {
		if ( ! wp_next_scheduled( 'aigs_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'aigs_daily' );
		}
		if ( ! wp_next_scheduled( 'aigs_weekly' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', 'aigs_weekly' );
		}
		if ( ! wp_next_scheduled( 'aigs_hourly' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'aigs_hourly' );
		}
	}

	/**
	 * Remove all scheduled events (deactivation).
	 */
	public static function clear_events() {
		foreach ( array( 'aigs_daily', 'aigs_weekly', 'aigs_hourly', 'aigs_scan_continue', 'aigs_visibility_continue', 'aigs_indexnow_ping' ) as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}

	/**
	 * Current time in MySQL format (site timezone, matching legacy rows).
	 *
	 * @return string
	 */
	public static function now() {
		return current_time( 'mysql' );
	}

	/**
	 * "N days ago" boundary in the same timezone as now().
	 *
	 * @param int $days Days.
	 * @return string
	 */
	public static function since( $days ) {
		$ts = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- rows are stored in site time.
		return gmdate( 'Y-m-d H:i:s', $ts - ( absint( $days ) * DAY_IN_SECONDS ) );
	}
}
