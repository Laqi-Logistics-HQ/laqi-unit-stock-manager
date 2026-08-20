<?php
/**
 * Plugin Name:       Laqi Unit Stock Manager for WooCommerce
 * Plugin URI:        https://laqi-logistics.com/plugins/laqi-unit-stock-manager/
 * Description:       Manage one bulk stock quantity shared by simple products and variations sold in different package sizes.
 * Version:           1.0.1
 * Author:            Laqi Logistics
 * Author URI:        https://laqi-logistics.com
 * Developer:         Laqi Logistics
 * Developer URI:     https://laqi-logistics.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       laqi-unit-stock-manager
 * Domain Path:       /languages
 * Requires PHP:      7.4
 * Requires at least: 6.8
 * Requires Plugins:  woocommerce
 * WC requires at least: 7.1
 * WC tested up to:   11.0
 *
 * @package LaqiUnitStockManager
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'LAQI_LUSM_VERSION' ) ) {
	if ( ! function_exists( 'laqi_lusm_render_duplicate_edition_notice' ) ) {
		/**
		 * Tell administrators that only the first-loaded edition is running.
		 *
		 * @return void
		 */
		function laqi_lusm_render_duplicate_edition_notice(): void {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				esc_html__( 'Two editions of Laqi Unit Stock Manager are active. Only one is running. Deactivate the edition you are not using, then reload this page. Both editions share the same inventory pools and settings, so removing the one you no longer need keeps them.', 'laqi-unit-stock-manager' )
			);
		}
	}
	add_action( 'admin_notices', 'laqi_lusm_render_duplicate_edition_notice' );
	return;
}

define( 'LAQI_LUSM_VERSION', '1.0.1' );
define( 'LAQI_LUSM_API_VERSION', '1.1' );
define( 'LAQI_LUSM_FILE', __FILE__ );
define( 'LAQI_LUSM_PATH', plugin_dir_path( __FILE__ ) );
define( 'LAQI_LUSM_URL', plugin_dir_url( __FILE__ ) );

// Load bundled dependencies when present. Always register the local PSR-4
// loader as well: an SDK's generated Composer metadata must never be able to
// prevent the plugin's own classes from loading after an edition upgrade.
if ( is_readable( LAQI_LUSM_PATH . 'vendor/autoload.php' ) ) {
	require LAQI_LUSM_PATH . 'vendor/autoload.php';
}
spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'LaqiUnitStockManager\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$file = LAQI_LUSM_PATH . 'src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

// Lifecycle hooks must be registered in the main file (not on a later hook).
register_activation_hook( __FILE__, array( '\LaqiUnitStockManager\Plugin', 'on_activate' ) );
register_deactivation_hook( __FILE__, array( '\LaqiUnitStockManager\Plugin', 'on_deactivate' ) );

$laqi_lusm_plugin = \LaqiUnitStockManager\Plugin::instance();
$laqi_lusm_plugin->register_early_hooks();
add_action( 'init', array( $laqi_lusm_plugin, 'boot' ) );
