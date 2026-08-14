<?php
/**
 * Paid scheduled stock reports.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Reports;

defined( 'ABSPATH' ) || exit;

/** Schedules and sends CSV snapshots through WordPress mail. */
final class StockReportScheduler {
	const CRON_HOOK      = 'laqi_lusm_send_stock_report';
	const HISTORY_OPTION = 'laqi_lusm_stock_report_history';

	/** Report settings.
	 *
	 * @var StockReportSettings
	 */ private $settings;
	/** Report builder.
	 *
	 * @var StockReportBuilder
	 */ private $builder;

	/** Constructor.
	 *
	 * @param StockReportSettings $settings Settings.
	 * @param StockReportBuilder  $builder Builder.
	 */
	public function __construct( StockReportSettings $settings, StockReportBuilder $builder ) {
		$this->settings = $settings;
		$this->builder  = $builder;
	}

	/** Register scheduling hooks. @return void */
	public function register(): void {
		add_filter( 'cron_schedules', array( $this, 'schedules' ) );
		add_action( 'init', array( $this, 'sync_schedule' ) );
		add_action( self::CRON_HOOK, array( $this, 'send' ) );
	}

	/** Add weekly recurrence.
	 *
	 * @param array<string,array<string,mixed>> $schedules Schedules.
	 * @return array<string,array<string,mixed>>
	 */
	public function schedules( array $schedules ): array {
		$schedules['laqi_lusm_weekly'] = array(
			'interval' => WEEK_IN_SECONDS,
			'display'  => __( 'Once weekly', 'laqi-unit-stock-manager' ),
		);
		return $schedules;
	}

	/** Match cron to current settings. @return void */
	public function sync_schedule(): void {
		$settings = $this->settings->get();
		$expected = 'daily' === $settings['frequency'] ? 'daily' : 'laqi_lusm_weekly';
		$current  = wp_get_scheduled_event( self::CRON_HOOK );
		if ( ! $settings['enabled'] || array() === $settings['recipients'] ) {
			$this->unschedule();
			return;
		}
		if ( false !== $current && $current->schedule === $expected ) {
			return;
		}
		$this->unschedule();
		wp_schedule_event( time() + HOUR_IN_SECONDS, $expected, self::CRON_HOOK );
	}

	/** Remove scheduled sends. @return void */
	public function unschedule(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/** Build and mail a snapshot.
	 *
	 * @param bool $manual Whether an administrator requested this delivery.
	 * @return bool
	 */
	public function send( bool $manual = false ): bool {
		$settings = $this->settings->get();
		if ( array() === $settings['recipients'] ) {
			return false;
		}
		$file = wp_tempnam( 'laqi-unit-stock-report.csv' );
		if ( ! is_string( $file ) ) {
			return false;
		}
		// WordPress has no filesystem abstraction for streaming a CSV attachment.
		$handle = fopen( $file, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $handle ) {
			wp_delete_file( $file );
			return false;
		}
		fwrite( $handle, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fputcsv( $handle, $this->builder->headers(), ',', '"', '' );
		$rows = $this->builder->rows();
		foreach ( $rows as $row ) {
			fputcsv( $handle, $row, ',', '"', '' );
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		$sent = wp_mail( $settings['recipients'], __( 'Unit stock report', 'laqi-unit-stock-manager' ), __( 'Your scheduled unit stock snapshot is attached.', 'laqi-unit-stock-manager' ), array(), array( $file ) );
		wp_delete_file( $file );
		$this->record_delivery( $sent, count( $settings['recipients'] ), count( $rows ), $manual );
		return $sent;
	}

	/** Record a bounded operational delivery history.
	 *
	 * @param bool $success Delivery result.
	 * @param int  $recipients Recipient count.
	 * @param int  $rows Snapshot row count.
	 * @param bool $manual Whether this was manually requested.
	 * @return void
	 */
	private function record_delivery( bool $success, int $recipients, int $rows, bool $manual ): void {
		$history = get_option( self::HISTORY_OPTION, array() );
		$history = is_array( $history ) ? $history : array();
		array_unshift(
			$history,
			array(
				'time'       => time(),
				'success'    => $success,
				'recipients' => $recipients,
				'rows'       => $rows,
				'trigger'    => $manual ? 'manual' : 'scheduled',
			)
		);
		update_option( self::HISTORY_OPTION, array_slice( $history, 0, 20 ), false );
	}
}
