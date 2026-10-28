<?php
/**
 * SEO plugin integration layer.
 *
 * Detects the active SEO plugin, reads its focus keyword, and reports whether it
 * already emits an entity schema graph (so we can avoid duplicating it).
 *
 * @package OmniGEO_Ai_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bridges Yoast, Rank Math, SEOPress and All in One SEO.
 */
class AIGS_SEO {

	/**
	 * Which SEO plugin is active.
	 *
	 * @return string yoast|rankmath|seopress|aioseo|''
	 */
	public static function active() {
		if ( defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' ) ) {
			return 'yoast';
		}
		if ( class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' ) ) {
			return 'rankmath';
		}
		if ( defined( 'SEOPRESS_VERSION' ) ) {
			return 'seopress';
		}
		if ( defined( 'AIOSEO_VERSION' ) || function_exists( 'aioseo' ) ) {
			return 'aioseo';
		}
		return '';
	}

	/**
	 * Human label for the active plugin.
	 *
	 * @return string
	 */
	public static function name() {
		$map = array(
			'yoast'    => 'Yoast SEO',
			'rankmath' => 'Rank Math',
			'seopress' => 'SEOPress',
			'aioseo'   => 'All in One SEO',
		);
		$a = self::active();
		return isset( $map[ $a ] ) ? $map[ $a ] : '';
	}

	/**
	 * Does the active plugin output its own Organization/Article schema graph?
	 *
	 * @return bool
	 */
	public static function outputs_schema() {
		return in_array( self::active(), array( 'yoast', 'rankmath', 'seopress', 'aioseo' ), true );
	}

	/**
	 * Read the focus keyword for a post from the active SEO plugin.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function focus_keyword( $post_id ) {
		switch ( self::active() ) {
			case 'yoast':
				return trim( (string) get_post_meta( $post_id, '_yoast_wpseo_focuskw', true ) );

			case 'rankmath':
				$kw = (string) get_post_meta( $post_id, 'rank_math_focus_keyword', true );
				return self::first( $kw );

			case 'seopress':
				$kw = (string) get_post_meta( $post_id, '_seopress_analysis_target_kw', true );
				return self::first( $kw );

			case 'aioseo':
				return self::aioseo_keyword( $post_id );
		}
		return '';
	}

	/**
	 * Read the SEO meta description for a post from the active SEO plugin.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function meta_description( $post_id ) {
		switch ( self::active() ) {
			case 'yoast':
				return trim( (string) get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ) );

			case 'rankmath':
				return trim( (string) get_post_meta( $post_id, 'rank_math_description', true ) );

			case 'seopress':
				return trim( (string) get_post_meta( $post_id, '_seopress_titles_desc', true ) );

			case 'aioseo':
				return self::aioseo_description( $post_id );
		}
		return '';
	}

	/**
	 * Read AIOSEO meta description from its custom table (best effort).
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private static function aioseo_description( $post_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aioseo_posts';
		$wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- optional read of AIOSEO table; value prepared, table name from $wpdb->prefix.
		$desc = $wpdb->get_var( $wpdb->prepare( "SELECT description FROM {$table} WHERE post_id = %d", $post_id ) );
		$wpdb->suppress_errors( false );
		return trim( (string) $desc );
	}

	/**
	 * Take the first keyword from a comma-separated list.
	 *
	 * @param string $kw Raw value.
	 * @return string
	 */
	private static function first( $kw ) {
		if ( '' === $kw ) {
			return '';
		}
		$parts = explode( ',', $kw );
		return trim( $parts[0] );
	}

	/**
	 * Read AIOSEO focus keyphrase from its custom table (best effort).
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private static function aioseo_keyword( $post_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aioseo_posts';
		$wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- optional read of AIOSEO table; value prepared, table name from $wpdb->prefix.
		$json = $wpdb->get_var( $wpdb->prepare( "SELECT keyphrases FROM {$table} WHERE post_id = %d", $post_id ) );
		$wpdb->suppress_errors( false );
		if ( $json ) {
			$data = json_decode( $json, true );
			if ( ! empty( $data['focus']['keyphrase'] ) ) {
				return trim( $data['focus']['keyphrase'] );
			}
		}
		return '';
	}
}
