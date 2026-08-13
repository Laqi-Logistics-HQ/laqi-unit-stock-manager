<?php
/**
 * Exact stock quantity formatting.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Presentation;

defined( 'ABSPATH' ) || exit;

use InvalidArgumentException;
use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Unit\UnitRegistry;

/**
 * Converts normalized integers to decimal display strings without floats.
 */
final class QuantityFormatter {

	/**
	 * Unit definitions.
	 *
	 * @var UnitRegistry
	 */
	private $units;

	/**
	 * Constructor.
	 *
	 * @param UnitRegistry $units Unit registry.
	 */
	public function __construct( UnitRegistry $units ) {
		$this->units = $units;
	}

	/**
	 * Format a quantity in a compatible unit.
	 *
	 * @param Quantity $quantity Quantity.
	 * @param string   $unit     Unit key.
	 * @return string
	 * @throws InvalidArgumentException When the unit family differs.
	 */
	public function format( Quantity $quantity, string $unit ): string {
		return $this->decimal( $quantity, $unit ) . ' ' . $unit;
	}

	/**
	 * Format only the exact decimal value for an editable field.
	 *
	 * @param Quantity $quantity Quantity.
	 * @param string   $unit     Unit key.
	 * @return string
	 * @throws InvalidArgumentException When the unit family differs.
	 */
	public function decimal( Quantity $quantity, string $unit ): string {
		$definition = $this->units->get( $unit );
		if ( $definition->family() !== $quantity->family() ) {
			throw new InvalidArgumentException( 'The display unit is incompatible with the quantity.' );
		}

		$amount    = $quantity->amount();
		$negative  = $amount < 0;
		$absolute  = abs( $amount );
		$factor    = $definition->base_factor();
		$integer   = intdiv( $absolute, $factor );
		$remainder = $absolute % $factor;
		$value     = (string) $integer;

		if ( 0 !== $remainder ) {
			$fraction = '';
			for ( $position = 0; $position < 12 && 0 !== $remainder; ++$position ) {
				if ( $remainder > intdiv( PHP_INT_MAX, 10 ) ) {
					return ( $negative ? '-' : '' ) . $integer . ' ' . $remainder . '/' . $factor;
				}
				$remainder *= 10;
				$fraction  .= (string) intdiv( $remainder, $factor );
				$remainder %= $factor;
			}
			$value .= '.' . rtrim( $fraction, '0' );
			if ( 0 !== $remainder ) {
				$value .= '…';
			}
		}

		return ( $negative ? '-' : '' ) . $value;
	}

	/**
	 * Choose a compatible unit whose editable decimal round-trips exactly.
	 *
	 * @param Quantity $quantity       Quantity.
	 * @param string   $preferred_unit Preferred unit key.
	 * @return array{value: string, unit: string}
	 * @throws InvalidArgumentException When no registered exact representation exists.
	 */
	public function editable( Quantity $quantity, string $preferred_unit ): array {
		$definitions = $this->units->all();
		$candidates  = array( $preferred_unit => $this->units->get( $preferred_unit ) ) + $definitions;
		foreach ( $candidates as $unit => $definition ) {
			if ( $definition->family() !== $quantity->family() ) {
				continue;
			}
			$value = $this->decimal( $quantity, $unit );
			if ( false === strpos( $value, '…' ) && false === strpos( $value, '/' ) && $this->units->normalize( $value, $unit )->amount() === $quantity->amount() ) {
				return array(
					'value' => $value,
					'unit'  => $unit,
				);
			}
		}

		throw new InvalidArgumentException( 'The quantity has no exact editable unit representation.' );
	}
}
