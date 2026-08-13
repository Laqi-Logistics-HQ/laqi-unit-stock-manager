<?php
/**
 * Paid reorder suggestion calculator.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Replenishment;

defined( 'ABSPATH' ) || exit;

use InvalidArgumentException;
use LaqiUnitStockManager\Premium\Forecasting\ForecastPolicyRepository;
use LaqiUnitStockManager\Premium\Forecasting\StockForecastService;
use LaqiUnitStockManager\Premium\Receiving\SupplierRepository;
use LaqiUnitStockManager\Storage\PoolRepository;

/** Calculates explainable, non-mutating pack recommendations. */
final class ReorderSuggestionService {
	/** Pools. @var PoolRepository
	 *
	 * @var PoolRepository
	 */ private $pools;
	/** Policies. @var ReorderPolicyRepository
	 *
	 * @var ReorderPolicyRepository
	 */ private $policies;
	/** Forecast policies. @var ForecastPolicyRepository
	 *
	 * @var ForecastPolicyRepository
	 */ private $forecast_policies;
	/** Forecasts. @var StockForecastService
	 *
	 * @var StockForecastService
	 */ private $forecasts;
	/** Suppliers. @var SupplierRepository
	 *
	 * @var SupplierRepository
	 */ private $suppliers;
	/** Constructor.
	 *
	 * @param PoolRepository           $pools Pools.
	 * @param ReorderPolicyRepository  $policies Policies.
	 * @param ForecastPolicyRepository $forecast_policies Forecast policies.
	 * @param StockForecastService     $forecasts Forecasts.
	 * @param SupplierRepository       $suppliers Suppliers.
	 */
	public function __construct( PoolRepository $pools, ReorderPolicyRepository $policies, ForecastPolicyRepository $forecast_policies, StockForecastService $forecasts, SupplierRepository $suppliers ) {
		$this->pools             = $pools;
		$this->policies          = $policies;
		$this->forecast_policies = $forecast_policies;
		$this->forecasts         = $forecasts;
		$this->suppliers         = $suppliers; }
	/** Suggest one replenishment.
	 *
	 * @param int $pool_id Pool ID.
	 * @return array<string,mixed>
	 * @throws InvalidArgumentException When policy configuration is invalid.
	 */
	public function suggest( int $pool_id ): array {
		$pool   = $this->pools->find( $pool_id );
		$policy = $this->policies->find( $pool_id );
		if ( null === $pool || null === $policy ) {
			throw new InvalidArgumentException( 'The pool has no reorder policy.' ); }
		$pack = $this->suppliers->pack( $policy['preferred_pack_id'] );
		if ( null === $pack || (int) $pack['pool_id'] !== $pool_id ) {
			throw new InvalidArgumentException( 'The preferred supplier pack is unavailable.' ); }
		$forecast = $this->forecasts->forecast( $pool, $this->forecast_policies->window( $pool_id ) );
		$incoming = $this->suppliers->incoming_quantity( $pool_id );
		$result   = array(
			'pool'              => $pool,
			'pack'              => $pack,
			'forecast_state'    => $forecast['state'],
			'incoming_base'     => $incoming,
			'safety_stock_base' => $policy['safety_stock_base'],
			'pack_count'        => 0,
			'suggested_base'    => 0,
			'shortfall_base'    => 0,
			'projected_base'    => $pool->quantity()->amount() + $incoming,
		);
		if ( 'forecast' !== $forecast['state'] ) {
			return $result; }
		$lead_demand                = (int) ceil( $forecast['daily_average_base'] * (int) $pack['lead_time_days'] );
		$target                     = $lead_demand + $policy['safety_stock_base'];
		$shortfall                  = max( 0, $target - $result['projected_base'] );
		$pack_count                 = $shortfall > 0 ? (int) ceil( $shortfall / (int) $pack['quantity_base'] ) : 0;
		$result['lead_demand_base'] = $lead_demand;
		$result['target_base']      = $target;
		$result['shortfall_base']   = $shortfall;
		$result['pack_count']       = $pack_count;
		$result['suggested_base']   = $pack_count * (int) $pack['quantity_base'];
		$result['projected_base']  += $result['suggested_base'];
		$result['confidence']       = $forecast['confidence'];
		return $result;
	}
}
