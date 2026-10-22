<?php
/**
 * Uninstall the plugin.
 *
 * Nothing is removed unless the seller switched on "Remove data on uninstall":
 * deleting a store's sales history because a plugin was removed is not a default.
 *
 * @since   1.0.0
 * @package EverCheckout
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( 'yes' !== get_option( 'ecout_delete_data_on_uninstall' ) ) {
	return;
}

global $wpdb;

foreach ( array( 'download_logs', 'order_items', 'orders', 'customers', 'downloads', 'products' ) as $ecout_table ) {
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'ecout_' . $ecout_table ) );
}

$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name LIKE %s', $wpdb->options, $wpdb->esc_like( 'ecout_' ) . '%' ) );

$ecout_uploads = wp_upload_dir( null, false );

if ( ! empty( $ecout_uploads['basedir'] ) ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	WP_Filesystem();

	global $wp_filesystem;

	if ( $wp_filesystem ) {
		$wp_filesystem->rmdir( $ecout_uploads['basedir'] . '/ever-checkout', true );
	}
}

wp_clear_scheduled_hook( 'ecout_queue_cron' );
delete_transient( 'ecout_queue_process_lock' );
wp_cache_delete( 'alloptions', 'options' );
