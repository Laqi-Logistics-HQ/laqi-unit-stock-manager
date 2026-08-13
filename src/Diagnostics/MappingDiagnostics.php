<?php
/**
 * Product mapping diagnostics.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Diagnostics;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Domain\ProductMapping;

/**
 * Detects configuration conflicts without changing merchant data.
 */
final class MappingDiagnostics {

	/**
	 * Diagnose one mapping.
	 *
	 * @param ProductMapping $mapping Product mapping.
	 * @return string[] Translatable warning messages.
	 */
	public function inspect( ProductMapping $mapping ): array {
		$product = wc_get_product( $mapping->variation_id() > 0 ? $mapping->variation_id() : $mapping->product_id() );
		if ( ! $product ) {
			return array( __( 'Linked product no longer exists.', 'laqi-unit-stock-manager' ) );
		}

		$warnings = array();
		if ( $product->managing_stock() ) {
			$warnings[] = __( 'WooCommerce stock quantity is also enabled. Disable native quantity management for this linked item to avoid conflicting stock sources.', 'laqi-unit-stock-manager' );
		}
		if ( ! $product->is_purchasable() ) {
			$warnings[] = __( 'This linked item is not currently purchasable.', 'laqi-unit-stock-manager' );
		}

		return $warnings;
	}
}
