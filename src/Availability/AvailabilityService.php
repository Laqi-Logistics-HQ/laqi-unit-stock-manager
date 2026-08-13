<?php
/**
 * Combined pool availability service.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Availability;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Consumption\CalculatorRegistry;
use LaqiUnitStockManager\Storage\MappingRepository;
use LaqiUnitStockManager\Storage\PoolRepository;

/**
 * Aggregates every cart/order line before checking shared pool balances.
 */
final class AvailabilityService {

	/**
	 * Product mappings.
	 *
	 * @var MappingRepository
	 */
	private $mappings;

	/**
	 * Inventory pools.
	 *
	 * @var PoolRepository
	 */
	private $pools;

	/**
	 * Consumption calculators.
	 *
	 * @var CalculatorRegistry
	 */
	private $calculators;

	/**
	 * Constructor.
	 *
	 * @param MappingRepository  $mappings    Mapping repository.
	 * @param PoolRepository     $pools       Pool repository.
	 * @param CalculatorRegistry $calculators Calculator registry.
	 */
	public function __construct( MappingRepository $mappings, PoolRepository $pools, CalculatorRegistry $calculators ) {
		$this->mappings    = $mappings;
		$this->pools       = $pools;
		$this->calculators = $calculators;
	}

	/**
	 * Check combined demand.
	 *
	 * Lines use product_id, variation_id, and quantity keys. Unmapped products do
	 * not consume pooled inventory and are ignored.
	 *
	 * @param array<int, array<string, int>> $lines Purchasable lines.
	 * @return AvailabilityResult
	 */
	public function check( array $lines ): AvailabilityResult {
		$demand = array();

		foreach ( $lines as $line ) {
			$product_id   = isset( $line['product_id'] ) ? (int) $line['product_id'] : 0;
			$variation_id = isset( $line['variation_id'] ) ? (int) $line['variation_id'] : 0;
			$quantity     = isset( $line['quantity'] ) ? (int) $line['quantity'] : 0;
			$mapping      = $this->mappings->find_for_product( $product_id, $variation_id );

			if ( null === $mapping || $quantity < 1 ) {
				continue;
			}

			$line_demand = $this->calculators->get( $mapping->calculator_type() )->calculate( $mapping, $quantity );
			foreach ( $line_demand as $pool_id => $amount ) {
				$demand[ $pool_id ] = ( $demand[ $pool_id ] ?? 0 ) + $amount;
			}
		}

		$shortages = array();
		foreach ( $demand as $pool_id => $required ) {
			$pool      = $this->pools->find( $pool_id );
			$available = null === $pool ? 0 : (int) apply_filters( 'laqi_lusm_pool_available_quantity', $pool->quantity()->amount(), $pool_id );
			if ( null === $pool || ( ! $pool->allows_backorders() && $available < $required ) ) {
				$shortages[ $pool_id ] = array(
					'required'  => $required,
					'available' => $available,
					'missing'   => max( 0, $required - $available ),
				);
			}
		}

		return new AvailabilityResult( $demand, $shortages );
	}

	/**
	 * Calculate how many units of one mapped item can be fulfilled.
	 *
	 * @param int $product_id   Parent/simple product ID.
	 * @param int $variation_id Variation ID or zero.
	 * @return int|null Null when the product is not pooled or allows backorders.
	 */
	public function saleable_quantity( int $product_id, int $variation_id = 0 ): ?int {
		$mapping = $this->mappings->find_for_product( $product_id, $variation_id );
		if ( null === $mapping ) {
			return null;
		}

		$one = $this->calculators->get( $mapping->calculator_type() )->calculate( $mapping, 1 );
		$max = PHP_INT_MAX;
		foreach ( $one as $pool_id => $consumption ) {
			$pool = $this->pools->find( $pool_id );
			if ( null === $pool ) {
				return 0;
			}
			if ( $pool->allows_backorders() ) {
				return null;
			}
			$available = (int) apply_filters( 'laqi_lusm_pool_available_quantity', $pool->quantity()->amount(), $pool_id );
			$max       = min( $max, intdiv( max( 0, $available ), $consumption ) );
		}

		return $max;
	}
}
