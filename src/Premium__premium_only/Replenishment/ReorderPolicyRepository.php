<?php
/**
 * Paid reorder policy persistence.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Replenishment;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Storage\Schema;
use RuntimeException;
use wpdb;

/** Owns replenishment settings in the shared pool policy envelope. */
final class ReorderPolicyRepository {
	/** Database.
	 *
	 * @var wpdb
	 */ private $db;
	/** Constructor.
	 *
	 * @param wpdb $db Database.
	 */
	public function __construct( wpdb $db ) {
		$this->db = $db; }
	/** Policy.
	 *
	 * @param int $pool_id Pool ID.
	 * @return array<string,int>|null
	 */
	public function find( int $pool_id ): ?array {
		$envelope = $this->envelope( $pool_id );
		if ( ! isset( $envelope['reorder'] ) || ! is_array( $envelope['reorder'] ) ) {
			return null; }
		return array(
			'preferred_pack_id' => absint( $envelope['reorder']['preferred_pack_id'] ?? 0 ),
			'safety_stock_base' => max( 0, (int) ( $envelope['reorder']['safety_stock_base'] ?? 0 ) ),
		);
	}
	/** Save policy without replacing other modules.
	 *
	 * @param int $pool_id Pool ID.
	 * @param int $preferred_pack_id Pack ID.
	 * @param int $safety_stock_base Safety stock.
	 * @return void
	 * @throws RuntimeException When persistence fails.
	 */
	public function save( int $pool_id, int $preferred_pack_id, int $safety_stock_base ): void {
		$envelope            = $this->envelope( $pool_id );
		$envelope['reorder'] = array(
			'preferred_pack_id' => $preferred_pack_id,
			'safety_stock_base' => max( 0, $safety_stock_base ),
		);
		if ( false === $this->db->update( Schema::table( 'pools' ), array( 'policy_json' => wp_json_encode( $envelope ) ), array( 'id' => $pool_id ), array( '%s' ), array( '%d' ) ) ) {
			throw new RuntimeException( 'Could not save the reorder policy.' ); }
	}
	/** Configured pool IDs. @return array<int,int> */
	public function configured_ids(): array {
		$rows = $this->db->get_results( 'SELECT id, policy_json FROM ' . Schema::table( 'pools' ) . " WHERE policy_json IS NOT NULL AND policy_json != ''", ARRAY_A );
		$ids  = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$decoded = json_decode( (string) $row['policy_json'], true );
			if ( is_array( $decoded ) && isset( $decoded['reorder'] ) ) {
				$ids[] = (int) $row['id']; }
		}
		return $ids;
	}
	/** Policy envelope.
	 *
	 * @param int $pool_id Pool ID.
	 * @return array<string,mixed>
	 */
	private function envelope( int $pool_id ): array {
		$decoded = json_decode( (string) $this->db->get_var( $this->db->prepare( 'SELECT policy_json FROM ' . Schema::table( 'pools' ) . ' WHERE id = %d', $pool_id ) ), true );
		return is_array( $decoded ) ? $decoded : array(); }
}
