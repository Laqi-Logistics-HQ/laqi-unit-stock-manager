<?php
/**
 * PHPUnit bootstrap — loads the WordPress test suite, WooCommerce, and this
 * plugin. Run `make test-install` in wordpress_localhost once to install the
 * suite.
 *
 * @package LaqiUnitStockManager
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		// WooCommerce is a hard dependency and lives alongside this plugin in the
		// bind-mounted plugins dir; load it so order-backed integration tests run
		// (without this, any test needing wc_get_order() silently skips).
		$woocommerce = dirname( __DIR__, 2 ) . '/woocommerce/woocommerce.php';
		if ( file_exists( $woocommerce ) ) {
			require $woocommerce;
		}
		require dirname( __DIR__ ) . '/laqi-unit-stock-manager.php';
	}
);

require $_tests_dir . '/includes/bootstrap.php';

// Install WooCommerce's tables/roles in the test database once it's loaded.
if ( class_exists( 'WC_Install' ) ) {
	WC_Install::install();
	$GLOBALS['wp_roles'] = new WP_Roles(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
}
