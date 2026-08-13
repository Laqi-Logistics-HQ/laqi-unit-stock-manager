<?php
/**
 * Optional paid-edition composition bootstrap.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager;

defined( 'ABSPATH' ) || exit;

/**
 * Give physically separate paid modules the completed shared composition root.
 *
 * Part 1 intentionally registers no paid functionality. Later paid modules can
 * attach to this action without adding edition checks or class references to
 * Free code.
 */
add_action(
	'laqi_lusm_booted',
	static function ( Container $container ): void {
		do_action( 'laqi_lusm_premium_ready', $container );
	}
);
