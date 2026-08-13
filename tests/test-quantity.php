<?php
/**
 * Exact quantity tests.
 *
 * @package LaqiUnitStockManager
 */

use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Unit\UnitRegistry;

/**
 * Tests exact conversion and family safety.
 */
class Test_Quantity extends WP_UnitTestCase {

	/**
	 * Decimal package sizes normalize without floating-point drift.
	 */
	public function test_metric_package_sizes_normalize_exactly(): void {
		$units = new UnitRegistry();

		$this->assertSame( 100000000, $units->normalize( '0.1', 'g' )->amount() );
		$this->assertSame( 250000000, $units->normalize( '0.25', 'g' )->amount() );
		$this->assertSame( 10000000000, $units->normalize( '10', 'g' )->amount() );
		$this->assertSame( 10000000000000, $units->normalize( '10', 'kg' )->amount() );
		$this->assertSame( 4000000000, $units->normalize( '0.25', 'l' )->amount() );
	}

	/**
	 * Input finer than the base unit is rejected instead of rounded.
	 */
	public function test_unsupported_precision_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );

		( new UnitRegistry() )->normalize( '0.0000000001', 'g' );
	}

	/**
	 * Quantities from different families cannot be combined.
	 */
	public function test_different_families_cannot_be_added(): void {
		$this->expectException( InvalidArgumentException::class );

		( new Quantity( 'mass', 1 ) )->plus( new Quantity( 'volume', 1 ) );
	}

	/**
	 * Common US and imperial units use exact integer factors.
	 */
	public function test_common_imperial_units_are_exact(): void {
		$units = new UnitRegistry();

		$this->assertSame( 28349523125, $units->normalize( '1', 'oz' )->amount() );
		$this->assertSame( 60566588544, $units->normalize( '1', 'us_gal' )->amount() );
		$this->assertSame( 72737440000, $units->normalize( '1', 'imp_gal' )->amount() );
	}

	/**
	 * Merchants can define exact pack and container quantities.
	 */
	public function test_custom_quantities_register_against_existing_units(): void {
		$units = new UnitRegistry();

		$sack = $units->register_custom( 'sack', '25', 'kg' );
		$tray = $units->register_custom( 'tray', '24', 'unit' );

		$this->assertSame( 25000000000000, $sack->base_factor() );
		$this->assertSame( 'mass', $sack->family() );
		$this->assertSame( 48, $units->normalize( '2', 'tray' )->amount() );
		$this->assertSame( 'count', $tray->family() );
	}
}
