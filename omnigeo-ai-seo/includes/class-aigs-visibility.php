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
