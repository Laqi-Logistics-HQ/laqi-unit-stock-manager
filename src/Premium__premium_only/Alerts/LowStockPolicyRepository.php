<?php
/**
 * Paid low-stock policy persistence.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Alerts;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Storage\Schema;
use RuntimeException;
use wpdb;

/** Owns the low-stock keys inside the pool policy extension envelope. */
final class LowStockPolicyRepository {
	/** Database connection.
	 *
	 * @var wpdb
	 */
	private $db;

	/** Constructor.
	 *
	 * @param wpdb $db Database.
	 */
	public function __construct( wpdb $db ) {
		$this->db = $db;
	}

	/** Read one policy.
	 *
	 * @param int $pool_id Pool ID.
	 * @return array<string, mixed>|null
	 */
	public function find( int $pool_id ): ?array {
		$json = $this->db->get_var( $this->db->prepare( 'SELECT policy_json FROM ' . Schema::table( 'pools' ) . ' WHERE id = %d', $pool_id ) );
		if ( null === $json ) {
			return null;
		}
		$policy = json_decode( (string) $json, true );
		return is_array( $policy ) && isset( $policy['low_stock'] ) && is_array( $policy['low_stock'] ) ? $policy['low_stock'] : null;
	}

	/** Save one policy without replacing other extension keys.
	 *
	 * @param int               $pool_id Pool ID.
	 * @param int               $threshold_base Warning threshold.
	 * @param array<int,string> $recipients Email recipients.
	 * @param int               $critical_base Critical threshold.
	 * @param int               $reminder_hours Reminder interval, or zero.
	 * @param int               $quiet_start Quiet start hour, or -1.
	 * @param int               $quiet_end Quiet end hour, or -1.
	 * @return void
	 * @throws RuntimeException When the pool cannot be updated.
	 */
	public function save( int $pool_id, int $threshold_base, array $recipients, int $critical_base = 0, int $reminder_hours = 0, int $quiet_start = -1, int $quiet_end = -1 ): void {
		$json                = $this->db->get_var( $this->db->prepare( 'SELECT policy_json FROM ' . Schema::table( 'pools' ) . ' WHERE id = %d', $pool_id ) );
		$policy              = json_decode( (string) $json, true );
		$policy              = is_array( $policy ) ? $policy : array();
		$existing            = isset( $policy['low_stock'] ) && is_array( $policy['low_stock'] ) ? $policy['low_stock'] : array();
		$policy['low_stock'] = array(
			'threshold_base' => $threshold_base,
			'critical_base'  => $critical_base,
			'recipients'     => array_values( $recipients ),
			'is_low'         => ! empty( $existing['is_low'] ),
			'severity'       => isset( $existing['severity'] ) ? (string) $existing['severity'] : ( ! empty( $existing['is_low'] ) ? 'warning' : 'healthy' ),
			'last_sent_at'   => isset( $existing['last_sent_at'] ) ? (int) $existing['last_sent_at'] : 0,
			'reminder_hours' => $reminder_hours,
			'quiet_start'    => $quiet_start,
			'quiet_end'      => $quiet_end,
		);
		$updated             = $this->db->update( Schema::table( 'pools' ), array( 'policy_json' => wp_json_encode( $policy ) ), array( 'id' => $pool_id ), array( '%s' ), array( '%d' ) );
		if ( false === $updated ) {
			throw new RuntimeException( 'Could not save the low-stock policy.' );
		}
	}

	/** Persist only the threshold state.
	 *
	 * @param int  $pool_id Pool ID.
	 * @param bool $is_low  Low state.
	 * @return void
	 */
	public function set_low_state( int $pool_id, bool $is_low ): void {
		$policy = $this->find( $pool_id );
		if ( null === $policy ) {
			return;
		}
		$policy['is_low']      = $is_low;
		$json                  = $this->db->get_var( $this->db->prepare( 'SELECT policy_json FROM ' . Schema::table( 'pools' ) . ' WHERE id = %d', $pool_id ) );
		$envelope              = json_decode( (string) $json, true );
		$envelope              = is_array( $envelope ) ? $envelope : array();
		$envelope['low_stock'] = $policy;
		$this->db->update( Schema::table( 'pools' ), array( 'policy_json' => wp_json_encode( $envelope ) ), array( 'id' => $pool_id ), array( '%s' ), array( '%d' ) );
	}

	/** Persist evaluator state without replacing settings.
	 *
	 * @param int    $pool_id Pool ID.
	 * @param string $severity Healthy, warning, or critical.
	 * @param int    $last_sent_at Last successful delivery timestamp.
	 * @return void
	 */
	public function set_evaluation_state( int $pool_id, string $severity, int $last_sent_at ): void {
		$policy = $this->find( $pool_id );
		if ( null === $policy ) {
			return;
		}
		$policy['severity']     = $severity;
		$policy['is_low']       = 'healthy' !== $severity;
		$policy['last_sent_at'] = $last_sent_at;
		$this->replace_low_stock( $pool_id, $policy );
	}

	/** Replace only the low-stock policy inside its envelope.
	 *
	 * @param int                  $pool_id Pool ID.
	 * @param array<string, mixed> $policy Low-stock policy.
	 * @return void
	 */
	private function replace_low_stock( int $pool_id, array $policy ): void {
		$json                  = $this->db->get_var( $this->db->prepare( 'SELECT policy_json FROM ' . Schema::table( 'pools' ) . ' WHERE id = %d', $pool_id ) );
		$envelope              = json_decode( (string) $json, true );
		$envelope              = is_array( $envelope ) ? $envelope : array();
		$envelope['low_stock'] = $policy;
		$this->db->update( Schema::table( 'pools' ), array( 'policy_json' => wp_json_encode( $envelope ) ), array( 'id' => $pool_id ), array( '%s' ), array( '%d' ) );
	}

	/** Configured pool IDs. @return array<int,int> */
	public function configured_ids(): array {
		return array_map(
			static function ( array $row ): int {
				return (int) $row['id'];
			},
			$this->configured()
		);
	}

	/** List configured policies with pool context. @return array<int,array<string,mixed>> */
	public function configured(): array {
		$rows = $this->db->get_results( 'SELECT id, name, family, display_unit, quantity_base, policy_json FROM ' . Schema::table( 'pools' ) . " WHERE policy_json IS NOT NULL AND policy_json != '' ORDER BY name ASC", ARRAY_A );
		return is_array( $rows ) ? array_values(
			array_filter(
				$rows,
				static function ( array $row ): bool {
					$policy = json_decode( (string) $row['policy_json'], true );
					return is_array( $policy ) && isset( $policy['low_stock'] );
				}
			)
		) : array();
	}
}
