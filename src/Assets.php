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
		wp_enqueue_script(
			'laqi-unit-stock-manager-admin',
			LAQI_LUSM_URL . 'assets/js/admin.js',
			array( 'jquery', 'wc-enhanced-select' ),
			LAQI_LUSM_VERSION,
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
		wp_localize_script(
			'laqi-unit-stock-manager-admin',
			'laqi_lusm_mobile_stocktake',
			array(
				'restUrl' => esc_url_raw( rest_url( 'laqi-lusm/v1/' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'strings' => array(
					'finding' => __( 'Finding stock…', 'laqi-unit-stock-manager' ),
					'saving'  => __( 'Saving count…', 'laqi-unit-stock-manager' ),
					'saved'   => __( 'Physical count saved.', 'laqi-unit-stock-manager' ),
					'camera'  => __( 'Point the camera at a barcode.', 'laqi-unit-stock-manager' ),
					'error'   => __( 'The stocktaking request could not be completed.', 'laqi-unit-stock-manager' ),
				),
			)
		);
		wp_enqueue_style( 'woocommerce_admin_styles' );
	}
}
