<?php
/**
 * Pooled order stock lifecycle integration tests.
 *
 * @package LaqiUnitStockManager
 */

use LaqiUnitStockManager\Inventory\StockMutationService;
use LaqiUnitStockManager\Consumption\CalculatorRegistry;
use LaqiUnitStockManager\Storage\MappingRepository;
use LaqiUnitStockManager\Storage\Schema;
use LaqiUnitStockManager\WooCommerce\OrderItemSnapshotter;
use LaqiUnitStockManager\WooCommerce\OrderStockLifecycle;

/**
 * Verifies reduction, restoration, and partial-refund stock movements.
 */
class Test_Order_Stock_Lifecycle extends WP_UnitTestCase {

	/** @var int */
	private $pool_id;

	/** @var WC_Order */
	private $order;

	/** @var OrderStockLifecycle */
	private $lifecycle;

	/**
	 * Install the plugin tables.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		Schema::install();
	}

	/**
	 * Create a pool and an order containing a two-item demand snapshot.
	 */
	public function set_up(): void {
		parent::set_up();
		global $wpdb;

		$now = current_time( 'mysql', true );
		$wpdb->insert(
			Schema::table( 'pools' ),
			array(
				'name'          => 'Lifecycle pool',
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

		$this->order = wc_create_order();
		foreach ( array_keys( $this->order->get_items() ) as $existing_item_id ) {
			$this->order->remove_item( $existing_item_id );
		}
		$item        = new WC_Order_Item_Product();
		$item->set_quantity( 2 );
		$item->add_meta_data(
			OrderItemSnapshotter::META_KEY,
			array(
				'version'         => 1,
				'mapping_id'      => 10,
				'mapping_version' => 1,
				'item_quantity'   => 2,
				'pool_demand'     => array( $this->pool_id => 500 ),
			),
			true
		);
		$this->order->add_item( $item );
		$this->order->save();

		$this->lifecycle = new OrderStockLifecycle(
			new StockMutationService( $wpdb ),
			new OrderItemSnapshotter( new MappingRepository( $wpdb ), new CalculatorRegistry() )
		);
	}

	/**
	 * Remove order and pool records after each test.
	 */
	public function tear_down(): void {
		global $wpdb;

		$this->order->delete( true );
		$wpdb->delete( Schema::table( 'movements' ), array( 'pool_id' => $this->pool_id ), array( '%d' ) );
		$wpdb->delete( Schema::table( 'pools' ), array( 'id' => $this->pool_id ), array( '%d' ) );
		parent::tear_down();
	}

	/**
	 * Reduction is idempotent and a restored order can begin a new stock cycle.
	 */
	public function test_reduce_restore_and_repeat_cycle(): void {
		$this->assertCount( 1, $this->order->get_items() );
		$this->assertSame( 0, $this->movement_count() );
		$this->lifecycle->reduce( $this->order );
		$this->assertSame( 500, $this->balance() );

		$this->lifecycle->reduce( $this->order );
		$this->assertSame( 500, $this->balance() );

		$this->lifecycle->restore( $this->order );
		$this->assertSame( 1000, $this->balance() );

		$this->lifecycle->reduce( $this->order );
		$this->assertSame( 500, $this->balance() );
		$this->assertSame( 2, (int) $this->order->get_meta( '_laqi_lusm_pool_stock_cycle', true ) );
	}

	/**
	 * A partial refund restores only its units and full restore adds the remainder.
	 */
	public function test_partial_refund_then_full_restore(): void {
		$this->lifecycle->reduce( $this->order );
		$item_id = array_key_first( $this->order->get_items() );

		$this->assertTrue(
			$this->lifecycle->restock_refund(
				true,
				$this->order,
				array( $item_id => array( 'qty' => 1 ) )
			)
		);
		$this->assertSame( 750, $this->balance() );

		$this->lifecycle->restore( $this->order );
		$this->assertSame( 1000, $this->balance() );

		$this->lifecycle->restock_refund( true, $this->order, array( $item_id => array( 'qty' => 1 ) ) );
		$this->assertSame( 1000, $this->balance() );
	}

	/**
	 * Read the current pool balance.
	 *
	 * @return int
	 */
	private function balance(): int {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT quantity_base FROM ' . Schema::table( 'pools' ) . ' WHERE id = %d', $this->pool_id ) );
	}

	/**
	 * Count movements belonging to the current pool.
	 *
	 * @return int
	 */
	private function movement_count(): int {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Schema::table( 'movements' ) . ' WHERE pool_id = %d', $this->pool_id ) );
	}
}
