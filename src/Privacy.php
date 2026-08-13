<?php
/**
 * WordPress personal-data and privacy-policy integration.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Storage\MovementRepository;

/**
 * Registers the privacy integration points every WooCommerce extension needs.
 *
 * Exports and anonymizes the user association stored on stock movements.
 */
final class Privacy {

	/**
	 * Identifier WordPress uses for this plugin's privacy data.
	 */
	const GROUP = 'laqi-unit-stock-manager';

	/**
	 * Stock movement persistence.
	 *
	 * @var MovementRepository
	 */
	private $movements;

	/**
	 * Constructor.
	 *
	 * @param MovementRepository $movements Movement repository.
	 */
	public function __construct( MovementRepository $movements ) {
		$this->movements = $movements;
	}

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
	 * @param string $email_address The person's email address.
	 * @param int    $page          Export page.
	 * @return array{data: array, done: bool}
	 */
	public function export( string $email_address, int $page = 1 ): array {
		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$rows = $this->movements->for_actor( $user->ID, $page );
		$data = array();
		foreach ( $rows as $row ) {
			$data[] = array(
				'group_id'    => self::GROUP,
				'group_label' => __( 'Unit stock movements', 'laqi-unit-stock-manager' ),
				'item_id'     => 'movement-' . $row['id'],
				'data'        => array(
					array(
						'name'  => __( 'Date', 'laqi-unit-stock-manager' ),
						'value' => $row['created_at'],
					),
					array(
						'name'  => __( 'Movement type', 'laqi-unit-stock-manager' ),
						'value' => $row['type'],
					),
					array(
						'name'  => __( 'Inventory pool ID', 'laqi-unit-stock-manager' ),
						'value' => $row['pool_id'],
					),
					array(
						'name'  => __( 'Stock change', 'laqi-unit-stock-manager' ),
						'value' => $row['delta_base'],
					),
					array(
						'name'  => __( 'Resulting balance', 'laqi-unit-stock-manager' ),
						'value' => $row['balance_base'],
					),
					array(
						'name'  => __( 'Reason', 'laqi-unit-stock-manager' ),
						'value' => $row['reason'],
					),
				),
			);
		}

		return array(
			'data' => $data,
			'done' => count( $rows ) < 100,
		);
	}

	/**
	 * Erase personal data belonging to an email address.
	 *
	 * @param string $email_address The person's email address.
	 * @param int    $page          Erasure page.
	 * @return array{items_removed: bool, items_retained: bool, messages: array, done: bool}
	 */
	public function erase( string $email_address, int $page = 1 ): array {
		unset( $page );
		$user    = get_user_by( 'email', $email_address );
		$removed = $user ? $this->movements->anonymize_actor( $user->ID ) : 0;
		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'       => $removed > 0 ? array( __( 'The user association was removed from stock movement records. The inventory ledger was retained for stock correctness.', 'laqi-unit-stock-manager' ) ) : array(),
			'done'           => true,
		);
	}

	/**
	 * Add suggested privacy-policy text.
	 *
	 * @return void
	 */
	public function add_privacy_policy_content(): void {
		$content  = '<p>' . __( 'Laqi Unit Stock Manager stores inventory pools, product-to-pool mappings, exact stock movements, and operational order references. When a logged-in user performs a manual adjustment, the movement records that WordPress user ID for accountability.', 'laqi-unit-stock-manager' ) . '</p>';
		$content .= '<p>' . __( 'The plugin does not copy customer names, email addresses, payment details, or shipping addresses into its own tables. It does not send inventory or personal data to Laqi Logistics or another external service. Movement records are retained until the plugin is deleted. A WordPress personal-data erasure request removes the user association while retaining the stock ledger required for inventory correctness.', 'laqi-unit-stock-manager' ) . '</p>';
		wp_add_privacy_policy_content( __( 'Laqi Unit Stock Manager for WooCommerce', 'laqi-unit-stock-manager' ), wp_kses_post( $content ) );
	}
}
