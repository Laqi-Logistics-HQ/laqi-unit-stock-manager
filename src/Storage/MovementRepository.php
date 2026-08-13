<?php
/**
 * Stock movement read repository.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Storage;

defined( 'ABSPATH' ) || exit;

use wpdb;

/**
 * Provides immutable ledger rows without exposing SQL to screen modules.
 */
final class MovementRepository {

	/**
	 * Database connection.
	 *
	 * @var wpdb
	 */
	private $db;

	/** Constructor.
	 *
	 * @param wpdb $db WordPress database connection.
	 */
	public function __construct( wpdb $db ) {
		$this->db = $db;
	}

	/**
	 * Get the latest correctness movements.
	 *
	 * @param int $limit Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function recent( int $limit = 50 ): array {
		$limit = max( 1, min( 100, $limit ) );
		$rows  = $this->db->get_results(
			$this->db->prepare(
				'SELECT m.id, m.pool_id, m.type, m.delta_base, m.balance_base, m.source_type, m.source_id, m.actor_id, m.reason, m.created_at, p.name AS pool_name, p.family, p.display_unit FROM ' . Schema::table( 'movements' ) . ' m LEFT JOIN ' . Schema::table( 'pools' ) . ' p ON p.id = m.pool_id ORDER BY m.id DESC LIMIT %d',
				$limit
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}
}
