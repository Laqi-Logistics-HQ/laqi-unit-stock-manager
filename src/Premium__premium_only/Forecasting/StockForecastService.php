<?php
/**
 * Paid stock forecasting service.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Forecasting;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Domain\Pool;
use LaqiUnitStockManager\Storage\MovementRepository;

/** Estimates demand and cover without mutating authoritative stock. */
final class StockForecastService {
	/** Movement reads.
	 *
	 * @var MovementRepository
	 */
	private $movements;
	/** Constructor.
	 *
	 * @param MovementRepository $movements Movements.
	 */
	public function __construct( MovementRepository $movements ) {
		$this->movements = $movements; }
	/** Forecast one pool.
	 *
	 * @param Pool     $pool Pool.
	 * @param int      $window_days Window.
	 * @param int|null $now Timestamp.
	 * @return array<string,mixed>
	 */
	public function forecast( Pool $pool, int $window_days, ?int $now = null ): array {
		$now           = null === $now ? time() : $now;
		$summary       = $this->movements->consumption_summary( $pool->id(), $window_days );
		$first         = '' !== $summary['first_at'] ? strtotime( $summary['first_at'] . ' UTC' ) : false;
		$observed_days = false === $first ? 0 : min( $window_days, max( 1, (int) floor( ( $now - $first ) / DAY_IN_SECONDS ) + 1 ) );
		if ( $observed_days < 7 ) {
			return array(
				'state'         => 'insufficient_data',
				'window_days'   => $window_days,
				'observed_days' => $observed_days,
				'demand_days'   => $summary['demand_days'],
				'consumed_base' => $summary['consumed_base'],
			);
		}
		if ( $summary['consumed_base'] <= 0 ) {
			return array(
				'state'         => 'no_demand',
				'window_days'   => $window_days,
				'observed_days' => $observed_days,
				'demand_days'   => 0,
				'consumed_base' => 0,
			);
		}
		if ( $summary['demand_days'] < 3 ) {
			return array(
				'state'         => 'insufficient_data',
				'window_days'   => $window_days,
				'observed_days' => $observed_days,
				'demand_days'   => $summary['demand_days'],
				'consumed_base' => $summary['consumed_base'],
			);
		}
		$daily_average = $summary['consumed_base'] / $observed_days;
		$days_cover    = max( 0, $pool->quantity()->amount() / $daily_average );
		$confidence    = $observed_days >= 28 && $summary['demand_days'] >= 14 ? 'high' : ( $summary['demand_days'] >= 7 ? 'medium' : 'low' );
		return array(
			'state'              => 'forecast',
			'confidence'         => $confidence,
			'window_days'        => $window_days,
			'observed_days'      => $observed_days,
			'demand_days'        => $summary['demand_days'],
			'consumed_base'      => $summary['consumed_base'],
			'daily_average_base' => $daily_average,
			'days_cover'         => $days_cover,
			'stockout_at'        => $now + (int) round( $days_cover * DAY_IN_SECONDS ),
		);
	}
}
