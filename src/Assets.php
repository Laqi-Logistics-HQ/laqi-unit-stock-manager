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
 * Handles are prefixed with the plugin slug to avoid collisions. File
 * modification times invalidate cached assets as soon as their contents change.
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
		$is_workspace = 'product_page_laqi-unit-stock-manager' === $hook_suffix;
		$screen       = get_current_screen();
		$is_product   = in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) && $screen && 'product' === $screen->post_type;
		if ( ! $is_workspace && ! $is_product ) {
			return;
		}

		$style_version  = (string) filemtime( LAQI_LUSM_PATH . 'assets/css/admin.css' );
		$script_version = (string) filemtime( LAQI_LUSM_PATH . 'assets/js/admin.js' );

		wp_enqueue_style(
			'laqi-unit-stock-manager-admin',
			LAQI_LUSM_URL . 'assets/css/admin.css',
			array(),
			$style_version
		);

		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_script(
			'laqi-unit-stock-manager-admin',
			LAQI_LUSM_URL . 'assets/js/admin.js',
			array( 'jquery', 'wc-enhanced-select' ),
			$script_version,
			true
		);
		wp_localize_script(
			'laqi-unit-stock-manager-admin',
			'laqi_lusm_pool_search',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'laqi_lusm_search_pools' ),
			)
		);
		wp_enqueue_style( 'woocommerce_admin_styles' );
	}
}
