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
		$this->assertNotFalse( has_action( 'admin_menu' ) );
		$this->assertNotFalse( has_action( 'admin_post_laqi_lusm_adjust_stock' ) );
		$this->assertNotFalse( has_action( 'admin_post_laqi_lusm_create_pool' ) );
		$this->assertNotFalse( has_action( 'admin_post_laqi_lusm_save_mapping' ) );
		$this->assertNotFalse( has_action( 'admin_post_laqi_lusm_unlink_mapping' ) );
		$this->assertNotFalse( has_action( 'admin_post_laqi_lusm_update_mapping' ) );
		$this->assertNotFalse( has_action( 'admin_post_laqi_lusm_retire_unit' ) );
		$this->assertNotFalse( has_action( 'wp_ajax_laqi_lusm_search_pools' ) );
		$this->assertNotFalse( has_action( 'admin_post_laqi_lusm_update_pool' ) );
		$this->assertNotFalse( has_action( 'admin_post_laqi_lusm_create_unit' ) );
		$this->assertNotFalse( has_action( 'admin_post_laqi_lusm_save_low_stock_alert' ) );
		$this->assertFalse( has_action( 'admin_post_laqi_lusm_save_forecast' ) );
		$this->assertFalse( has_action( 'admin_post_laqi_lusm_save_stock_report' ) );
		$this->assertFalse( has_action( 'admin_post_laqi_lusm_send_stock_report' ) );
		$this->assertNotFalse( has_action( 'admin_post_laqi_lusm_create_supplier' ) );
		$this->assertNotFalse( has_action( 'admin_post_laqi_lusm_create_supplier_pack' ) );
		$this->assertNotFalse( has_action( 'admin_post_laqi_lusm_receive_supplier_pack' ) );
		$this->assertNotFalse( has_action( 'admin_post_laqi_lusm_schedule_incoming_stock' ) );
		$this->assertNotFalse( has_action( 'admin_post_laqi_lusm_receive_incoming_stock' ) );
		$this->assertNotFalse( has_action( 'admin_post_laqi_lusm_save_reorder_policy' ) );
		$this->assertNotFalse( has_action( 'admin_post_laqi_lusm_export_operations' ) );
		$this->assertNotFalse( has_action( 'admin_post_laqi_lusm_import_operations' ) );
		$this->assertNotFalse( has_action( 'laqi_lusm_stock_mutated' ) );
		$this->assertNotFalse( has_action( 'laqi_lusm_stock_movement_applying' ) );
		$this->assertNotFalse( has_filter( 'laqi_lusm_pool_available_quantity' ) );
		$this->assertNotFalse( has_action( 'woocommerce_checkout_order_created' ) );
		$this->assertNotFalse( has_action( 'admin_post_laqi_lusm_place_stock_hold' ) );
		$this->assertNotFalse( has_action( 'admin_post_laqi_lusm_release_stock_hold' ) );
		$this->assertNotFalse( has_action( 'admin_post_laqi_lusm_write_off_stock_hold' ) );
		$this->assertNotFalse( has_action( 'admin_post_laqi_lusm_save_safety_stock' ) );
		$this->assertNotFalse( has_action( 'admin_post_laqi_lusm_batch_quarantine' ) );
		$this->assertNotFalse( has_action( 'admin_post_laqi_lusm_batch_release' ) );
		$this->assertNotFalse( has_action( 'admin_post_laqi_lusm_batch_write_off' ) );
		$this->assertNotFalse( has_action( 'admin_post_laqi_lusm_batch_stocktake' ) );
		$this->assertNotFalse( has_action( 'admin_post_laqi_lusm_batch_recall' ) );
		$this->assertNotFalse( has_action( 'admin_post_laqi_lusm_batch_transfer' ) );
		$this->assertNotFalse( has_action( 'admin_post_laqi_lusm_batch_expiry_write_off' ) );
		$this->assertNotFalse( has_action( 'admin_post_laqi_lusm_save_batch_expiry' ) );
		$this->assertNotFalse( has_action( \LaqiUnitStockManager\Premium\Batches\BatchExpiryEvaluator::CRON_HOOK ) );
		$this->assertNotFalse( has_action( \LaqiUnitStockManager\Premium\Alerts\LowStockAlertEvaluator::CRON_HOOK ) );
		$this->assertNotFalse( has_action( 'laqi_lusm_mapping_changed' ) );
		$this->assertNotFalse( has_action( 'rest_api_init' ) );
		$this->assertSame( '_laqi_lusm_stock_snapshot', OrderItemSnapshotter::META_KEY );
	}
}
