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

// TODO: delete plugin data, e.g.:
// delete_option( 'laqi_lusm_settings' );
// delete_site_option( 'laqi_lusm_settings' ); // multisite/network.
//
// For custom tables, drop them via $wpdb here. Never delete merchant-owned
// WooCommerce orders/products. Remove plugin-owned personal data unless a
// documented legal or operational retention reason applies, and keep the
// exporter/eraser in src/Privacy.php consistent with that decision.
