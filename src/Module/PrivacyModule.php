<?php
/**
 * Free privacy integration module.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Module;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Container;
use LaqiUnitStockManager\Privacy;

/** Registers WordPress privacy tools. */
final class PrivacyModule implements ModuleInterface {

	/**
	 * Register privacy services.
	 *
	 * @param Container $container Internal service container.
	 * @return void
	 */
	public function register( Container $container ): void {
		( new Privacy( $container->movement_repository() ) )->register();
	}
}
