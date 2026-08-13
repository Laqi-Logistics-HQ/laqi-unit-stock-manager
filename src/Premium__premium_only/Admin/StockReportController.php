<?php
/**
 * Paid stock report controller.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Admin;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Admin\UnitStockPage;
use LaqiUnitStockManager\Premium\Reports\StockReportScheduler;
use LaqiUnitStockManager\Premium\Reports\StockReportSettings;

/** Saves report settings and handles send-now requests. */
final class StockReportController {
	/** Report settings.
	 *
	 * @var StockReportSettings
	 */
	private $settings;

	/** Report scheduler.
	 *
	 * @var StockReportScheduler
	 */
	private $scheduler;

	/** Constructor.
	 *
	 * @param StockReportSettings  $settings Settings.
	 * @param StockReportScheduler $scheduler Scheduler.
	 */
	public function __construct( StockReportSettings $settings, StockReportScheduler $scheduler ) {
		$this->settings  = $settings;
		$this->scheduler = $scheduler;
	}

	/** Register endpoints. @return void */
	public function register(): void {
		add_action( 'admin_post_laqi_lusm_save_stock_report', array( $this, 'save' ) );
		add_action( 'admin_post_laqi_lusm_send_stock_report', array( $this, 'send' ) );
	}

	/** Save report settings. @return void */
	public function save(): void {
		$this->authorize( 'laqi_lusm_save_stock_report' );
		// Nonce and capability were verified above.
		$raw       = isset( $_POST['recipients'] ) ? sanitize_textarea_field( wp_unslash( $_POST['recipients'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$parts     = preg_split( '/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY );
		$emails    = array_values( array_unique( array_filter( array_map( 'sanitize_email', is_array( $parts ) ? $parts : array() ), 'is_email' ) ) );
		$frequency = isset( $_POST['frequency'] ) ? sanitize_key( wp_unslash( $_POST['frequency'] ) ) : 'weekly'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$enabled   = ! empty( $_POST['enabled'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $enabled && array() === $emails ) {
			$this->redirect( 'report_error' );
		}
		$this->settings->save( $enabled, $frequency, $emails );
		$this->scheduler->sync_schedule();
		$this->redirect( 'report_saved' );
	}

	/** Send a report immediately. @return void */
	public function send(): void {
		$this->authorize( 'laqi_lusm_send_stock_report' );
		$this->redirect( $this->scheduler->send( true ) ? 'report_sent' : 'report_error' );
	}

	/** Authorize an administrator request.
	 *
	 * @param string $nonce Nonce action.
	 * @return void
	 */
	private function authorize( string $nonce ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage stock reports.', 'laqi-unit-stock-manager' ) );
		}
		check_admin_referer( $nonce );
	}

	/** Redirect to the Reports tab.
	 *
	 * @param string $result Result key.
	 * @return void
	 */
	private function redirect( string $result ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                    => UnitStockPage::SLUG,
					'section'                 => 'reports',
					'laqi_lusm_report_result' => $result,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
