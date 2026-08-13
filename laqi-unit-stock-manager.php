<?php
/**
 * Plugin Name:       Laqi Unit Stock Manager for WooCommerce
 * Plugin URI:        https://laqi-logistics.com/plugins/laqi-unit-stock-manager/
 * Description:        Laqi Unit Stock Manager for WooCommerce for WordPress / WooCommerce.
 * Version:           0.1.0
 * Author:            Laqi Logistics
 * Author URI:        https://laqi-logistics.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       laqi-unit-stock-manager
 * Domain Path:       /languages
 * Requires PHP:      7.4
 * Requires at least: 6.8
 * Requires Plugins:  woocommerce
 * WC requires at least: 7.1
 * WC tested up to:   10.9
 * Update URI:        false
 *
 * @package LaqiUnitStockManager
 */

defined( 'ABSPATH' ) || exit;

define( 'LAQI_LUSM_VERSION', '0.1.0' );
define( 'LAQI_LUSM_FILE', __FILE__ );
define( 'LAQI_LUSM_PATH', plugin_dir_path( __FILE__ ) );
define( 'LAQI_LUSM_URL', plugin_dir_url( __FILE__ ) );

// Composer PSR-4 autoloader if present; otherwise register a minimal PSR-4
// autoloader for src/ so the plugin still works when dropped in without Composer.
if ( is_readable( LAQI_LUSM_PATH . 'vendor/autoload.php' ) ) {
	require LAQI_LUSM_PATH . 'vendor/autoload.php';
} else {
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
}

// Lifecycle hooks must be registered in the main file (not on a later hook).
register_activation_hook( __FILE__, array( '\LaqiUnitStockManager\Plugin', 'on_activate' ) );
register_deactivation_hook( __FILE__, array( '\LaqiUnitStockManager\Plugin', 'on_deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		\LaqiUnitStockManager\Plugin::instance()->boot();
	}
);
