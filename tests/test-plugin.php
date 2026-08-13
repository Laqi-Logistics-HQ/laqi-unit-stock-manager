<?php
/**
 * Basic smoke test.
 *
 * @package LaqiUnitStockManager
 */

/**
 * Ensures the plugin boots.
 */
class Test_Plugin extends WP_UnitTestCase {

	/**
	 * The main class should be available once the plugin is loaded.
	 */
	public function test_plugin_class_exists(): void {
		$this->assertTrue( class_exists( '\LaqiUnitStockManager\Plugin' ) );
	}

	/**
	 * The version constant should be defined.
	 */
	public function test_version_constant_defined(): void {
		$this->assertTrue( defined( 'LAQI_LUSM_VERSION' ) );
	}

	/**
	 * WooCommerce is loaded by the test bootstrap, so the dependency guard should
	 * report it active and the plugin should register its feature-compatibility
	 * declaration on the proper hook (calling it directly is flagged by WC as
	 * incorrect usage, so we assert the wiring, not a direct call).
	 */
	public function test_woocommerce_dependency_is_satisfied(): void {
		$plugin = \LaqiUnitStockManager\Plugin::instance();
		$this->assertTrue( $plugin->is_woocommerce_active() );
		$this->assertNotFalse( has_action( 'before_woocommerce_init' ) );
	}
}
