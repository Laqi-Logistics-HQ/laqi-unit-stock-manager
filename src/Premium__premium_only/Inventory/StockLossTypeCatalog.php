<?php
/**
 * Paid stock-loss movement types.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Inventory;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Inventory\MovementRegistry;
use LaqiUnitStockManager\Inventory\MovementType;

/** Keeps loss keys and labels consistent across forms, mutations, and reports. */
final class StockLossTypeCatalog {
	/** Registered loss types. @return array<string, string> */
	public function all(): array {
		return array(
			'loss_spillage'    => __( 'Spillage', 'laqi-unit-stock-manager' ),
			'loss_cutting'     => __( 'Cutting or processing loss', 'laqi-unit-stock-manager' ),
			'loss_evaporation' => __( 'Evaporation', 'laqi-unit-stock-manager' ),
			'loss_damage'      => __( 'Damage', 'laqi-unit-stock-manager' ),
			'loss_sample'      => __( 'Samples or internal use', 'laqi-unit-stock-manager' ),
		);
	}

	/** Whether a submitted key is registered.
	 *
	 * @param string $key Type key.
	 * @return bool
	 */
	public function has( string $key ): bool {
		return isset( $this->all()[ $key ] );
	}

	/** Add loss types to the shared movement registry.
	 *
	 * @param MovementRegistry $registry Movement types.
	 * @return void
	 */
	public function register( MovementRegistry $registry ): void {
		foreach ( $this->all() as $key => $label ) {
			$registry->register( new MovementType( $key, $label ) );
		}
	}
}
