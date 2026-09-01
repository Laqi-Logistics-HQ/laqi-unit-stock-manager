<?php
/**
 * Consumption calculator contract.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Consumption;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Domain\ProductMapping;

/**
 * Calculates normalized demand by pool for a mapped sold quantity.
 */
interface ConsumptionCalculatorInterface {

	/**
	 * Registered calculator type.
	 *
	 * @return string
	 */
	public function type(): string;

	/**
	 * Calculate pool demand.
	 *
	 * @param ProductMapping $mapping Mapping definition.
	 * @param int            $quantity Number of sold items.
	 * @return array<int, int> Pool ID to normalized demand.
	 */
	public function calculate( ProductMapping $mapping, int $quantity ): array;
}
