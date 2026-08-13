<?php
/**
 * WooCommerce purchasable-object resolution.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\WooCommerce;

defined( 'ABSPATH' ) || exit;

use InvalidArgumentException;
use WC_Product_Variation;

/**
 * Resolves a simple product or variation to its stable mapping identity.
 */
final class PurchasableResolver {

	/**
	 * Resolve one WooCommerce product ID.
	 *
	 * @param int $purchasable_id Simple product or variation ID.
	 * @return array{product_id: int, variation_id: int}
	 * @throws InvalidArgumentException When the object cannot be mapped.
	 */
	public function resolve( int $purchasable_id ): array {
		$product = wc_get_product( $purchasable_id );
		if ( $product instanceof WC_Product_Variation && $product->get_parent_id() > 0 ) {
			return array(
				'product_id'   => $product->get_parent_id(),
				'variation_id' => $product->get_id(),
			);
		}
		if ( $product && $product->is_type( 'simple' ) ) {
			return array(
				'product_id'   => $product->get_id(),
				'variation_id' => 0,
			);
		}

		throw new InvalidArgumentException( 'Only simple products and valid variations can be linked.' );
	}
}
