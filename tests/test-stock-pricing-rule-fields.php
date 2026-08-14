<?php
/** Read-only Stock & Pricing Automation field tests. @package LaqiUnitStockManager */

use LaqiUnitStockManager\Container;
use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Premium\Forecasting\ForecastPolicyRepository;
use LaqiUnitStockManager\Premium\Forecasting\StockForecastService;
use LaqiUnitStockManager\Premium\Integrations\StockPricingRuleFieldProvider;
use LaqiUnitStockManager\Storage\MovementRepository;
use LaqiUnitStockManager\Storage\Schema;

/** Verifies rule engines receive current values without a mutation path. */
class Test_Stock_Pricing_Rule_Fields extends WP_UnitTestCase {
	/** @var Container */ private $container;
	/** @var StockPricingRuleFieldProvider */ private $provider;
	/** @var WC_Product_Simple */ private $product;
	/** @var int[] */ private $pool_ids = array();

	/** Create one mapped product backed by a forecastable pool. */
	public function set_up(): void {
		parent::set_up();
		global $wpdb;
		$this->container = new Container();
		$this->product = new WC_Product_Simple();
		$this->product->set_name( 'Rule field product' );
		$this->product->set_regular_price( '10' );
		$this->product->save();
		$stale_mapping_ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . Schema::table( 'mappings' ) . ' WHERE product_id = %d', $this->product->get_id() ) );
		foreach ( $stale_mapping_ids as $mapping_id ) {
			$wpdb->delete( Schema::table( 'mapping_components' ), array( 'mapping_id' => $mapping_id ), array( '%d' ) );
		}
		$wpdb->delete( Schema::table( 'mappings' ), array( 'product_id' => $this->product->get_id() ), array( '%d' ) );

		$first          = $this->container->pool_repository()->create( 'Rule contents ' . wp_generate_uuid4(), new Quantity( 'count', 100 ), 'unit', 'unit', false, 'RULE-A-' . wp_generate_uuid4() );
		$this->pool_ids = array( $first->id() );
		$this->container->mapping_repository()->create_single_pool( $this->product->get_id(), 0, $first->id(), 4 );
		$this->seed_forecast_history( $first->id() );
		$policies = new ForecastPolicyRepository( $wpdb );
		$forecasts = new StockForecastService( new MovementRepository( $wpdb ) );
		$this->provider = new StockPricingRuleFieldProvider( $this->container->mapping_repository(), $this->container->pool_repository(), $this->container->calculator_registry(), $this->container->availability_service(), $policies, $forecasts );
	}

	/** Remove rule-field fixtures. */
	public function tear_down(): void {
		global $wpdb;
		$mapping = $this->container->mapping_repository()->find_for_product( $this->product->get_id() );
		if ( null !== $mapping ) {
			$wpdb->delete( Schema::table( 'mapping_components' ), array( 'mapping_id' => $mapping->id() ), array( '%d' ) );
			$wpdb->delete( Schema::table( 'mappings' ), array( 'id' => $mapping->id() ), array( '%d' ) );
		}
		foreach ( $this->pool_ids as $pool_id ) {
			$wpdb->delete( Schema::table( 'movements' ), array( 'pool_id' => $pool_id ), array( '%d' ) );
			$wpdb->delete( Schema::table( 'pools' ), array( 'id' => $pool_id ), array( '%d' ) );
		}
		$this->product->delete( true );
		parent::tear_down();
	}

	/** Catalog entries are explicitly read-only and preserve other providers. */
	public function test_catalog_declares_read_only_fields(): void {
		$catalog = $this->provider->catalog( array( 'consumer_field' => array( 'type' => 'string' ) ) );
		$this->assertArrayHasKey( 'consumer_field', $catalog );
		$this->assertTrue( $catalog['laqi_lusm_saleable_quantity']['read_only'] );
		$this->assertSame( 'collection', $catalog['laqi_lusm_pools']['type'] );
		$this->assertArrayNotHasKey( 'setter', $catalog['laqi_lusm_pools'] );
	}

	/** Mapped products expose limiting and per-pool values. */
	public function test_values_expose_limiting_quantity_and_pool_collection(): void {
		global $wpdb;
		$before = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Schema::table( 'movements' ) );
		$values = $this->provider->values( array( 'consumer_value' => 7 ), $this->product->get_id() );
		$this->assertSame( 7, $values['consumer_value'] );
		$this->assertTrue( $values['laqi_lusm_is_mapped'] );
		$this->assertSame( 25, $values['laqi_lusm_saleable_quantity'] );
		$this->assertCount( 1, $values['laqi_lusm_pools'] );
		$this->assertSame( array( 4 ), wp_list_pluck( $values['laqi_lusm_pools'], 'consumption_base' ) );
		$this->assertEqualsWithDelta( 150.0, $values['laqi_lusm_minimum_days_cover'], 0.01 );
		$this->assertSame( $before, (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Schema::table( 'movements' ) ) );
	}

	/** Unmapped products return explicit empty state and never create stock data. */
	public function test_unmapped_product_returns_empty_read_only_values(): void {
		$values = $this->provider->values( array(), $this->product->get_id() + 1000000 );
		$this->assertFalse( $values['laqi_lusm_is_mapped'] );
		$this->assertNull( $values['laqi_lusm_saleable_quantity'] );
		$this->assertNull( $values['laqi_lusm_minimum_days_cover'] );
		$this->assertSame( array(), $values['laqi_lusm_pools'] );
	}

	/** Seed 30 observed days and four demand days. @param int $pool_id Pool ID. */
	private function seed_forecast_history( int $pool_id ): void {
		global $wpdb;
		$now = time();
		$wpdb->insert( Schema::table( 'movements' ), array( 'pool_id' => $pool_id, 'type' => 'opening', 'delta_base' => 0, 'balance_base' => 0, 'idempotency_key' => 'rule-history:' . $pool_id, 'created_at' => gmdate( 'Y-m-d H:i:s', $now - ( 29 * DAY_IN_SECONDS ) ) ) );
		foreach ( array( 21, 14, 7, 1 ) as $day ) {
			$wpdb->insert( Schema::table( 'movements' ), array( 'pool_id' => $pool_id, 'type' => 'order_reduction', 'delta_base' => -5, 'balance_base' => 0, 'idempotency_key' => 'rule-demand:' . $pool_id . ':' . $day, 'created_at' => gmdate( 'Y-m-d H:i:s', $now - ( $day * DAY_IN_SECONDS ) ) ) );
		}
	}
}
