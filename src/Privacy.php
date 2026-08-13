<?php
/**
 * WordPress personal-data and privacy-policy integration.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the privacy integration points every WooCommerce extension needs.
 *
 * The boilerplate callbacks intentionally report no data. Replace their bodies
 * when the plugin stores personal data, and add accurate suggested policy text.
 */
final class Privacy {

	/**
	 * Identifier WordPress uses for this plugin's privacy data.
	 */
	const GROUP = 'laqi-unit-stock-manager';

	/**
	 * Register privacy hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
		add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );
	}

	/**
	 * Register the personal-data exporter.
	 *
	 * @param array $exporters Registered exporters.
	 * @return array
	 */
	public function register_exporter( array $exporters ): array {
		$exporters[ self::GROUP ] = array(
			'exporter_friendly_name' => __( 'Laqi Unit Stock Manager for WooCommerce', 'laqi-unit-stock-manager' ),
			'callback'               => array( $this, 'export' ),
		);

		return $exporters;
	}

	/**
	 * Register the personal-data eraser.
	 *
	 * @param array $erasers Registered erasers.
	 * @return array
	 */
	public function register_eraser( array $erasers ): array {
		$erasers[ self::GROUP ] = array(
			'eraser_friendly_name' => __( 'Laqi Unit Stock Manager for WooCommerce', 'laqi-unit-stock-manager' ),
			'callback'             => array( $this, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * Export personal data belonging to an email address.
	 *
	 * Replace this no-data result when the plugin stores personal data.
	 *
	 * @param string $email_address The person's email address.
	 * @param int    $page          Export page.
	 * @return array{data: array, done: bool}
	 */
	public function export( string $email_address, int $page = 1 ): array {
		unset( $email_address, $page );

		return array(
			'data' => array(),
			'done' => true,
		);
	}

	/**
	 * Erase personal data belonging to an email address.
	 *
	 * Replace this no-data result when the plugin stores personal data. When data
	 * must be retained, return `items_retained` with an explanatory message.
	 *
	 * @param string $email_address The person's email address.
	 * @param int    $page          Erasure page.
	 * @return array{items_removed: bool, items_retained: bool, messages: array, done: bool}
	 */
	public function erase( string $email_address, int $page = 1 ): array {
		unset( $email_address, $page );

		return array(
			'items_removed'  => false,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * Add accurate suggested privacy-policy text before release.
	 *
	 * @return void
	 */
	public function add_privacy_policy_content(): void {
		// TODO: call wp_add_privacy_policy_content() with an accurate inventory
		// of stored data, retention, browser storage, and third-party sharing.
	}
}
