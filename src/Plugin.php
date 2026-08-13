<?php
/**
 * Main plugin class.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager;

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

		// WordPress privacy tools. Replace the boilerplate's no-data callbacks
		// when this plugin stores or transmits personal data.
		( new Privacy() )->register();

		// Register CSS/JS enqueues (admin + frontend).
		( new Assets() )->register();

		// TODO: wire up the plugin's own hooks here (admin pages, REST routes,
		// order/product integrations, etc.).
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
		// TODO: create DB tables/options, set defaults, flush rewrite rules if a
		// custom post type / endpoint was registered, etc.
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
