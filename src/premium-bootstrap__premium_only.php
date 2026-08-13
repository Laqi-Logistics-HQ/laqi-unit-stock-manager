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
require_once __DIR__ . '/Premium__premium_only/Alerts/LowStockPolicyRepository.php';
require_once __DIR__ . '/Premium__premium_only/Alerts/LowStockAlertEvaluator.php';
require_once __DIR__ . '/Premium__premium_only/Alerts/AlertChannelInterface.php';
require_once __DIR__ . '/Premium__premium_only/Alerts/AlertChannelRegistry.php';
require_once __DIR__ . '/Premium__premium_only/Alerts/EmailAlertChannel.php';
require_once __DIR__ . '/Premium__premium_only/Alerts/WebhookAlertChannel.php';
require_once __DIR__ . '/Premium__premium_only/Alerts/AlertDeliveryRepository.php';
require_once __DIR__ . '/Premium__premium_only/Admin/LowStockAlertController.php';
require_once __DIR__ . '/Premium__premium_only/Admin/LowStockAlertsSection.php';
require_once __DIR__ . '/Premium__premium_only/Forecasting/ForecastPolicyRepository.php';
require_once __DIR__ . '/Premium__premium_only/Forecasting/StockForecastService.php';
require_once __DIR__ . '/Premium__premium_only/Admin/ForecastController.php';
require_once __DIR__ . '/Premium__premium_only/Admin/ForecastSection.php';
require_once __DIR__ . '/Premium__premium_only/Reports/StockReportSettings.php';
require_once __DIR__ . '/Premium__premium_only/Reports/StockReportBuilder.php';
require_once __DIR__ . '/Premium__premium_only/Reports/StockReportScheduler.php';
require_once __DIR__ . '/Premium__premium_only/Admin/StockReportController.php';
require_once __DIR__ . '/Premium__premium_only/Admin/StockReportSection.php';
require_once __DIR__ . '/Premium__premium_only/Planning/StockScenarioPlanner.php';
require_once __DIR__ . '/Premium__premium_only/Admin/StockScenarioSection.php';
require_once __DIR__ . '/Premium__premium_only/Receiving/SupplierRepository.php';
require_once __DIR__ . '/Premium__premium_only/Receiving/ReceivingService.php';
require_once __DIR__ . '/Premium__premium_only/Admin/ReceivingController.php';
require_once __DIR__ . '/Premium__premium_only/Admin/ReceivingSection.php';

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
		global $wpdb;
		$alert_policies   = new Premium\Alerts\LowStockPolicyRepository( $wpdb );
		$alert_deliveries = new Premium\Alerts\AlertDeliveryRepository( $wpdb );
		$alert_deliveries->install();
		$alert_channels = new Premium\Alerts\AlertChannelRegistry();
		$alert_channels->register( new Premium\Alerts\EmailAlertChannel() );
		$alert_channels->register( new Premium\Alerts\WebhookAlertChannel() );
		$forecast_policies = new Premium\Forecasting\ForecastPolicyRepository( $wpdb );
		$forecast_service  = new Premium\Forecasting\StockForecastService( $container->movement_repository() );
		$report_settings   = new Premium\Reports\StockReportSettings();
		$report_builder    = new Premium\Reports\StockReportBuilder( $container->pool_repository(), $container->quantity_formatter(), $alert_policies, $forecast_policies, $forecast_service );
		$report_scheduler  = new Premium\Reports\StockReportScheduler( $report_settings, $report_builder );
		$scenario_planner  = new Premium\Planning\StockScenarioPlanner( $container->pool_repository(), $container->mapping_repository(), $forecast_policies, $forecast_service );
		$suppliers         = new Premium\Receiving\SupplierRepository( $wpdb );
		$suppliers->install();
		$receiving = new Premium\Receiving\ReceivingService( $suppliers, $container->stock_mutation_service() );
		$loss_types->register( $container->movement_registry() );
		$container->movement_registry()->register( new Inventory\MovementType( 'supplier_receipt', __( 'Supplier receipt', 'laqi-unit-stock-manager' ) ) );
		$container->screen_section_catalog()->register( new Premium\Admin\MovementLedgerSection( $container->movement_repository(), $container->movement_presenter(), new Admin\PaginationRenderer() ) );
		$container->screen_section_catalog()->register( new Premium\Admin\StockLossSection( $container->pool_repository(), $container->quantity_formatter(), $loss_types ) );
		$container->screen_section_catalog()->register( new Premium\Admin\LowStockAlertsSection( $alert_policies, $container->pool_repository(), $container->quantity_formatter(), $alert_deliveries ) );
		$container->screen_section_catalog()->register( new Premium\Admin\ForecastSection( $container->pool_repository(), $forecast_policies, $forecast_service, $container->quantity_formatter(), new Admin\PaginationRenderer() ) );
		$container->screen_section_catalog()->register( new Premium\Admin\StockReportSection( $report_settings ) );
		$container->screen_section_catalog()->register( new Premium\Admin\StockScenarioSection( $container->pool_repository(), $scenario_planner, $container->quantity_formatter() ) );
		$container->screen_section_catalog()->register( new Premium\Admin\ReceivingSection( $suppliers, $container->pool_repository(), $container->quantity_formatter() ) );
		( new Premium\Admin\MovementLedgerExportController( $container->movement_repository(), $container->movement_presenter() ) )->register();
		( new Premium\Admin\StockLossController( $container->stock_adjustment_service(), $loss_types ) )->register();
		( new Premium\Admin\LowStockAlertController( $alert_policies, $container->pool_repository(), $container->unit_registry() ) )->register();
		( new Premium\Admin\ForecastController( $forecast_policies, $container->pool_repository() ) )->register();
		( new Premium\Admin\StockReportController( $report_settings, $report_scheduler ) )->register();
		( new Premium\Admin\ReceivingController( $suppliers, $container->pool_repository(), $container->unit_registry(), $receiving ) )->register();
		$report_scheduler->register();
		$alert_evaluator = new Premium\Alerts\LowStockAlertEvaluator( $alert_policies, $container->pool_repository(), $container->quantity_formatter(), $alert_channels, $alert_deliveries );
		$alert_evaluator->register();
		register_deactivation_hook( LAQI_LUSM_FILE, array( $alert_evaluator, 'unschedule' ) );
		register_deactivation_hook( LAQI_LUSM_FILE, array( $report_scheduler, 'unschedule' ) );
		do_action( 'laqi_lusm_premium_ready', $container );
	}
);
