<?php
/**
 * Batch expiry notification settings.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Batches;

defined( 'ABSPATH' ) || exit;

/** Stores one site-wide operational expiry policy. */
final class BatchExpirySettings {

	const OPTION = 'laqi_lusm_batch_expiry_settings';

	/**
	 * Get the current settings.
	 *
	 * @return array{warning_days:int,recipients:array<int,string>}
	 */
	public function get(): array {
		$saved = get_option( self::OPTION, array() );

		return array(
			'warning_days' => isset( $saved['warning_days'] ) ? max( 0, min( 365, absint( $saved['warning_days'] ) ) ) : 14,
			'recipients'   => isset( $saved['recipients'] ) ? array_values( array_filter( (array) $saved['recipients'], 'is_email' ) ) : array_filter( array( get_option( 'admin_email' ) ), 'is_email' ),
		);
	}

	/**
	 * Save settings.
	 *
	 * @param int               $warning_days Days before expiry to notify.
	 * @param array<int,string> $recipients Recipient emails.
	 */
	public function save( int $warning_days, array $recipients ): void {
		update_option(
			self::OPTION,
			array(
				'warning_days' => max( 0, min( 365, $warning_days ) ),
				'recipients'   => array_values( array_filter( array_unique( $recipients ), 'is_email' ) ),
			),
			false
		);
	}
}
