<?php
/**
 * Combined pool availability integration tests.
 *
 * @package LaqiUnitStockManager
 */

use LaqiUnitStockManager\Availability\AvailabilityService;
use LaqiUnitStockManager\Consumption\CalculatorRegistry;
use LaqiUnitStockManager\Domain\MappingComponent;
use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Storage\MappingRepository;
use LaqiUnitStockManager\Storage\PoolRepository;
use LaqiUnitStockManager\Storage\Schema;

/**
 * Tests multiple products and variations drawing from one balance.
 */
class Test_Availability_Service extends WP_UnitTestCase {

	/** @var int */
	private $pool_id;

	/** @var AvailabilityService */
	private $service;

	/**
	 * Install tables once.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		Schema::install();
	}

	/**
	 * Create one 10 g pool with 0.25 g and 2 g variation mappings.
	 */
	public function set_up(): void {
		parent::set_up();
		global $wpdb;

		// Repository transactions commit the test framework's outer transaction
		// on MariaDB, so make this fixture self-cleaning before every test too.
		$old_mapping_ids = $wpdb->get_col( 'SELECT id FROM ' . Schema::table( 'mappings' ) . ' WHERE product_id = 100' );
		foreach ( $old_mapping_ids as $mapping_id ) {
			$wpdb->delete( Schema::table( 'mapping_components' ), array( 'mapping_id' => $mapping_id ), array( '%d' ) );
		}
		$wpdb->delete( Schema::table( 'mappings' ), array( 'product_id' => 100 ), array( '%d' ) );

		$pools = new PoolRepository( $wpdb );
		$maps  = new MappingRepository( $wpdb );
		$pool  = $pools->create( 'Ingredient A', new Quantity( 'mass', 10000000000 ), 'ng', 'g' );
		$maps->create_single_pool( 100, 101, $pool->id(), 250000000 );
		$maps->create_single_pool( 100, 102, $pool->id(), 2000000000 );

		$this->pool_id = $pool->id();
		$this->service = new AvailabilityService( $maps, $pools, new CalculatorRegistry() );
	}

	/**
	 * Remove custom rows after each test.
	 */
	public function tear_down(): void {
		global $wpdb;

		$mapping_ids = $wpdb->get_col( 'SELECT id FROM ' . Schema::table( 'mappings' ) . ' WHERE product_id = 100' );
		foreach ( $mapping_ids as $mapping_id ) {
			$wpdb->delete( Schema::table( 'mapping_components' ), array( 'mapping_id' => $mapping_id ), array( '%d' ) );
		}
		$wpdb->delete( Schema::table( 'mappings' ), array( 'product_id' => 100 ), array( '%d' ) );
		$wpdb->delete( Schema::table( 'pools' ), array( 'id' => $this->pool_id ), array( '%d' ) );
		parent::tear_down();
	}

	/**
	 * Individually valid lines are rejected when their combined demand is too high.
	 */
	public function test_mixed_lines_are_aggregated_by_pool(): void {
		$result = $this->service->check(
			array(
				array( 'product_id' => 100, 'variation_id' => 101, 'quantity' => 9 ),
				array( 'product_id' => 100, 'variation_id' => 102, 'quantity' => 4 ),
			)
		);

		$this->assertFalse( $result->is_available() );
		$this->assertSame( 10250000000, $result->demand()[ $this->pool_id ] );
		$this->assertSame( 250000000, $result->shortages()[ $this->pool_id ]['missing'] );
	}

	/**
	 * An exact mixed-package allocation remains available.
	 */
	public function test_exact_mixed_allocation_is_available(): void {
		$result = $this->service->check(
			array(
				array( 'product_id' => 100, 'variation_id' => 101, 'quantity' => 8 ),
				array( 'product_id' => 100, 'variation_id' => 102, 'quantity' => 4 ),
			)
		);

		$this->assertTrue( $result->is_available() );
		$this->assertSame( 10000000000, $result->demand()[ $this->pool_id ] );
	}

	/**
	 * Each variation gets a package-specific saleable quantity.
	 */
	public function test_saleable_quantity_uses_each_variations_consumption(): void {
		$this->assertSame( 40, $this->service->saleable_quantity( 100, 101 ) );
		$this->assertSame( 5, $this->service->saleable_quantity( 100, 102 ) );
		$this->assertNull( $this->service->saleable_quantity( 999, 0 ) );
	}

	/** Persisted add-on mappings fall back safely while their calculator is unavailable. */
	public function test_unavailable_extension_calculator_does_not_break_free_availability(): void {
		global $wpdb;

		$maps = new MappingRepository( $wpdb );
		$maps->save_components( 100, 101, 'recipe', array( new MappingComponent( $this->pool_id, 250000000, 'component' ) ) );

		$result = $this->service->check(
			array(
				array( 'product_id' => 100, 'variation_id' => 101, 'quantity' => 1 ),
			)
		);

		$this->assertTrue( $result->is_available() );
		$this->assertSame( array(), $result->demand() );
		$this->assertNull( $this->service->saleable_quantity( 100, 101 ) );
	}

	/** Public pool reads apply the same availability adjustments as saleability. */
	public function test_pool_quantity_exposes_filtered_availability(): void {
		$filter = static function ( int $quantity, int $pool_id ): int {
			return $pool_id > 0 ? $quantity - 25 : $quantity;
		};
		add_filter( 'laqi_lusm_pool_available_quantity', $filter, 10, 2 );
		$this->assertSame( 9999999975, $this->service->pool_quantity( $this->pool_id ) );
		remove_filter( 'laqi_lusm_pool_available_quantity', $filter, 10 );
		$this->assertSame( 0, $this->service->pool_quantity( PHP_INT_MAX ) );
	}

	/**
	 * Explicit setup edits replace the mapping component without duplicate rows.
	 */
	public function test_mapping_can_be_updated_through_shared_repository(): void {
		global $wpdb;

		$maps = new MappingRepository( $wpdb );
		$maps->save_single_pool( 100, 101, $this->pool_id, 500000000 );

		$this->assertSame( 20, $this->service->saleable_quantity( 100, 101 ) );
		$this->assertSame( 1, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Schema::table( 'mapping_components' ) . ' WHERE mapping_id = (SELECT id FROM ' . Schema::table( 'mappings' ) . ' WHERE product_id = %d AND variation_id = %d)', 100, 101 ) ) );
	}

	/** Active mapping reads expose complete counts and stable offsets. */
	public function test_active_mappings_can_be_paginated(): void {
		global $wpdb;

		$maps   = new MappingRepository( $wpdb );
		$first  = $maps->active( 1, 0 );
		$second = $maps->active( 1, 1 );

		$this->assertGreaterThanOrEqual( 2, $maps->count_active() );
		$this->assertCount( 1, $first );
		$this->assertCount( 1, $second );
		$this->assertNotSame( $first[0]->id(), $second[0]->id() );
	}

	/** Stale setup forms cannot overwrite a newer mapping version. */
	public function test_mapping_update_rejects_a_stale_version(): void {
		global $wpdb;

		$maps    = new MappingRepository( $wpdb );
		$mapping = $maps->find_for_product( 100, 101 );
		$this->assertNotNull( $mapping );
		$maps->save_single_pool( 100, 101, $this->pool_id, 500000000, true, $mapping->version() );

		try {
			$maps->save_single_pool( 100, 101, $this->pool_id, 750000000, true, $mapping->version() );
			$this->fail( 'Expected a stale mapping version to be rejected.' );
		} catch ( RuntimeException $error ) {
			$this->assertSame( 'The product mapping changed before this edit was saved.', $error->getMessage() );
			$this->assertSame( 20, $this->service->saleable_quantity( 100, 101 ) );
		}
	}

	/**
	 * Unlinking keeps the versioned definition but removes it from availability.
	 */
	public function test_mapping_can_be_deactivated_without_deleting_components(): void {
		global $wpdb;

		$maps    = new MappingRepository( $wpdb );
		$mapping = $maps->find_for_product( 100, 101 );
		$this->assertNotNull( $mapping );
		$this->assertContains( $mapping->id(), array_map( static function ( $item ): int { return $item->id(); }, $maps->active() ) );

		$deactivated = $maps->deactivate( $mapping->id() );

		$this->assertSame( $mapping->id(), $deactivated->id() );
		$this->assertNull( $maps->find_for_product( 100, 101 ) );
		$this->assertNull( $this->service->saleable_quantity( 100, 101 ) );
		$this->assertNotContains( $mapping->id(), array_map( static function ( $item ): int { return $item->id(); }, $maps->active() ) );
		$this->assertSame( 1, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Schema::table( 'mapping_components' ) . ' WHERE mapping_id = %d', $mapping->id() ) ) );
		$this->assertSame( $mapping->version() + 1, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT version FROM ' . Schema::table( 'mappings' ) . ' WHERE id = %d', $mapping->id() ) ) );
	}
}
