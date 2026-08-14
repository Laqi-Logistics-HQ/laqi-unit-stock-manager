<?php
/**
 * Read-only Stock & Pricing Automation rule fields.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Integrations;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Availability\AvailabilityService;
use LaqiUnitStockManager\Consumption\CalculatorRegistry;
use LaqiUnitStockManager\Premium\Forecasting\ForecastPolicyRepository;
use LaqiUnitStockManager\Premium\Forecasting\StockForecastService;
use LaqiUnitStockManager\Storage\MappingRepository;
use LaqiUnitStockManager\Storage\PoolRepository;

/** Publishes pooled inventory as consumer-neutral read-only rule data. */
final class StockPricingRuleFieldProvider {
	/** Product mappings.
	 *
	 * @var MappingRepository */ private $mappings;
	/** Inventory pools.
	 *
	 * @var PoolRepository */ private $pools;
	/** Consumption calculators.
	 *
	 * @var CalculatorRegistry */ private $calculators;
	/** Filtered availability.
	 *
	 * @var AvailabilityService */ private $availability;
	/** Forecast policies.
	 *
	 * @var ForecastPolicyRepository */ private $forecast_policies;
	/** Stock forecasts.
	 *
	 * @var StockForecastService */ private $forecasts;

	/**
	 * Constructor.
	 *
	 * @param MappingRepository        $mappings          Product mappings.
	 * @param PoolRepository           $pools             Inventory pools.
	 * @param CalculatorRegistry       $calculators       Consumption calculators.
	 * @param AvailabilityService      $availability      Filtered availability.
	 * @param ForecastPolicyRepository $forecast_policies Forecast policies.
	 * @param StockForecastService     $forecasts         Stock forecasts.
	 */
	public function __construct( MappingRepository $mappings, PoolRepository $pools, CalculatorRegistry $calculators, AvailabilityService $availability, ForecastPolicyRepository $forecast_policies, StockForecastService $forecasts ) {
		$this->mappings          = $mappings;
		$this->pools             = $pools;
		$this->calculators       = $calculators;
		$this->availability      = $availability;
		$this->forecast_policies = $forecast_policies;
		$this->forecasts         = $forecasts;
	}

	/** Register the public read-only provider filters. @return void */
	public function register(): void {
		add_filter( 'laqi_lusm_read_only_rule_field_catalog', array( $this, 'catalog' ) );
		add_filter( 'laqi_lusm_read_only_rule_field_values', array( $this, 'values' ), 10, 3 );
	}

	/**
	 * Append stable field definitions.
	 *
	 * @param array<string, array<string, mixed>> $fields Existing field definitions.
	 * @return array<string, array<string, mixed>>
	 */
	public function catalog( array $fields ): array {
		$fields['laqi_lusm_is_mapped']          = array(
			'label'     => __( 'Unit Stock: mapped', 'laqi-unit-stock-manager' ),
			'type'      => 'boolean',
			'read_only' => true,
		);
		$fields['laqi_lusm_saleable_quantity']  = array(
			'label'     => __( 'Unit Stock: saleable product quantity', 'laqi-unit-stock-manager' ),
			'type'      => 'integer',
			'nullable'  => true,
			'read_only' => true,
		);
		$fields['laqi_lusm_minimum_days_cover'] = array(
			'label'     => __( 'Unit Stock: minimum days of cover', 'laqi-unit-stock-manager' ),
			'type'      => 'number',
			'nullable'  => true,
			'read_only' => true,
		);
		$fields['laqi_lusm_pools']              = array(
			'label'       => __( 'Unit Stock: mapped pools', 'laqi-unit-stock-manager' ),
			'type'        => 'collection',
			'read_only'   => true,
			'item_fields' => array(
				'pool_id'             => 'integer',
				'internal_sku'        => 'string',
				'balance_base'        => 'integer',
				'available_base'      => 'integer',
				'consumption_base'    => 'integer',
				'days_cover'          => 'number|null',
				'forecast_state'      => 'string',
				'forecast_confidence' => 'string',
			),
		);
		return $fields;
	}

	/**
	 * Append current rule values for one simple product or variation.
	 *
	 * @param array<string, mixed> $values       Existing values.
	 * @param int                  $product_id   Parent/simple product ID.
	 * @param int                  $variation_id Variation ID or zero.
	 * @return array<string, mixed>
	 */
	public function values( array $values, int $product_id, int $variation_id = 0 ): array {
		$mapping = $this->mappings->find_for_product( $product_id, $variation_id );
		if ( null === $mapping ) {
			return array_merge(
				$values,
				array(
					'laqi_lusm_is_mapped'          => false,
					'laqi_lusm_saleable_quantity'  => null,
					'laqi_lusm_minimum_days_cover' => null,
					'laqi_lusm_pools'              => array(),
				)
			);
		}

		$demand = $this->calculators->get( $mapping->calculator_type() )->calculate( $mapping, 1 );
		$rows   = array();
		$cover  = array();
		foreach ( $demand as $pool_id => $consumption ) {
			$pool = $this->pools->find( (int) $pool_id );
			if ( null === $pool ) {
				continue;
			}
			$forecast   = $this->forecasts->forecast( $pool, $this->forecast_policies->window( $pool->id() ) );
			$days_cover = isset( $forecast['days_cover'] ) ? (float) $forecast['days_cover'] : null;
			if ( null !== $days_cover ) {
				$cover[] = $days_cover;
			}
			$rows[] = array(
				'pool_id'             => $pool->id(),
				'internal_sku'        => $pool->internal_sku(),
				'balance_base'        => $pool->quantity()->amount(),
				'available_base'      => (int) apply_filters( 'laqi_lusm_pool_available_quantity', $pool->quantity()->amount(), $pool->id() ),
				'consumption_base'    => (int) $consumption,
				'days_cover'          => $days_cover,
				'forecast_state'      => (string) $forecast['state'],
				'forecast_confidence' => (string) ( $forecast['confidence'] ?? '' ),
			);
		}

		return array_merge(
			$values,
			array(
				'laqi_lusm_is_mapped'          => true,
				'laqi_lusm_saleable_quantity'  => $this->availability->saleable_quantity( $product_id, $variation_id ),
				'laqi_lusm_minimum_days_cover' => array() === $cover ? null : min( $cover ),
				'laqi_lusm_pools'              => $rows,
			)
		);
	}
}
