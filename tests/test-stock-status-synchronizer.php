<?php
/**
 * Pooled WooCommerce stock-status tests.
 *
 * @package LaqiUnitStockManager
 */

use LaqiUnitStockManager\Availability\AvailabilityService;
use LaqiUnitStockManager\Consumption\CalculatorRegistry;
use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Storage\MappingRepository;
use LaqiUnitStockManager\Storage\PoolRepository;
use LaqiUnitStockManager\Storage\Schema;
use LaqiUnitStockManager\WooCommerce\StockStatusSynchronizer;

/**
 * Verifies catalog status follows pooled saleable quantity.
 */
class Test_Stock_Status_Synchronizer extends WP_UnitTestCase {

	/** @var int */
	private $pool_id;

	/** @var WC_Product_Simple */
	private $product;

	/** @var StockStatusSynchronizer */
	private $synchronizer;

	/** Install plugin tables. */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		Schema::install();
	}

	/** Create a mapped product with two saleable units. */
	public function set_up(): void {
		parent::set_up();
		global $wpdb;

		$this->product = new WC_Product_Simple();
		$this->product->set_name( 'Mapped cocoa' );
		$this->product->set_regular_price( '5' );
		$this->product->set_status( 'publish' );
		$this->product->save();
		$stale_mapping_ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . Schema::table( 'mappings' ) . ' WHERE product_id = %d', $this->product->get_id() ) );
		foreach ( $stale_mapping_ids as $stale_mapping_id ) {
			$wpdb->delete( Schema::table( 'mapping_components' ), array( 'mapping_id' => $stale_mapping_id ), array( '%d' ) );
		}
		$wpdb->delete( Schema::table( 'mappings' ), array( 'product_id' => $this->product->get_id() ), array( '%d' ) );

		$pools         = new PoolRepository( $wpdb );
		$mappings      = new MappingRepository( $wpdb );
		$pool          = $pools->create( 'Cocoa', new Quantity( 'mass', 500000000 ), 'ng', 'g' );
		$this->pool_id = $pool->id();
		$mappings->create_single_pool( $this->product->get_id(), 0, $pool->id(), 250000000 );
		$this->synchronizer = new StockStatusSynchronizer( $mappings, new AvailabilityService( $mappings, $pools, new CalculatorRegistry() ) );
	}

	/** Remove custom records and product. */
	public function tear_down(): void {
		global $wpdb;
		$mapping = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . Schema::table( 'mappings' ) . ' WHERE product_id = %d', $this->product->get_id() ) );
		$wpdb->delete( Schema::table( 'mapping_components' ), array( 'mapping_id' => $mapping ), array( '%d' ) );
		$wpdb->delete( Schema::table( 'mappings' ), array( 'id' => $mapping ), array( '%d' ) );
		$wpdb->delete( Schema::table( 'pools' ), array( 'id' => $this->pool_id ), array( '%d' ) );
		$this->product->delete( true );
		parent::tear_down();
	}

	/** A depleted pool marks its linked simple product out of stock. */
	public function test_depleted_pool_sets_out_of_stock(): void {
		global $wpdb;
		$wpdb->update( Schema::table( 'pools' ), array( 'quantity_base' => 0 ), array( 'id' => $this->pool_id ), array( '%d' ), array( '%d' ) );

		$this->synchronizer->sync_pools( array( $this->pool_id ) );

		$this->assertSame( 'outofstock', wc_get_product( $this->product->get_id() )->get_stock_status() );
	}

	/** A replenished pool returns its linked product to in stock. */
	public function test_positive_saleable_quantity_sets_in_stock(): void {
		$this->product->set_stock_status( 'outofstock' );
		$this->product->save();

		$this->synchronizer->sync_mapping( $this->product->get_id() );

		$this->assertSame( 'instock', wc_get_product( $this->product->get_id() )->get_stock_status() );
	}
}
