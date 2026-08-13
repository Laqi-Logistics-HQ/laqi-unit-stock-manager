<?php
/**
 * Admin stylesheet registration and enqueueing.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues the plugin's stylesheet only where its admin UI is rendered.
 *
 * Handles are prefixed with the plugin slug to avoid collisions. Versions use
 * the plugin version constant so a release busts the browser cache.
 */
final class Assets {

	/**
	 * Hook the enqueue callbacks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );
	}

	/**
	 * Enqueue admin-side assets.
	 *
	 * Guard on the current screen so you don't load globally across wp-admin.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin( string $hook_suffix ): void {
		if ( 'woocommerce_page_laqi-unit-stock-manager' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'laqi-unit-stock-manager-admin',
			LAQI_LUSM_URL . 'assets/css/admin.css',
			array(),
			LAQI_LUSM_VERSION
		);

		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_style( 'woocommerce_admin_styles' );
	}
}
