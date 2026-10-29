<?php
/**
 * AI crawler & referral tracking (Bot & Referral Intelligence).
 *
 * Three layers that are never added together:
 *  1. Bot observations : requests whose User-Agent claims to be an AI bot.
 *  2. Verified bots    : observations whose IP was confirmed with the method the
 *                        operator documents (reverse DNS or published IP ranges).
 *                        Opt-in, runs in the background, never on page load.
 *  3. AI referrals     : human visits arriving from an AI product (Referer header
 *                        or utm_source added by the assistant).
 *
 * Raw rows are kept for a limited time and rolled up into daily totals, so the
 * table stays small on busy sites.
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

	const UNVERIFIED   = 0;
	const VERIFIED     = 1;
	const FAILED       = 2;
	const UNVERIFIABLE = 3;

	/**
	 * Known AI user-agents → [platform, hit_type, class].
	 *
	 * hit_type: fetch (user-triggered) | crawl (everything else) — kept for 1.0 compatibility.
	 * class   : fetch    = a person asked an assistant and it is reading your page now
	 *           search   = indexing for an AI search / answer product
	 *           training = collecting data to train models
	 *
	 * @return array
	 */
	public static function bot_map() {
		return array(
			// User-triggered fetches.
			'ChatGPT-User'          => array( 'OpenAI', 'fetch', 'fetch' ),
			'Perplexity-User'       => array( 'Perplexity', 'fetch', 'fetch' ),
			'Claude-User'           => array( 'Anthropic', 'fetch', 'fetch' ),
			'MistralAI-User'        => array( 'Mistral', 'fetch', 'fetch' ),
			'Meta-ExternalFetcher'  => array( 'Meta', 'fetch', 'fetch' ),
			// AI search / answer indexing.
			'OAI-SearchBot'         => array( 'OpenAI', 'crawl', 'search' ),
			'Claude-SearchBot'      => array( 'Anthropic', 'crawl', 'search' ),
			'PerplexityBot'         => array( 'Perplexity', 'crawl', 'search' ),
			'DuckAssistBot'         => array( 'DuckDuckGo', 'crawl', 'search' ),
			'YouBot'                => array( 'You.com', 'crawl', 'search' ),
			'Amazonbot'             => array( 'Amazon', 'crawl', 'search' ),
			'Applebot'              => array( 'Apple', 'crawl', 'search' ),
			'bingbot'               => array( 'Microsoft', 'crawl', 'search' ),
			// Model training / bulk data collection.
			'GPTBot'                => array( 'OpenAI', 'crawl', 'training' ),
			'ClaudeBot'             => array( 'Anthropic', 'crawl', 'training' ),
			'anthropic-ai'          => array( 'Anthropic', 'crawl', 'training' ),
			'Google-CloudVertexBot' => array( 'Google', 'crawl', 'training' ),
			'GoogleOther'           => array( 'Google', 'crawl', 'training' ),
			'Meta-ExternalAgent'    => array( 'Meta', 'crawl', 'training' ),
			'FacebookBot'           => array( 'Meta', 'crawl', 'training' ),
			'Bytespider'            => array( 'ByteDance', 'crawl', 'training' ),
			'CCBot'                 => array( 'Common Crawl', 'crawl', 'training' ),
			'cohere-ai'             => array( 'Cohere', 'crawl', 'training' ),
			'cohere-training-data-crawler' => array( 'Cohere', 'crawl', 'training' ),
			'Diffbot'               => array( 'Diffbot', 'crawl', 'training' ),
		);
	}

	/**
	 * robots.txt tokens that never appear in a User-Agent but control AI use.
	 *
	 * @return array token => class
	 */
	public static function robots_only_tokens() {
		return array(
			'Google-Extended'   => 'training',
			'Applebot-Extended' => 'training',
		);
	}

	/**
	 * How each bot can be verified.
	 *
	 * @return array bot => [method, data]
	 */
	public static function verification_methods() {
		return array(
			'bingbot'         => array( 'rdns', array( 'search.msn.com' ) ),
			'Applebot'        => array( 'rdns', array( 'applebot.apple.com' ) ),
			'Amazonbot'       => array( 'rdns', array( 'crawl.amazonbot.amazon' ) ),
			'GoogleOther'     => array( 'rdns', array( 'googlebot.com', 'google.com', 'googleusercontent.com' ) ),
			'GPTBot'          => array( 'ips', 'https://openai.com/gptbot.json' ),
			'OAI-SearchBot'   => array( 'ips', 'https://openai.com/searchbot.json' ),
			'ChatGPT-User'    => array( 'ips', 'https://openai.com/chatgpt-user.json' ),
			'PerplexityBot'   => array( 'ips', 'https://www.perplexity.ai/perplexitybot.json' ),
			'Perplexity-User' => array( 'ips', 'https://www.perplexity.ai/perplexity-user.json' ),
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
			'meta.ai',
			'mistral.ai',
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
			'meta.ai'           => 'Meta AI',
			'chat.mistral.ai'   => 'Mistral',
		);
	}

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		return AIGS_DB::table( 'hits' );
	}

	/**
	 * Create the tracking table (kept for backwards compatibility).
	 */
	public static function install_table() {
		AIGS_DB::install();
	}

	/**
	 * Register hooks.
	 */
	public function hooks() {
		if ( aigs_get( 'enable_tracking', 1 ) ) {
			add_action( 'wp', aigs_guard( array( $this, 'maybe_log_hit' ) ), 99 );
		}
		add_action( 'aigs_hourly', aigs_guard( array( $this, 'verify_pending' ) ) );
		add_action( 'aigs_daily', aigs_guard( array( $this, 'rollup_and_prune' ) ) );
	}

	/**
	 * Client IP using the header chosen in Settings (for sites behind a proxy/CDN).
	 *
	 * @return string
	 */
	private static function client_ip() {
		$header  = (string) aigs_get( 'ip_header', 'REMOTE_ADDR' );
		$allowed = array( 'REMOTE_ADDR', 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP' );
		if ( ! in_array( $header, $allowed, true ) ) {
			$header = 'REMOTE_ADDR';
		}
		$raw = isset( $_SERVER[ $header ] ) ? sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) : '';
		if ( '' === $raw && 'REMOTE_ADDR' !== $header && isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$raw = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		$ip = trim( explode( ',', $raw )[0] );
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * Match a User-Agent against the bot map.
	 *
	 * @param string $ua User agent.
	 * @return array|null [needle, info]
	 */
	public static function match_bot( $ua ) {
		if ( '' === $ua ) {
			return null;
		}
		foreach ( self::bot_map() as $needle => $info ) {
			if ( false !== stripos( $ua, $needle ) ) {
				return array( $needle, $info );
			}
		}
		return null;
	}

	/**
	 * Match a referral by Referer host or utm_source.
	 *
	 * @param string $ref Referer.
	 * @param string $utm utm_source value.
	 * @return array|null [platform, source]
	 */
	public static function match_referral( $ref, $utm ) {
		foreach ( self::referral_map() as $needle => $platform ) {
			if ( '' !== $ref && false !== stripos( $ref, $needle ) ) {
				return array( $platform, 'referrer' );
			}
		}
		if ( '' !== $utm ) {
			foreach ( self::referral_map() as $needle => $platform ) {
				$short = preg_replace( '/^(chat\.)/', '', $needle );
				if ( false !== stripos( $utm, $short ) ) {
					return array( $platform, 'utm' );
				}
			}
		}
		return null;
	}

	/**
	 * Inspect the current request and log AI crawls / referrals.
	 */
	public function maybe_log_hit() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		// Until an administrator visit has upgraded the table, do not write to it.
		if ( ! AIGS_DB::is_current() ) {
			return;
		}

		$ua     = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$ref    = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$utm    = isset( $_GET['utm_source'] ) ? sanitize_text_field( wp_unslash( $_GET['utm_source'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only analytics of a public URL parameter.
		$status = is_404() ? 404 : 200;

		$bot = self::match_bot( $ua );
		if ( $bot ) {
			list( $needle, $info ) = $bot;
			$this->insert(
				array(
					'hit_type'  => $info[1],
					'bot_class' => $info[2],
					'platform'  => $info[0],
					'bot'       => $needle,
					'url'       => $uri,
					'ua'        => $ua,
					'source'    => 'ua',
					'status'    => $status,
					'ip'        => self::client_ip(),
				)
			);
			return;
		}

		$r = self::match_referral( $ref, $utm );
		if ( $r ) {
			// Human visit: no IP is stored.
			$this->insert(
				array(
					'hit_type' => 'referral',
					'platform' => $r[0],
					'url'      => $uri,
					'ua'       => $ua,
					'referrer' => $ref,
					'source'   => $r[1],
					'status'   => $status,
				)
			);
		}
	}

	/**
	 * Insert a hit row.
	 *
	 * @param array $row Columns.
	 */
	private function insert( $row ) {
		global $wpdb;
		$row = wp_parse_args(
			$row,
			array(
				'hit_type'  => 'crawl',
				'bot_class' => '',
				'platform'  => '',
				'bot'       => '',
				'url'       => '',
				'ua'        => '',
				'referrer'  => '',
				'source'    => '',
				'status'    => 200,
				'ip'        => '',
			)
		);
		foreach ( array( 'url', 'ua', 'referrer' ) as $k ) {
			$row[ $k ] = mb_substr( (string) $row[ $k ], 0, 255 );
		}
		$row['verified']   = self::UNVERIFIED;
		$row['created_at'] = AIGS_DB::now();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom analytics table insert.
		$wpdb->insert( self::table(), $row, array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s' ) );
	}

	/* ============================================================
	 * Verification (background, opt-in)
	 * ============================================================ */

	/**
	 * Is an IP inside a CIDR range (IPv4 or IPv6)?
	 *
	 * @param string $ip   IP.
	 * @param string $cidr Range.
	 * @return bool
	 */
	public static function ip_in_cidr( $ip, $cidr ) {
		$parts = explode( '/', $cidr, 2 );
		$net   = @inet_pton( $parts[0] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- invalid input returns false.
		$addr  = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- invalid input returns false.
		if ( false === $net || false === $addr || strlen( $net ) !== strlen( $addr ) ) {
			return false;
		}
		$bits = isset( $parts[1] ) ? (int) $parts[1] : strlen( $net ) * 8;
		$full = intdiv( $bits, 8 );
		if ( substr( $net, 0, $full ) !== substr( $addr, 0, $full ) ) {
			return false;
		}
		$rem = $bits % 8;
		if ( 0 === $rem ) {
			return true;
		}
		$mask = chr( ( 0xff << ( 8 - $rem ) ) & 0xff );
		return ( $net[ $full ] & $mask ) === ( $addr[ $full ] & $mask );
	}

	/**
	 * Published IP prefixes for a bot (cached for a day).
	 *
	 * @param string $url JSON URL.
	 * @return array|null Null when the list could not be fetched.
	 */
	private static function published_ranges( $url ) {
		$key    = 'aigs_ips_' . md5( $url );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$resp = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 10,
				'limit_response_size' => 512 * KB_IN_BYTES,
			)
		);
		if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
			return null;
		}
		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		$out  = array();
		if ( isset( $data['prefixes'] ) && is_array( $data['prefixes'] ) ) {
			foreach ( $data['prefixes'] as $p ) {
				foreach ( array( 'ipv4Prefix', 'ipv6Prefix' ) as $k ) {
					if ( ! empty( $p[ $k ] ) ) {
						$out[] = (string) $p[ $k ];
					}
				}
			}
		}
		if ( ! $out ) {
			return null;
		}
		set_transient( $key, $out, DAY_IN_SECONDS );
		return $out;
	}

	/**
	 * Reverse DNS + forward-confirm.
	 *
	 * @param string $ip       IP.
	 * @param array  $suffixes Allowed host suffixes.
	 * @return bool
	 */
	private static function rdns_ok( $ip, $suffixes ) {
		$host = gethostbyaddr( $ip );
		if ( ! $host || $host === $ip ) {
			return false;
		}
		$host  = strtolower( rtrim( $host, '.' ) );
		$match = false;
		foreach ( $suffixes as $s ) {
			if ( $host === $s || substr( $host, -strlen( '.' . $s ) ) === '.' . $s ) {
				$match = true;
				break;
			}
		}
		if ( ! $match ) {
			return false;
		}
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$ips = gethostbynamel( $host );
			return is_array( $ips ) && in_array( $ip, $ips, true );
		}
		$recs = function_exists( 'dns_get_record' ) ? @dns_get_record( $host, DNS_AAAA ) : array(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- resolver warnings are expected.
		foreach ( (array) $recs as $r ) {
			if ( isset( $r['ipv6'] ) && inet_pton( $r['ipv6'] ) === inet_pton( $ip ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Verify one IP for one bot.
	 *
	 * @param string $bot Bot needle.
	 * @param string $ip  IP.
	 * @return int Status constant.
	 */
	public static function verify( $bot, $ip ) {
		$methods = self::verification_methods();
		if ( ! isset( $methods[ $bot ] ) || '' === $ip ) {
			return self::UNVERIFIABLE;
		}
		$cache_key = 'aigs_v_' . md5( $bot . '|' . $ip );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return (int) $cached;
		}
		list( $method, $data ) = $methods[ $bot ];
		if ( 'rdns' === $method ) {
			$res = self::rdns_ok( $ip, $data ) ? self::VERIFIED : self::FAILED;
		} else {
			$ranges = self::published_ranges( $data );
			if ( null === $ranges ) {
				return self::UNVERIFIED; // Try again later.
			}
			$res = self::FAILED;
			foreach ( $ranges as $cidr ) {
				if ( self::ip_in_cidr( $ip, $cidr ) ) {
					$res = self::VERIFIED;
					break;
				}
			}
		}
		set_transient( $cache_key, $res, WEEK_IN_SECONDS );
		return $res;
	}

	/**
	 * Hourly: verify up to 40 pending (bot, IP) pairs.
	 */
	public function verify_pending() {
		global $wpdb;
		$table = self::table();
		if ( ! aigs_get( 'verify_bots', 0 ) ) {
			return;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
		$pairs = $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT bot, ip FROM {$table} WHERE verified = 0 AND ip <> '' AND bot <> '' AND created_at >= %s LIMIT 40", AIGS_DB::since( 7 ) ), ARRAY_A );
		foreach ( (array) $pairs as $p ) {
			$res = self::verify( $p['bot'], $p['ip'] );
			if ( self::UNVERIFIED === $res ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
			$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET verified = %d WHERE bot = %s AND ip = %s AND verified = 0", $res, $p['bot'], $p['ip'] ) );
		}
	}

	/**
	 * Daily: roll old rows into daily totals, then delete them.
	 */
	public function rollup_and_prune() {
		global $wpdb;
		$table = self::table();
		$daily = AIGS_DB::table( 'bot_daily' );
		$days  = max( 7, (int) aigs_get( 'retention_days', 90 ) );
		$cut   = AIGS_DB::since( $days );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom tables; aggregate copy before delete.
		$wpdb->query( $wpdb->prepare( "INSERT INTO {$daily} (day, hit_type, bot_class, platform, verified, hits) SELECT DATE(created_at), hit_type, bot_class, platform, verified, COUNT(*) FROM {$table} WHERE created_at < %s GROUP BY DATE(created_at), hit_type, bot_class, platform, verified ON DUPLICATE KEY UPDATE hits = hits + VALUES(hits)", $cut ) );
		// Delete in chunks to avoid long table locks.
		for ( $i = 0; $i < 20; $i++ ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
			$n = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s LIMIT 5000", $cut ) );
			if ( ! $n ) {
				break;
			}
		}
		// Keep two years of daily totals.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$daily} WHERE day < %s", gmdate( 'Y-m-d', time() - 730 * DAY_IN_SECONDS ) ) );
	}

	/* ============================================================
	 * Stats
	 * ============================================================ */

	/**
	 * Count hits in the last N days.
	 *
	 * @param string $type crawl|fetch|referral.
	 * @param int    $days Window.
	 * @return int
	 */
	public function count( $type, $days = 30 ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom analytics table; values prepared, table name from $wpdb->prefix.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE hit_type = %s AND created_at >= %s", $type, AIGS_DB::since( $days ) ) );
	}

	/**
	 * Bot observations by class with verification breakdown.
	 *
	 * @param int $days Window.
	 * @return array class => [total, verified, failed, unverifiable]
	 */
	public function by_class( $days = 30 ) {
		global $wpdb;
		$table = self::table();
		$out   = array();
		foreach ( array( 'fetch', 'search', 'training' ) as $c ) {
			$out[ $c ] = array(
				'total'        => 0,
				'verified'     => 0,
				'failed'       => 0,
				'unverifiable' => 0,
			);
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT bot_class, verified, COUNT(*) AS c FROM {$table} WHERE hit_type <> 'referral' AND created_at >= %s GROUP BY bot_class, verified", AIGS_DB::since( $days ) ), ARRAY_A );
		foreach ( (array) $rows as $r ) {
			$c = $r['bot_class'];
			if ( ! isset( $out[ $c ] ) ) {
				continue;
			}
			$n                  = (int) $r['c'];
			$out[ $c ]['total'] += $n;
			$v                  = (int) $r['verified'];
			if ( self::VERIFIED === $v ) {
				$out[ $c ]['verified'] += $n;
			} elseif ( self::FAILED === $v ) {
				$out[ $c ]['failed'] += $n;
			} elseif ( self::UNVERIFIABLE === $v ) {
				$out[ $c ]['unverifiable'] += $n;
			}
		}
		return $out;
	}

	/**
	 * Per-platform breakdown.
	 *
	 * @param string $type crawl|fetch|referral.
	 * @param int    $days Window.
	 * @return array
	 */
	public function by_platform( $type, $days = 30 ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom analytics table; values prepared, table name from $wpdb->prefix.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT platform, COUNT(*) AS c FROM {$table} WHERE hit_type = %s AND created_at >= %s GROUP BY platform ORDER BY c DESC", $type, AIGS_DB::since( $days ) ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[ $r['platform'] ] = (int) $r['c'];
		}
		return $out;
	}

	/**
	 * Per-bot breakdown with verification counts.
	 *
	 * @param int $days Window.
	 * @return array
	 */
	public function by_bot( $days = 30 ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT bot, platform, bot_class, COUNT(*) AS c, SUM(verified = 1) AS v, SUM(verified = 2) AS f FROM {$table} WHERE hit_type <> 'referral' AND created_at >= %s GROUP BY bot, platform, bot_class ORDER BY c DESC LIMIT 30", AIGS_DB::since( $days ) ), ARRAY_A );
	}

	/**
	 * Daily series for a chart.
	 *
	 * @param string $type crawl|fetch|referral.
	 * @param int    $days Window.
	 * @return array date => count
	 */
	public function daily_series( $type, $days = 30 ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom analytics table; values prepared, table name from $wpdb->prefix.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT DATE(created_at) AS d, COUNT(*) AS c FROM {$table} WHERE hit_type = %s AND created_at >= %s GROUP BY DATE(created_at)", $type, AIGS_DB::since( $days ) ), ARRAY_A );
		$map  = array();
		foreach ( (array) $rows as $r ) {
			$map[ $r['d'] ] = (int) $r['c'];
		}
		$now    = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- rows are stored in site time.
		$series = array();
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$day            = gmdate( 'Y-m-d', $now - $i * DAY_IN_SECONDS );
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
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom analytics table; values prepared, table name from $wpdb->prefix.
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", absint( $limit ) ), ARRAY_A );
	}

	/**
	 * Most-requested URLs.
	 *
	 * @param int    $limit Rows.
	 * @param int    $days  Window.
	 * @param string $type  crawl|fetch|referral.
	 * @return array
	 */
	public function top_urls( $limit = 10, $days = 30, $type = 'crawl' ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom analytics table; values prepared, table name from $wpdb->prefix.
		return $wpdb->get_results( $wpdb->prepare( "SELECT url, COUNT(*) AS c FROM {$table} WHERE hit_type = %s AND status < 400 AND created_at >= %s GROUP BY url ORDER BY c DESC LIMIT %d", $type, AIGS_DB::since( $days ), absint( $limit ) ), ARRAY_A );
	}

	/**
	 * Referral counts by how they were detected.
	 *
	 * @param int $days Window.
	 * @return array source => count
	 */
	public function referral_sources( $days = 30 ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT source, COUNT(*) AS c FROM {$table} WHERE hit_type = 'referral' AND created_at >= %s GROUP BY source", AIGS_DB::since( $days ) ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[ $r['source'] ? $r['source'] : 'referrer' ] = (int) $r['c'];
		}
		return $out;
	}

	/**
	 * Requests from AI search crawlers and assistant fetches (not training bots).
	 *
	 * @param int $days Window.
	 * @return int
	 */
	public static function ai_visits( $days = 21 ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE bot_class IN ('fetch','search') AND bot NOT IN ('bingbot','Applebot') AND created_at >= %s", AIGS_DB::since( $days ) ) );
	}

	/**
	 * Missing URLs (404) requested by AI bots.
	 *
	 * @param int $days  Window.
	 * @param int $limit Rows.
	 * @return array [url => count]
	 */
	public static function bot_404s( $days = 30, $limit = 10 ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT url, COUNT(*) AS c FROM {$table} WHERE hit_type <> 'referral' AND status = 404 AND verified <> 2 AND created_at >= %s GROUP BY url ORDER BY c DESC LIMIT %d", AIGS_DB::since( $days ), absint( $limit ) ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $r ) {
			// Ignore obvious probes for files that never existed.
			if ( preg_match( '#(\.(php|env|sql|bak|zip|git)|wp-login|xmlrpc|/\.well-known/)#i', $r['url'] ) ) {
				continue;
			}
			$out[ $r['url'] ] = (int) $r['c'];
		}
		return $out;
	}
}
