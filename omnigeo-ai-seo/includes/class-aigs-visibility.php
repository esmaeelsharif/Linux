<?php
/**
 * Visibility controls: site-health checks + IndexNow submission.
 *
 * @package OmniGEO_Ai_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One-click site health checks for AI discoverability and IndexNow pings.
 */
class AIGS_Visibility {

	/**
	 * Register hooks.
	 */
	public function hooks() {
		add_action( 'wp_ajax_aigs_health', array( $this, 'ajax_health' ) );
		add_action( 'wp_ajax_aigs_indexnow', array( $this, 'ajax_indexnow' ) );
		add_action( 'init', array( $this, 'serve_indexnow_key' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		if ( aigs_get( 'indexnow_auto', 0 ) ) {
			add_action( 'transition_post_status', array( $this, 'queue_ping' ), 10, 3 );
		}
		add_action( 'aigs_indexnow_ping', array( $this, 'send_ping' ) );
	}

	/**
	 * Queue an IndexNow ping when public content is published or updated.
	 * Runs in the background so saving a post never waits on the network.
	 *
	 * @param string  $new  New status.
	 * @param string  $old  Old status.
	 * @param WP_Post $post Post.
	 */
	public function queue_ping( $new, $old, $post ) {
		if ( 'publish' !== $new && 'publish' !== $old ) {
			return;
		}
		if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) || '' !== $post->post_password ) {
			return;
		}
		$type = get_post_type_object( $post->post_type );
		if ( ! $type || ! $type->public || 'attachment' === $post->post_type ) {
			return;
		}
		if ( '0' === (string) get_option( 'blog_public' ) ) {
			return;
		}
		// One ping per post per 10 minutes, however often it is saved.
		$key = 'aigs_inow_' . $post->ID;
		if ( get_transient( $key ) ) {
			return;
		}
		set_transient( $key, 1, 10 * MINUTE_IN_SECONDS );
		wp_schedule_single_event( time() + 30, 'aigs_indexnow_ping', array( (int) $post->ID ) );
	}

	/**
	 * Background: submit the post URL (also for unpublished posts, so engines drop them).
	 *
	 * @param int $post_id Post ID.
	 */
	public function send_ping( $post_id ) {
		$url = get_permalink( $post_id );
		if ( $url ) {
			$this->submit_indexnow( array( $url ) );
		}
	}

	/**
	 * Register query var for the IndexNow key file.
	 *
	 * @param array $vars Vars.
	 * @return array
	 */
	public function query_vars( $vars ) {
		$vars[] = 'aigs_indexnow_key';
		return $vars;
	}

	/**
	 * Serve the IndexNow verification key at /{key}.txt.
	 */
	public function serve_indexnow_key() {
		$key = aigs_get( 'indexnow_key', '' );
		if ( ! $key ) {
			return;
		}
		$req = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( trim( $req, '/' ) === $key . '.txt' ) {
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo esc_html( $key );
			exit;
		}
	}

	/* ============================================================
	 * Edge (CDN / firewall) check for AI crawlers
	 * ============================================================ */

	/**
	 * Lower-cased header array from a WP HTTP response (array or dictionary object).
	 *
	 * @param array|WP_Error $resp Response.
	 * @return array
	 */
	private static function headers_of( $resp ) {
		if ( is_wp_error( $resp ) ) {
			return array();
		}
		$h = wp_remote_retrieve_headers( $resp );
		if ( is_object( $h ) && method_exists( $h, 'getAll' ) ) {
			$h = $h->getAll();
		}
		$out = array();
		foreach ( (array) $h as $k => $v ) {
			$out[ strtolower( (string) $k ) ] = is_array( $v ) ? implode( ', ', $v ) : (string) $v;
		}
		return $out;
	}

	/**
	 * Which CDN / edge network serves the site, from response headers.
	 *
	 * @param array $h Lower-cased headers.
	 * @return string Name, or '' when none recognised.
	 */
	public static function detect_cdn( $h ) {
		$server = isset( $h['server'] ) ? strtolower( $h['server'] ) : '';
		$via    = isset( $h['via'] ) ? strtolower( $h['via'] ) : '';
		if ( isset( $h['cf-ray'] ) || false !== strpos( $server, 'cloudflare' ) ) {
			return 'Cloudflare';
		}
		if ( isset( $h['x-sucuri-id'] ) || false !== strpos( $server, 'sucuri' ) ) {
			return 'Sucuri';
		}
		if ( isset( $h['x-iinfo'] ) || ( isset( $h['x-cdn'] ) && false !== stripos( $h['x-cdn'], 'imperva' ) ) || ( isset( $h['x-cdn'] ) && false !== stripos( $h['x-cdn'], 'incapsula' ) ) ) {
			return 'Imperva';
		}
		if ( isset( $h['x-amz-cf-id'] ) || false !== strpos( $via, 'cloudfront' ) ) {
			return 'Amazon CloudFront';
		}
		if ( false !== strpos( $server, 'akamai' ) || isset( $h['x-akamai-transformed'] ) || isset( $h['akamai-grn'] ) ) {
			return 'Akamai';
		}
		if ( isset( $h['x-vercel-id'] ) ) {
			return 'Vercel';
		}
		if ( isset( $h['x-nf-request-id'] ) ) {
			return 'Netlify';
		}
		$fastly_cache = isset( $h['x-served-by'] ) && false !== stripos( $h['x-served-by'], 'cache-' ) && false !== strpos( $via, 'varnish' );
		if ( isset( $h['x-fastly-request-id'] ) || $fastly_cache ) {
			return 'Fastly';
		}
		if ( isset( $h['x-qc-cache'] ) || isset( $h['x-qc-pop'] ) || false !== strpos( $server, 'quic.cloud' ) ) {
			return 'QUIC.cloud';
		}
		if ( false !== strpos( $server, 'bunnycdn' ) ) {
			return 'Bunny CDN';
		}
		return '';
	}

	/**
	 * Is this response a block or a bot challenge rather than the page?
	 *
	 * @param int    $code HTTP status.
	 * @param array  $h    Lower-cased headers.
	 * @param string $body Body (first bytes are enough).
	 * @return bool
	 */
	public static function is_blocked_response( $code, $h, $body ) {
		if ( in_array( (int) $code, array( 401, 403, 406, 429, 503 ), true ) ) {
			return true;
		}
		if ( isset( $h['cf-mitigated'] ) ) {
			return true;
		}
		$b = substr( (string) $body, 0, 20000 );
		$markers = array( 'Just a moment...', '_cf_chl_opt', 'cf-browser-verification', 'Attention Required! | Cloudflare', 'Access denied | ', 'Sucuri WebSite Firewall - Access Denied', 'Incapsula incident ID', 'Request unsuccessful. Incapsula', 'Pardon Our Interruption' );
		foreach ( $markers as $m ) {
			if ( false !== stripos( $b, $m ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Request the homepage as a browser and as AI crawlers, and compare.
	 * Cached for 12 hours.
	 *
	 * @param bool $force Ignore the cache.
	 * @return array{cdn:string,browser:int,blocked:array,tested:array,time:int,error:string}
	 */
	public static function edge_probe( $force = false ) {
		$cached = get_transient( 'aigs_edge_probe' );
		if ( ! $force && is_array( $cached ) ) {
			return $cached;
		}
		$url  = home_url( '/' );
		$args = array(
			'timeout'             => 12,
			'redirection'         => 3,
			'limit_response_size' => 200 * KB_IN_BYTES,
		);
		$res = array(
			'cdn'     => '',
			'browser' => 0,
			'blocked' => array(),
			'tested'  => array(),
			'time'    => time(),
			'error'   => '',
		);

		$args['user-agent'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';
		$base               = wp_safe_remote_get( $url, $args );
		if ( is_wp_error( $base ) ) {
			$res['error'] = $base->get_error_message();
			set_transient( 'aigs_edge_probe', $res, HOUR_IN_SECONDS );
			return $res;
		}
		$h              = self::headers_of( $base );
		$res['cdn']     = self::detect_cdn( $h );
		$res['browser'] = (int) wp_remote_retrieve_response_code( $base );
		if ( self::is_blocked_response( $res['browser'], $h, wp_remote_retrieve_body( $base ) ) ) {
			// Even a normal browser request is challenged from this server; the test is not conclusive.
			$res['error'] = 'browser_blocked';
			set_transient( 'aigs_edge_probe', $res, 12 * HOUR_IN_SECONDS );
			return $res;
		}

		$bots = array(
			'OAI-SearchBot' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; OAI-SearchBot/1.0; +https://openai.com/searchbot',
			'ChatGPT-User'  => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; ChatGPT-User/1.0; +https://openai.com/bot',
			'PerplexityBot' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; PerplexityBot/1.0; +https://perplexity.ai/perplexitybot)',
			'GPTBot'        => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; GPTBot/1.1; +https://openai.com/gptbot',
		);
		foreach ( $bots as $name => $ua ) {
			$args['user-agent'] = $ua;
			$r                  = wp_safe_remote_get( $url, $args );
			$code               = is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r );
			$res['tested'][ $name ] = $code;
			if ( is_wp_error( $r ) || self::is_blocked_response( $code, self::headers_of( $r ), wp_remote_retrieve_body( $r ) ) ) {
				$res['blocked'][] = $name;
			}
		}
		set_transient( 'aigs_edge_probe', $res, 12 * HOUR_IN_SECONDS );
		return $res;
	}

	/**
	 * Where to change the setting, per edge network.
	 *
	 * @param string $cdn CDN name.
	 * @return string
	 */
	public static function edge_fix_hint( $cdn ) {
		switch ( $cdn ) {
			case 'Cloudflare':
				return __( 'In Cloudflare, review the AI crawler controls (for example “Block AI bots” or AI Crawl Control) and Bot Fight Mode, then allow the AI search crawlers you want, such as OAI-SearchBot, ChatGPT-User and PerplexityBot.', 'omnigeo-ai-seo' );
			case 'Sucuri':
			case 'Imperva':
			case 'Akamai':
			case 'Amazon CloudFront':
			case 'Fastly':
				/* translators: %s: CDN or firewall name. */
				return sprintf( __( 'In your %s firewall or bot management settings, allow the AI search crawlers you want (OAI-SearchBot, ChatGPT-User, PerplexityBot).', 'omnigeo-ai-seo' ), $cdn );
		}
		return __( 'Check your host, CDN, firewall and any security plugin for bot blocking rules, and allow the AI search crawlers you want (OAI-SearchBot, ChatGPT-User, PerplexityBot).', 'omnigeo-ai-seo' );
	}

	/**
	 * AJAX: run site-health checks.
	 */
	public function ajax_health() {
		check_ajax_referer( 'aigs_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}
		wp_send_json_success( $this->run_checks() );
	}

	/**
	 * Run all site-health checks.
	 *
	 * @return array
	 */
	public function run_checks() {
		$out = array();

		// HTTPS.
		$out[] = array(
			'label'  => __( 'HTTPS enabled', 'omnigeo-ai-seo' ),
			'pass'   => is_ssl() || 0 === stripos( home_url(), 'https' ),
			'remark' => __( 'Browsers warn visitors on insecure pages and engines prefer HTTPS.', 'omnigeo-ai-seo' ),
		);

		// robots.txt.
		$robots = wp_remote_get( home_url( '/robots.txt' ), array( 'timeout' => 10, 'sslverify' => true ) );
		$out[]  = array(
			'label'  => __( 'robots.txt reachable', 'omnigeo-ai-seo' ),
			'pass'   => ! is_wp_error( $robots ) && 200 === (int) wp_remote_retrieve_response_code( $robots ),
			'remark' => __( 'Crawlers read robots.txt before fetching.', 'omnigeo-ai-seo' ),
		);

		// Sitemap (try common locations).
		$sitemap_ok = false;
		foreach ( array( '/sitemap.xml', '/sitemap_index.xml', '/wp-sitemap.xml' ) as $path ) {
			$r = wp_remote_head( home_url( $path ), array( 'timeout' => 10, 'sslverify' => true ) );
			if ( ! is_wp_error( $r ) && 200 === (int) wp_remote_retrieve_response_code( $r ) ) {
				$sitemap_ok = true;
				break;
			}
		}
		$out[] = array(
			'label'  => __( 'XML sitemap available', 'omnigeo-ai-seo' ),
			'pass'   => $sitemap_ok,
			'remark' => __( 'Helps AI crawlers discover all your URLs.', 'omnigeo-ai-seo' ),
		);

		// llms.txt.
		$llms = wp_remote_head( home_url( '/llms.txt' ), array( 'timeout' => 10, 'sslverify' => true ) );
		$out[] = array(
			'label'  => __( 'llms.txt served (optional)', 'omnigeo-ai-seo' ),
			'pass'   => ! is_wp_error( $llms ) && 200 === (int) wp_remote_retrieve_response_code( $llms ),
			'remark' => __( 'Optional. A map for AI tools; Google says it is not needed for AI Overviews.', 'omnigeo-ai-seo' ),
		);

		// Search engine visibility (blog_public).
		$out[] = array(
			'label'  => __( 'Search engine indexing allowed', 'omnigeo-ai-seo' ),
			'pass'   => '1' === get_option( 'blog_public' ),
			'remark' => __( 'Settings → Reading must allow indexing.', 'omnigeo-ai-seo' ),
		);

		// Permalinks (pretty).
		$out[] = array(
			'label'  => __( 'Readable permalinks', 'omnigeo-ai-seo' ),
			'pass'   => '' !== get_option( 'permalink_structure' ),
			'remark' => __( 'Clean URLs are easier for AI to cite.', 'omnigeo-ai-seo' ),
		);

		// AI crawlers reach the site through the CDN / firewall.
		$probe = self::edge_probe( true );
		if ( '' === $probe['error'] ) {
			$out[] = array(
				'label'  => __( 'AI crawlers get through your CDN / firewall', 'omnigeo-ai-seo' ),
				'pass'   => empty( $probe['blocked'] ),
				'remark' => empty( $probe['blocked'] )
					/* translators: %s: CDN name or "no CDN detected". */
					? sprintf( __( 'Requests with AI crawler user agents receive your page. Edge network: %s.', 'omnigeo-ai-seo' ), '' !== $probe['cdn'] ? $probe['cdn'] : __( 'none detected', 'omnigeo-ai-seo' ) )
					/* translators: %s: bot names. */
					: sprintf( __( 'Blocked or challenged: %s.', 'omnigeo-ai-seo' ), implode( ', ', $probe['blocked'] ) ) . ' ' . self::edge_fix_hint( $probe['cdn'] ),
			);
		}

		// Homepage freshness (modified within 180 days).
		$front = (int) get_option( 'page_on_front' );
		$fresh = false;
		if ( $front ) {
			$mod   = get_post_modified_time( 'U', true, $front );
			$fresh = $mod && ( time() - $mod ) < ( 180 * DAY_IN_SECONDS );
		}
		$out[] = array(
			'label'  => __( 'Homepage freshness', 'omnigeo-ai-seo' ),
			'pass'   => $fresh,
			'remark' => __( 'Recently updated content signals relevance.', 'omnigeo-ai-seo' ),
		);

		return $out;
	}

	/**
	 * AJAX: submit URL(s) to IndexNow.
	 */
	public function ajax_indexnow() {
		check_ajax_referer( 'aigs_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}
		$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : home_url( '/' );
		if ( ! AIGS_AI_Client::host_matches( $url, home_url() ) ) {
			wp_send_json_error( array( 'message' => __( 'IndexNow only accepts URLs on this site.', 'omnigeo-ai-seo' ) ) );
		}
		$result = $this->submit_indexnow( array( $url ) );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'code' => $result ) );
	}

	/**
	 * Submit URLs to the IndexNow endpoint.
	 *
	 * @param array $urls URLs.
	 * @return int|WP_Error HTTP status code.
	 */
	public function submit_indexnow( $urls ) {
		$key  = aigs_get( 'indexnow_key', '' );
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! $key ) {
			return new WP_Error( 'no_key', __( 'No IndexNow key set.', 'omnigeo-ai-seo' ) );
		}
		$body = wp_json_encode(
			array(
				'host'        => $host,
				'key'         => $key,
				'keyLocation' => home_url( '/' . $key . '.txt' ),
				'urlList'     => array_values( $urls ),
			)
		);
		$resp = wp_remote_post(
			'https://api.indexnow.org/indexnow',
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
				'body'    => $body,
			)
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		return (int) wp_remote_retrieve_response_code( $resp );
	}
}
