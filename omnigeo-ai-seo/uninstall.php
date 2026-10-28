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

delete_option( 'aigs_settings' );

// Drop tracking table. Table name is built from $wpdb->prefix and is safe.
$aigs_table = $wpdb->prefix . 'aigs_hits';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter -- one-off uninstall cleanup; table name from $wpdb->prefix.
$wpdb->query( "DROP TABLE IF EXISTS {$aigs_table}" );

// Remove post meta.
$aigs_keys = array( '_aigs_schema_type', '_aigs_about', '_aigs_keywords', '_aigs_sameas', '_aigs_speakable', '_aigs_faqs', '_aigs_extract_score' );
foreach ( $aigs_keys as $aigs_k ) {
	delete_post_meta_by_key( $aigs_k );
}

// Remove author entity user meta.
foreach ( array( 'aigs_jobtitle', 'aigs_linkedin', 'aigs_twitter', 'aigs_knowsabout' ) as $aigs_uk ) {
	delete_metadata( 'user', 0, $aigs_uk, '', true );
}
