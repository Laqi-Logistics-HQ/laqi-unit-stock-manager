<?php
/** Stock anomaly detection tests. @package LaqiUnitStockManager */

use LaqiUnitStockManager\Container;
use LaqiUnitStockManager\Diagnostics\MappingDiagnostics;
use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Premium\Anomalies\StockAnomalyDetector;
use LaqiUnitStockManager\Storage\Schema;

/** Verifies suspicious states are reported without changing inventory. */
class Test_Stock_Anomaly_Detection extends WP_UnitTestCase {
	/** @var Container */ private $container;
	/** @var StockAnomalyDetector */ private $detector;
	/** @var int */ private $pool_id;
	/** @var WC_Product_Simple */ private $product;

	/** Create isolated detector fixtures. */
	public function set_up(): void {
		parent::set_up();
		$this->container = new Container();
		$pool            = $this->container->pool_repository()->create( 'Anomaly pool ' . wp_generate_uuid4(), new Quantity( 'count', 100 ), 'unit', 'unit', true, 'ANOM-' . wp_generate_uuid4() );
		$this->pool_id   = $pool->id();
		$this->detector  = new StockAnomalyDetector( $this->container->movement_repository(), $this->container->mapping_repository(), new MappingDiagnostics() );
	}

	/** Remove all fixtures. */
	public function tear_down(): void {
		global $wpdb;
		if ( isset( $this->product ) ) {
			$mapping = $this->container->mapping_repository()->find_for_product( $this->product->get_id() );
			if ( null !== $mapping ) {
				$wpdb->delete( Schema::table( 'mapping_components' ), array( 'mapping_id' => $mapping->id() ), array( '%d' ) );
				$wpdb->delete( Schema::table( 'mappings' ), array( 'id' => $mapping->id() ), array( '%d' ) );
			}
			$this->product->delete( true );
		}
		$wpdb->delete( Schema::table( 'movements' ), array( 'pool_id' => $this->pool_id ), array( '%d' ) );
		$wpdb->delete( Schema::table( 'pools' ), array( 'id' => $this->pool_id ), array( '%d' ) );
		parent::tear_down();
	}

	/** Large changes, negative runs, malformed demand, and excess restores are surfaced. */
	public function test_detects_ledger_anomalies(): void {
		$this->insert_movement( 'manual_subtract', -80, 20, 'manual', 0 );
		$this->insert_movement( 'order_reduction', -10, -1, '', 0 );
		$this->insert_movement( 'order_reduction', -10, -2, 'order', 99 );
		$this->insert_movement( 'order_restore', 15, -3, 'order', 99 );
		$this->insert_movement( 'manual_subtract', -1, -4, 'manual', 0 );

		global $wpdb;
		$before = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT quantity_base FROM ' . Schema::table( 'pools' ) . ' WHERE id = %d', $this->pool_id ) );
		$keys   = wp_list_pluck( $this->detector->detect(), 'key' );

		$this->assertContains( 'large_adjustment', $keys );
		$this->assertContains( 'unexpected_consumption', $keys );
		$this->assertContains( 'repeated_negative_balance', $keys );
		$this->assertContains( 'excess_restoration', $keys );
		$this->assertSame( $before, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT quantity_base FROM ' . Schema::table( 'pools' ) . ' WHERE id = %d', $this->pool_id ) ) );
	}

	/** Existing mapping diagnostics are included in the operational review. */
	public function test_detects_invalid_mapping(): void {
		$this->product = new WC_Product_Simple();
		$this->product->set_name( 'Conflicting anomaly product' );
		$this->product->set_regular_price( '5' );
		$this->product->set_manage_stock( true );
		$this->product->set_stock_quantity( 4 );
		$this->product->save();
		global $wpdb;
		$stale_mapping_ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . Schema::table( 'mappings' ) . ' WHERE product_id = %d', $this->product->get_id() ) );
		foreach ( $stale_mapping_ids as $mapping_id ) {
			$wpdb->delete( Schema::table( 'mapping_components' ), array( 'mapping_id' => $mapping_id ), array( '%d' ) );
		}
		$wpdb->delete( Schema::table( 'mappings' ), array( 'product_id' => $this->product->get_id() ), array( '%d' ) );
		$this->container->mapping_repository()->create_single_pool( $this->product->get_id(), 0, $this->pool_id, 1 );

		$this->assertContains( 'invalid_mapping', wp_list_pluck( $this->detector->detect(), 'key' ) );
	}

	/** Insert one immutable ledger row. */
	private function insert_movement( string $type, int $delta, int $balance, string $source_type, int $source_id ): void {
		global $wpdb;
		$wpdb->insert(
			Schema::table( 'movements' ),
			array(
				'pool_id'        => $this->pool_id,
				'type'           => $type,
				'delta_base'     => $delta,
				'balance_base'   => $balance,
				'source_type'    => $source_type,
				'source_id'      => $source_id,
				'idempotency_key' => wp_generate_uuid4(),
				'created_at'     => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%d', '%d', '%s', '%d', '%s', '%s' )
		);
	}
}
