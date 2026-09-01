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
		$this->assertSame( 6350293180000, $units->normalize( '1', 'st' )->amount() );
	}

	/** Metric and international imperial length units share exact conversions. */
	public function test_length_units_are_exact_and_compatible(): void {
		$units = new UnitRegistry();

		$this->assertSame( 1000000000, $units->normalize( '1', 'm' )->amount() );
		$this->assertSame( 100000000, $units->normalize( '1', 'dm' )->amount() );
		$this->assertSame( 304800000, $units->normalize( '1', 'ft' )->amount() );
		$this->assertSame( 914400000, $units->normalize( '1', 'yd' )->amount() );
		$this->assertSame( 1609344000000, $units->normalize( '1', 'mi' )->amount() );
		$this->assertSame( 'length', $units->normalize( '50', 'm' )->family() );
	}

	/** Surface-area units convert within a separate exact family. */
	public function test_area_units_are_exact_and_separate_from_length(): void {
		$units = new UnitRegistry();

		$this->assertSame( 1000000000000, $units->normalize( '1', 'm2' )->amount() );
		$this->assertSame( 92903040000, $units->normalize( '1', 'ft2' )->amount() );
		$this->assertSame( 'area', $units->normalize( '1', 'yd2' )->family() );
		$this->assertNotSame( $units->normalize( '1', 'm' )->family(), $units->normalize( '1', 'm2' )->family() );
	}

	/**
	 * Merchants can define exact pack and container quantities.
	 */
	public function test_custom_quantities_register_against_existing_units(): void {
		$units = new UnitRegistry();

		$sack = $units->register_custom( 'sack', '25', 'kg' );
		$tray = $units->register_custom( 'tray', '24', 'unit' );
		$roll = $units->register_custom( 'rope_roll', '50', 'm' );

		$this->assertSame( 25000000000000, $sack->base_factor() );
		$this->assertSame( 'mass', $sack->family() );
		$this->assertSame( 48, $units->normalize( '2', 'tray' )->amount() );
		$this->assertSame( 'count', $tray->family() );
		$this->assertSame( 50000000000, $roll->base_factor() );
		$this->assertSame( 'length', $roll->family() );
	}
}
