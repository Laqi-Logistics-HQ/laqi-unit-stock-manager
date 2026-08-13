<?php
/**
 * Single-pool consumption calculator.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Consumption;

defined( 'ABSPATH' ) || exit;

use InvalidArgumentException;
use LaqiUnitStockManager\Domain\ProductMapping;

/**
 * Part 1 calculator requiring exactly one mapping component.
 */
final class SinglePoolCalculator implements ConsumptionCalculatorInterface {

	/**
	 * Registered calculator type.
	 *
	 * @return string
	 */
	public function type(): string {
		return 'single_pool';
	}

	/**
	 * Calculate pool demand.
	 *
	 * @param ProductMapping $mapping Mapping definition.
	 * @param int            $quantity Number of sold items.
	 * @return array<int, int>
	 * @throws InvalidArgumentException When the mapping violates Part 1 invariants.
	 */
	public function calculate( ProductMapping $mapping, int $quantity ): array {
		$components = $mapping->components();
		if ( 1 !== count( $components ) || $quantity < 1 ) {
			throw new InvalidArgumentException( 'A single-pool mapping requires one component and a positive item quantity.' );
		}

		$component = $components[0];
		if ( $component->consumption() > intdiv( PHP_INT_MAX, $quantity ) ) {
			throw new InvalidArgumentException( 'Calculated stock consumption is too large.' );
		}

		return array( $component->pool_id() => $component->consumption() * $quantity );
	}
}
