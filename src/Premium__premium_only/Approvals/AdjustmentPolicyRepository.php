<?php
/**
 * Adjustment reason and approval-policy persistence.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Approvals;

defined( 'ABSPATH' ) || exit;

use InvalidArgumentException;

/** Stores one site-wide operational policy without adding stock tables. */
final class AdjustmentPolicyRepository {
	const OPTION = 'laqi_lusm_adjustment_policy';

	/** Read normalized policy settings. @return array<string, mixed> */
	public function get(): array {
		$value = get_option( self::OPTION, array() );
		$value = is_array( $value ) ? $value : array();
		return array(
			'templates'           => isset( $value['templates'] ) && is_array( $value['templates'] ) ? array_values( $value['templates'] ) : array( 'Cycle count', 'Damaged stock', 'Supplier correction', 'Data correction' ),
			'sensitive_ratio'     => isset( $value['sensitive_ratio'] ) ? (float) $value['sensitive_ratio'] : 0.25,
			'approver_capability' => isset( $value['approver_capability'] ) ? (string) $value['approver_capability'] : 'manage_options',
		);
	}

	/**
	 * Save normalized policy settings.
	 *
	 * @param string[] $templates           Reason labels.
	 * @param float    $sensitive_ratio     Ratio from zero through one.
	 * @param string   $approver_capability Approved capability key.
	 * @return void
	 * @throws InvalidArgumentException When policy input is invalid.
	 */
	public function save( array $templates, float $sensitive_ratio, string $approver_capability ): void {
		$templates = array_values(
			array_unique(
				array_filter(
					array_map( 'sanitize_text_field', array_slice( $templates, 0, 20 ) )
				)
			)
		);
		if ( $sensitive_ratio < 0 || $sensitive_ratio > 1 || ! in_array( $approver_capability, array( 'manage_options', 'manage_woocommerce' ), true ) ) {
			throw new InvalidArgumentException( 'Invalid adjustment approval policy.' );
		}
		update_option(
			self::OPTION,
			array(
				'templates'           => $templates,
				'sensitive_ratio'     => $sensitive_ratio,
				'approver_capability' => $approver_capability,
			),
			false
		);
	}
}
