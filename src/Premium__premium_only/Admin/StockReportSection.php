<?php
/**
 * Paid stock report screen.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Admin;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Admin\ScreenSectionInterface;
use LaqiUnitStockManager\Premium\Reports\StockReportScheduler;
use LaqiUnitStockManager\Premium\Reports\StockReportSettings;

/** Configures scheduled operational snapshots. */
final class StockReportSection implements ScreenSectionInterface {
	/** Report settings.
	 *
	 * @var StockReportSettings
	 */
	private $settings;

	/** Constructor.
	 *
	 * @param StockReportSettings $settings Settings.
	 */
	public function __construct( StockReportSettings $settings ) {
		$this->settings = $settings;
	}

	/** Section ID. @return string */
	public function id(): string {
		return 'reports';
	}

	/** Section title. @return string */
	public function title(): string {
		return __( 'Reports', 'laqi-unit-stock-manager' );
	}

	/** Render the report controls. @return void */
	public function render(): void {
		$settings = $this->settings->get();
		$history  = get_option( StockReportScheduler::HISTORY_OPTION, array() );
		$history  = is_array( $history ) ? $history : array();
		$this->notice();
		?>
		<div class="laqi-lusm-setup-grid">
			<section class="card">
				<h2><?php esc_html_e( 'Scheduled stock report', 'laqi-unit-stock-manager' ); ?></h2>
				<p><?php esc_html_e( 'Email a CSV snapshot of every pool, current alert severity, and available forecast.', 'laqi-unit-stock-manager' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="laqi_lusm_save_stock_report" />
					<?php wp_nonce_field( 'laqi_lusm_save_stock_report' ); ?>
					<label for="laqi-lusm-report-enabled"><?php esc_html_e( 'Scheduled delivery', 'laqi-unit-stock-manager' ); ?></label>
					<label><input type="checkbox" id="laqi-lusm-report-enabled" name="enabled" value="1" <?php checked( $settings['enabled'] ); ?> /> <?php esc_html_e( 'Enable scheduled reports', 'laqi-unit-stock-manager' ); ?></label>
					<label for="laqi-lusm-report-frequency"><?php esc_html_e( 'Frequency', 'laqi-unit-stock-manager' ); ?></label>
					<select id="laqi-lusm-report-frequency" name="frequency">
						<option value="daily" <?php selected( $settings['frequency'], 'daily' ); ?>><?php esc_html_e( 'Daily', 'laqi-unit-stock-manager' ); ?></option>
						<option value="weekly" <?php selected( $settings['frequency'], 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'laqi-unit-stock-manager' ); ?></option>
					</select>
					<label for="laqi-lusm-report-recipients"><?php esc_html_e( 'Email recipients', 'laqi-unit-stock-manager' ); ?></label>
					<textarea id="laqi-lusm-report-recipients" name="recipients"><?php echo esc_textarea( implode( "\n", $settings['recipients'] ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Enter one address per line. Recipients are required only when scheduled delivery is enabled.', 'laqi-unit-stock-manager' ); ?></p>
					<?php submit_button( __( 'Save report schedule', 'laqi-unit-stock-manager' ) ); ?>
				</form>
			</section>
			<section class="card">
				<h2><?php esc_html_e( 'Send a snapshot now', 'laqi-unit-stock-manager' ); ?></h2>
				<p><?php esc_html_e( 'Uses the saved recipients and does not change the schedule.', 'laqi-unit-stock-manager' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="laqi_lusm_send_stock_report" />
					<?php wp_nonce_field( 'laqi_lusm_send_stock_report' ); ?>
					<?php submit_button( __( 'Send report now', 'laqi-unit-stock-manager' ), 'secondary' ); ?>
				</form>
			</section>
		</div>
		<section class="card">
			<h2><?php esc_html_e( 'Delivery history', 'laqi-unit-stock-manager' ); ?></h2>
			<?php if ( array() === $history ) : ?>
				<p><?php esc_html_e( 'No reports have been sent yet.', 'laqi-unit-stock-manager' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead><tr><th><?php esc_html_e( 'Attempted', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Trigger', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Recipients', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Pools', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Result', 'laqi-unit-stock-manager' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $history as $delivery ) : ?>
						<tr>
							<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $delivery['time'] ) ); ?></td>
							<td><?php echo esc_html( 'manual' === $delivery['trigger'] ? __( 'Manual', 'laqi-unit-stock-manager' ) : __( 'Scheduled', 'laqi-unit-stock-manager' ) ); ?></td>
							<td><?php echo esc_html( (string) $delivery['recipients'] ); ?></td>
							<td><?php echo esc_html( (string) $delivery['rows'] ); ?></td>
							<td><?php echo esc_html( ! empty( $delivery['success'] ) ? __( 'Sent', 'laqi-unit-stock-manager' ) : __( 'Failed', 'laqi-unit-stock-manager' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>
		<?php
	}

	/** Render a request-result notice. @return void */
	private function notice(): void {
		// The value only selects a fixed message and does not mutate state.
		$result   = isset( $_GET['laqi_lusm_report_result'] ) ? sanitize_key( wp_unslash( $_GET['laqi_lusm_report_result'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$messages = array(
			'report_saved' => __( 'Stock report schedule saved.', 'laqi-unit-stock-manager' ),
			'report_sent'  => __( 'Stock report sent.', 'laqi-unit-stock-manager' ),
			'report_error' => __( 'The stock report could not be saved or sent.', 'laqi-unit-stock-manager' ),
		);
		if ( isset( $messages[ $result ] ) ) {
			wp_admin_notice(
				$messages[ $result ],
				array(
					'type'        => 'report_error' === $result ? 'error' : 'success',
					'dismissible' => true,
				)
			);
		}
	}
}
