<?php
/**
 * Product Bundles and Composite Products stock-demand adapter.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Integrations;

defined( 'ABSPATH' ) || exit;

use WC_Cart;
use WC_Order;
use WC_Order_Item_Product;

/** Keeps container lines out while normal child lines consume stock once. */
final class GroupedProductAdapter {

	/** Register shared demand-inclusion filters. @return void */
	public function register(): void {
		add_filter( 'laqi_lusm_include_cart_item_stock_demand', array( $this, 'include_cart_item' ), 10, 4 );
		add_filter( 'laqi_lusm_include_checkout_item_stock_demand', array( $this, 'include_checkout_item' ), 10, 5 );
		add_filter( 'laqi_lusm_include_order_item_stock_demand', array( $this, 'include_order_item' ), 10, 2 );
	}

	/**
	 * Exclude Product Bundle and Composite container cart lines.
	 *
	 * @param bool    $included      Existing decision.
	 * @param array   $cart_item     Cart item data.
	 * @param string  $cart_item_key Cart item key.
	 * @param WC_Cart $cart          Cart.
	 * @return bool
	 */
	public function include_cart_item( bool $included, array $cart_item, string $cart_item_key, WC_Cart $cart ): bool {
		unset( $cart_item_key, $cart );
		if ( ! $included ) {
			return false;
		}
		if ( function_exists( 'wc_pb_is_bundle_container_cart_item' ) && wc_pb_is_bundle_container_cart_item( $cart_item ) ) {
			return false;
		}
		if ( function_exists( 'wc_cp_is_composite_container_cart_item' ) && wc_cp_is_composite_container_cart_item( $cart_item ) ) {
			return false;
		}

		return ! $this->has_children( $cart_item, 'bundled_items', 'composite_children' );
	}

	/**
	 * Apply the cart decision while checkout order items are being created.
	 *
	 * @param bool                  $included      Existing decision.
	 * @param WC_Order_Item_Product $item          New order item.
	 * @param array                 $values        Source cart item data.
	 * @param string                $cart_item_key Cart item key.
	 * @param WC_Order              $order         Order.
	 * @return bool
	 */
	public function include_checkout_item( bool $included, WC_Order_Item_Product $item, array $values, string $cart_item_key, WC_Order $order ): bool {
		unset( $item, $cart_item_key, $order );
		return $included && ! $this->has_children( $values, 'bundled_items', 'composite_children' );
	}

	/**
	 * Exclude persisted container lines from admin-origin snapshots.
	 *
	 * @param bool                  $included Existing decision.
	 * @param WC_Order_Item_Product $item    Order item.
	 * @return bool
	 */
	public function include_order_item( bool $included, WC_Order_Item_Product $item ): bool {
		if ( ! $included ) {
			return false;
		}
		$bundle_children    = $item->get_meta( '_bundled_items', true );
		$composite_children = $item->get_meta( '_composite_children', true );

		return empty( $bundle_children ) && empty( $composite_children );
	}

	/**
	 * Whether either documented relationship field contains child line keys.
	 *
	 * @param array  $item          Cart item data.
	 * @param string $bundle_key    Product Bundles children key.
	 * @param string $composite_key Composite Products children key.
	 * @return bool
	 */
	private function has_children( array $item, string $bundle_key, string $composite_key ): bool {
		return ! empty( $item[ $bundle_key ] ) || ! empty( $item[ $composite_key ] );
	}
}
