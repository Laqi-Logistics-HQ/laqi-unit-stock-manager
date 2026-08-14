<?php
/**
 * Read-only stock anomaly detection.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Anomalies;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Diagnostics\MappingDiagnostics;
use LaqiUnitStockManager\Storage\MappingRepository;
use LaqiUnitStockManager\Storage\MovementRepository;

/** Detects suspicious ledger and configuration states without mutating stock. */
final class StockAnomalyDetector {
	/**
	 * Movement reads.
	 *
	 * @var MovementRepository
	 */
	private $movements;
	/**
	 * Mapping reads.
	 *
	 * @var MappingRepository
	 */
	private $mappings;
	/**
	 * Mapping diagnostics.
	 *
	 * @var MappingDiagnostics
	 */
	private $diagnostics;

	/**
	 * Constructor.
	 *
	 * @param MovementRepository $movements   Movement reads.
	 * @param MappingRepository  $mappings    Mapping reads.
	 * @param MappingDiagnostics $diagnostics Mapping diagnostics.
	 */
	public function __construct( MovementRepository $movements, MappingRepository $mappings, MappingDiagnostics $diagnostics ) {
		$this->movements   = $movements;
		$this->mappings    = $mappings;
		$this->diagnostics = $diagnostics;
	}

	/**
	 * Inspect recent ledger activity and all active mappings.
	 *
	 * @param int $movement_limit Maximum recent movements to inspect.
	 * @return array<int, array<string, mixed>>
	 */
	public function detect( int $movement_limit = 500 ): array {
		$rows      = $this->movements->recent( max( 1, min( 500, $movement_limit ) ) );
		$anomalies = array_merge( $this->movement_anomalies( $rows ), $this->mapping_anomalies() );

		usort(
			$anomalies,
			static function ( array $left, array $right ): int {
				$severity = array(
					'critical' => 0,
					'warning'  => 1,
					'notice'   => 2,
				);
				$compare  = ( $severity[ $left['severity'] ] ?? 3 ) <=> ( $severity[ $right['severity'] ] ?? 3 );
				return 0 !== $compare ? $compare : strcmp( (string) $right['created_at'], (string) $left['created_at'] );
			}
		);

		/**
		 * Filters detected read-only stock anomalies.
		 *
		 * @param array<int, array<string, mixed>> $anomalies Detected anomalies.
		 */
		return apply_filters( 'laqi_lusm_stock_anomalies', $anomalies );
	}

	/**
	 * Detect movement anomalies.
	 *
	 * @param array<int, array<string, mixed>> $rows Recent movements.
	 * @return array<int, array<string, mixed>>
	 */
	private function movement_anomalies( array $rows ): array {
		$anomalies        = array();
		$negative_runs    = array();
		$order_totals     = array();
		$adjustment_types = array( 'manual_add', 'manual_subtract', 'stock_count', 'external_add', 'external_subtract' );
		$ratio            = (float) apply_filters( 'laqi_lusm_large_adjustment_ratio', 0.5 );
		$ratio            = max( 0.01, min( 1.0, $ratio ) );
		$percentage       = (int) round( $ratio * 100 );

		foreach ( $rows as $row ) {
			$pool_id = (int) $row['pool_id'];
			$delta   = (int) $row['delta_base'];
			$balance = (int) $row['balance_base'];
			$type    = (string) $row['type'];
			$prior   = (float) $balance - (float) $delta;

			if ( in_array( $type, $adjustment_types, true ) && 0 !== $delta && abs( (float) $delta ) >= max( 1.0, abs( $prior ) * $ratio ) ) {
				/* translators: %d: configured percentage of the preceding balance. */
				$detail      = sprintf( __( 'This adjustment changed at least %d%% of the preceding pool balance.', 'laqi-unit-stock-manager' ), $percentage );
				$anomalies[] = $this->movement_anomaly( 'large_adjustment', 'warning', __( 'Large stock adjustment', 'laqi-unit-stock-manager' ), $detail, $row );
			}

			if ( $balance < 0 ) {
				$negative_runs[ $pool_id ] = ( $negative_runs[ $pool_id ] ?? 0 ) + 1;
			} elseif ( ! isset( $negative_runs[ $pool_id ] ) ) {
				$negative_runs[ $pool_id ] = 0;
			}

			if ( in_array( $type, array( 'order_reduction', 'order_edit' ), true ) && $delta < 0 && ( 'order' !== $row['source_type'] || (int) $row['source_id'] < 1 ) ) {
				$anomalies[] = $this->movement_anomaly( 'unexpected_consumption', 'critical', __( 'Unexpected order consumption', 'laqi-unit-stock-manager' ), __( 'An order consumption movement is missing its order source.', 'laqi-unit-stock-manager' ), $row );
			}

			if ( 'order' === $row['source_type'] && (int) $row['source_id'] > 0 ) {
				$key = $pool_id . ':' . (int) $row['source_id'];
				if ( ! isset( $order_totals[ $key ] ) ) {
					$order_totals[ $key ] = array(
						'reduced'  => 0.0,
						'restored' => 0.0,
						'row'      => $row,
					);
				}
				if ( in_array( $type, array( 'order_reduction', 'order_edit' ), true ) && $delta < 0 ) {
					$order_totals[ $key ]['reduced'] += abs( (float) $delta );
				}
				if ( in_array( $type, array( 'order_restore', 'refund_restore' ), true ) && $delta > 0 ) {
					$order_totals[ $key ]['restored'] += (float) $delta;
					$order_totals[ $key ]['row']       = $row;
				}
			}
		}

		foreach ( $negative_runs as $pool_id => $count ) {
			if ( $count >= 3 ) {
				$row         = $this->first_pool_row( $rows, (int) $pool_id );
				$anomalies[] = $this->movement_anomaly( 'repeated_negative_balance', 'critical', __( 'Repeated negative balance', 'laqi-unit-stock-manager' ), __( 'At least three recent ledger entries for this pool ended below zero.', 'laqi-unit-stock-manager' ), $row );
			}
		}

		foreach ( $order_totals as $totals ) {
			if ( $totals['reduced'] > 0 && $totals['restored'] > $totals['reduced'] ) {
				$anomalies[] = $this->movement_anomaly( 'excess_restoration', 'critical', __( 'Possible duplicate restoration', 'laqi-unit-stock-manager' ), __( 'Recent restorations for this order and pool exceed its recorded reductions.', 'laqi-unit-stock-manager' ), $totals['row'] );
			}
		}

		return $anomalies;
	}

	/**
	 * Detect active mapping anomalies.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function mapping_anomalies(): array {
		$anomalies = array();
		$offset    = 0;
		do {
			$mappings = $this->mappings->active( 500, $offset );
			foreach ( $mappings as $mapping ) {
				foreach ( $this->diagnostics->inspect( $mapping ) as $warning ) {
					$anomalies[] = array(
						'key'         => 'invalid_mapping',
						'severity'    => 'warning',
						'title'       => __( 'Invalid or conflicting mapping', 'laqi-unit-stock-manager' ),
						'detail'      => $warning,
						'pool_name'   => '',
						'source'      => sprintf( 'mapping #%d', $mapping->id() ),
						'created_at'  => '',
						'movement_id' => 0,
					);
				}
			}
			$offset       += 500;
			$mapping_count = count( $mappings );
		} while ( 500 === $mapping_count );
		return $anomalies;
	}

	/**
	 * Build a normalized movement finding.
	 *
	 * @param string               $key      Stable anomaly key.
	 * @param string               $severity Finding severity.
	 * @param string               $title    Finding title.
	 * @param string               $detail   Finding detail.
	 * @param array<string, mixed> $row      Movement row.
	 * @return array<string, mixed>
	 */
	private function movement_anomaly( string $key, string $severity, string $title, string $detail, array $row ): array {
		$source = (string) $row['source_type'];
		if ( (int) $row['source_id'] > 0 ) {
			$source .= ' #' . (int) $row['source_id'];
		}
		return array(
			'key'         => $key,
			'severity'    => $severity,
			'title'       => $title,
			'detail'      => $detail,
			'pool_name'   => (string) $row['pool_name'],
			'source'      => $source,
			'created_at'  => (string) $row['created_at'],
			'movement_id' => (int) $row['id'],
		);
	}

	/**
	 * Find the latest inspected row for a pool.
	 *
	 * @param array<int, array<string, mixed>> $rows    Movement rows.
	 * @param int                              $pool_id Pool ID.
	 * @return array<string, mixed>
	 */
	private function first_pool_row( array $rows, int $pool_id ): array {
		foreach ( $rows as $row ) {
			if ( (int) $row['pool_id'] === $pool_id ) {
				return $row;
			}
		}
		return array(
			'id'          => 0,
			'pool_id'     => $pool_id,
			'pool_name'   => '',
			'source_type' => '',
			'source_id'   => 0,
			'created_at'  => '',
		);
	}
}
