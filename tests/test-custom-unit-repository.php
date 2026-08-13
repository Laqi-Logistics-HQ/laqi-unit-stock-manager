<?php
/**
 * Merchant-defined unit persistence tests.
 *
 * @package LaqiUnitStockManager
 */

use LaqiUnitStockManager\Storage\CustomUnitRepository;
use LaqiUnitStockManager\Storage\Schema;
use LaqiUnitStockManager\Unit\UnitRegistry;

/**
 * Tests custom pack/container definitions survive a new runtime registry.
 */
class Test_Custom_Unit_Repository extends WP_UnitTestCase {

	/**
	 * Install tables once.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		Schema::install();
	}

	/**
	 * Clear custom definitions after each test.
	 */
	public function tear_down(): void {
		global $wpdb;

		$wpdb->query( 'TRUNCATE TABLE ' . Schema::table( 'units' ) );
		parent::tear_down();
	}

	/**
	 * A 25 kg sack reloads with the exact same normalized factor.
	 */
	public function test_custom_unit_is_persisted_and_reloaded(): void {
		global $wpdb;

		$repository = new CustomUnitRepository( $wpdb );
		$registry   = new UnitRegistry();
		$repository->create( $registry, 'sack', 'Supplier sack', 'sack', '25', 'kg' );

		$reloaded = new UnitRegistry();
		$repository->register_all( $reloaded );

		$this->assertSame( 25000000000000, $reloaded->get( 'sack' )->base_factor() );
		$this->assertSame( 50000000000000, $reloaded->normalize( '2', 'sack' )->amount() );
	}
}
