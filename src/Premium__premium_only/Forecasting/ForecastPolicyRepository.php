<?php
/**
 * Paid forecast policy persistence.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Forecasting;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Storage\Schema;
use wpdb;

/** Owns forecast settings inside the shared pool policy envelope. */
final class ForecastPolicyRepository {
	/** Database.
	 *
	 * @var wpdb
	 */
	private $db;
	/** Constructor.
	 *
	 * @param wpdb $db Database.
	 */
	public function __construct( wpdb $db ) {
		$this->db = $db; }
	/** Demand window.
	 *
	 * @param int $pool_id Pool ID.
	 * @return int
	 */
	public function window( int $pool_id ): int {
		$envelope = $this->envelope( $pool_id );
		return isset( $envelope['forecast']['window_days'] ) ? max( 7, min( 365, absint( $envelope['forecast']['window_days'] ) ) ) : 30;
	}
	/** Save window.
	 *
	 * @param int $pool_id Pool ID.
	 * @param int $days Days.
	 * @return void
	 */
	public function save_window( int $pool_id, int $days ): void {
		$envelope             = $this->envelope( $pool_id );
		$envelope['forecast'] = array( 'window_days' => max( 7, min( 365, $days ) ) );
		$this->db->update( Schema::table( 'pools' ), array( 'policy_json' => wp_json_encode( $envelope ) ), array( 'id' => $pool_id ), array( '%s' ), array( '%d' ) );
	}
	/** Read envelope.
	 *
	 * @param int $pool_id Pool ID.
	 * @return array<string,mixed>
	 */
	private function envelope( int $pool_id ): array {
		$value   = $this->db->get_var( $this->db->prepare( 'SELECT policy_json FROM ' . Schema::table( 'pools' ) . ' WHERE id = %d', $pool_id ) );
		$decoded = json_decode( (string) $value, true );
		return is_array( $decoded ) ? $decoded : array();
	}
}
