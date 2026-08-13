<?php
/**
 * WooCommerce adapter wiring tests.
 *
 * @package LaqiUnitStockManager
 */

use LaqiUnitStockManager\WooCommerce\OrderItemSnapshotter;

/**
 * Verifies classic, Store API, and checkout snapshot hooks are active.
 */
class Test_WooCommerce_Wiring extends WP_UnitTestCase {

	/**
	 * Pooled stock hooks are registered after plugin boot.
	 */
	public function test_cart_and_snapshot_hooks_are_registered(): void {
		$this->assertNotFalse( has_action( 'woocommerce_check_cart_items' ) );
		$this->assertNotFalse( has_action( 'woocommerce_store_api_cart_errors' ) );
		$this->assertNotFalse( has_action( 'woocommerce_checkout_create_order_line_item' ) );
		$this->assertNotFalse( has_action( 'woocommerce_reduce_order_stock' ) );
		$this->assertNotFalse( has_action( 'woocommerce_restore_order_stock' ) );
		$this->assertNotFalse( has_filter( 'woocommerce_can_restock_refunded_items' ) );
		$this->assertSame( '_laqi_lusm_stock_snapshot', OrderItemSnapshotter::META_KEY );
	}
}
