<?php
/**
 * WooCommerce order-item stock snapshots.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\WooCommerce;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Consumption\CalculatorRegistry;
use LaqiUnitStockManager\Storage\MappingRepository;
use WC_Order;
use WC_Order_Item_Product;

/**
 * Freezes the exact mapping used for later reduction and restoration.
 */
final class OrderItemSnapshotter {

	const META_KEY = '_laqi_lusm_stock_snapshot';

	/**
	 * Product mappings.
	 *
	 * @var MappingRepository
	 */
	private $mappings;

	/**
	 * Consumption calculators.
	 *
	 * @var CalculatorRegistry
	 */
	private $calculators;

	/**
	 * Constructor.
	 *
	 * @param MappingRepository  $mappings    Mapping repository.
	 * @param CalculatorRegistry $calculators Calculator registry.
	 */
	public function __construct( MappingRepository $mappings, CalculatorRegistry $calculators ) {
		$this->mappings    = $mappings;
		$this->calculators = $calculators;
	}

	/**
	 * Register checkout line-item snapshotting.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'snapshot' ), 20, 4 );
		add_action( 'woocommerce_before_order_item_object_save', array( $this, 'snapshot_admin_item' ), 10, 1 );
	}

	/**
	 * Add a private, immutable pooled-stock snapshot before the line item saves.
	 *
	 * @param WC_Order_Item_Product $item          Order item.
	 * @param string                $cart_item_key Cart item key.
	 * @param array                 $values        Cart item values.
	 * @param WC_Order              $order         Order being created.
	 * @return void
	 */
	public function snapshot( WC_Order_Item_Product $item, string $cart_item_key, array $values, WC_Order $order ): void {
		if ( ! apply_filters( 'laqi_lusm_include_checkout_item_stock_demand', true, $item, $values, $cart_item_key, $order ) ) {
			return;
		}
		$product_id   = isset( $values['product_id'] ) ? (int) $values['product_id'] : $item->get_product_id();
		$variation_id = isset( $values['variation_id'] ) ? (int) $values['variation_id'] : $item->get_variation_id();
		$quantity     = isset( $values['quantity'] ) ? (int) $values['quantity'] : $item->get_quantity();
		$mapping      = $this->mappings->find_for_product( $product_id, $variation_id );

		if ( null === $mapping || $quantity < 1 ) {
			return;
		}

		$this->write_snapshot( $item, $mapping->id(), $mapping->version(), $mapping->calculator_type(), $quantity, 'checkout' );
	}

	/**
	 * Create or refresh an admin-origin snapshot until stock is reduced.
	 *
	 * @param mixed $item WooCommerce data object.
	 * @return void
	 */
	public function snapshot_admin_item( $item ): void {
		if ( ! $item instanceof WC_Order_Item_Product || $item->get_order_id() < 1 ) {
			return;
		}
		$order = wc_get_order( $item->get_order_id() );
		if ( ! $order || 'reduced' === $order->get_meta( OrderStockLifecycle::STATE_META, true ) ) {
			return;
		}
		$existing = $item->get_meta( self::META_KEY, true );
		if ( is_array( $existing ) && 'checkout' === ( $existing['origin'] ?? '' ) ) {
			return;
		}
		$this->snapshot_admin_demand( $item );
	}

	/**
	 * Ensure all admin-created items have current snapshots before reduction.
	 *
	 * @param WC_Order $order Order about to reduce stock.
	 * @return void
	 */
	public function prepare_order( WC_Order $order ): void {
		foreach ( $order->get_items() as $item ) {
			$existing = $item->get_meta( self::META_KEY, true );
			if ( ! is_array( $existing ) || 'checkout' !== ( $existing['origin'] ?? '' ) ) {
				$this->snapshot_admin_demand( $item );
				$item->save();
			}
		}
	}

	/**
	 * Snapshot one admin item from its explicit saved mapping.
	 *
	 * This is also used when a line is added after an order has already reduced
	 * stock, where the normal pre-reduction snapshot hook must remain disabled.
	 *
	 * @param WC_Order_Item_Product $item Order item.
	 * @return array<string, mixed>|null Snapshot, or null for an unmapped item.
	 */
	public function snapshot_admin_demand( WC_Order_Item_Product $item ): ?array {
		if ( ! apply_filters( 'laqi_lusm_include_order_item_stock_demand', true, $item ) ) {
			return null;
		}
		$mapping  = $this->mappings->find_for_product( $item->get_product_id(), $item->get_variation_id() );
		$quantity = $item->get_quantity();
		if ( null === $mapping || $quantity < 1 ) {
			return null;
		}
		return $this->write_snapshot( $item, $mapping->id(), $mapping->version(), $mapping->calculator_type(), $quantity, 'admin' );
	}

	/**
	 * Write one normalized snapshot.
	 *
	 * @param WC_Order_Item_Product $item            Order item.
	 * @param int                   $mapping_id      Mapping ID.
	 * @param int                   $mapping_version Mapping version.
	 * @param string                $calculator_type Calculator type.
	 * @param int                   $quantity        Item quantity.
	 * @param string                $origin          Snapshot origin.
	 * @return array<string, mixed> Snapshot data.
	 */
	private function write_snapshot( WC_Order_Item_Product $item, int $mapping_id, int $mapping_version, string $calculator_type, int $quantity, string $origin ): array {
		$mapping = $this->mappings->find_for_product( $item->get_product_id(), $item->get_variation_id() );
		if ( null === $mapping ) {
			return array();
		}
		$demand   = $this->calculators->get( $calculator_type )->calculate( $mapping, $quantity );
		$snapshot = array(
			'version'         => 1,
			'origin'          => $origin,
			'mapping_id'      => $mapping_id,
			'mapping_version' => $mapping_version,
			'item_quantity'   => $quantity,
			'pool_demand'     => $demand,
		);
		$item->update_meta_data( self::META_KEY, $snapshot );
		return $snapshot;
	}
}
