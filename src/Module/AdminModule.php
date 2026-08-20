<?php
/**
 * Free administration module.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Module;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Admin;
use LaqiUnitStockManager\Assets;
use LaqiUnitStockManager\Container;
use LaqiUnitStockManager\WooCommerce;

/** Registers the Free admin screen and mutation controllers. */
final class AdminModule implements ModuleInterface {

	/**
	 * Register administration services.
	 *
	 * @param Container $container Internal service container.
	 * @return void
	 */
	public function register( Container $container ): void {
		$sections   = $container->screen_section_catalog();
		$pagination = new Admin\PaginationRenderer();
		$tables     = new Admin\DatasetRenderer( $pagination );
		$sections->register( new Admin\PoolStockSection( $container->pool_repository(), $container->pool_presenter(), $container->mapping_repository(), $container->availability_service(), $container->quantity_formatter(), $container->mapping_diagnostics(), $pagination, $container->unit_registry() ) );
		$sections->register( new Admin\SetupSection( $container->pool_repository(), $container->unit_registry(), $container->custom_unit_repository(), $container->mapping_repository(), $container->quantity_formatter(), $pagination ) );
		$sections->register( new Admin\ActivitySection( $container->movement_repository(), $container->movement_presenter(), $tables, $container->movement_registry(), $container->pool_repository() ) );
		( new Admin\UnitStockPage( $sections ) )->register();
		( new Admin\ProductEditor( $container->pool_repository(), $container->mapping_repository(), $container->unit_registry(), $container->quantity_formatter(), new WooCommerce\ExistingStockMigrator( $container->stock_mutation_service() ) ) )->register();
		( new Admin\ProductList( $container->mapping_repository() ) )->register();
		( new Admin\OrderStockMetaBox( $container->movement_repository(), $container->movement_presenter() ) )->register();
		( new Admin\StockAdjustmentController( $container->stock_adjustment_service() ) )->register();
		( new Admin\SetupController( $container->pool_repository(), $container->mapping_repository(), $container->unit_registry(), $container->stock_mutation_service(), $container->custom_unit_repository(), new WooCommerce\ExistingStockMigrator( $container->stock_mutation_service() ), new WooCommerce\PurchasableResolver() ) )->register();
		( new Admin\PoolSearchController( $container->pool_repository() ) )->register();
		( new Admin\PoolDetailsController( $container->pool_repository(), $container->unit_registry() ) )->register();
		( new Assets() )->register();
	}
}
