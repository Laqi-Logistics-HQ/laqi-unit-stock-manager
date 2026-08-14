<?php
/**
 * WooCommerce REST and mobile order compatibility.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Integrations;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Premium\Reservations\OrderReservationService;
use LaqiUnitStockManager\WooCommerce\OrderItemSnapshotter;
use LaqiUnitStockManager\WooCommerce\OrderStockLifecycle;
use WC_Order;
use WC_Order_Item_Product;
use WP_REST_Request;

/** Prepares orders created by WooCommerce REST clients such as Woo Mobile. */
final class MobileOrderAdapter {
	const PREPARED_META = '_laqi_lusm_mobile_order_prepared';

	/** Order item snapshot service.
	 *
	 * @var OrderItemSnapshotter
	 */
	private $snapshots;

	/** Order reservation service.
	 *
	 * @var OrderReservationService
	 */
	private $reservations;

	/**
	 * Constructor.
	 *
	 * @param OrderItemSnapshotter    $snapshots    Snapshots.
	 * @param OrderReservationService $reservations Reservations.
	 */
	public function __construct( OrderItemSnapshotter $snapshots, OrderReservationService $reservations ) {
		$this->snapshots    = $snapshots;
		$this->reservations = $reservations;
	}

	/** Register the HPOS-safe REST object hook. @return void */
	public function register(): void {
		add_action( 'woocommerce_rest_insert_shop_order_object', array( $this, 'prepare' ), 20, 3 );
	}

	/**
	 * Prepare a newly created API order exactly once.
	 *
	 * @param WC_Order        $order    Order.
	 * @param WP_REST_Request $request  Request.
	 * @param bool            $creating Whether the request created the order.
	 * @return void
	 */
	public function prepare( WC_Order $order, WP_REST_Request $request, bool $creating ): void {
		unset( $request );
		if ( ! $creating || 'yes' === $order->get_meta( self::PREPARED_META, true ) ) {
			return;
		}
		if ( 'reduced' !== $order->get_meta( OrderStockLifecycle::STATE_META, true ) ) {
			foreach ( $order->get_items() as $item ) {
				if ( ! $item instanceof WC_Order_Item_Product ) {
					continue;
				}
				$item->delete_meta_data( OrderItemSnapshotter::META_KEY );
				$this->snapshots->snapshot_admin_demand( $item );
				$item->save();
			}
			$this->reservations->reserve_order( $order );
		}
		$order->update_meta_data( self::PREPARED_META, 'yes' );
		$order->save();
	}
}
