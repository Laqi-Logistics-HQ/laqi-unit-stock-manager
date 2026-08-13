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
	 * @param int               $threshold_base Exact normalized threshold.
	 * @param array<int,string> $recipients Email recipients.
	 * @return void
	 * @throws RuntimeException When the pool cannot be updated.
	 */
	public function save( int $pool_id, int $threshold_base, array $recipients ): void {
		$json                = $this->db->get_var( $this->db->prepare( 'SELECT policy_json FROM ' . Schema::table( 'pools' ) . ' WHERE id = %d', $pool_id ) );
		$policy              = json_decode( (string) $json, true );
		$policy              = is_array( $policy ) ? $policy : array();
		$policy['low_stock'] = array(
			'threshold_base' => $threshold_base,
			'recipients'     => array_values( $recipients ),
			'is_low'         => false,
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
