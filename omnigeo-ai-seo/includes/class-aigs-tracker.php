<?php
/**
 * AI crawler & referral tracking.
 *
 * @package OmniGEO_Ai_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects AI bot crawls and AI-platform referral visits, stores them, and exposes stats.
 */
class AIGS_Tracker {

	/**
	 * Known AI user-agents mapped to [platform, kind].
	 *
	 * kind = 'fetch'  → user-triggered (a human is reading you inside an AI answer)
	 * kind = 'crawl'  → bulk index/training crawler
	 *
	 * @return array
	 */
	public static function bot_map() {
		return array(
			// User-triggered fetches, a person prompted the AI and it is reading your page now.
			'ChatGPT-User'         => array( 'OpenAI', 'fetch' ),
			'Perplexity-User'      => array( 'Perplexity', 'fetch' ),
			'Claude-User'          => array( 'Anthropic', 'fetch' ),
			'Meta-ExternalFetcher' => array( 'Meta', 'fetch' ),
			'DuckAssistBot'        => array( 'DuckDuckGo', 'fetch' ),
			// Search / index / training crawlers.
			'OAI-SearchBot'        => array( 'OpenAI', 'crawl' ),
			'GPTBot'               => array( 'OpenAI', 'crawl' ),
			'Claude-SearchBot'     => array( 'Anthropic', 'crawl' ),
			'ClaudeBot'            => array( 'Anthropic', 'crawl' ),
			'anthropic-ai'         => array( 'Anthropic', 'crawl' ),
			'PerplexityBot'        => array( 'Perplexity', 'crawl' ),
			'Google-Extended'      => array( 'Google', 'crawl' ),
			'GoogleOther'          => array( 'Google', 'crawl' ),
			'Applebot-Extended'    => array( 'Apple', 'crawl' ),
			'Applebot'             => array( 'Apple', 'crawl' ),
			'Meta-ExternalAgent'   => array( 'Meta', 'crawl' ),
			'FacebookBot'          => array( 'Meta', 'crawl' ),
			'Bytespider'           => array( 'ByteDance', 'crawl' ),
			'Amazonbot'            => array( 'Amazon', 'crawl' ),
			'CCBot'                => array( 'Common Crawl', 'crawl' ),
			'cohere-ai'            => array( 'Cohere', 'crawl' ),
			'YouBot'               => array( 'You.com', 'crawl' ),
			'bingbot'              => array( 'Microsoft', 'crawl' ),
		);
	}

	/**
	 * Hosts used to build a GA4 channel-group source regex.
	 *
	 * @return array
	 */
	public static function ga4_source_hosts() {
		return array(
			'chatgpt.com',
			'chat.openai.com',
			'perplexity.ai',
			'gemini.google.com',
			'claude.ai',
			'copilot.microsoft.com',
			'bing.com',
			'you.com',
			'poe.com',
			'deepseek.com',
			'grok.com',
		);
	}

	/**
	 * GA4-ready regex matching AI referral sources.
	 *
	 * @return string
	 */
	public static function ga4_regex() {
		$escaped = array_map(
			static function ( $d ) {
				return str_replace( '.', '\\.', $d );
			},
			self::ga4_source_hosts()
		);
		return implode( '|', $escaped );
	}

	/**
	 * Known AI referral host fragments mapped to platform.
	 *
	 * @return array
	 */
	public static function referral_map() {
		return array(
			'chat.openai.com'   => 'ChatGPT',
			'chatgpt.com'       => 'ChatGPT',
			'perplexity.ai'     => 'Perplexity',
			'claude.ai'         => 'Claude',
			'gemini.google.com' => 'Gemini',
			'bard.google.com'   => 'Gemini',
			'copilot.microsoft' => 'Copilot',
			'bing.com/chat'     => 'Copilot',
			'you.com'           => 'You.com',
			'poe.com'           => 'Poe',
			'deepseek.com'      => 'DeepSeek',
			'grok.com'          => 'Grok',
		);
	}

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'aigs_hits';
	}

	/**
	 * Create the tracking table.
	 */
	public static function install_table() {
		global $wpdb;
		$table           = self::table();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			hit_type VARCHAR(20) NOT NULL DEFAULT 'crawl',
			platform VARCHAR(50) NOT NULL DEFAULT '',
			bot VARCHAR(80) NOT NULL DEFAULT '',
			url VARCHAR(255) NOT NULL DEFAULT '',
			ua VARCHAR(255) NOT NULL DEFAULT '',
			referrer VARCHAR(255) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY hit_type (hit_type),
			KEY platform (platform),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Register hooks.
	 */
	public function hooks() {
		if ( aigs_get( 'enable_tracking', 1 ) ) {
			add_action( 'init', array( $this, 'maybe_log_hit' ), 1 );
		}
	}

	/**
	 * Inspect the current request and log AI crawls / referrals.
	 */
	public function maybe_log_hit() {
		if ( is_admin() ) {
			return;
		}

		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$ref = isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		// Detect crawler / user-triggered fetch.
		foreach ( self::bot_map() as $needle => $info ) {
			if ( false !== stripos( $ua, $needle ) ) {
				$this->insert( $info[1], $info[0], $needle, $uri, $ua, '' );
				return;
			}
		}

		// Detect referral from an AI platform.
		if ( $ref ) {
			foreach ( self::referral_map() as $needle => $platform ) {
				if ( false !== stripos( $ref, $needle ) ) {
					$this->insert( 'referral', $platform, '', $uri, $ua, $ref );
					return;
				}
			}
		}
	}

	/**
	 * Insert a hit row.
	 *
	 * @param string $type     crawl|referral.
	 * @param string $platform Platform label.
	 * @param string $bot      Bot name.
	 * @param string $url      Request URI.
	 * @param string $ua       User agent.
	 * @param string $ref      Referrer.
	 */
	private function insert( $type, $platform, $bot, $url, $ua, $ref ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom analytics table insert.
		$wpdb->insert(
			self::table(),
			array(
				'hit_type'   => $type,
				'platform'   => $platform,
				'bot'        => $bot,
				'url'        => mb_substr( $url, 0, 255 ),
				'ua'         => mb_substr( $ua, 0, 255 ),
				'referrer'   => mb_substr( $ref, 0, 255 ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Count hits in the last N days.
	 *
	 * @param string $type crawl|referral.
	 * @param int    $days Window.
	 * @return int
	 */
	public function count( $type, $days = 30 ) {
		global $wpdb;
		$table = self::table();
		$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days", current_time( 'timestamp' ) ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom analytics table; values prepared, table name from $wpdb->prefix.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE hit_type = %s AND created_at >= %s", $type, $since ) );
	}

	/**
	 * Per-platform breakdown.
	 *
	 * @param string $type crawl|referral.
	 * @param int    $days Window.
	 * @return array
	 */
	public function by_platform( $type, $days = 30 ) {
		global $wpdb;
		$table = self::table();
		$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days", current_time( 'timestamp' ) ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom analytics table; values prepared, table name from $wpdb->prefix.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT platform, COUNT(*) AS c FROM {$table} WHERE hit_type = %s AND created_at >= %s GROUP BY platform ORDER BY c DESC", $type, $since ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[ $r['platform'] ] = (int) $r['c'];
		}
		return $out;
	}

	/**
	 * Daily series for a chart.
	 *
	 * @param string $type crawl|referral.
	 * @param int    $days Window.
	 * @return array date => count
	 */
	public function daily_series( $type, $days = 30 ) {
		global $wpdb;
		$table = self::table();
		$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days", current_time( 'timestamp' ) ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom analytics table; values prepared, table name from $wpdb->prefix.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT DATE(created_at) AS d, COUNT(*) AS c FROM {$table} WHERE hit_type = %s AND created_at >= %s GROUP BY DATE(created_at)", $type, $since ), ARRAY_A );
		$map  = array();
		foreach ( (array) $rows as $r ) {
			$map[ $r['d'] ] = (int) $r['c'];
		}
		$series = array();
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$day            = gmdate( 'Y-m-d', strtotime( "-{$i} days", current_time( 'timestamp' ) ) );
			$series[ $day ] = isset( $map[ $day ] ) ? $map[ $day ] : 0;
		}
		return $series;
	}

	/**
	 * Recent hits.
	 *
	 * @param int $limit Rows.
	 * @return array
	 */
	public function recent( $limit = 25 ) {
		global $wpdb;
		$table = self::table();
		$limit = absint( $limit );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom analytics table; values prepared, table name from $wpdb->prefix.
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A );
	}

	/**
	 * Most-crawled URLs.
	 *
	 * @param int $limit Rows.
	 * @param int $days  Window.
	 * @return array
	 */
	public function top_urls( $limit = 10, $days = 30, $type = 'crawl' ) {
		global $wpdb;
		$table = self::table();
		$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days", current_time( 'timestamp' ) ) );
		$limit = absint( $limit );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom analytics table; values prepared, table name from $wpdb->prefix.
		return $wpdb->get_results( $wpdb->prepare( "SELECT url, COUNT(*) AS c FROM {$table} WHERE hit_type = %s AND created_at >= %s GROUP BY url ORDER BY c DESC LIMIT %d", $type, $since, $limit ), ARRAY_A );
	}
}
