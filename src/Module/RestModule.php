<?php
/**
 * Free REST API module.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Module;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Container;
use LaqiUnitStockManager\Rest\InventoryController;

/** Registers the Free inventory API. */
final class RestModule implements ModuleInterface {

	/**
	 * Register REST services.
	 *
	 * @param Container $container Internal service container.
	 * @return void
	 */
	public function register( Container $container ): void {
		( new InventoryController( $container->pool_repository(), $container->pool_presenter(), $container->movement_repository(), $container->movement_presenter(), $container->stock_adjustment_service() ) )->register();
	}
}
