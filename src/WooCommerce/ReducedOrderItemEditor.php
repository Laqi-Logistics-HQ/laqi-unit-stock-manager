<?php
/**
 * Reduced-order quantity edit synchronization.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\WooCommerce;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Inventory\StockMutationService;
use RuntimeException;
use WC_Order;
use WC_Order_Item_Product;

/**
 * Applies exact pool deltas when an already-reduced order item is edited.
 */
final class ReducedOrderItemEditor {

	const SEQUENCE_META = '_laqi_lusm_stock_edit_sequence';

	const ADDED_CYCLE_META = '_laqi_lusm_stock_added_cycle';

	/**
	 * Stock mutation service.
	 *
	 * @var StockMutationService
	 */
	private $mutations;

	/**
	 * Order item snapshot service.
	 *
	 * @var OrderItemSnapshotter
	 */
	private $snapshots;

	/**
	 * Constructor.
	 *
	 * @param StockMutationService $mutations Stock mutations.
	 * @param OrderItemSnapshotter $snapshots Order item snapshots.
	 */
	public function __construct( StockMutationService $mutations, OrderItemSnapshotter $snapshots ) {
		$this->mutations = $mutations;
		$this->snapshots = $snapshots;
	}

	/**
	 * Register order-item CRUD hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'woocommerce_before_order_item_object_save', array( $this, 'adjust_saved_item' ), 20, 1 );
		add_action( 'woocommerce_new_order_item', array( $this, 'add_saved_item' ), 20, 3 );
		add_action( 'woocommerce_before_delete_order_item', array( $this, 'remove_saved_item' ), 20, 1 );
	}

	/**
	 * Consume pooled stock for a product line added to a reduced order.
	 *
	 * @param int   $item_id  Order item ID.
	 * @param mixed $item     WooCommerce order item.
	 * @param int   $order_id Order ID.
	 * @return void
	 * @throws \Throwable When the pool cannot satisfy the added demand.
	 */
	public function add_saved_item( int $item_id, $item, int $order_id ): void {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order || 'reduced' !== $order->get_meta( OrderStockLifecycle::STATE_META, true ) ) {
			return;
		}
		$cycle = max( 1, (int) $order->get_meta( OrderStockLifecycle::CYCLE_META, true ) );
		if ( $cycle === (int) $item->get_meta( self::ADDED_CYCLE_META, true ) ) {
			return;
		}
		$snapshot = $this->snapshots->snapshot_admin_demand( $item );
		if ( null === $snapshot ) {
			return;
		}
		try {
			$this->apply_demand( $order, $item_id, $snapshot['pool_demand'], -1, 'add' );
		} catch ( \Throwable $error ) {
			$data_store = \WC_Data_Store::load( 'order-item' );
			$data_store->delete_order_item( $item_id );
			throw $error;
		}
		$item->update_meta_data( self::ADDED_CYCLE_META, $cycle );
		$item->save();
	}

	/**
	 * Restore outstanding pooled stock before a reduced-order line is deleted.
	 *
	 * @param int $item_id Order item ID.
	 * @return void
	 */
	public function remove_saved_item( int $item_id ): void {
		$item = new WC_Order_Item_Product( $item_id );
		if ( $item->get_id() < 1 || $item->get_order_id() < 1 ) {
			return;
		}
		$order = wc_get_order( $item->get_order_id() );
		if ( ! $order || 'reduced' !== $order->get_meta( OrderStockLifecycle::STATE_META, true ) ) {
			return;
		}
		$snapshot = $item->get_meta( OrderItemSnapshotter::META_KEY, true );
		if ( ! is_array( $snapshot ) || empty( $snapshot['item_quantity'] ) || empty( $snapshot['pool_demand'] ) ) {
			return;
		}
		$quantity  = (int) $snapshot['item_quantity'];
		$restocked = min( $quantity, (int) $item->get_meta( OrderStockLifecycle::RESTOCKED_QUANTITY_META, true ) );
		$demand    = array();
		foreach ( $snapshot['pool_demand'] as $pool_id => $total ) {
			$demand[ $pool_id ] = intdiv( (int) $total, $quantity ) * ( $quantity - $restocked );
		}
		$this->apply_demand( $order, $item_id, $demand, 1, 'delete' );
	}

	/**
	 * Reconcile the changed quantity against its immutable per-item demand.
	 *
	 * @param mixed $item WooCommerce data object.
	 * @return void
	 * @throws RuntimeException When a partially refunded item is edited.
	 */
	public function adjust_saved_item( $item ): void {
		if ( ! $item instanceof WC_Order_Item_Product || $item->get_id() < 1 || $item->get_order_id() < 1 ) {
			return;
		}
		$order = wc_get_order( $item->get_order_id() );
		if ( ! $order || 'reduced' !== $order->get_meta( OrderStockLifecycle::STATE_META, true ) ) {
			return;
		}
		$snapshot = $item->get_meta( OrderItemSnapshotter::META_KEY, true );
		if ( ! is_array( $snapshot ) || empty( $snapshot['item_quantity'] ) || empty( $snapshot['pool_demand'] ) ) {
			return;
		}
		$old_quantity = (int) $snapshot['item_quantity'];
		$new_quantity = (int) $item->get_quantity();
		if ( $old_quantity === $new_quantity ) {
			return;
		}
		if ( (int) $item->get_meta( OrderStockLifecycle::RESTOCKED_QUANTITY_META, true ) > 0 ) {
			throw new RuntimeException( 'A partially refunded pooled-stock item cannot be quantity-edited.' );
		}

		$sequence = (int) $item->get_meta( self::SEQUENCE_META, true ) + 1;
		$cycle    = max( 1, (int) $order->get_meta( OrderStockLifecycle::CYCLE_META, true ) );
		$commands = array();
		$demand   = array();
		foreach ( $snapshot['pool_demand'] as $pool_id => $old_total ) {
			$per_item           = intdiv( (int) $old_total, $old_quantity );
			$new_total          = $per_item * $new_quantity;
			$demand[ $pool_id ] = $new_total;
			$commands[]         = array(
				'pool_id'         => (int) $pool_id,
				'delta'           => -( $new_total - (int) $old_total ),
				'type'            => 'order_edit',
				'idempotency_key' => 'order:' . $order->get_id() . ':item:' . $item->get_id() . ':cycle:' . $cycle . ':edit:' . $sequence . ':pool:' . $pool_id,
				'context'         => array(
					'source_type' => 'order',
					'source_id'   => $order->get_id(),
					'actor_id'    => get_current_user_id(),
				),
			);
		}
		$this->mutations->apply_batch( $commands );
		$snapshot['item_quantity'] = $new_quantity;
		$snapshot['pool_demand']   = $demand;
		$item->update_meta_data( OrderItemSnapshotter::META_KEY, $snapshot );
		$item->update_meta_data( self::SEQUENCE_META, $sequence );
	}

	/**
	 * Apply a snapshotted demand as an order-edit movement batch.
	 *
	 * @param WC_Order        $order     Order being edited.
	 * @param int             $item_id   Order item ID.
	 * @param array<int, int> $demand    Normalized demand keyed by pool ID.
	 * @param int             $direction Negative to consume, positive to restore.
	 * @param string          $operation Stable operation name.
	 * @return void
	 */
	private function apply_demand( WC_Order $order, int $item_id, array $demand, int $direction, string $operation ): void {
		$cycle    = max( 1, (int) $order->get_meta( OrderStockLifecycle::CYCLE_META, true ) );
		$commands = array();
		foreach ( $demand as $pool_id => $quantity ) {
			if ( (int) $quantity < 1 ) {
				continue;
			}
			$commands[] = array(
				'pool_id'         => (int) $pool_id,
				'delta'           => $direction * (int) $quantity,
				'type'            => 'order_edit',
				'idempotency_key' => 'order:' . $order->get_id() . ':item:' . $item_id . ':cycle:' . $cycle . ':' . $operation . ':pool:' . $pool_id,
				'context'         => array(
					'source_type' => 'order',
					'source_id'   => $order->get_id(),
					'actor_id'    => get_current_user_id(),
				),
			);
		}
		if ( array() !== $commands ) {
			$this->mutations->apply_batch( $commands );
		}
	}
}
