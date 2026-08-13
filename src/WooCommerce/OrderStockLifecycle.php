<?php
/**
 * Pooled order stock lifecycle.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\WooCommerce;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Inventory\StockMutationService;
use WC_Order;

/**
 * Reduces and restores exact snapshot demand through atomic batches.
 */
final class OrderStockLifecycle {

	/** Order lifecycle state metadata key. */
	private const STATE_META = '_laqi_lusm_pool_stock_state';

	/** Order lifecycle cycle metadata key. */
	private const CYCLE_META = '_laqi_lusm_pool_stock_cycle';

	/** Restocked line-item quantity metadata key. */
	private const RESTOCKED_QUANTITY_META = '_laqi_lusm_restocked_quantity';

	/**
	 * Stock mutation service.
	 *
	 * @var StockMutationService
	 */
	private $mutations;

	/**
	 * Constructor.
	 *
	 * @param StockMutationService $mutations Stock mutation service.
	 */
	public function __construct( StockMutationService $mutations ) {
		$this->mutations = $mutations;
	}

	/**
	 * Register WooCommerce lifecycle hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'woocommerce_reduce_order_stock', array( $this, 'reduce' ), 20 );
		add_action( 'woocommerce_restore_order_stock', array( $this, 'restore' ), 20 );
		add_filter( 'woocommerce_can_restock_refunded_items', array( $this, 'restock_refund' ), 20, 3 );
	}

	/**
	 * Reduce pooled stock for an order.
	 *
	 * @param WC_Order $order Order being reduced.
	 * @return void
	 */
	public function reduce( WC_Order $order ): void {
		$cycle = max( 1, (int) $order->get_meta( self::CYCLE_META, true ) );
		if ( 'restored' === $order->get_meta( self::STATE_META, true ) ) {
			++$cycle;
		}

		$this->apply_demand( $order, $this->total_demand( $order ), -1, 'order_reduction', 'reduce-' . $cycle );
		$order->update_meta_data( self::CYCLE_META, $cycle );
		$order->update_meta_data( self::STATE_META, 'reduced' );
		$order->save_meta_data();
	}

	/**
	 * Restore pooled stock for an order.
	 *
	 * @param WC_Order $order Order being restored.
	 * @return void
	 */
	public function restore( WC_Order $order ): void {
		$cycle = max( 1, (int) $order->get_meta( self::CYCLE_META, true ) );
		$this->apply_demand( $order, $this->total_demand( $order, true ), 1, 'order_restore', 'restore-' . $cycle );
		$order->update_meta_data( self::CYCLE_META, $cycle );
		$order->update_meta_data( self::STATE_META, 'restored' );
		$order->save_meta_data();
	}

	/**
	 * Restock explicitly selected refunded quantities for pooled items.
	 *
	 * @param bool     $allowed    Existing WooCommerce decision.
	 * @param WC_Order $order      Refunded order.
	 * @param array    $line_items Requested refunded line quantities.
	 * @return bool
	 */
	public function restock_refund( bool $allowed, WC_Order $order, array $line_items ): bool {
		if ( ! $allowed ) {
			return false;
		}
		if ( 'restored' === $order->get_meta( self::STATE_META, true ) ) {
			return true;
		}

		$demand  = array();
		$items   = array();
		$targets = array();
		foreach ( $order->get_items() as $item_id => $item ) {
			if ( ! isset( $line_items[ $item_id ]['qty'] ) ) {
				continue;
			}
			$snapshot = $item->get_meta( OrderItemSnapshotter::META_KEY, true );
			if ( ! is_array( $snapshot ) || empty( $snapshot['item_quantity'] ) || empty( $snapshot['pool_demand'] ) ) {
				continue;
			}

			$already = (int) $item->get_meta( self::RESTOCKED_QUANTITY_META, true );
			$added   = min( (int) $line_items[ $item_id ]['qty'], (int) $snapshot['item_quantity'] - $already );
			if ( $added < 1 ) {
				continue;
			}
			foreach ( $snapshot['pool_demand'] as $pool_id => $total ) {
				$per_item           = intdiv( (int) $total, (int) $snapshot['item_quantity'] );
				$demand[ $pool_id ] = ( $demand[ $pool_id ] ?? 0 ) + ( $per_item * $added );
			}
			$items[]             = array( $item, $already + $added );
			$targets[ $item_id ] = $already + $added;
		}

		if ( array() !== $demand ) {
			$cycle     = max( 1, (int) $order->get_meta( self::CYCLE_META, true ) );
			$signature = substr( hash( 'sha256', wp_json_encode( array( $order->get_id(), $cycle, $demand, $targets ) ) ), 0, 16 );
			$this->apply_demand( $order, $demand, 1, 'refund_restore', 'refund-' . $signature );
			foreach ( $items as $state ) {
				$state[0]->update_meta_data( self::RESTOCKED_QUANTITY_META, $state[1] );
				$state[0]->save();
			}
		}

		return true;
	}

	/**
	 * Aggregate snapshot demand.
	 *
	 * @param WC_Order $order     Order.
	 * @param bool     $remaining Whether to subtract quantities already refunded.
	 * @return array<int, int>
	 */
	private function total_demand( WC_Order $order, bool $remaining = false ): array {
		$demand = array();
		foreach ( $order->get_items() as $item ) {
			$snapshot = $item->get_meta( OrderItemSnapshotter::META_KEY, true );
			if ( ! is_array( $snapshot ) || empty( $snapshot['pool_demand'] ) ) {
				continue;
			}
			$item_quantity = (int) ( $snapshot['item_quantity'] ?? 0 );
			$restocked     = $remaining ? (int) $item->get_meta( self::RESTOCKED_QUANTITY_META, true ) : 0;
			$quantity      = max( 0, $item_quantity - $restocked );
			foreach ( $snapshot['pool_demand'] as $pool_id => $amount ) {
				$per_item           = $item_quantity > 0 ? intdiv( (int) $amount, $item_quantity ) : 0;
				$demand[ $pool_id ] = ( $demand[ $pool_id ] ?? 0 ) + ( $per_item * $quantity );
			}
		}
		return $demand;
	}

	/**
	 * Apply aggregated demand.
	 *
	 * @param WC_Order       $order     Order.
	 * @param array<int,int> $demand    Demand by pool.
	 * @param int            $direction Minus one to reduce, plus one to restore.
	 * @param string         $type      Movement type.
	 * @param string         $event     Idempotency event.
	 * @return void
	 */
	private function apply_demand( WC_Order $order, array $demand, int $direction, string $type, string $event ): void {
		$commands = array();
		foreach ( $demand as $pool_id => $amount ) {
			$commands[] = array(
				'pool_id'         => (int) $pool_id,
				'delta'           => $direction * (int) $amount,
				'type'            => $type,
				'idempotency_key' => 'order:' . $order->get_id() . ':pool:' . $pool_id . ':' . $event,
				'context'         => array(
					'source_type' => 'order',
					'source_id'   => $order->get_id(),
				),
			);
		}
		if ( array() !== $commands ) {
			$this->mutations->apply_batch( $commands );
		}
	}
}
