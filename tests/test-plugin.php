<?php
/**
 * Basic smoke test.
 *
 * @package LaqiUnitStockManager
 */

/**
 * Ensures the plugin boots.
 */
class Test_Plugin extends WP_UnitTestCase {

	/**
	 * The main class should be available once the plugin is loaded.
	 */
	public function test_plugin_class_exists(): void {
		$this->assertTrue( class_exists( '\LaqiUnitStockManager\Plugin' ) );
	}

	/**
	 * The version constant should be defined.
	 */
	public function test_version_constant_defined(): void {
		$this->assertTrue( defined( 'LAQI_LUSM_VERSION' ) );
		$this->assertSame( '1.0', LAQI_LUSM_API_VERSION );
	}

	/**
	 * Add-ons receive a versioned context instead of the internal container.
	 */
	public function test_public_extension_context_is_available(): void {
		$context = new \LaqiUnitStockManager\Extension\ExtensionContext( new \LaqiUnitStockManager\Container() );

		$this->assertInstanceOf( \LaqiUnitStockManager\Extension\ExtensionContextInterface::class, $context );
		$this->assertSame( LAQI_LUSM_API_VERSION, $context->api_version() );
		$this->assertInstanceOf( \LaqiUnitStockManager\Unit\UnitRegistry::class, $context->units() );
		$this->assertInstanceOf( \LaqiUnitStockManager\Admin\ScreenSectionCatalog::class, $context->admin_sections() );
		$this->assertInstanceOf( \LaqiUnitStockManager\Diagnostics\MappingDiagnostics::class, $context->mapping_diagnostics() );
		$this->assertInstanceOf( \LaqiUnitStockManager\Extension\PoolPolicyStore::class, $context->pool_policies() );
		$this->assertSame( 1, did_action( 'laqi_lusm_extensions_ready' ) );
	}

	/**
	 * Public services retain identity for the complete request.
	 */
	public function test_extension_context_returns_shared_services(): void {
		$context = new \LaqiUnitStockManager\Extension\ExtensionContext( new \LaqiUnitStockManager\Container() );

		$this->assertSame( $context->pools(), $context->pools() );
		$this->assertSame( $context->mappings(), $context->mappings() );
		$this->assertSame( $context->pool_policies(), $context->pool_policies() );
		$this->assertSame( $context->mapping_diagnostics(), $context->mapping_diagnostics() );
		$this->assertSame( $context->movement_history(), $context->movement_history() );
		$this->assertSame( $context->stock_mutations(), $context->stock_mutations() );
		$this->assertSame( $context->stock_adjustments(), $context->stock_adjustments() );
		$this->assertSame( $context->availability(), $context->availability() );
		$this->assertSame( $context->quantities(), $context->quantities() );
		$this->assertSame( $context->pool_presenter(), $context->pool_presenter() );
		$this->assertSame( $context->movement_presenter(), $context->movement_presenter() );
	}

	/**
	 * Add-ons can check an explicit supported API range before registering.
	 */
	public function test_extension_api_compatibility_range(): void {
		$compatibility = \LaqiUnitStockManager\Extension\ApiCompatibility::class;

		$this->assertTrue( $compatibility::supports( '1.0', '2.0' ) );
		$this->assertFalse( $compatibility::supports( '1.1', '2.0' ) );
		$this->assertFalse( $compatibility::supports( '0.1', '1.0' ) );
	}

	/**
	 * WooCommerce is loaded by the test bootstrap, so the dependency guard should
	 * report it active and the plugin should register its feature-compatibility
	 * declaration on the proper hook (calling it directly is flagged by WC as
	 * incorrect usage, so we assert the wiring, not a direct call).
	 */
	public function test_woocommerce_dependency_is_satisfied(): void {
		$plugin = \LaqiUnitStockManager\Plugin::instance();
		$this->assertTrue( $plugin->is_woocommerce_active() );
		$this->assertNotFalse( has_action( 'before_woocommerce_init' ) );
	}

	/**
	 * Paid modules receive the shared container through the removable bootstrap.
	 */
	public function test_optional_paid_bootstrap_registers_composition_hook(): void {
		$this->assertNotFalse( has_action( 'laqi_lusm_booted' ) );
	}

	/**
	 * Extracted adjustment policies leave only Free's extension hooks behind.
	 */
	public function test_adjustment_policy_implementation_is_not_bundled(): void {
		$this->assertFalse( class_exists( '\LaqiUnitStockManager\Premium\Approvals\AdjustmentPolicy' ) );
		$this->assertTrue( apply_filters( 'laqi_lusm_adjustment_authorized', true, 1, 1, 'manual_add', 1, 'Count' ) );
		$this->assertSame( array(), apply_filters( 'laqi_lusm_adjustment_reason_templates', array(), 'manual' ) );
	}

	/**
	 * The typed stock-loss UI and movement catalog live in the Pro add-on.
	 */
	public function test_stock_loss_implementation_is_not_bundled(): void {
		$this->assertFalse( class_exists( '\LaqiUnitStockManager\Premium\Inventory\StockLossTypeCatalog' ) );
		$this->assertFalse( class_exists( '\LaqiUnitStockManager\Premium\Admin\StockLossSection' ) );
		$this->assertFalse( class_exists( '\LaqiUnitStockManager\Premium\Admin\StockLossController' ) );
		$this->assertFalse( has_action( 'admin_post_laqi_lusm_record_loss' ) );
	}

	/**
	 * Optional grouped-product integration belongs only to the Pro add-on.
	 */
	public function test_grouped_product_adapter_is_not_bundled(): void {
		$this->assertFalse( class_exists( '\LaqiUnitStockManager\Premium\Integrations\GroupedProductAdapter' ) );
		$this->assertFalse( has_filter( 'laqi_lusm_include_cart_item_stock_demand' ) );
		$this->assertFalse( has_filter( 'laqi_lusm_include_checkout_item_stock_demand' ) );
		$this->assertFalse( has_filter( 'laqi_lusm_include_order_item_stock_demand' ) );
	}

	/**
	 * Read-only stock anomaly workflows belong only to the Pro add-on.
	 */
	public function test_stock_anomaly_implementation_is_not_bundled(): void {
		$this->assertFalse( class_exists( '\LaqiUnitStockManager\Premium\Anomalies\StockAnomalyDetector' ) );
		$this->assertFalse( class_exists( '\LaqiUnitStockManager\Premium\Admin\StockAnomaliesSection' ) );
		$this->assertFalse( has_filter( 'laqi_lusm_large_adjustment_ratio' ) );
		$this->assertFalse( has_filter( 'laqi_lusm_stock_anomalies' ) );
	}

	/**
	 * Searchable ledger presentation and export belong only to the Pro add-on.
	 */
	public function test_movement_ledger_implementation_is_not_bundled(): void {
		$this->assertFalse( class_exists( '\LaqiUnitStockManager\Premium\Admin\MovementLedgerSection' ) );
		$this->assertFalse( class_exists( '\LaqiUnitStockManager\Premium\Admin\MovementLedgerExportController' ) );
		$this->assertFalse( has_action( 'admin_post_laqi_lusm_export_ledger' ) );
	}

	/**
	 * Forecast administration belongs only to the Pro add-on.
	 */
	public function test_forecast_admin_implementation_is_not_bundled(): void {
		$this->assertFalse( class_exists( '\LaqiUnitStockManager\Premium\Admin\ForecastSection' ) );
		$this->assertFalse( class_exists( '\LaqiUnitStockManager\Premium\Admin\ForecastController' ) );
		$this->assertFalse( has_action( 'admin_post_laqi_lusm_save_forecast' ) );
	}
}
