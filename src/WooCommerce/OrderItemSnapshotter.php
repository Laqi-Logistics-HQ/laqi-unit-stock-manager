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
		unset( $cart_item_key, $order );
		$product_id   = isset( $values['product_id'] ) ? (int) $values['product_id'] : $item->get_product_id();
		$variation_id = isset( $values['variation_id'] ) ? (int) $values['variation_id'] : $item->get_variation_id();
		$quantity     = isset( $values['quantity'] ) ? (int) $values['quantity'] : $item->get_quantity();
		$mapping      = $this->mappings->find_for_product( $product_id, $variation_id );

		if ( null === $mapping || $quantity < 1 ) {
			return;
		}

		$demand = $this->calculators->get( $mapping->calculator_type() )->calculate( $mapping, $quantity );
		$item->add_meta_data(
			self::META_KEY,
			array(
				'version'         => 1,
				'mapping_id'      => $mapping->id(),
				'mapping_version' => 1,
				'item_quantity'   => $quantity,
				'pool_demand'     => $demand,
			),
			true
		);
	}
}
