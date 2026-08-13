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
use WC_Order_Item_Product;

/**
 * Applies exact pool deltas when an already-reduced order item is edited.
 */
final class ReducedOrderItemEditor {

	const SEQUENCE_META = '_laqi_lusm_stock_edit_sequence';

	/**
	 * Stock mutation service.
	 *
	 * @var StockMutationService
	 */
	private $mutations;

	/**
	 * Constructor.
	 *
	 * @param StockMutationService $mutations Stock mutations.
	 */
	public function __construct( StockMutationService $mutations ) {
		$this->mutations = $mutations;
	}

	/**
	 * Register order-item CRUD hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'woocommerce_before_order_item_object_save', array( $this, 'adjust_saved_item' ), 20, 1 );
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
}
