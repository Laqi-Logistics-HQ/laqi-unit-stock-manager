<?php
/**
 * Optional paid-edition composition bootstrap.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/Premium__premium_only/Alerts/AlertChannelInterface.php';
require_once __DIR__ . '/Premium__premium_only/Alerts/AlertChannelRegistry.php';
require_once __DIR__ . '/Premium__premium_only/Alerts/EmailAlertChannel.php';
require_once __DIR__ . '/Premium__premium_only/Alerts/WebhookAlertChannel.php';
require_once __DIR__ . '/Premium__premium_only/Alerts/AlertDeliveryRepository.php';
require_once __DIR__ . '/Premium__premium_only/Forecasting/ForecastPolicyRepository.php';
require_once __DIR__ . '/Premium__premium_only/Forecasting/StockForecastService.php';
require_once __DIR__ . '/Premium__premium_only/Receiving/SupplierRepository.php';
require_once __DIR__ . '/Premium__premium_only/Batches/BatchRepository.php';
require_once __DIR__ . '/Premium__premium_only/Batches/BatchAllocationRepository.php';
require_once __DIR__ . '/Premium__premium_only/Batches/BatchMovementAllocator.php';
require_once __DIR__ . '/Premium__premium_only/Batches/ExpiredBatchAvailability.php';
require_once __DIR__ . '/Premium__premium_only/Batches/BatchOperationsService.php';
require_once __DIR__ . '/Premium__premium_only/Batches/BatchTransferReceiver.php';
require_once __DIR__ . '/Premium__premium_only/Batches/BatchTransferService.php';
require_once __DIR__ . '/Premium__premium_only/Batches/BatchExpirySettings.php';
require_once __DIR__ . '/Premium__premium_only/Batches/BatchExpiryEvaluator.php';
require_once __DIR__ . '/Premium__premium_only/Admin/BatchOperationsController.php';
require_once __DIR__ . '/Premium__premium_only/Admin/BatchExpiryController.php';
require_once __DIR__ . '/Premium__premium_only/Receiving/ReceivingService.php';
require_once __DIR__ . '/Premium__premium_only/Admin/ReceivingController.php';
require_once __DIR__ . '/Premium__premium_only/Admin/ReceivingSection.php';
require_once __DIR__ . '/Premium__premium_only/Replenishment/ReorderPolicyRepository.php';
require_once __DIR__ . '/Premium__premium_only/Replenishment/ReorderSuggestionService.php';
require_once __DIR__ . '/Premium__premium_only/Admin/ReorderController.php';
require_once __DIR__ . '/Premium__premium_only/Admin/ReorderSection.php';
require_once __DIR__ . '/Premium__premium_only/Exchange/CsvRowMapperInterface.php';
require_once __DIR__ . '/Premium__premium_only/Exchange/CsvRowMapperRegistry.php';
require_once __DIR__ . '/Premium__premium_only/Exchange/OperationsCsvService.php';
require_once __DIR__ . '/Premium__premium_only/Exchange/SupplierCsvRowMapper.php';
require_once __DIR__ . '/Premium__premium_only/Exchange/PackCsvRowMapper.php';
require_once __DIR__ . '/Premium__premium_only/Exchange/IncomingCsvRowMapper.php';
require_once __DIR__ . '/Premium__premium_only/Exchange/ReorderCsvRowMapper.php';
require_once __DIR__ . '/Premium__premium_only/Admin/CsvExchangeController.php';
require_once __DIR__ . '/Premium__premium_only/Costing/MaterialCostRepository.php';
require_once __DIR__ . '/Premium__premium_only/Costing/MaterialEconomicsService.php';
require_once __DIR__ . '/Premium__premium_only/Admin/MaterialCostsSection.php';
require_once __DIR__ . '/Premium__premium_only/Reservations/ReservationRepository.php';
require_once __DIR__ . '/Premium__premium_only/Reservations/OrderReservationService.php';
require_once __DIR__ . '/Premium__premium_only/Admin/ReservationsSection.php';
require_once __DIR__ . '/Premium__premium_only/Supply/StockHoldRepository.php';
require_once __DIR__ . '/Premium__premium_only/Supply/StockHoldService.php';
require_once __DIR__ . '/Premium__premium_only/Supply/SafetyStockPolicyRepository.php';
require_once __DIR__ . '/Premium__premium_only/Supply/SafetyStockAvailability.php';
require_once __DIR__ . '/Premium__premium_only/Supply/SupplyProjectionService.php';
require_once __DIR__ . '/Premium__premium_only/Admin/SupplyStateController.php';
require_once __DIR__ . '/Premium__premium_only/Integrations/SubscriptionRenewalAdapter.php';
require_once __DIR__ . '/Premium__premium_only/Integrations/MobileOrderAdapter.php';

/**
 * Give physically separate paid modules the completed shared composition root.
 *
 * Paid modules attach here without adding edition checks or class references
 * to Free code.
 */
add_action(
	'laqi_lusm_booted',
	static function ( Container $container ): void {
		global $wpdb;
		$alert_deliveries = new Premium\Alerts\AlertDeliveryRepository( $wpdb );
		$alert_deliveries->install();
		$alert_channels = new Premium\Alerts\AlertChannelRegistry();
		$alert_channels->register( new Premium\Alerts\EmailAlertChannel() );
		$alert_channels->register( new Premium\Alerts\WebhookAlertChannel() );
		$forecast_policies = new Premium\Forecasting\ForecastPolicyRepository( $wpdb );
		$forecast_service  = new Premium\Forecasting\StockForecastService( $container->movement_repository() );
		$suppliers         = new Premium\Receiving\SupplierRepository( $wpdb );
		$suppliers->install();
		$material_costs = new Premium\Costing\MaterialCostRepository( $wpdb );
		$material_costs->install();
		$batches = new Premium\Batches\BatchRepository( $wpdb );
		$batches->install();
		$batch_allocations = new Premium\Batches\BatchAllocationRepository( $wpdb );
		$batch_allocations->install();
		( new Premium\Batches\BatchMovementAllocator( $batch_allocations ) )->register();
		( new Premium\Batches\BatchTransferReceiver( $batches ) )->register();
		( new Premium\Batches\ExpiredBatchAvailability( $batches ) )->register();
		$batch_operations   = new Premium\Batches\BatchOperationsService( $batches, $container->stock_mutation_service() );
		$batch_transfers    = new Premium\Batches\BatchTransferService( $batches, $container->pool_repository(), $container->stock_mutation_service() );
		$batch_expiry       = new Premium\Batches\BatchExpirySettings();
		$material_economics = new Premium\Costing\MaterialEconomicsService( $material_costs );
		$reservations       = new Premium\Reservations\ReservationRepository( $wpdb );
		$reservations->install();
		$reservation_service = new Premium\Reservations\OrderReservationService( $reservations );
		$reservation_service->register();
		$stock_holds = new Premium\Supply\StockHoldRepository( $wpdb );
		$stock_holds->install();
		$safety_stock = new Premium\Supply\SafetyStockPolicyRepository( $wpdb );
		( new Premium\Supply\SafetyStockAvailability( $safety_stock ) )->register();
		$supply_projections = new Premium\Supply\SupplyProjectionService( $stock_holds, $safety_stock );
		$stock_hold_service = new Premium\Supply\StockHoldService( $stock_holds, $container->pool_repository(), $container->stock_mutation_service() );
		$stock_hold_service->register();
		$receiving           = new Premium\Receiving\ReceivingService( $suppliers, $container->stock_mutation_service(), $material_costs, $batches );
		$reorder_policies    = new Premium\Replenishment\ReorderPolicyRepository( $wpdb );
		$reorder_suggestions = new Premium\Replenishment\ReorderSuggestionService( $container->pool_repository(), $reorder_policies, $forecast_policies, $forecast_service, $suppliers );
		$csv_mappers         = new Premium\Exchange\CsvRowMapperRegistry();
		$csv_mappers->register( new Premium\Exchange\SupplierCsvRowMapper( $suppliers ) );
		$csv_mappers->register( new Premium\Exchange\PackCsvRowMapper( $suppliers, $container->pool_repository(), $container->quantity_formatter(), $container->unit_registry() ) );
		$csv_mappers->register( new Premium\Exchange\IncomingCsvRowMapper( $suppliers, $container->pool_repository() ) );
		$csv_mappers->register( new Premium\Exchange\ReorderCsvRowMapper( $container->pool_repository(), $suppliers, $reorder_policies, $container->quantity_formatter(), $container->unit_registry() ) );
		$csv_exchange = new Premium\Exchange\OperationsCsvService( $csv_mappers );
		$container->movement_registry()->register( new Inventory\MovementType( 'supplier_receipt', __( 'Supplier receipt', 'laqi-unit-stock-manager' ) ) );
		$container->movement_registry()->register( new Inventory\MovementType( 'batch_transfer_out', __( 'Batch transfer out', 'laqi-unit-stock-manager' ) ) );
		$container->movement_registry()->register( new Inventory\MovementType( 'batch_transfer_in', __( 'Batch transfer in', 'laqi-unit-stock-manager' ) ) );
		$container->screen_section_catalog()->register( new Premium\Admin\ReceivingSection( $suppliers, $container->pool_repository(), $container->quantity_formatter(), $batches, $batch_allocations, $batch_expiry ) );
		$container->screen_section_catalog()->register( new Premium\Admin\ReorderSection( $container->pool_repository(), $suppliers, $reorder_policies, $reorder_suggestions, $container->quantity_formatter() ) );
		$container->screen_section_catalog()->register( new Premium\Admin\MaterialCostsSection( $material_economics, $container->mapping_repository() ) );
		$container->screen_section_catalog()->register( new Premium\Admin\ReservationsSection( $stock_holds, $container->pool_repository(), $container->quantity_formatter(), $safety_stock, $supply_projections ) );
		( new Premium\Admin\ReceivingController( $suppliers, $container->pool_repository(), $container->unit_registry(), $receiving ) )->register();
		( new Premium\Admin\BatchOperationsController( $batch_operations, $batches, $container->unit_registry(), $batch_transfers ) )->register();
		( new Premium\Admin\BatchExpiryController( $batch_expiry ) )->register();
		( new Premium\Admin\ReorderController( $reorder_policies, $container->pool_repository(), $suppliers, $container->unit_registry() ) )->register();
		( new Premium\Admin\CsvExchangeController( $csv_exchange ) )->register();
		( new Premium\Admin\SupplyStateController( $stock_hold_service, $container->pool_repository(), $container->unit_registry(), $safety_stock ) )->register();
		$renewal_snapshots = new WooCommerce\OrderItemSnapshotter( $container->mapping_repository(), $container->calculator_registry() );
		( new Premium\Integrations\SubscriptionRenewalAdapter( $renewal_snapshots, $reservation_service ) )->register();
		$mobile_snapshots = new WooCommerce\OrderItemSnapshotter( $container->mapping_repository(), $container->calculator_registry() );
		( new Premium\Integrations\MobileOrderAdapter( $mobile_snapshots, $reservation_service ) )->register();
		$batch_expiry_evaluator = new Premium\Batches\BatchExpiryEvaluator( $batches, $batch_expiry, $container->quantity_formatter(), $alert_channels, $alert_deliveries );
		$batch_expiry_evaluator->register();
		register_deactivation_hook( LAQI_LUSM_FILE, array( $reservation_service, 'unschedule' ) );
		register_deactivation_hook( LAQI_LUSM_FILE, array( $batch_expiry_evaluator, 'unschedule' ) );
		do_action( 'laqi_lusm_premium_ready', $container );
	}
);
