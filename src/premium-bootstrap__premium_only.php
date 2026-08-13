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
		$loss_types->register( $container->movement_registry() );
		$container->screen_section_catalog()->register( new Premium\Admin\MovementLedgerSection( $container->movement_repository(), $container->movement_presenter(), new Admin\PaginationRenderer() ) );
		$container->screen_section_catalog()->register( new Premium\Admin\StockLossSection( $container->pool_repository(), $container->quantity_formatter(), $loss_types ) );
		$container->screen_section_catalog()->register( new Premium\Admin\LowStockAlertsSection( $alert_policies, $container->pool_repository(), $container->quantity_formatter(), $alert_deliveries ) );
		$container->screen_section_catalog()->register( new Premium\Admin\ForecastSection( $container->pool_repository(), $forecast_policies, $forecast_service, $container->quantity_formatter(), new Admin\PaginationRenderer() ) );
		( new Premium\Admin\MovementLedgerExportController( $container->movement_repository(), $container->movement_presenter() ) )->register();
		( new Premium\Admin\StockLossController( $container->stock_adjustment_service(), $loss_types ) )->register();
		( new Premium\Admin\LowStockAlertController( $alert_policies, $container->pool_repository(), $container->unit_registry() ) )->register();
		( new Premium\Admin\ForecastController( $forecast_policies, $container->pool_repository() ) )->register();
		$alert_evaluator = new Premium\Alerts\LowStockAlertEvaluator( $alert_policies, $container->pool_repository(), $container->quantity_formatter(), $alert_channels, $alert_deliveries );
		$alert_evaluator->register();
		register_deactivation_hook( LAQI_LUSM_FILE, array( $alert_evaluator, 'unschedule' ) );
		do_action( 'laqi_lusm_premium_ready', $container );
	}
);
