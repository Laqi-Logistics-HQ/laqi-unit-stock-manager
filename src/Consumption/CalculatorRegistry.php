<?php
/**
 * Consumption calculator registry.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Consumption;

defined( 'ABSPATH' ) || exit;

use InvalidArgumentException;

/**
 * Resolves calculator implementations without branching in availability code.
 */
final class CalculatorRegistry {

	/**
	 * Registered calculators.
	 *
	 * @var array<string, ConsumptionCalculatorInterface>
	 */
	private $calculators = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->register( new SinglePoolCalculator() );
	}

	/**
	 * Register or replace a calculator.
	 *
	 * @param ConsumptionCalculatorInterface $calculator Calculator.
	 * @return void
	 */
	public function register( ConsumptionCalculatorInterface $calculator ): void {
		$this->calculators[ $calculator->type() ] = $calculator;
	}

	/**
	 * Resolve a calculator.
	 *
	 * @param string $type Calculator type.
	 * @return ConsumptionCalculatorInterface
	 * @throws InvalidArgumentException When the type is unknown.
	 */
	public function get( string $type ): ConsumptionCalculatorInterface {
		if ( ! isset( $this->calculators[ $type ] ) ) {
			throw new InvalidArgumentException( 'Unknown consumption calculator.' );
		}

		return $this->calculators[ $type ];
	}
}
