<?php
/**
 * Paid stock scenario planner.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Planning;

defined( 'ABSPATH' ) || exit;

use InvalidArgumentException;
use LaqiUnitStockManager\Domain\ProductMapping;
use LaqiUnitStockManager\Premium\Forecasting\ForecastPolicyRepository;
use LaqiUnitStockManager\Premium\Forecasting\StockForecastService;
use LaqiUnitStockManager\Storage\MappingRepository;
use LaqiUnitStockManager\Storage\PoolRepository;
use OverflowException;

/** Calculates read-only promotion and allocation-mix scenarios. */
final class StockScenarioPlanner {
	/** Pool storage.
	 *
	 * @var PoolRepository
	 */
	private $pools;
	/** Mapping storage.
	 *
	 * @var MappingRepository
	 */
	private $mappings;
	/** Forecast settings.
	 *
	 * @var ForecastPolicyRepository
	 */
	private $forecast_policies;
	/** Forecast calculator.
	 *
	 * @var StockForecastService
	 */
	private $forecasts;

	/** Constructor.
	 *
	 * @param PoolRepository           $pools Pool storage.
	 * @param MappingRepository        $mappings Mapping storage.
	 * @param ForecastPolicyRepository $forecast_policies Forecast settings.
	 * @param StockForecastService     $forecasts Forecast calculator.
	 */
	public function __construct( PoolRepository $pools, MappingRepository $mappings, ForecastPolicyRepository $forecast_policies, StockForecastService $forecasts ) {
		$this->pools             = $pools;
		$this->mappings          = $mappings;
		$this->forecast_policies = $forecast_policies;
		$this->forecasts         = $forecasts;
	}

	/** Active mappings consuming one pool.
	 *
	 * @param int $pool_id Pool ID.
	 * @return ProductMapping[]
	 */
	public function mappings_for_pool( int $pool_id ): array {
		$matches = array();
		$offset  = 0;
		do {
			$batch = $this->mappings->active( 500, $offset );
			foreach ( $batch as $mapping ) {
				if ( null !== $this->consumption_for_pool( $mapping, $pool_id ) ) {
					$matches[] = $mapping;
				}
			}
			$batch_size = count( $batch );
			$offset    += $batch_size;
		} while ( 500 === $batch_size );
		return $matches;
	}

	/** Calculate a scenario without writing stock or policy state.
	 *
	 * @param int            $pool_id Pool ID.
	 * @param array<int,int> $baseline_units Expected units by mapping ID.
	 * @param int            $uplift_percent Promotional uplift percentage.
	 * @return array<string,mixed>
	 * @throws InvalidArgumentException When the pool or allocation is invalid.
	 * @throws OverflowException When calculated demand exceeds integer storage.
	 */
	public function calculate( int $pool_id, array $baseline_units, int $uplift_percent = 0 ): array {
		$pool = $this->pools->find( $pool_id );
		if ( null === $pool || $uplift_percent < 0 || $uplift_percent > 1000 ) {
			throw new InvalidArgumentException( 'The stock scenario is invalid.' );
		}
		$lines        = array();
		$total_demand = 0;
		foreach ( $baseline_units as $mapping_id => $baseline ) {
			$mapping = $this->mappings->find_active( (int) $mapping_id );
			if ( null === $mapping || $baseline < 0 || $baseline > 1000000 ) {
				throw new InvalidArgumentException( 'The scenario contains an invalid allocation.' );
			}
			$consumption = $this->consumption_for_pool( $mapping, $pool_id );
			if ( null === $consumption ) {
				throw new InvalidArgumentException( 'The scenario mapping does not consume this pool.' );
			}
			$projected = (int) ceil( $baseline * ( 100 + $uplift_percent ) / 100 );
			if ( $projected > 0 && $consumption > intdiv( PHP_INT_MAX, $projected ) ) {
				throw new OverflowException( 'The scenario demand is too large.' );
			}
			$demand = $projected * $consumption;
			if ( $demand > PHP_INT_MAX - $total_demand ) {
				throw new OverflowException( 'The scenario demand is too large.' );
			}
			$total_demand += $demand;
			$lines[]       = array(
				'mapping'          => $mapping,
				'baseline_units'   => $baseline,
				'projected_units'  => $projected,
				'consumption_base' => $consumption,
				'demand_base'      => $demand,
			);
		}
		$on_hand   = $pool->quantity()->amount();
		$remaining = $on_hand - $total_demand;
		$forecast  = $this->forecasts->forecast( $pool, $this->forecast_policies->window( $pool_id ) );
		$days      = null;
		if ( 'forecast' === $forecast['state'] && $remaining > 0 && $on_hand > 0 ) {
			$days = $forecast['days_cover'] * ( $remaining / $on_hand );
		}
		return array(
			'pool'                 => $pool,
			'lines'                => $lines,
			'uplift_percent'       => $uplift_percent,
			'demand_base'          => $total_demand,
			'remaining_base'       => $remaining,
			'enough_stock'         => $remaining >= 0,
			'projected_days_cover' => $days,
			'forecast_state'       => $forecast['state'],
		);
	}

	/** Consumption of one mapping from one pool.
	 *
	 * @param ProductMapping $mapping Mapping.
	 * @param int            $pool_id Pool ID.
	 * @return int|null
	 */
	public function consumption_for_pool( ProductMapping $mapping, int $pool_id ): ?int {
		foreach ( $mapping->components() as $component ) {
			if ( $component->pool_id() === $pool_id ) {
				return $component->consumption();
			}
		}
		return null;
	}
}
