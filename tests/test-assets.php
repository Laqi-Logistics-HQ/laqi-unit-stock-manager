<?php
/**
 * Runtime asset scoping tests.
 *
 * @package LaqiUnitStockManager
 */

use LaqiUnitStockManager\Assets;

/**
 * Ensures the plugin adds no payload outside its one admin screen.
 */
class Test_Assets extends WP_UnitTestCase {

	/** Remove test handles after every assertion. */
	public function tear_down(): void {
		wp_dequeue_style( 'laqi-unit-stock-manager-admin' );
		wp_deregister_style( 'laqi-unit-stock-manager-admin' );
		wp_dequeue_script( 'laqi-unit-stock-manager-admin' );
		wp_deregister_script( 'laqi-unit-stock-manager-admin' );
		parent::tear_down();
	}

	/** Unrelated admin screens receive no plugin assets. */
	public function test_admin_assets_are_not_loaded_globally(): void {
		( new Assets() )->enqueue_admin( 'woocommerce_page_wc-settings' );

		$this->assertFalse( wp_style_is( 'laqi-unit-stock-manager-admin', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'laqi-unit-stock-manager-admin', 'enqueued' ) );
	}

	/** The single Unit Stock screen receives its required assets. */
	public function test_admin_assets_load_on_unit_stock_screen(): void {
		( new Assets() )->enqueue_admin( 'woocommerce_page_laqi-unit-stock-manager' );

		$this->assertTrue( wp_style_is( 'laqi-unit-stock-manager-admin', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'laqi-unit-stock-manager-admin', 'registered' ) );
	}

	/** The plugin registers no storefront enqueue callback or asset handles. */
	public function test_no_frontend_assets_are_registered(): void {
		$this->assertFalse( method_exists( new Assets(), 'enqueue_frontend' ) );
		$this->assertFalse( wp_style_is( 'laqi-unit-stock-manager-frontend', 'registered' ) );
		$this->assertFalse( wp_script_is( 'laqi-unit-stock-manager-frontend', 'registered' ) );
	}
}
