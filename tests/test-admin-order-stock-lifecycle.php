<?php
/**
 * Admin order pooled-stock lifecycle integration tests.
 *
 * @package LaqiUnitStockManager
 */

use LaqiUnitStockManager\Consumption\CalculatorRegistry;
use LaqiUnitStockManager\Inventory\StockMutationService;
use LaqiUnitStockManager\Storage\MappingRepository;
use LaqiUnitStockManager\Storage\Schema;
use LaqiUnitStockManager\WooCommerce\OrderItemSnapshotter;
use LaqiUnitStockManager\WooCommerce\OrderStockLifecycle;
use LaqiUnitStockManager\WooCommerce\ReducedOrderItemEditor;

/**
 * Verifies manual order snapshots and reduced-order quantity edits.
 */
class Test_Admin_Order_Stock_Lifecycle extends WP_UnitTestCase {

	/** @var int */
	private $pool_id;

	/** @var WC_Product_Simple */
	private $product;

	/** @var WC_Order */
	private $order;

	/** @var OrderStockLifecycle */
	private $lifecycle;

	/** @var OrderItemSnapshotter */
	private $snapshotter;

	/** Install plugin tables. */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		Schema::install();
	}

	/** Create a mapped product, pool, and manual order item. */
	public function set_up(): void {
		parent::set_up();
		global $wpdb;

		$now = current_time( 'mysql', true );
		$wpdb->insert(
			Schema::table( 'pools' ),
			array(
				'name'          => 'Admin order pool',
				'family'        => 'mass',
				'base_unit'     => 'ng',
				'display_unit'  => 'g',
				'quantity_base' => 1000,
				'created_at'    => $now,
				'updated_at'    => $now,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
		$this->pool_id = (int) $wpdb->insert_id;
		$wpdb->delete( Schema::table( 'movements' ), array( 'pool_id' => $this->pool_id ), array( '%d' ) );

		$this->product = new WC_Product_Simple();
		$this->product->set_name( 'Admin order product' );
		$this->product->set_regular_price( '10' );
		$this->product->save();

		$mappings = new MappingRepository( $wpdb );
		$stale_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . Schema::table( 'mappings' ) . ' WHERE product_id = %d', $this->product->get_id() ) );
		if ( $stale_id > 0 ) {
			$wpdb->delete( Schema::table( 'mapping_components' ), array( 'mapping_id' => $stale_id ), array( '%d' ) );
			$wpdb->delete( Schema::table( 'mappings' ), array( 'id' => $stale_id ), array( '%d' ) );
		}
		$mappings->create_single_pool( $this->product->get_id(), 0, $this->pool_id, 250 );

		$this->order = wc_create_order();
		$this->order->delete_meta_data( OrderStockLifecycle::STATE_META );
		$this->order->delete_meta_data( OrderStockLifecycle::CYCLE_META );
		foreach ( array_keys( $this->order->get_items() ) as $existing_item_id ) {
			$this->order->remove_item( $existing_item_id );
		}
		$this->order->save();
		$item        = new WC_Order_Item_Product();
		$item->set_product( $this->product );
		$item->set_quantity( 2 );
		$this->order->add_item( $item );
		$this->order->save();

		$snapshotter       = new OrderItemSnapshotter( $mappings, new CalculatorRegistry() );
		$this->snapshotter = $snapshotter;
		$item->delete_meta_data( OrderItemSnapshotter::META_KEY );
		$item->delete_meta_data( ReducedOrderItemEditor::SEQUENCE_META );
		$item->delete_meta_data( ReducedOrderItemEditor::ADDED_CYCLE_META );
		$item->delete_meta_data( OrderStockLifecycle::RESTOCKED_QUANTITY_META );
		$snapshotter->snapshot_admin_item( $item );
		$item->save();
		$this->lifecycle = new OrderStockLifecycle( new StockMutationService( $wpdb ), $snapshotter );
	}

	/** Remove test records. */
	public function tear_down(): void {
		global $wpdb;

		$mapping_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . Schema::table( 'mappings' ) . ' WHERE product_id = %d', $this->product->get_id() ) );
		$wpdb->delete( Schema::table( 'mapping_components' ), array( 'mapping_id' => $mapping_id ), array( '%d' ) );
		$wpdb->delete( Schema::table( 'mappings' ), array( 'id' => $mapping_id ), array( '%d' ) );
		$wpdb->delete( Schema::table( 'movements' ), array( 'pool_id' => $this->pool_id ), array( '%d' ) );
		$this->order->delete( true );
		$this->product->delete( true );
		$wpdb->delete( Schema::table( 'pools' ), array( 'id' => $this->pool_id ), array( '%d' ) );
		parent::tear_down();
	}

	/** A manual order receives a current snapshot before its first reduction. */
	public function test_manual_order_is_snapshotted_before_reduction(): void {
		$item = current( $this->order->get_items() );
		$item->delete_meta_data( OrderItemSnapshotter::META_KEY );
		$item->save();
		$item_id = $item->get_id();

		$this->lifecycle->reduce( $this->order );
		$item     = new WC_Order_Item_Product( $item_id );
		$snapshot = $item->get_meta( OrderItemSnapshotter::META_KEY, true );

		$this->assertSame( 500, $this->balance() );
		$this->assertSame( 'admin', $snapshot['origin'] );
		$this->assertSame( 2, $snapshot['item_quantity'] );
		$this->assertSame( 500, $snapshot['pool_demand'][ $this->pool_id ] );
	}

	/** Repeated quantity changes each produce one exact stock correction. */
	public function test_reduced_order_quantity_edits_are_reconciled(): void {
		global $wpdb;

		$this->lifecycle->reduce( $this->order );
		$editor = new ReducedOrderItemEditor( new StockMutationService( $wpdb ), $this->snapshotter );
		$item   = current( $this->order->get_items() );

		$item->set_quantity( 3 );
		$editor->adjust_saved_item( $item );
		$item->save();
		$this->assertSame( 250, $this->balance() );

		$item->set_quantity( 2 );
		$editor->adjust_saved_item( $item );
		$item->save();
		$this->assertSame( 500, $this->balance() );

		$item->set_quantity( 3 );
		$editor->adjust_saved_item( $item );
		$item->save();
		$this->assertSame( 250, $this->balance() );
		$this->assertSame( 3, (int) $item->get_meta( ReducedOrderItemEditor::SEQUENCE_META, true ) );
	}

	/** A line added after reduction immediately consumes its mapped demand. */
	public function test_line_added_to_reduced_order_consumes_stock(): void {
		global $wpdb;

		$this->lifecycle->reduce( $this->order );
		$item = new WC_Order_Item_Product();
		$item->set_product( $this->product );
		$item->set_quantity( 1 );
		$this->order->add_item( $item );
		$this->order->save();

		$editor = new ReducedOrderItemEditor( new StockMutationService( $wpdb ), $this->snapshotter );
		$editor->add_saved_item( $item->get_id(), $item, $this->order->get_id() );
		$this->assertSame( 250, $this->balance() );

		$editor->add_saved_item( $item->get_id(), $item, $this->order->get_id() );
		$this->assertSame( 250, $this->balance() );
		$this->assertSame( 250, $item->get_meta( OrderItemSnapshotter::META_KEY, true )['pool_demand'][ $this->pool_id ] );
	}

	/** A line that cannot reserve stock is removed instead of becoming untracked. */
	public function test_insufficient_added_line_is_rolled_back(): void {
		$this->lifecycle->reduce( $this->order );
		$item = new WC_Order_Item_Product();
		$item->set_product( $this->product );
		$item->set_quantity( 3 );
		$this->order->add_item( $item );
		$this->order->save();
		global $wpdb;
		$editor = new ReducedOrderItemEditor( new StockMutationService( $wpdb ), $this->snapshotter );

		try {
			$editor->add_saved_item( $item->get_id(), $item, $this->order->get_id() );
			$this->fail( 'Expected the added line to exceed pooled stock.' );
		} catch ( \LaqiUnitStockManager\Inventory\InsufficientStockException $error ) {
			$this->assertSame( 500, $this->balance() );
			$this->assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = %d", $item->get_id() ) ) );
		}
	}

	/** Deleting a partially restocked line restores only its outstanding demand. */
	public function test_deleted_line_restores_only_outstanding_stock(): void {
		global $wpdb;

		$this->lifecycle->reduce( $this->order );
		$item_id = array_key_first( $this->order->get_items() );
		$this->lifecycle->restock_refund( true, $this->order, array( $item_id => array( 'qty' => 1 ) ) );
		$this->assertSame( 750, $this->balance() );

		$editor = new ReducedOrderItemEditor( new StockMutationService( $wpdb ), $this->snapshotter );
		$editor->remove_saved_item( $item_id );
		$this->assertSame( 1000, $this->balance() );

		$editor->remove_saved_item( $item_id );
		$this->assertSame( 1000, $this->balance() );
	}

	/** Read current pool balance. */
	private function balance(): int {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT quantity_base FROM ' . Schema::table( 'pools' ) . ' WHERE id = %d', $this->pool_id ) );
	}
}
