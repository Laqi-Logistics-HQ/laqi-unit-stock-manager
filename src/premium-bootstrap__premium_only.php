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
require_once __DIR__ . '/Premium__premium_only/Inventory/StockLossTypeCatalog.php';
require_once __DIR__ . '/Premium__premium_only/Admin/StockLossController.php';
require_once __DIR__ . '/Premium__premium_only/Admin/StockLossSection.php';

/**
 * Give physically separate paid modules the completed shared composition root.
 *
 * Paid modules attach here without adding edition checks or class references
 * to Free code.
 */
add_action(
	'laqi_lusm_booted',
	static function ( Container $container ): void {
		$loss_types = new Premium\Inventory\StockLossTypeCatalog();
		$loss_types->register( $container->movement_registry() );
		$container->screen_section_catalog()->register( new Premium\Admin\MovementLedgerSection( $container->movement_repository(), $container->movement_presenter(), new Admin\PaginationRenderer() ) );
		$container->screen_section_catalog()->register( new Premium\Admin\StockLossSection( $container->pool_repository(), $container->quantity_formatter(), $loss_types ) );
		( new Premium\Admin\MovementLedgerExportController( $container->movement_repository(), $container->movement_presenter() ) )->register();
		( new Premium\Admin\StockLossController( $container->stock_adjustment_service(), $loss_types ) )->register();
		do_action( 'laqi_lusm_premium_ready', $container );
	}
);
