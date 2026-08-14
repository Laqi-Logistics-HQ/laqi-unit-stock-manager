<?php
/**
 * Free inventory service module.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Module;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Container;

/** Initializes registries whose contents must be stable before adapters attach. */
final class InventoryModule implements ModuleInterface {

	/**
	 * Register inventory services.
	 *
	 * @param Container $container Internal service container.
	 * @return void
	 */
	public function register( Container $container ): void {
		$container->unit_registry();
	}
}
