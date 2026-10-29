<?php
/**
 * Small site tools: duplicate as draft, settings backup and restore, and a
 * diagnostics check that explains (and where safe, repairs) setup problems.
 *
 * @package OmniGEO_Ai_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Duplicate, export/import and diagnostics.
 */
class AIGS_Tools {

	/**
	 * Register hooks.
	 */
	public function hooks() {
		if ( aigs_get( 'tb_duplicate', 0 ) ) {
			add_filter( 'post_row_actions', array( $this, 'row_action' ), 10, 2 );
			add_filter( 'page_row_actions', array( $this, 'row_action' ), 10, 2 );
			add_action( 'admin_post_aigs_duplicate', array( $this, 'duplicate' ) );
		}
		add_action( 'admin_post_aigs_export', array( $this, 'export' ) );
		add_action( 'admin_post_aigs_import', array( $this, 'import' ) );
		add_action( 'admin_post_aigs_repair', array( $this, 'repair' ) );
	}

	/* ------------------------------------------------------------
	 * Duplicate as draft
	 * ------------------------------------------------------------ */

	/**
	 * Add "Duplicate as draft" under each post title.
	 *
	 * @param array   $actions Row actions.
	 * @param WP_Post $post    Post.
	 * @return array
	 */
	public function row_action( $actions, $post ) {
		if ( ! current_user_can( 'edit_posts' ) || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=aigs_duplicate&post=' . (int) $post->ID ), 'aigs_duplicate_' . (int) $post->ID );
		$actions['aigs_duplicate'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Duplicate as draft', 'omnigeo-ai-seo' ) . '</a>';
		return $actions;
	}

	/**
	 * Meta keys never copied to a duplicate.
	 *
	 * @return array
	 */
	public static function skip_meta() {
		return array( '_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date', '_wp_trash_meta_status', '_wp_trash_meta_time' );
	}

	/**
	 * Should a meta key be copied? Scan results and undo data belong to the
	 * original; what the author typed (schema, FAQs, snippet) is copied.
	 *
	 * @param string $key Meta key.
	 * @return bool
	 */
	public static function copy_meta_key( $key ) {
		if ( in_array( $key, self::skip_meta(), true ) ) {
			return false;
		}
		if ( 0 === strpos( $key, '_aigs_' ) ) {
			return in_array( $key, array( '_aigs_about', '_aigs_faqs', '_aigs_keywords', '_aigs_sameas', '_aigs_schema_type', '_aigs_speakable', '_aigs_seo_title', '_aigs_seo_desc' ), true );
		}
		return true;
	}

	/**
	 * Create the draft copy and open it in the editor.
	 */
	public function duplicate() {
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( 'aigs_duplicate_' . $id );
		$src = get_post( $id );
		if ( ! $src || ! current_user_can( 'edit_post', $id ) ) {
			wp_die( esc_html__( 'Permission denied.', 'omnigeo-ai-seo' ) );
		}
		$new_id = wp_insert_post(
			wp_slash(
				array(
					/* translators: %s: original title. */
					'post_title'     => sprintf( __( '%s (copy)', 'omnigeo-ai-seo' ), $src->post_title ),
					'post_content'   => $src->post_content,
					'post_excerpt'   => $src->post_excerpt,
					'post_type'      => $src->post_type,
					'post_status'    => 'draft',
					'post_parent'    => $src->post_parent,
					'menu_order'     => $src->menu_order,
					'comment_status' => $src->comment_status,
					'ping_status'    => $src->ping_status,
					'post_author'    => get_current_user_id(),
				)
			),
			true
		);
		if ( is_wp_error( $new_id ) ) {
			wp_die( esc_html( $new_id->get_error_message() ) );
		}
		foreach ( get_object_taxonomies( $src->post_type ) as $tax ) {
			$terms = wp_get_object_terms( $id, $tax, array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $terms ) && $terms ) {
				wp_set_object_terms( $new_id, $terms, $tax );
			}
		}
		foreach ( get_post_meta( $id ) as $key => $values ) {
			if ( ! self::copy_meta_key( $key ) ) {
				continue;
			}
			foreach ( $values as $v ) {
				add_post_meta( $new_id, $key, wp_slash( maybe_unserialize( $v ) ) );
			}
		}
		wp_safe_redirect( get_edit_post_link( $new_id, 'raw' ) );
		exit;
	}

	/* ------------------------------------------------------------
	 * Settings backup
	 * ------------------------------------------------------------ */

	/**
	 * Settings that must never leave the site in a backup file.
	 *
	 * @return array
	 */
	public static function secret_keys() {
		return array( 'ai_api_key', 'indexnow_key' );
	}

	/**
	 * Build the backup array (pure apart from reading options).
	 *
	 * @return array
	 */
	public static function backup() {
		$settings = get_option( 'aigs_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();
		foreach ( self::secret_keys() as $k ) {
			unset( $settings[ $k ] );
		}
		return array(
			'plugin'    => 'omnigeo-ai-seo',
			'version'   => AIGS_VERSION,
			'created'   => gmdate( 'c' ),
			'settings'  => $settings,
			'redirects' => AIGS_Redirects::rules(),
		);
	}

	/**
	 * Restore a backup into current settings. Only keys the plugin knows are
	 * taken, with their type preserved; secrets are never overwritten.
	 *
	 * @param array $current Current settings.
	 * @param array $data    Decoded backup.
	 * @return array|WP_Error [settings, redirects]
	 */
	public static function restore( $current, $data ) {
		if ( ! is_array( $data ) || ! isset( $data['plugin'] ) || 'omnigeo-ai-seo' !== $data['plugin'] || ! isset( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
			return new WP_Error( 'aigs_backup', __( 'This file is not an OmniGEO Ai SEO backup.', 'omnigeo-ai-seo' ) );
		}
		$current = is_array( $current ) ? $current : array();
		$secrets = self::secret_keys();
		foreach ( $data['settings'] as $k => $v ) {
			$k = sanitize_key( $k );
			if ( '' === $k || in_array( $k, $secrets, true ) ) {
				continue;
			}
			if ( is_array( $v ) ) {
				$current[ $k ] = array_map( 'sanitize_text_field', array_filter( $v, 'is_scalar' ) );
			} elseif ( is_int( $v ) || is_float( $v ) ) {
				$current[ $k ] = $v;
			} elseif ( is_string( $v ) ) {
				$current[ $k ] = false !== strpos( $v, "\n" ) ? sanitize_textarea_field( $v ) : sanitize_text_field( $v );
			}
		}
		$redirects = array();
		if ( isset( $data['redirects'] ) && is_array( $data['redirects'] ) ) {
			foreach ( $data['redirects'] as $from => $r ) {
				if ( ! is_array( $r ) || ! isset( $r['to'], $r['code'] ) ) {
					continue;
				}
				$next = AIGS_Redirects::add_rule( $redirects, $from, $r['to'], (int) $r['code'], 'import' );
				if ( ! is_wp_error( $next ) ) {
					$redirects = $next;
				}
			}
		}
		return array( $current, $redirects );
	}

	/**
	 * Download the backup file.
	 */
	public function export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'omnigeo-ai-seo' ) );
		}
		check_admin_referer( 'aigs_export' );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="omnigeo-ai-seo-' . gmdate( 'Y-m-d' ) . '.json"' );
		echo wp_json_encode( self::backup(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		exit;
	}

	/**
	 * Restore from an uploaded backup file.
	 */
	public function import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'omnigeo-ai-seo' ) );
		}
		check_admin_referer( 'aigs_import' );
		$raw = '';
		if ( isset( $_FILES['backup']['tmp_name'] ) && is_uploaded_file( $_FILES['backup']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- path from PHP's upload handler, checked with is_uploaded_file.
			$raw = (string) file_get_contents( $_FILES['backup']['tmp_name'], false, null, 0, 2000000 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- reading the uploaded temp file; content is validated below.
		}
		$result = self::restore( get_option( 'aigs_settings', array() ), json_decode( $raw, true ) );
		$status = 'restored';
		if ( is_wp_error( $result ) ) {
			$status = 'bad_backup';
		} else {
			update_option( 'aigs_settings', $result[0] );
			if ( $result[1] ) {
				AIGS_Redirects::save_rules( array_merge( AIGS_Redirects::rules(), $result[1] ) );
			}
		}
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'   => 'aigs-toolbox',
					'tab'    => 'tools',
					'backup' => $status,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/* ------------------------------------------------------------
	 * Diagnostics
	 * ------------------------------------------------------------ */

	/**
	 * Run every diagnostic. Each item: [status ok|warn|fail|info, title, detail, repair-action or ''].
	 *
	 * @return array
	 */
	public static function diagnostics() {
		global $wp_version;
		$out = array();

		$php = PHP_VERSION;
		$out[] = array(
			version_compare( $php, '7.4', '>=' ) ? 'ok' : 'warn',
			/* translators: %s: PHP version. */
			sprintf( __( 'PHP %s', 'omnigeo-ai-seo' ), $php ),
			version_compare( $php, '7.4', '>=' ) ? '' : __( 'The plugin works, but your host supports newer PHP versions that are faster and safer. Ask your host to upgrade.', 'omnigeo-ai-seo' ),
			'',
		);
		$out[] = array(
			'ok',
			/* translators: %s: WordPress version. */
			sprintf( __( 'WordPress %s', 'omnigeo-ai-seo' ), $wp_version ),
			'',
			'',
		);

		$pretty = '' !== (string) get_option( 'permalink_structure' );
		$out[]  = array(
			$pretty ? 'ok' : 'fail',
			__( 'Pretty permalinks', 'omnigeo-ai-seo' ),
			$pretty ? '' : __( '/llms.txt and /llms-full.txt need pretty permalinks. Go to Settings → Permalinks and pick any option except "Plain".', 'omnigeo-ai-seo' ),
			'',
		);

		$rules   = get_option( 'rewrite_rules' );
		$has_llm = is_array( $rules ) && isset( $rules['^llms\.txt$'] ) && isset( $rules['^llms-full\.txt$'] );
		$out[]   = array(
			$has_llm || ! $pretty ? 'ok' : 'warn',
			__( 'llms.txt addresses registered', 'omnigeo-ai-seo' ),
			$has_llm || ! $pretty ? '' : __( 'WordPress has not picked up the llms.txt addresses yet.', 'omnigeo-ai-seo' ),
			$has_llm || ! $pretty ? '' : 'rewrites',
		);

		foreach ( array( 'robots.txt', 'llms.txt' ) as $file ) {
			$exists = file_exists( ABSPATH . $file );
			$out[]  = array(
				$exists ? 'warn' : 'ok',
				/* translators: %s: file name. */
				sprintf( __( 'No physical %s file', 'omnigeo-ai-seo' ), $file ),
				/* translators: %s: file name. */
				$exists ? sprintf( __( 'A real %s file in your site folder is served instead of the one this plugin builds, so your settings here have no effect. Rename or delete it with your host\'s file manager (keep a copy first).', 'omnigeo-ai-seo' ), $file ) : '',
				'',
			);
		}

		$public = '0' !== (string) get_option( 'blog_public' );
		$out[]  = array(
			$public ? 'ok' : 'fail',
			__( 'Site visible to search engines', 'omnigeo-ai-seo' ),
			$public ? '' : __( '"Discourage search engines" is on in Settings → Reading. Search engines and AI crawlers are asked to stay away.', 'omnigeo-ai-seo' ),
			'',
		);

		$https = 0 === strpos( home_url(), 'https://' );
		$out[] = array(
			$https ? 'ok' : 'warn',
			__( 'Secure address (HTTPS)', 'omnigeo-ai-seo' ),
			$https ? '' : __( 'Your site address starts with http://. Ask your host for a free certificate, then update the address in Settings → General.', 'omnigeo-ai-seo' ),
			'',
		);

		$cron_off = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		$next     = wp_next_scheduled( 'aigs_daily' );
		$late     = $next && $next < time() - DAY_IN_SECONDS;
		$out[]    = array(
			( ! $next || $late ) ? 'warn' : 'ok',
			__( 'Background jobs (WP-Cron)', 'omnigeo-ai-seo' ),
			! $next ? __( 'The daily job is not scheduled.', 'omnigeo-ai-seo' ) : ( $late ? ( $cron_off ? __( 'WP-Cron is turned off and no server cron is running it. Ask your host to run wp-cron.php every few minutes.', 'omnigeo-ai-seo' ) : __( 'Scheduled jobs are running late. Low traffic sites can ask their host for a server cron.', 'omnigeo-ai-seo' ) ) : '' ),
			! $next ? 'cron' : '',
		);

		$db_ok = AIGS_DB::is_current();
		$out[] = array(
			$db_ok ? 'ok' : 'warn',
			__( 'Database tables', 'omnigeo-ai-seo' ),
			$db_ok ? '' : __( 'The plugin tables are missing or out of date.', 'omnigeo-ai-seo' ),
			$db_ok ? '' : 'db',
		);

		$seo   = AIGS_SEO::name();
		$out[] = array(
			'info',
			__( 'SEO plugin', 'omnigeo-ai-seo' ),
			'' !== $seo
				/* translators: %s: SEO plugin name. */
				? sprintf( __( '%s is active, so titles, descriptions, social tags and sitemaps are left to it. OmniGEO adds the AI layer on top.', 'omnigeo-ai-seo' ), $seo )
				: __( 'None found. The Search & social tab covers titles, descriptions, social cards and verification for you.', 'omnigeo-ai-seo' ),
			'',
		);

		$att = get_option( 'wp_attachment_pages_enabled', null );
		if ( null !== $att ) {
			$on    = '1' === (string) $att;
			$out[] = array(
				$on ? 'warn' : 'ok',
				__( 'Attachment pages turned off', 'omnigeo-ai-seo' ),
				$on ? __( 'Every uploaded image has its own thin page that search engines and AI crawlers can index. Turning them off sends visitors to the file itself.', 'omnigeo-ai-seo' ) : '',
				$on ? 'attachments' : '',
			);
		}

		$errors = get_option( 'aigs_error_log', array() );
		$count  = is_array( $errors ) ? count( $errors ) : 0;
		$out[]  = array(
			$count ? 'warn' : 'ok',
			__( 'Plugin errors', 'omnigeo-ai-seo' ),
			/* translators: %d: number of errors. */
			$count ? sprintf( _n( '%d error was caught safely. The site kept working; details are on the Settings page.', '%d errors were caught safely. The site kept working; details are on the Settings page.', $count, 'omnigeo-ai-seo' ), $count ) : '',
			'',
		);
		return $out;
	}

	/**
	 * Repair one diagnostic.
	 */
	public function repair() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'omnigeo-ai-seo' ) );
		}
		check_admin_referer( 'aigs_repair' );
		$what = isset( $_GET['what'] ) ? sanitize_key( $_GET['what'] ) : '';
		switch ( $what ) {
			case 'rewrites':
				flush_rewrite_rules( false );
				break;
			case 'cron':
				AIGS_DB::ensure_events();
				break;
			case 'db':
				AIGS_DB::install();
				break;
			case 'attachments':
				update_option( 'wp_attachment_pages_enabled', '0' );
				break;
		}
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'     => 'aigs-toolbox',
					'tab'      => 'tools',
					'repaired' => $what,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
