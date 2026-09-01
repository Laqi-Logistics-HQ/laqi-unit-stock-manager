<?php
/**
 * Combined availability result.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Availability;

defined( 'ABSPATH' ) || exit;

/**
 * Reports combined demand and shortages by inventory pool.
 */
final class AvailabilityResult {

	/**
	 * Demand by pool.
	 *
	 * @var array<int, int>
	 */
	private $demand;

	/**
	 * Shortages by pool.
	 *
	 * @var array<int, array<string, int>>
	 */
	private $shortages;

	/**
	 * Constructor.
	 *
	 * @param array<int, int>                $demand    Demand by pool.
	 * @param array<int, array<string, int>> $shortages Shortages by pool.
	 */
	public function __construct( array $demand, array $shortages ) {
		$this->demand    = $demand;
		$this->shortages = $shortages;
	}

	/**
	 * Whether all pooled demand can be fulfilled.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return array() === $this->shortages;
	}

	/**
	 * Demand by pool.
	 *
	 * @return array<int, int>
	 */
	public function demand(): array {
		return $this->demand;
	}

	/**
	 * Shortages by pool.
	 *
	 * @return array<int, array<string, int>>
	 */
	public function shortages(): array {
		return $this->shortages;
	}
}
