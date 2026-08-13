<?php
/**
 * Batch expiry requests.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Admin;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Admin\UnitStockPage;
use LaqiUnitStockManager\Premium\Batches\BatchExpirySettings;

/** Saves the site-wide batch expiry policy. */
final class BatchExpiryController {

	/** Expiry policy storage.
	 *
	 * @var BatchExpirySettings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param BatchExpirySettings $settings Expiry policy storage.
	 */
	public function __construct( BatchExpirySettings $settings ) {
		$this->settings = $settings;
	}

	/** Register the request handler. */
	public function register(): void {
		add_action( 'admin_post_laqi_lusm_save_batch_expiry', array( $this, 'save' ) );
	}

	/** Save the expiry policy. */
	public function save(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage batch expiry settings.', 'laqi-unit-stock-manager' ) );
		}

		check_admin_referer( 'laqi_lusm_save_batch_expiry' );
		$days       = isset( $_POST['warning_days'] ) ? absint( $_POST['warning_days'] ) : 14;
		$raw        = isset( $_POST['recipients'] ) ? sanitize_textarea_field( wp_unslash( $_POST['recipients'] ) ) : '';
		$recipients = preg_split( '/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY );

		$this->settings->save( $days, is_array( $recipients ) ? $recipients : array() );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                       => UnitStockPage::SLUG,
					'section'                    => 'receiving',
					'laqi_lusm_receiving_result' => 'batch_expiry_saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
