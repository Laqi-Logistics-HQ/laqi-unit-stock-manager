<?php
/**
 * Paid stock report settings.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Reports;

defined( 'ABSPATH' ) || exit;

/** Owns the global scheduled-report configuration. */
final class StockReportSettings {
	const OPTION = 'laqi_lusm_stock_report_settings';

	/** Read normalized settings. @return array<string,mixed> */
	public function get(): array {
		$value = get_option( self::OPTION, array() );
		$value = is_array( $value ) ? $value : array();
		return array(
			'enabled'    => ! empty( $value['enabled'] ),
			'frequency'  => isset( $value['frequency'] ) && in_array( $value['frequency'], array( 'daily', 'weekly' ), true ) ? $value['frequency'] : 'weekly',
			'recipients' => isset( $value['recipients'] ) && is_array( $value['recipients'] ) ? array_values( array_filter( $value['recipients'], 'is_email' ) ) : array(),
		);
	}

	/** Save normalized settings.
	 *
	 * @param bool              $enabled Enabled.
	 * @param string            $frequency Daily or weekly.
	 * @param array<int,string> $recipients Emails.
	 * @return void
	 */
	public function save( bool $enabled, string $frequency, array $recipients ): void {
		update_option(
			self::OPTION,
			array(
				'enabled'    => $enabled,
				'frequency'  => 'daily' === $frequency ? 'daily' : 'weekly',
				'recipients' => array_values( array_unique( array_filter( $recipients, 'is_email' ) ) ),
			),
			false
		);
	}
}
