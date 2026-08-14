<?php
/**
 * Multi-component recipe consumption.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Recipes;

defined( 'ABSPATH' ) || exit;

use InvalidArgumentException;
use LaqiUnitStockManager\Consumption\ConsumptionCalculatorInterface;
use LaqiUnitStockManager\Domain\ProductMapping;

/** Aggregates ingredient and packaging demand by pool. */
final class RecipeCalculator implements ConsumptionCalculatorInterface {

	/** Registered calculator type. @return string */
	public function type(): string {
		return 'recipe';
	}

	/**
	 * Calculate exact demand across every recipe component.
	 *
	 * Multiple roles may intentionally draw from one pool; they are summed before
	 * the shared transaction service locks and mutates that pool.
	 *
	 * @param ProductMapping $mapping  Recipe mapping.
	 * @param int            $quantity Sold item quantity.
	 * @return array<int,int>
	 * @throws InvalidArgumentException When the recipe or calculated demand is invalid.
	 */
	public function calculate( ProductMapping $mapping, int $quantity ): array {
		if ( 'recipe' !== $mapping->calculator_type() || count( $mapping->components() ) < 2 || $quantity < 1 ) {
			throw new InvalidArgumentException( 'A recipe requires at least two components and a positive item quantity.' );
		}

		$demand = array();
		foreach ( $mapping->components() as $component ) {
			if ( $component->consumption() < 1 || $component->consumption() > intdiv( PHP_INT_MAX, $quantity ) ) {
				throw new InvalidArgumentException( 'Calculated recipe consumption is invalid or too large.' );
			}
			$amount  = $component->consumption() * $quantity;
			$current = $demand[ $component->pool_id() ] ?? 0;
			if ( $amount > PHP_INT_MAX - $current ) {
				throw new InvalidArgumentException( 'Calculated recipe consumption is too large.' );
			}
			$demand[ $component->pool_id() ] = $current + $amount;
		}

		return $demand;
	}
}
