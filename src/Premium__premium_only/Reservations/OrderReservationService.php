<?php
/**
 * WooCommerce order reservation lifecycle.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Reservations;

defined( 'ABSPATH' ) || exit;

// Compact lifecycle methods remain explicit through hook names and types.
// phpcs:disable Generic.Commenting.DocComment.MissingShort, Squiz.Commenting.FunctionComment

use LaqiUnitStockManager\WooCommerce\OrderItemSnapshotter;
use WC_Order;

/** Reserves checkout snapshots and converts or releases them idempotently. */
final class OrderReservationService {
	const CRON_HOOK = 'laqi_lusm_expire_reservations';
	/** @var ReservationRepository */ private $reservations;
	/** @param ReservationRepository $reservations Reservations. */ public function __construct( ReservationRepository $reservations ) {
		$this->reservations = $reservations; }
	/** Register lifecycle and availability hooks. */
	public function register(): void {
		add_action( 'woocommerce_checkout_order_created', array( $this, 'reserve_order' ), 30 );
		add_action( 'woocommerce_reduce_order_stock', array( $this, 'convert_order' ), 30 );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'release_order' ), 10 );
		add_action( 'woocommerce_order_status_failed', array( $this, 'release_order' ), 10 );
		add_filter( 'laqi_lusm_pool_available_quantity', array( $this, 'available_quantity' ), 10, 2 );
		add_action( self::CRON_HOOK, array( $this, 'expire' ) );
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::CRON_HOOK ); } }
	/** Reserve checkout order demand. */ public function reserve_order( WC_Order $order ): void {
		$minutes = max( 1, (int) get_option( 'woocommerce_hold_stock_minutes', 60 ) );
		$this->reservations->reserve( $order->get_id(), $this->demand( $order ), gmdate( 'Y-m-d H:i:s', time() + ( $minutes * MINUTE_IN_SECONDS ) ) ); }
	/** Convert after authoritative stock reduction. */ public function convert_order( WC_Order $order ): void {
		$this->reservations->transition( $order->get_id(), 'converted' ); }
	/** Release failed/cancelled order. @param int|WC_Order $order Order. */ public function release_order( $order ): void {
		$this->reservations->transition( $order instanceof WC_Order ? $order->get_id() : absint( $order ), 'released' ); }
	/** Subtract active reservations. */ public function available_quantity( int $on_hand, int $pool_id ): int {
		return max( 0, $on_hand - $this->reservations->reserved_quantity( $pool_id ) ); }
	/** Expire elapsed rows. */ public function expire(): void {
		$this->reservations->expire(); }
	/** Unschedule expiry. */ public function unschedule(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK ); }
	/** Aggregate immutable snapshots. @return array<int,int> */ private function demand( WC_Order $order ): array {
		$demand = array();
		foreach ( $order->get_items() as $item ) {
			$snapshot = $item->get_meta( OrderItemSnapshotter::META_KEY, true );
			if ( ! is_array( $snapshot ) || empty( $snapshot['pool_demand'] ) ) {
				continue;
			} foreach ( $snapshot['pool_demand'] as $pool_id => $quantity ) {
				$demand[ (int) $pool_id ] = ( $demand[ (int) $pool_id ] ?? 0 ) + (int) $quantity;
			}
		} return $demand; }
}
