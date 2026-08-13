<?php
/**
 * Supply projection composition.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Supply;

defined( 'ABSPATH' ) || exit;

// Compact composition methods remain explicit through types and names.
// phpcs:disable Generic.Commenting.DocComment.MissingShort, Squiz.Commenting.FunctionComment

/** Combines independently owned supply states into availability projections. */
final class SupplyProjectionService {
	/** @var StockHoldRepository */
	private $holds;

	/** @var SafetyStockPolicyRepository */
	private $safety_stock;

	/** Constructor. */
	public function __construct( StockHoldRepository $holds, SafetyStockPolicyRepository $safety_stock ) {
		$this->holds        = $holds;
		$this->safety_stock = $safety_stock;
	}

	/**
	 * Project all pools with an operational state or safety policy.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function rows(): array {
		$rows = array();
		foreach ( $this->holds->summary() as $row ) {
			$row['safety_stock_base']      = $this->safety_stock->quantity( (int) $row['pool_id'] );
			$rows[ (int) $row['pool_id'] ] = $this->project( $row );
		}

		foreach ( $this->safety_stock->configured() as $row ) {
			$id = (int) $row['pool_id'];
			if ( isset( $rows[ $id ] ) ) {
				continue;
			}
			$row        += array(
				'reserved_base'    => 0,
				'incoming_base'    => 0,
				'quarantined_base' => 0,
				'damaged_base'     => 0,
			);
			$rows[ $id ] = $this->project( $row );
		}

		return array_values( $rows );
	}

	/** @param array<string,mixed> $row Supply state. @return array<string,mixed> */
	private function project( array $row ): array {
		$unavailable           = (int) $row['reserved_base'] + (int) $row['quarantined_base'] + (int) $row['damaged_base'] + (int) $row['safety_stock_base'];
		$row['available_base'] = max( 0, (int) $row['quantity_base'] - $unavailable );
		$row['projected_base'] = $row['available_base'] + (int) $row['incoming_base'];
		return $row;
	}
}
