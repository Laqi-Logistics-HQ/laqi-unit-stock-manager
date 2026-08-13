<?php
/**
 * Merchant-defined unit persistence tests.
 *
 * @package LaqiUnitStockManager
 */

use LaqiUnitStockManager\Storage\CustomUnitRepository;
use LaqiUnitStockManager\Storage\Schema;
use LaqiUnitStockManager\Unit\UnitRegistry;
use LaqiUnitStockManager\Storage\PoolRepository;
use LaqiUnitStockManager\Domain\Quantity;

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
		$this->assertSame( 'Supplier sack', $repository->active()[0]['label'] );
		$this->assertSame( 'kg', $repository->active()[0]['reference_unit'] );
	}

	/** An unused custom unit can be retired while retaining its stable record. */
	public function test_unused_custom_unit_can_be_retired(): void {
		global $wpdb;

		$repository = new CustomUnitRepository( $wpdb );
		$registry   = new UnitRegistry();
		$repository->create( $registry, 'crate', 'Supplier crate', 'crate', '12', 'unit' );
		$unit = $repository->active()[0];

		$repository->deactivate( (int) $unit['id'] );

		$this->assertSame( array(), $repository->active() );
		$this->assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT active FROM ' . Schema::table( 'units' ) . ' WHERE id = %d', $unit['id'] ) ) );
		$reloaded = new UnitRegistry();
		$repository->register_all( $reloaded );
		$this->expectException( InvalidArgumentException::class );
		$reloaded->get( 'crate' );
	}

	/** A pool display dependency prevents a unit from being retired. */
	public function test_custom_unit_in_use_by_pool_cannot_be_retired(): void {
		global $wpdb;

		$repository = new CustomUnitRepository( $wpdb );
		$registry   = new UnitRegistry();
		$definition = $repository->create( $registry, 'sack', 'Supplier sack', 'sack', '25', 'kg' );
		$unit       = $repository->active()[0];
		$pool       = ( new PoolRepository( $wpdb ) )->create( 'Sacks', new Quantity( 'mass', $definition->base_factor() ), 'ng', 'sack' );

		try {
			$repository->deactivate( (int) $unit['id'] );
			$this->fail( 'Expected an in-use custom unit to be protected.' );
		} catch ( InvalidArgumentException $error ) {
			$this->assertSame( 'A custom stock unit cannot be retired while it is in use.', $error->getMessage() );
			$this->assertCount( 1, $repository->active() );
		} finally {
			$wpdb->delete( Schema::table( 'pools' ), array( 'id' => $pool->id() ), array( '%d' ) );
		}
	}

	/** Active custom units that reference another unit protect that dependency. */
	public function test_custom_unit_dependency_prevents_retirement(): void {
		global $wpdb;

		$repository = new CustomUnitRepository( $wpdb );
		$registry   = new UnitRegistry();
		$repository->create( $registry, 'case', 'Case', 'case', '12', 'unit' );
		$case = $repository->active()[0];
		$repository->create( $registry, 'pallet', 'Pallet', 'pallet', '40', 'case' );

		$this->expectException( InvalidArgumentException::class );
		$repository->deactivate( (int) $case['id'] );
	}
}
