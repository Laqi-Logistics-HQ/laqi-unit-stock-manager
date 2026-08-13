<?php
/**
 * Atomic stock mutation integration tests.
 *
 * @package LaqiUnitStockManager
 */

use LaqiUnitStockManager\Inventory\InsufficientStockException;
use LaqiUnitStockManager\Inventory\StockMutationService;
use LaqiUnitStockManager\Storage\Schema;

/**
 * Tests the single authoritative stock mutation path.
 */
class Test_Stock_Mutation_Service extends WP_UnitTestCase {

	/** @var int */
	private $pool_id;

	/**
	 * Install tables once.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		Schema::install();
	}

	/**
	 * Create a fresh 10 kg pool before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		global $wpdb;

		$now = current_time( 'mysql', true );
		$wpdb->insert(
			Schema::table( 'pools' ),
			array(
				'name'          => 'Ingredient A',
				'family'        => 'mass',
				'base_unit'     => 'ng',
				'display_unit'  => 'kg',
				'quantity_base' => 10000000000000,
				'created_at'    => $now,
				'updated_at'    => $now,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
		$this->pool_id = (int) $wpdb->insert_id;
	}

	/**
	 * Remove custom-table rows after each test.
	 */
	public function tear_down(): void {
		global $wpdb;

		$wpdb->delete( Schema::table( 'movements' ), array( 'pool_id' => $this->pool_id ), array( '%d' ) );
		$wpdb->delete( Schema::table( 'pools' ), array( 'id' => $this->pool_id ), array( '%d' ) );
		parent::tear_down();
	}

	/**
	 * A sale decrements the pool and records the resulting balance.
	 */
	public function test_decrement_updates_pool_and_records_movement(): void {
		global $wpdb;

		$result = ( new StockMutationService( $wpdb ) )->apply(
			$this->pool_id,
			-250000000,
			'order_reduction',
			'order-item:10:reduce:' . $this->pool_id
		);

		$this->assertSame( 9999750000000, $result->balance() );
		$this->assertFalse( $result->is_duplicate() );
		$this->assertSame(
			'9999750000000',
			$wpdb->get_var( $wpdb->prepare( 'SELECT quantity_base FROM ' . Schema::table( 'pools' ) . ' WHERE id = %d', $this->pool_id ) )
		);
	}

	/**
	 * Repeating the same event returns its first result without decrementing.
	 */
	public function test_idempotency_key_prevents_duplicate_decrement(): void {
		global $wpdb;

		$service = new StockMutationService( $wpdb );
		$key     = 'order-item:11:reduce:' . $this->pool_id;
		$first   = $service->apply( $this->pool_id, -1000000000, 'order_reduction', $key );
		$second  = $service->apply( $this->pool_id, -1000000000, 'order_reduction', $key );

		$this->assertSame( $first->movement_id(), $second->movement_id() );
		$this->assertTrue( $second->is_duplicate() );
		$this->assertSame( 9999000000000, $second->balance() );
	}

	/**
	 * A rejected decrement leaves both balance and ledger unchanged.
	 */
	public function test_insufficient_stock_rolls_back(): void {
		global $wpdb;

		try {
			( new StockMutationService( $wpdb ) )->apply(
				$this->pool_id,
				-10000000000001,
				'order_reduction',
				'order-item:12:reduce:' . $this->pool_id
			);
			$this->fail( 'Expected insufficient stock exception.' );
		} catch ( InsufficientStockException $error ) {
			$this->assertSame( 10000000000000, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT quantity_base FROM ' . Schema::table( 'pools' ) . ' WHERE id = %d', $this->pool_id ) ) );
			$this->assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Schema::table( 'movements' ) . ' WHERE pool_id = %d', $this->pool_id ) ) );
		}
	}
}
