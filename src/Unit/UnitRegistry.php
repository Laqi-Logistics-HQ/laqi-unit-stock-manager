<?php
/**
 * Measurement unit registry.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Unit;

defined( 'ABSPATH' ) || exit;

use InvalidArgumentException;
use LaqiUnitStockManager\Domain\Quantity;

/**
 * Registers exact metric units and converts decimal input without floats.
 */
final class UnitRegistry {

	/**
	 * Registered unit definitions.
	 *
	 * @var array<string, UnitDefinition>
	 */
	private $definitions = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Mass base: nanogram. Common metric and imperial definitions are exact.
		$this->register( new UnitDefinition( 'mg', 'mass', 1000000, 'metric', __( 'Milligram', 'laqi-unit-stock-manager' ), 'mg' ) );
		$this->register( new UnitDefinition( 'g', 'mass', 1000000000, 'metric', __( 'Gram', 'laqi-unit-stock-manager' ), 'g' ) );
		$this->register( new UnitDefinition( 'kg', 'mass', 1000000000000, 'metric', __( 'Kilogram', 'laqi-unit-stock-manager' ), 'kg' ) );
		$this->register( new UnitDefinition( 't', 'mass', 1000000000000000, 'metric', __( 'Tonne', 'laqi-unit-stock-manager' ), 't' ) );
		$this->register( new UnitDefinition( 'oz', 'mass', 28349523125, 'imperial', __( 'Ounce', 'laqi-unit-stock-manager' ), 'oz' ) );
		$this->register( new UnitDefinition( 'lb', 'mass', 453592370000, 'imperial', __( 'Pound', 'laqi-unit-stock-manager' ), 'lb' ) );
		$this->register( new UnitDefinition( 'st', 'mass', 6350293180000, 'imperial', __( 'Stone', 'laqi-unit-stock-manager' ), 'st' ) );
		$this->register( new UnitDefinition( 'short_ton', 'mass', 907184740000000, 'us_customary', __( 'US short ton', 'laqi-unit-stock-manager' ), 'short ton' ) );
		$this->register( new UnitDefinition( 'long_ton', 'mass', 1016046908800000, 'imperial', __( 'Imperial long ton', 'laqi-unit-stock-manager' ), 'long ton' ) );
		$this->register( new UnitDefinition( 'ct', 'mass', 200000000, 'metric', __( 'Carat', 'laqi-unit-stock-manager' ), 'ct' ) );

		// Length base: nanometre. Metric and international imperial units are exact.
		$this->register( new UnitDefinition( 'um', 'length', 1000, 'metric', __( 'Micrometre', 'laqi-unit-stock-manager' ), 'µm' ) );
		$this->register( new UnitDefinition( 'mm', 'length', 1000000, 'metric', __( 'Millimetre', 'laqi-unit-stock-manager' ), 'mm' ) );
		$this->register( new UnitDefinition( 'cm', 'length', 10000000, 'metric', __( 'Centimetre', 'laqi-unit-stock-manager' ), 'cm' ) );
		$this->register( new UnitDefinition( 'dm', 'length', 100000000, 'metric', __( 'Decimetre', 'laqi-unit-stock-manager' ), 'dm' ) );
		$this->register( new UnitDefinition( 'm', 'length', 1000000000, 'metric', __( 'Metre', 'laqi-unit-stock-manager' ), 'm' ) );
		$this->register( new UnitDefinition( 'km', 'length', 1000000000000, 'metric', __( 'Kilometre', 'laqi-unit-stock-manager' ), 'km' ) );
		$this->register( new UnitDefinition( 'in', 'length', 25400000, 'imperial', __( 'Inch', 'laqi-unit-stock-manager' ), 'in' ) );
		$this->register( new UnitDefinition( 'ft', 'length', 304800000, 'imperial', __( 'Foot', 'laqi-unit-stock-manager' ), 'ft' ) );
		$this->register( new UnitDefinition( 'yd', 'length', 914400000, 'imperial', __( 'Yard', 'laqi-unit-stock-manager' ), 'yd' ) );
		$this->register( new UnitDefinition( 'mi', 'length', 1609344000000, 'imperial', __( 'Mile', 'laqi-unit-stock-manager' ), 'mi' ) );
		$this->register( new UnitDefinition( 'nmi', 'length', 1852000000000, 'international', __( 'Nautical mile', 'laqi-unit-stock-manager' ), 'nmi' ) );

		// Area base: square micrometre. This keeps common metric and imperial
		// surface units exact without exhausting signed BIGINT capacity.
		$this->register( new UnitDefinition( 'mm2', 'area', 1000000, 'metric', __( 'Square millimetre', 'laqi-unit-stock-manager' ), 'mm²' ) );
		$this->register( new UnitDefinition( 'cm2', 'area', 100000000, 'metric', __( 'Square centimetre', 'laqi-unit-stock-manager' ), 'cm²' ) );
		$this->register( new UnitDefinition( 'm2', 'area', 1000000000000, 'metric', __( 'Square metre', 'laqi-unit-stock-manager' ), 'm²' ) );
		$this->register( new UnitDefinition( 'ha', 'area', 10000000000000000, 'metric', __( 'Hectare', 'laqi-unit-stock-manager' ), 'ha' ) );
		$this->register( new UnitDefinition( 'in2', 'area', 645160000, 'imperial', __( 'Square inch', 'laqi-unit-stock-manager' ), 'in²' ) );
		$this->register( new UnitDefinition( 'ft2', 'area', 92903040000, 'imperial', __( 'Square foot', 'laqi-unit-stock-manager' ), 'ft²' ) );
		$this->register( new UnitDefinition( 'yd2', 'area', 836127360000, 'imperial', __( 'Square yard', 'laqi-unit-stock-manager' ), 'yd²' ) );
		$this->register( new UnitDefinition( 'acre', 'area', 4046856422400000, 'imperial', __( 'Acre', 'laqi-unit-stock-manager' ), 'ac' ) );

		// Volume base: one sixteenth of a nanolitre. This keeps common US and
		// imperial definitions exact while retaining practical BIGINT capacity.
		$this->register( new UnitDefinition( 'ml', 'volume', 16000000, 'metric', __( 'Millilitre', 'laqi-unit-stock-manager' ), 'ml' ) );
		$this->register( new UnitDefinition( 'l', 'volume', 16000000000, 'metric', __( 'Litre', 'laqi-unit-stock-manager' ), 'l' ) );
		$this->register( new UnitDefinition( 'cl', 'volume', 160000000, 'metric', __( 'Centilitre', 'laqi-unit-stock-manager' ), 'cl' ) );
		$this->register( new UnitDefinition( 'dl', 'volume', 1600000000, 'metric', __( 'Decilitre', 'laqi-unit-stock-manager' ), 'dl' ) );
		$this->register( new UnitDefinition( 'm3', 'volume', 16000000000000, 'metric', __( 'Cubic metre', 'laqi-unit-stock-manager' ), 'm³' ) );
		$this->register( new UnitDefinition( 'us_fl_oz', 'volume', 473176473, 'us_customary', __( 'US fluid ounce', 'laqi-unit-stock-manager' ), 'US fl oz' ) );
		$this->register( new UnitDefinition( 'us_pt', 'volume', 7570823568, 'us_customary', __( 'US pint', 'laqi-unit-stock-manager' ), 'US pt' ) );
		$this->register( new UnitDefinition( 'us_qt', 'volume', 15141647136, 'us_customary', __( 'US quart', 'laqi-unit-stock-manager' ), 'US qt' ) );
		$this->register( new UnitDefinition( 'us_gal', 'volume', 60566588544, 'us_customary', __( 'US gallon', 'laqi-unit-stock-manager' ), 'US gal' ) );
		$this->register( new UnitDefinition( 'imp_fl_oz', 'volume', 454609000, 'imperial', __( 'Imperial fluid ounce', 'laqi-unit-stock-manager' ), 'imp fl oz' ) );
		$this->register( new UnitDefinition( 'imp_pt', 'volume', 9092180000, 'imperial', __( 'Imperial pint', 'laqi-unit-stock-manager' ), 'imp pt' ) );
		$this->register( new UnitDefinition( 'imp_gal', 'volume', 72737440000, 'imperial', __( 'Imperial gallon', 'laqi-unit-stock-manager' ), 'imp gal' ) );
		$this->register( new UnitDefinition( 'in3', 'volume', 262193024, 'imperial', __( 'Cubic inch', 'laqi-unit-stock-manager' ), 'in³' ) );
		$this->register( new UnitDefinition( 'ft3', 'volume', 453069545472, 'imperial', __( 'Cubic foot', 'laqi-unit-stock-manager' ), 'ft³' ) );
		$this->register( new UnitDefinition( 'yd3', 'volume', 12232877727744, 'imperial', __( 'Cubic yard', 'laqi-unit-stock-manager' ), 'yd³' ) );

		$this->register( new UnitDefinition( 'unit', 'count', 1, 'count', __( 'Unit', 'laqi-unit-stock-manager' ), __( 'units', 'laqi-unit-stock-manager' ) ) );
	}

	/**
	 * Register or replace a unit definition.
	 *
	 * @param UnitDefinition $definition Definition.
	 * @return void
	 * @throws InvalidArgumentException When the factor is invalid.
	 */
	public function register( UnitDefinition $definition ): void {
		if ( $definition->base_factor() < 1 ) {
			throw new InvalidArgumentException( 'A unit factor must be positive.' );
		}

		$this->definitions[ $definition->key() ] = $definition;
	}

	/**
	 * Resolve a unit definition.
	 *
	 * @param string $key Unit key.
	 * @return UnitDefinition
	 * @throws InvalidArgumentException When the unit is unknown.
	 */
	public function get( string $key ): UnitDefinition {
		if ( ! isset( $this->definitions[ $key ] ) ) {
			throw new InvalidArgumentException( 'Unknown stock unit.' );
		}

		return $this->definitions[ $key ];
	}

	/**
	 * Convert a decimal string to a normalized exact quantity.
	 *
	 * @param string $value Decimal input using a period separator.
	 * @param string $unit  Unit key.
	 * @return Quantity
	 * @throws InvalidArgumentException When the input cannot be represented exactly.
	 */
	public function normalize( string $value, string $unit ): Quantity {
		$definition = $this->get( $unit );
		$value      = trim( $value );

		if ( ! preg_match( '/^(0|[1-9][0-9]*)(?:\.([0-9]+))?$/', $value, $matches ) ) {
			throw new InvalidArgumentException( 'Quantity must be a non-negative decimal number.' );
		}

		$integer  = $matches[1];
		$fraction = isset( $matches[2] ) ? $matches[2] : '';

		if ( strlen( $integer ) > 18 || strlen( $fraction ) > 12 ) {
			throw new InvalidArgumentException( 'Quantity is outside the supported precision.' );
		}

		$scale = 10 ** strlen( $fraction );
		if ( (int) $integer > intdiv( PHP_INT_MAX, $scale ) ) {
			throw new InvalidArgumentException( 'Quantity is too large to store safely.' );
		}

		$scaled = ( (int) $integer * $scale ) + ( '' === $fraction ? 0 : (int) $fraction );

		if ( $scaled > intdiv( PHP_INT_MAX, $definition->base_factor() ) ) {
			throw new InvalidArgumentException( 'Quantity is too large to store safely.' );
		}

		$product = $scaled * $definition->base_factor();

		if ( 0 !== $product % $scale ) {
			throw new InvalidArgumentException( 'Quantity is more precise than the selected unit supports.' );
		}

		return new Quantity( $definition->family(), (int) ( $product / $scale ) );
	}

	/**
	 * All definitions.
	 *
	 * @return array<string, UnitDefinition>
	 */
	public function all(): array {
		return $this->definitions;
	}

	/**
	 * Define a merchant unit as an exact multiple of an existing unit.
	 *
	 * Examples: sack = 25 kg, rope roll = 50 m, tray = 24 units.
	 *
	 * @param string $key        Custom unit key.
	 * @param string $equivalent Decimal quantity of the reference unit.
	 * @param string $unit       Reference unit key.
	 * @param string $label      Merchant-facing label.
	 * @param string $symbol     Merchant-facing symbol.
	 * @return UnitDefinition
	 * @throws InvalidArgumentException When the custom definition is invalid.
	 */
	public function register_custom( string $key, string $equivalent, string $unit, string $label = '', string $symbol = '' ): UnitDefinition {
		if ( ! preg_match( '/^[a-z][a-z0-9_]{1,49}$/', $key ) || isset( $this->definitions[ $key ] ) ) {
			throw new InvalidArgumentException( 'Custom unit key is invalid or already registered.' );
		}

		$quantity   = $this->normalize( $equivalent, $unit );
		$definition = new UnitDefinition( $key, $quantity->family(), $quantity->amount(), 'custom', $label, $symbol );
		$this->register( $definition );

		return $definition;
	}
}
