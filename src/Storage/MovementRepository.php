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
	 * @param int $offset Number of recent rows to skip.
	 * @return array<int, array<string, mixed>>
	 */
	public function recent( int $limit = 50, int $offset = 0 ): array {
		$limit  = max( 1, min( 100, $limit ) );
		$offset = max( 0, $offset );
		$rows   = $this->db->get_results(
			$this->db->prepare(
				'SELECT m.id, m.pool_id, m.type, m.delta_base, m.balance_base, m.source_type, m.source_id, m.actor_id, m.reason, m.created_at, p.name AS pool_name, p.family, p.display_unit FROM ' . Schema::table( 'movements' ) . ' m LEFT JOIN ' . Schema::table( 'pools' ) . ' p ON p.id = m.pool_id ORDER BY m.id DESC LIMIT %d OFFSET %d',
				$limit,
				$offset
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/** Get the total number of immutable movements. @return int */
	public function count(): int {
		return (int) $this->db->get_var( 'SELECT COUNT(*) FROM ' . Schema::table( 'movements' ) );
	}

	/**
	 * Search immutable movements for registered operational modules.
	 *
	 * @param string $term   Pool, type, source, or reason search.
	 * @param int    $limit  Maximum rows.
	 * @param int    $offset Matching rows to skip.
	 * @return array<int, array<string, mixed>>
	 */
	public function search( string $term, int $limit = 50, int $offset = 0 ): array {
		$limit  = max( 1, min( 500, $limit ) );
		$offset = max( 0, $offset );
		$like   = '%' . $this->db->esc_like( $term ) . '%';
		$rows   = $this->db->get_results(
			$this->db->prepare(
				'SELECT m.id, m.pool_id, m.type, m.delta_base, m.balance_base, m.source_type, m.source_id, m.actor_id, m.reason, m.created_at, p.name AS pool_name, p.family, p.display_unit, u.display_name AS actor_name FROM ' . Schema::table( 'movements' ) . ' m LEFT JOIN ' . Schema::table( 'pools' ) . ' p ON p.id = m.pool_id LEFT JOIN ' . $this->db->users . ' u ON u.ID = m.actor_id WHERE p.name LIKE %s OR m.type LIKE %s OR m.source_type LIKE %s OR m.reason LIKE %s OR u.display_name LIKE %s OR u.user_login LIKE %s ORDER BY m.id DESC LIMIT %d OFFSET %d',
				$like,
				$like,
				$like,
				$like,
				$like,
				$like,
				$limit,
				$offset
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/** Count movements matching an operational search.
	 *
	 * @param string $term Search.
	 * @return int
	 */
	public function count_search( string $term ): int {
		$like = '%' . $this->db->esc_like( $term ) . '%';
		return (int) $this->db->get_var(
			$this->db->prepare(
				'SELECT COUNT(*) FROM ' . Schema::table( 'movements' ) . ' m LEFT JOIN ' . Schema::table( 'pools' ) . ' p ON p.id = m.pool_id LEFT JOIN ' . $this->db->users . ' u ON u.ID = m.actor_id WHERE p.name LIKE %s OR m.type LIKE %s OR m.source_type LIKE %s OR m.reason LIKE %s OR u.display_name LIKE %s OR u.user_login LIKE %s',
				$like,
				$like,
				$like,
				$like,
				$like,
				$like
			)
		);
	}

	/**
	 * Get movements attributed to one WordPress user.
	 *
	 * @param int $actor_id User ID.
	 * @param int $page     One-based export page.
	 * @param int $limit    Rows per page.
	 * @return array<int, array<string, mixed>>
	 */
	public function for_actor( int $actor_id, int $page = 1, int $limit = 100 ): array {
		$limit  = max( 1, min( 500, $limit ) );
		$offset = ( max( 1, $page ) - 1 ) * $limit;
		$rows   = $this->db->get_results(
			$this->db->prepare(
				'SELECT id, pool_id, type, delta_base, balance_base, source_type, source_id, reason, created_at FROM ' . Schema::table( 'movements' ) . ' WHERE actor_id = %d ORDER BY id ASC LIMIT %d OFFSET %d',
				$actor_id,
				$limit,
				$offset
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Remove the user association while retaining the immutable stock ledger.
	 *
	 * @param int $actor_id User ID.
	 * @return int Number of anonymized movements.
	 */
	public function anonymize_actor( int $actor_id ): int {
		$updated = $this->db->update(
			Schema::table( 'movements' ),
			array( 'actor_id' => 0 ),
			array( 'actor_id' => $actor_id ),
			array( '%d' ),
			array( '%d' )
		);

		return false === $updated ? 0 : $updated;
	}
}
