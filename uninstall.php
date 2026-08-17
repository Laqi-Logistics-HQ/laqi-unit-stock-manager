<?php
/**
 * Uninstall handler — runs when the user deletes the plugin from wp-admin.
 *
 * WordPress loads this file directly with WP_UNINSTALL_PLUGIN defined; the
 * plugin itself is NOT bootstrapped, so its classes/constants/autoloader are
 * unavailable. Use plain WordPress functions and hard-code the slug/prefix.
 *
 * Only remove data the user expects to lose on delete (deactivation already
 * handled the reversible state). Prefix every option/table with the slug.
 *
 * @package LaqiUnitStockManager
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$laqi_lusm_self     = wp_normalize_path( __DIR__ );
$laqi_lusm_root     = wp_normalize_path( defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : dirname( $laqi_lusm_self ) );
$laqi_lusm_editions = glob( $laqi_lusm_root . '/*/laqi-unit-stock-manager.php' );

if ( is_array( $laqi_lusm_editions ) ) {
	foreach ( $laqi_lusm_editions as $laqi_lusm_edition ) {
		if ( wp_normalize_path( dirname( $laqi_lusm_edition ) ) !== $laqi_lusm_self ) {
			return;
		}
	}
}

global $wpdb;
foreach ( array( 'batch_events', 'batch_allocations', 'batches', 'stock_holds', 'reservations', 'receipt_costs', 'pool_costs', 'incoming_deliveries', 'receipts', 'supplier_packs', 'suppliers', 'alert_deliveries', 'movements', 'mapping_components', 'mappings', 'units', 'pools' ) as $laqi_lusm_suffix ) {
	$laqi_lusm_table = $wpdb->prefix . 'laqi_lusm_' . $laqi_lusm_suffix;
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $laqi_lusm_table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
}
delete_option( 'laqi_lusm_schema_version' );
delete_option( 'laqi_lusm_alert_schema_version' );
delete_option( 'laqi_lusm_receiving_schema_version' );
delete_option( 'laqi_lusm_cost_schema_version' );
delete_option( 'laqi_lusm_reservation_schema_version' );
delete_option( 'laqi_lusm_supply_schema_version' );
delete_option( 'laqi_lusm_batch_schema_version' );
delete_option( 'laqi_lusm_batch_allocation_schema_version' );
delete_option( 'laqi_lusm_batch_expiry_settings' );
delete_option( 'laqi_lusm_stock_report_settings' );
delete_option( 'laqi_lusm_stock_report_history' );
delete_site_option( 'laqi_lusm_schema_version' );
wp_clear_scheduled_hook( 'laqi_lusm_evaluate_stock_alerts' );
wp_clear_scheduled_hook( 'laqi_lusm_send_stock_report' );
wp_clear_scheduled_hook( 'laqi_lusm_expire_reservations' );
wp_clear_scheduled_hook( 'laqi_lusm_evaluate_batch_expiry' );
