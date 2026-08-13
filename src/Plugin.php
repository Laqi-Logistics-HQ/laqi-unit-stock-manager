<?php
/**
 * Main plugin class.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager;

use LaqiUnitStockManager\Storage\Schema;
use LaqiUnitStockManager\Diagnostics\MappingDiagnostics;

defined( 'ABSPATH' ) || exit;

/**
 * Bootstraps the plugin (singleton).
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get the shared instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Wire up WordPress hooks. Called once on plugins_loaded.
	 *
	 * @return void
	 */
	public function boot(): void {
		$this->load_premium_features();

		// HPOS / Cart-Checkout-Blocks compatibility must be declared even when
		// WooCommerce is loading; declare it before bailing on a missing WC.
		add_action( 'before_woocommerce_init', array( $this, 'declare_woocommerce_compatibility' ) );

		// Translations load themselves. WordPress reads the Text Domain and Domain
		// Path headers and loads the .mo file when a string is first translated,
		// so there is nothing to register here. This needs WP 6.8+, which is why
		// "Requires at least" is 6.8: just-in-time loading only covers plugins
		// outside the WordPress.org directory from 6.8 onward.

		// WooCommerce is a hard dependency (see "Requires Plugins" header). The
		// header blocks activation without WC on WP 6.5+; this guard also covers
		// older cores and the case where WC is deactivated at runtime.
		if ( ! $this->is_woocommerce_active() ) {
			add_action( 'admin_notices', array( $this, 'render_missing_woocommerce_notice' ) );
			return;
		}

		$this->maybe_upgrade_schema();
		$container = new Container();
		$container->unit_registry();
		( new WooCommerce\CartValidator( $container->availability_service() ) )->register();
		$snapshotter = new WooCommerce\OrderItemSnapshotter( $container->mapping_repository(), $container->calculator_registry() );
		$snapshotter->register();
		( new WooCommerce\OrderStockLifecycle( $container->stock_mutation_service(), $snapshotter ) )->register();
		( new WooCommerce\ReducedOrderItemEditor( $container->stock_mutation_service() ) )->register();
		( new WooCommerce\StockStatusSynchronizer( $container->mapping_repository(), $container->availability_service() ) )->register();
		$sections = $container->screen_section_catalog();
		$sections->register( new Admin\PoolStockSection( $container->pool_repository(), $container->pool_presenter(), $container->mapping_repository(), $container->availability_service(), $container->quantity_formatter(), new MappingDiagnostics() ) );
		$sections->register( new Admin\SetupSection( $container->pool_repository(), $container->unit_registry(), $container->custom_unit_repository() ) );
		$sections->register( new Admin\ActivitySection( $container->movement_repository(), $container->movement_presenter() ) );
		( new Admin\UnitStockPage( $sections ) )->register();
		( new Admin\StockAdjustmentController( $container->stock_adjustment_service() ) )->register();
		( new Admin\SetupController( $container->pool_repository(), $container->mapping_repository(), $container->unit_registry(), $container->stock_mutation_service(), $container->custom_unit_repository(), new WooCommerce\ExistingStockMigrator( $container->stock_mutation_service() ) ) )->register();
		( new Rest\InventoryController( $container->pool_repository(), $container->pool_presenter(), $container->movement_repository(), $container->movement_presenter(), $container->stock_adjustment_service() ) )->register();

		// WordPress privacy tools. Replace the boilerplate's no-data callbacks
		// when this plugin stores or transmits personal data.
		( new Privacy() )->register();

		// Register CSS/JS enqueues (admin + frontend).
		( new Assets() )->register();

		// Pro modules self-register from a physically removable bootstrap. Shared
		// code never names a Pro class, so the WordPress.org build remains whole
		// when premium files are stripped.
		do_action( 'laqi_lusm_booted', $container );
	}

	/**
	 * Load optional Pro wiring when the build contains it.
	 *
	 * @return void
	 */
	private function load_premium_features(): void {
		$bootstrap = LAQI_LUSM_PATH . 'src/premium-bootstrap__premium_only.php';

		if ( is_readable( $bootstrap ) ) {
			require_once $bootstrap;
		}
	}

	/**
	 * Whether WooCommerce is loaded and available.
	 *
	 * @return bool
	 */
	public function is_woocommerce_active(): bool {
		return class_exists( '\WooCommerce' );
	}

	/**
	 * Declare compatibility with WooCommerce features.
	 *
	 * - custom_order_tables: High-Performance Order Storage (HPOS).
	 * - cart_checkout_blocks: the block-based Cart & Checkout.
	 *
	 * Declaring `true` means "this plugin does not break that feature". If you
	 * add code that bypasses Woo's CRUD/data stores, revisit these flags.
	 * Safe to call even when WooCommerce is not active.
	 *
	 * @return void
	 */
	public function declare_woocommerce_compatibility(): void {
		if ( ! class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			return;
		}
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			LAQI_LUSM_FILE,
			true
		);
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'cart_checkout_blocks',
			LAQI_LUSM_FILE,
			true
		);
	}

	/**
	 * Admin notice shown when WooCommerce (a hard dependency) is missing.
	 *
	 * @return void
	 */
	public function render_missing_woocommerce_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Laqi Unit Stock Manager for WooCommerce requires WooCommerce to be installed and active.', 'laqi-unit-stock-manager' )
		);
	}

	/**
	 * Runs on plugin activation.
	 *
	 * Keep this idempotent and fast. Schedule heavy work for a later hook.
	 *
	 * @return void
	 */
	public static function on_activate(): void {
		Schema::install();
	}

	/**
	 * Apply an additive schema upgrade when the installed version is stale.
	 *
	 * @return void
	 */
	private function maybe_upgrade_schema(): void {
		if ( Schema::VERSION !== (int) get_option( Schema::VERSION_OPTION, 0 ) ) {
			Schema::install();
		}
	}

	/**
	 * Runs on plugin deactivation.
	 *
	 * Clear scheduled events and transient state here; leave user data for
	 * uninstall.php so deactivation is reversible.
	 *
	 * @return void
	 */
	public static function on_deactivate(): void {
		// Every Action Scheduler hook introduced by this plugin MUST be listed
		// here so deactivation cannot leave callbacks that no longer exist.
		$action_scheduler_hooks = array(
			// 'laqi-unit-stock-manager_background_job',
		);
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			foreach ( $action_scheduler_hooks as $hook ) {
				as_unschedule_all_actions( $hook, null, 'laqi-unit-stock-manager' );
			}
		}

		// TODO: unschedule wp-cron events, flush rewrite rules, clear caches.
	}
}
