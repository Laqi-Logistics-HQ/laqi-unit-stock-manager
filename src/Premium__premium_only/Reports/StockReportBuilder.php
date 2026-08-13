<?php
/**
 * Paid stock report builder.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Reports;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Premium\Alerts\LowStockPolicyRepository;
use LaqiUnitStockManager\Premium\Forecasting\ForecastPolicyRepository;
use LaqiUnitStockManager\Premium\Forecasting\StockForecastService;
use LaqiUnitStockManager\Presentation\QuantityFormatter;
use LaqiUnitStockManager\Storage\PoolRepository;
use InvalidArgumentException;

/** Produces one stable, spreadsheet-safe snapshot schema. */
final class StockReportBuilder {
	/** Pool storage. @var PoolRepository
	 *
	 * @var PoolRepository
	 */ private $pools;
	/** Quantity presentation.
	 *
	 * @var QuantityFormatter
	 */ private $formatter;
	/** Alert policies.
	 *
	 * @var LowStockPolicyRepository
	 */ private $alerts;
	/** Forecast policies.
	 *
	 * @var ForecastPolicyRepository
	 */ private $forecast_policies;
	/** Forecast calculator.
	 *
	 * @var StockForecastService
	 */ private $forecasts;

	/** Constructor.
	 *
	 * @param PoolRepository           $pools Pool storage.
	 * @param QuantityFormatter        $formatter Quantity presentation.
	 * @param LowStockPolicyRepository $alerts Alert policies.
	 * @param ForecastPolicyRepository $forecast_policies Forecast policies.
	 * @param StockForecastService     $forecasts Forecast calculator.
	 */
	public function __construct( PoolRepository $pools, QuantityFormatter $formatter, LowStockPolicyRepository $alerts, ForecastPolicyRepository $forecast_policies, StockForecastService $forecasts ) {
		$this->pools             = $pools;
		$this->formatter         = $formatter;
		$this->alerts            = $alerts;
		$this->forecast_policies = $forecast_policies;
		$this->forecasts         = $forecasts;
	}

	/** Column headings. @return array<int,string> */
	public function headers(): array {
		return array(
			__( 'Pool ID', 'laqi-unit-stock-manager' ),
			__( 'Inventory pool', 'laqi-unit-stock-manager' ),
			__( 'Internal SKU', 'laqi-unit-stock-manager' ),
			__( 'On hand', 'laqi-unit-stock-manager' ),
			__( 'Unit', 'laqi-unit-stock-manager' ),
			__( 'Alert status', 'laqi-unit-stock-manager' ),
			__( 'Days remaining', 'laqi-unit-stock-manager' ),
			__( 'Estimated stock-out', 'laqi-unit-stock-manager' ),
			__( 'Forecast confidence', 'laqi-unit-stock-manager' ),
		);
	}

	/** Snapshot rows. @return array<int,array<int,string|int>> */
	public function rows(): array {
		$rows   = array();
		$offset = 0;
		do {
			$pools = $this->pools->search( '', 500, $offset );
			foreach ( $pools as $pool ) {
				$alert    = $this->alerts->find( $pool->id() );
				$forecast = $this->forecasts->forecast( $pool, $this->forecast_policies->window( $pool->id() ) );
				$rows[]   = array(
					$pool->id(),
					self::safe( $pool->name() ),
					self::safe( $pool->internal_sku() ),
					self::safe( $this->display_quantity( $pool->quantity(), $pool->display_unit() ) ),
					$pool->display_unit(),
					null === $alert ? 'not configured' : ( isset( $alert['severity'] ) ? $alert['severity'] : ( ! empty( $alert['is_low'] ) ? 'warning' : 'healthy' ) ),
					'forecast' === $forecast['state'] ? number_format( $forecast['days_cover'], 1, '.', '' ) : $forecast['state'],
					'forecast' === $forecast['state'] ? gmdate( 'Y-m-d', $forecast['stockout_at'] ) : '',
					isset( $forecast['confidence'] ) ? $forecast['confidence'] : '',
				);
			}
			$batch_size = count( $pools );
			$offset    += $batch_size;
		} while ( 500 === $batch_size );
		return $rows;
	}

	/** Prevent spreadsheet formula execution.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	public static function safe( string $value ): string {
		return preg_match( '/^[=+\-@]/', $value ) ? "'" . $value : $value;
	}

	/** Format a balance without allowing one stale unit definition to abort the report.
	 *
	 * @param \LaqiUnitStockManager\Domain\Quantity $quantity Quantity.
	 * @param string                                $unit Unit key.
	 * @return string
	 */
	private function display_quantity( \LaqiUnitStockManager\Domain\Quantity $quantity, string $unit ): string {
		try {
			return $this->formatter->decimal( $quantity, $unit );
		} catch ( InvalidArgumentException $exception ) {
			return __( 'Unavailable', 'laqi-unit-stock-manager' );
		}
	}
}
