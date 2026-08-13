<?php
/** Stock scenario planner integration tests. @package LaqiUnitStockManager */

use LaqiUnitStockManager\Container;
use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Premium\Forecasting\ForecastPolicyRepository;
use LaqiUnitStockManager\Premium\Forecasting\StockForecastService;
use LaqiUnitStockManager\Premium\Planning\StockScenarioPlanner;
use LaqiUnitStockManager\Storage\Schema;

/** Verifies promotion scenarios remain exact and read-only. */
class Test_Stock_Scenario_Planner extends WP_UnitTestCase {
	/** @var int */ private $pool_id;
	/** @var int */ private $other_pool_id;
	/** @var array<int,int> */ private $mapping_ids = array();

	/** Install custom tables. */
	public static function set_up_before_class(): void { parent::set_up_before_class(); Schema::install(); }

	/** Create pools and differently sized package mappings. */
	public function set_up(): void {
		parent::set_up();
		$container = new Container();
		$pool = $container->pool_repository()->create( 'Scenario ingredient', new Quantity( 'mass', 10000000000000 ), 'ng', 'kg' );
		$other = $container->pool_repository()->create( 'Other ingredient', new Quantity( 'mass', 1000000000000 ), 'ng', 'kg' );
		$this->pool_id = $pool->id(); $this->other_pool_id = $other->id();
		$product_seed        = 900000 + ( $this->pool_id * 10 );
		$this->mapping_ids[] = $container->mapping_repository()->create_single_pool( $product_seed + 1, 0, $this->pool_id, 100000000000 )->id();
		$this->mapping_ids[] = $container->mapping_repository()->create_single_pool( $product_seed + 2, 0, $this->pool_id, 250000000000 )->id();
		$this->mapping_ids[] = $container->mapping_repository()->create_single_pool( $product_seed + 3, 0, $this->other_pool_id, 100000000000 )->id();
	}

	/** Remove custom table fixtures. */
	public function tear_down(): void {
		global $wpdb;
		foreach ( $this->mapping_ids as $mapping_id ) { $wpdb->delete( Schema::table( 'mapping_components' ), array( 'mapping_id' => $mapping_id ), array( '%d' ) ); $wpdb->delete( Schema::table( 'mappings' ), array( 'id' => $mapping_id ), array( '%d' ) ); }
		$wpdb->delete( Schema::table( 'pools' ), array( 'id' => $this->pool_id ), array( '%d' ) );
		$wpdb->delete( Schema::table( 'pools' ), array( 'id' => $this->other_pool_id ), array( '%d' ) );
		parent::tear_down();
	}

	/** Mixed package demand and promotional uplift use exact normalized units. */
	public function test_calculates_promotional_allocation_mix(): void {
		$result = $this->planner()->calculate( $this->pool_id, array( $this->mapping_ids[0] => 10, $this->mapping_ids[1] => 10 ), 20 );
		$this->assertSame( 4200000000000, $result['demand_base'] );
		$this->assertSame( 5800000000000, $result['remaining_base'] );
		$this->assertTrue( $result['enough_stock'] );
		$this->assertSame( 12, $result['lines'][0]['projected_units'] );
		$this->assertSame( 12, $result['lines'][1]['projected_units'] );
	}

	/** Planning never changes the balance or creates movements. */
	public function test_overstock_scenario_is_reported_without_mutation(): void {
		global $wpdb;
		$result = $this->planner()->calculate( $this->pool_id, array( $this->mapping_ids[1] => 50 ) );
		$this->assertFalse( $result['enough_stock'] );
		$this->assertSame( -2500000000000, $result['remaining_base'] );
		$this->assertSame( '10000000000000', $wpdb->get_var( $wpdb->prepare( 'SELECT quantity_base FROM ' . Schema::table( 'pools' ) . ' WHERE id = %d', $this->pool_id ) ) );
		$this->assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Schema::table( 'movements' ) . ' WHERE pool_id = %d', $this->pool_id ) ) );
	}

	/** A scenario cannot smuggle in a mapping from another pool. */
	public function test_rejects_mapping_from_another_pool(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->planner()->calculate( $this->pool_id, array( $this->mapping_ids[2] => 1 ) );
	}

	/** Build production planner dependencies. */
	private function planner(): StockScenarioPlanner {
		global $wpdb;
		$container = new Container();
		return new StockScenarioPlanner( $container->pool_repository(), $container->mapping_repository(), new ForecastPolicyRepository( $wpdb ), new StockForecastService( $container->movement_repository() ) );
	}
}
