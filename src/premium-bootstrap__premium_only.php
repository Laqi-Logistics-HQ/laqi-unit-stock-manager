<?php
/**
 * Optional paid-edition composition bootstrap.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/Premium__premium_only/Admin/MovementLedgerSection.php';
require_once __DIR__ . '/Premium__premium_only/Admin/MovementLedgerExportController.php';

/**
 * Give physically separate paid modules the completed shared composition root.
 *
 * Paid modules attach here without adding edition checks or class references
 * to Free code.
 */
add_action(
	'laqi_lusm_booted',
	static function ( Container $container ): void {
		$container->screen_section_catalog()->register( new Premium\Admin\MovementLedgerSection( $container->movement_repository(), $container->movement_presenter(), new Admin\PaginationRenderer() ) );
		( new Premium\Admin\MovementLedgerExportController( $container->movement_repository(), $container->movement_presenter() ) )->register();
		do_action( 'laqi_lusm_premium_ready', $container );
	}
);
