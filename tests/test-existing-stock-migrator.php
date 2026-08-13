<?php
/**
 * Existing WooCommerce stock migration tests.
 *
 * @package LaqiUnitStockManager
 */

use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Inventory\StockMutationService;
use LaqiUnitStockManager\Storage\PoolRepository;
use LaqiUnitStockManager\Storage\Schema;
use LaqiUnitStockManager\WooCommerce\ExistingStockMigrator;

/**
 * Verifies explicit native-stock decisions are safe and idempotent.
 */
class Test_Existing_Stock_Migrator extends WP_UnitTestCase {

	/** @var int */
	private $pool_id;

	/** @var WC_Product_Simple */
	private $product;

	/** @var ExistingStockMigrator */
	private $migrator;

	/** Install plugin tables. */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		Schema::install();
	}

	/** Create native stock and an empty destination pool. */
	public function set_up(): void {
		parent::set_up();
		global $wpdb;

		$this->product = new WC_Product_Simple();
		$this->product->set_name( 'Native stock product' );
		$this->product->set_manage_stock( true );
		$this->product->set_stock_quantity( 4 );
		$this->product->save();

		$pool          = ( new PoolRepository( $wpdb ) )->create( 'Migration pool', new Quantity( 'count', 0 ), 'unit', 'unit' );
		$this->pool_id = $pool->id();
		$this->migrator = new ExistingStockMigrator( new StockMutationService( $wpdb ) );
	}

	/** Remove plugin and product fixtures. */
	public function tear_down(): void {
		global $wpdb;
		$wpdb->delete( Schema::table( 'movements' ), array( 'pool_id' => $this->pool_id ), array( '%d' ) );
		$wpdb->delete( Schema::table( 'pools' ), array( 'id' => $this->pool_id ), array( '%d' ) );
		$this->product->delete( true );
		parent::tear_down();
	}

	/** Transfer multiplies count by consumption exactly and runs only once. */
	public function test_transfer_is_exact_and_idempotent(): void {
		$this->migrator->apply( $this->product, $this->pool_id, 25, ExistingStockMigrator::TRANSFER );
		$this->assertSame( 100, $this->balance() );
		$this->assertFalse( wc_get_product( $this->product->get_id() )->managing_stock() );

		$this->product->set_manage_stock( true );
		$this->product->set_stock_quantity( 4 );
		$this->product->save();
		$this->migrator->apply( $this->product, $this->pool_id, 25, ExistingStockMigrator::TRANSFER );
		$this->assertSame( 100, $this->balance() );
	}

	/** Keep preserves native quantity management and pool balance. */
	public function test_keep_leaves_both_stock_sources_unchanged(): void {
		$this->migrator->apply( $this->product, $this->pool_id, 25, ExistingStockMigrator::KEEP );

		$this->assertTrue( wc_get_product( $this->product->get_id() )->managing_stock() );
		$this->assertSame( 0, $this->balance() );
	}

	/** Read destination balance. @return int */
	private function balance(): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT quantity_base FROM ' . Schema::table( 'pools' ) . ' WHERE id = %d', $this->pool_id ) );
	}
}
