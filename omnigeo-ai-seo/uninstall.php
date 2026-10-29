<?php
/**
 * Uninstall cleanup.
 *
 * @package OmniGEO_Ai_SEO
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

foreach ( array( 'aigs_settings', 'aigs_db_version', 'aigs_scan_state', 'aigs_scan_summary', 'aigs_score_history', 'aigs_vis_state', 'aigs_vis_last_batch', 'aigs_vis_last_run', 'aigs_ai_usage', 'aigs_bulk_log', 'aigs_onboarding_done', 'aigs_link_log', 'aigs_tracking_since', 'aigs_error_log', 'aigs_upgrade_lock', 'aigs_backfill_pending', 'aigs_backfill_done' ) as $aigs_opt ) {
	delete_option( $aigs_opt );
}

foreach ( array( 'aigs_daily', 'aigs_weekly', 'aigs_hourly', 'aigs_scan_continue', 'aigs_visibility_continue', 'aigs_indexnow_ping' ) as $aigs_hook ) {
	wp_clear_scheduled_hook( $aigs_hook );
}

// Drop custom tables. Names are built from $wpdb->prefix and are safe.
foreach ( array( 'aigs_hits', 'aigs_findings', 'aigs_ai_runs', 'aigs_bot_daily' ) as $aigs_t ) {
	$aigs_table = $wpdb->prefix . $aigs_t;
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter -- one-off uninstall cleanup; table name from $wpdb->prefix.
	$wpdb->query( "DROP TABLE IF EXISTS {$aigs_table}" );
}

// Remove post meta.
$aigs_keys = array(
	'_aigs_schema_type',
	'_aigs_about',
	'_aigs_keywords',
	'_aigs_sameas',
	'_aigs_speakable',
	'_aigs_faqs',
	'_aigs_extract_score',
	'_aigs_page_score',
	'_aigs_importance',
	'_aigs_important',
	'_aigs_scan_hash',
	'_aigs_scanned_at',
	'_aigs_links_out',
	'_aigs_links_in',
	'_aigs_path',
	'_aigs_words',
	'_aigs_desc_hash',
	'_aigs_title_hash',
	'_aigs_pdesc_hash',
	'_aigs_low',
	'_aigs_link_undo',
);
foreach ( $aigs_keys as $aigs_k ) {
	delete_post_meta_by_key( $aigs_k );
}

// Remove author entity user meta.
foreach ( array( 'aigs_jobtitle', 'aigs_linkedin', 'aigs_twitter', 'aigs_knowsabout' ) as $aigs_uk ) {
	delete_metadata( 'user', 0, $aigs_uk, '', true );
}

// Cached drafts, IP lists and verification results.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off uninstall cleanup of our own transients.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_aigs\_%' OR option_name LIKE '\_transient\_timeout\_aigs\_%'" );
