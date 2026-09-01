<?php
/**
 * Internal Free module contract.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Module;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Container;

interface ModuleInterface {

	/**
	 * Register this module's runtime collaborators.
	 *
	 * @param Container $container Internal service container.
	 * @return void
	 */
	public function register( Container $container ): void;
}
