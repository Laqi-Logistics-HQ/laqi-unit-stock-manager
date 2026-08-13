<?php
/**
 * CSS / JS asset registration and enqueueing.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and enqueues the plugin's stylesheets and scripts.
 *
 * Source assets live in /assets (css/, js/). Handles are prefixed with the
 * plugin slug to avoid collisions. Versions use the plugin version constant so
 * a release busts the browser cache.
 *
 * If you add a build step (blocks / React / SCSS via @wordpress/scripts), point
 * the URLs at /build instead and read deps + version from the generated
 * `*.asset.php` file (see README).
 */
final class Assets {

	/**
	 * Hook the enqueue callbacks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend' ) );
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

		wp_enqueue_script(
			'laqi-unit-stock-manager-admin',
			LAQI_LUSM_URL . 'assets/js/admin.js',
			array( 'wp-i18n' ), // exposes wp.i18n.__ for translatable JS strings.
			LAQI_LUSM_VERSION,
			true // load in footer.
		);

		// Make this script's strings translatable; pairs with `wp i18n make-json`.
		wp_set_script_translations(
			'laqi-unit-stock-manager-admin',
			'laqi-unit-stock-manager',
			LAQI_LUSM_PATH . 'languages'
		);
	}

	/**
	 * Enqueue front-end assets.
	 *
	 * @return void
	 */
	public function enqueue_frontend(): void {
		wp_enqueue_style(
			'laqi-unit-stock-manager-frontend',
			LAQI_LUSM_URL . 'assets/css/frontend.css',
			array(),
			LAQI_LUSM_VERSION
		);

		wp_enqueue_script(
			'laqi-unit-stock-manager-frontend',
			LAQI_LUSM_URL . 'assets/js/frontend.js',
			array( 'wp-i18n' ),
			LAQI_LUSM_VERSION,
			true
		);

		wp_set_script_translations(
			'laqi-unit-stock-manager-frontend',
			'laqi-unit-stock-manager',
			LAQI_LUSM_PATH . 'languages'
		);
	}
}
