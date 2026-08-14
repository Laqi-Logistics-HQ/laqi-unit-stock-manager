<?php
/**
 * WooCommerce Subscriptions renewal-order stock adapter.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Integrations;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Premium\Reservations\OrderReservationService;
use LaqiUnitStockManager\WooCommerce\OrderItemSnapshotter;
use WC_Order;
use WC_Order_Item_Product;

/** Replaces copied snapshots with one current, reserved renewal snapshot. */
final class SubscriptionRenewalAdapter {
	/** Preparation marker on the renewal order. */
	const PREPARED_META = '_laqi_lusm_subscription_renewal_prepared';

	/** Renewal order snapshots.
	 *
	 * @var OrderItemSnapshotter
	 */
	private $snapshots;

	/** Renewal order reservations.
	 *
	 * @var OrderReservationService
	 */
	private $reservations;

	/**
	 * Constructor.
	 *
	 * @param OrderItemSnapshotter    $snapshots    Order-item snapshots.
	 * @param OrderReservationService $reservations Order reservations.
	 */
	public function __construct( OrderItemSnapshotter $snapshots, OrderReservationService $reservations ) {
		$this->snapshots    = $snapshots;
		$this->reservations = $reservations;
	}

	/** Register the renewal-order creation filter. @return void */
	public function register(): void {
		add_filter( 'wcs_renewal_order_created', array( $this, 'prepare' ), 20, 2 );
	}

	/**
	 * Snapshot current mappings and reserve their exact normalized demand once.
	 *
	 * Subscriptions copies arbitrary line-item metadata to renewal orders. A
	 * pooled-stock snapshot belongs to one order event, so an inherited snapshot
	 * must never be allowed to select an old mapping for a future renewal.
	 *
	 * @param WC_Order $renewal_order Renewal order.
	 * @param mixed    $subscription Related subscription object.
	 * @return WC_Order
	 * @throws \Throwable When current demand cannot be snapshotted or reserved.
	 */
	public function prepare( WC_Order $renewal_order, $subscription ): WC_Order {
		unset( $subscription );
		if ( 'yes' === $renewal_order->get_meta( self::PREPARED_META, true ) ) {
			return $renewal_order;
		}

		foreach ( $renewal_order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$item->delete_meta_data( OrderItemSnapshotter::META_KEY );
			$this->snapshots->snapshot_admin_demand( $item );
			$item->save();
		}

		$this->reservations->reserve_order( $renewal_order );
		$renewal_order->update_meta_data( self::PREPARED_META, 'yes' );
		$renewal_order->save_meta_data();

		return $renewal_order;
	}
}
