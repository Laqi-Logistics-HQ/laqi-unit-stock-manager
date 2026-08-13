<?php
/** Reorder suggestion integration tests. @package LaqiUnitStockManager */

use LaqiUnitStockManager\Container;
use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Premium\Forecasting\ForecastPolicyRepository;
use LaqiUnitStockManager\Premium\Forecasting\StockForecastService;
use LaqiUnitStockManager\Premium\Receiving\SupplierRepository;
use LaqiUnitStockManager\Premium\Replenishment\ReorderPolicyRepository;
use LaqiUnitStockManager\Premium\Replenishment\ReorderSuggestionService;
use LaqiUnitStockManager\Storage\Schema;

/** Verifies explainable pack rounding and incoming-stock deductions. */
class Test_Reorder_Suggestions extends WP_UnitTestCase {
	/** @var int */ private $pool_id;
	/** @var int */ private $supplier_id;
	/** @var int */ private $pack_id;
	/** @var SupplierRepository */ private $suppliers;
	/** @var ReorderPolicyRepository */ private $policies;

	/** Install tables. */
	public static function set_up_before_class(): void { parent::set_up_before_class(); Schema::install(); global $wpdb; ( new SupplierRepository( $wpdb ) )->install(); }

	/** Create a configured 1 kg pool, 5 kg pack, and ten observed days. */
	public function set_up(): void {
		parent::set_up(); global $wpdb;
		$container = new Container(); $this->suppliers = new SupplierRepository( $wpdb ); $this->policies = new ReorderPolicyRepository( $wpdb );
		$this->pool_id = $container->pool_repository()->create( 'Reorder flour ' . wp_generate_uuid4(), new Quantity( 'mass', 1000000000000 ), 'ng', 'kg' )->id();
		$this->supplier_id = $this->suppliers->create_supplier( 'Fast mill ' . $this->pool_id, '', 7 );
		$this->pack_id = $this->suppliers->create_pack( $this->supplier_id, $this->pool_id, '5 kg sack', 5000000000000 );
		$this->policies->save( $this->pool_id, $this->pack_id, 2000000000000 );
		foreach ( array( 9, 4, 0 ) as $days_ago ) { $wpdb->insert( Schema::table( 'movements' ), array( 'pool_id' => $this->pool_id, 'type' => 'order_reduction', 'delta_base' => -2000000000000, 'balance_base' => 1000000000000, 'idempotency_key' => 'reorder-demand:' . $this->pool_id . ':' . $days_ago, 'created_at' => gmdate( 'Y-m-d H:i:s', time() - ( $days_ago * DAY_IN_SECONDS ) ) ), array( '%d', '%s', '%d', '%d', '%s', '%s' ) ); }
	}

	/** Remove test data. */
	public function tear_down(): void {
		global $wpdb;
		$wpdb->delete( Schema::table( 'incoming_deliveries' ), array( 'pool_id' => $this->pool_id ), array( '%d' ) ); $wpdb->delete( Schema::table( 'supplier_packs' ), array( 'id' => $this->pack_id ), array( '%d' ) ); $wpdb->delete( Schema::table( 'suppliers' ), array( 'id' => $this->supplier_id ), array( '%d' ) ); $wpdb->delete( Schema::table( 'movements' ), array( 'pool_id' => $this->pool_id ), array( '%d' ) ); $wpdb->delete( Schema::table( 'pools' ), array( 'id' => $this->pool_id ), array( '%d' ) ); parent::tear_down();
	}

	/** Suggestion covers lead-time demand and safety stock in whole packs. */
	public function test_rounds_shortfall_up_to_supplier_packs(): void {
		$result = $this->service()->suggest( $this->pool_id );
		$this->assertSame( 'forecast', $result['forecast_state'] );
		$this->assertSame( 4200000000000, $result['lead_demand_base'] );
		$this->assertSame( 6200000000000, $result['target_base'] );
		$this->assertSame( 5200000000000, $result['shortfall_base'] );
		$this->assertSame( 2, $result['pack_count'] );
		$this->assertSame( 10000000000000, $result['suggested_base'] );
	}

	/** Pending incoming stock is deducted before whole-pack rounding. */
	public function test_incoming_stock_reduces_reorder_quantity(): void {
		$this->suppliers->create_incoming( $this->pack_id, 1, gmdate( 'Y-m-d', time() + DAY_IN_SECONDS ), 'PO-reorder' );
		$result = $this->service()->suggest( $this->pool_id );
		$this->assertSame( 5000000000000, $result['incoming_base'] );
		$this->assertSame( 200000000000, $result['shortfall_base'] );
		$this->assertSame( 1, $result['pack_count'] );
	}

	/** Calculating a suggestion writes no stock or movement state. */
	public function test_suggestion_is_read_only(): void {
		global $wpdb;
		$before = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Schema::table( 'movements' ) . ' WHERE pool_id = %d', $this->pool_id ) );
		$this->service()->suggest( $this->pool_id );
		$this->assertSame( '1000000000000', $wpdb->get_var( $wpdb->prepare( 'SELECT quantity_base FROM ' . Schema::table( 'pools' ) . ' WHERE id = %d', $this->pool_id ) ) );
		$this->assertSame( $before, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Schema::table( 'movements' ) . ' WHERE pool_id = %d', $this->pool_id ) ) );
	}

	/** Production service. */
	private function service(): ReorderSuggestionService { global $wpdb; $container = new Container(); return new ReorderSuggestionService( $container->pool_repository(), $this->policies, new ForecastPolicyRepository( $wpdb ), new StockForecastService( $container->movement_repository() ), $this->suppliers ); }
}
