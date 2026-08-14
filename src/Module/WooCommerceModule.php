<?php
/**
 * WooCommerce integration module.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Module;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Container;
use LaqiUnitStockManager\WooCommerce;

/** Registers pooled-stock behavior with WooCommerce. */
final class WooCommerceModule implements ModuleInterface {

	/**
	 * Register WooCommerce adapters.
	 *
	 * @param Container $container Internal service container.
	 * @return void
	 */
	public function register( Container $container ): void {
		( new WooCommerce\CartValidator( $container->availability_service() ) )->register();
		$snapshotter = new WooCommerce\OrderItemSnapshotter( $container->mapping_repository(), $container->calculator_registry() );
		$snapshotter->register();
		( new WooCommerce\OrderStockLifecycle( $container->stock_mutation_service(), $snapshotter ) )->register();
		( new WooCommerce\ReducedOrderItemEditor( $container->stock_mutation_service(), $snapshotter ) )->register();
		( new WooCommerce\StockStatusSynchronizer( $container->mapping_repository(), $container->availability_service() ) )->register();
	}
}
