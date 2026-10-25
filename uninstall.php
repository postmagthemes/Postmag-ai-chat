<?php
/**
 * Remove all plugin data when the plugin is deleted.
 *
 * @package PostMag_AI_Chat
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Removing the plugin's own data.
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'pmaic_chunks' ) );
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'pmaic_log' ) );

foreach ( array( 'pmaic_settings', 'pmaic_db_version', 'pmaic_indexed_at', 'pmaic_index_stale', 'pmaic_last_error', '_transient_pmaic_catalog', '_transient_timeout_pmaic_catalog' ) as $pmaic_option ) {
	delete_option( $pmaic_option );
}

// Daily counters and rate-limit transients.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( 'pmaic_usage_' ) . '%',
		$wpdb->esc_like( '_transient_pmaic_ip_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_pmaic_ip_' ) . '%'
	)
);

delete_post_meta_by_key( '_pmaic_exclude' );
// phpcs:enable
