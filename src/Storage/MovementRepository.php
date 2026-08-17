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
		$limit  = max( 1, min( 500, $limit ) );
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
	 * Get movements attributed to one operational source.
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Source object ID.
	 * @param int    $limit       Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function for_source( string $source_type, int $source_id, int $limit = 100 ): array {
		$source_type = sanitize_key( $source_type );
		$source_id   = absint( $source_id );
		$limit       = max( 1, min( 500, $limit ) );
		if ( '' === $source_type || $source_id < 1 ) {
			return array();
		}

		$rows = $this->db->get_results(
			$this->db->prepare(
				'SELECT m.id, m.pool_id, m.type, m.delta_base, m.balance_base, m.source_type, m.source_id, m.actor_id, m.reason, m.created_at, p.name AS pool_name, p.family, p.display_unit FROM ' . Schema::table( 'movements' ) . ' m LEFT JOIN ' . Schema::table( 'pools' ) . ' p ON p.id = m.pool_id WHERE m.source_type = %s AND m.source_id = %d ORDER BY m.id DESC LIMIT %d',
				$source_type,
				$source_id,
				$limit
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get recent movements for one or more mapped pools.
	 *
	 * @param int[] $pool_ids Pool IDs.
	 * @param int   $limit Maximum rows.
	 * @param int   $offset Matching rows to skip.
	 * @return array<int, array<string, mixed>>
	 */
	public function recent_for_pools( array $pool_ids, int $limit = 50, int $offset = 0 ): array {
		$pool_ids = array_values( array_unique( array_filter( array_map( 'absint', $pool_ids ) ) ) );
		if ( array() === $pool_ids ) {
			return array();
		}
		$limit        = max( 1, min( 500, $limit ) );
		$offset       = max( 0, $offset );
		$placeholders = implode( ', ', array_fill( 0, count( $pool_ids ), '%d' ) );
		$sql          = 'SELECT m.id, m.pool_id, m.type, m.delta_base, m.balance_base, m.source_type, m.source_id, m.actor_id, m.reason, m.created_at, p.name AS pool_name, p.family, p.display_unit FROM ' . Schema::table( 'movements' ) . ' m LEFT JOIN ' . Schema::table( 'pools' ) . ' p ON p.id = m.pool_id WHERE m.pool_id IN (' . $placeholders . ') ORDER BY m.id DESC LIMIT %d OFFSET %d';
		$args         = array_merge( $pool_ids, array( $limit, $offset ) );
		$rows         = $this->db->get_results( $this->db->prepare( $sql, ...$args ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic placeholders are generated from validated integer IDs.
		return is_array( $rows ) ? $rows : array();
	}

	/** Count movements for one or more mapped pools.
	 *
	 * @param int[] $pool_ids Pool IDs.
	 * @return int
	 */
	public function count_for_pools( array $pool_ids ): int {
		$pool_ids = array_values( array_unique( array_filter( array_map( 'absint', $pool_ids ) ) ) );
		if ( array() === $pool_ids ) {
			return 0;
		}
		$placeholders = implode( ', ', array_fill( 0, count( $pool_ids ), '%d' ) );
		$sql          = 'SELECT COUNT(*) FROM ' . Schema::table( 'movements' ) . ' WHERE pool_id IN (' . $placeholders . ')';
		return (int) $this->db->get_var( $this->db->prepare( $sql, ...$pool_ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic placeholders are generated from validated integer IDs.
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

	/** Summarize sales consumption for forecasting modules.
	 *
	 * Replenishment, restoration, manual changes, and typed losses are excluded.
	 * Negative order edits count because they represent additional sold demand.
	 *
	 * @param int $pool_id Pool ID.
	 * @param int $days Calendar window.
	 * @return array{consumed_base:int,first_at:string,demand_days:int}
	 */
	public function consumption_summary( int $pool_id, int $days ): array {
		$days = max( 7, min( 365, $days ) );
		$row  = $this->db->get_row(
			$this->db->prepare(
				'SELECT COALESCE(SUM(CASE WHEN delta_base < 0 AND type IN ("order_reduction", "order_edit") THEN ABS(delta_base) ELSE 0 END), 0) AS consumed_base, COALESCE(MIN(created_at), "") AS first_at, COUNT(DISTINCT CASE WHEN delta_base < 0 AND type IN ("order_reduction", "order_edit") THEN DATE(created_at) ELSE NULL END) AS demand_days FROM ' . Schema::table( 'movements' ) . ' WHERE pool_id = %d AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)',
				$pool_id,
				$days
			),
			ARRAY_A
		);
		return is_array( $row ) ? array(
			'consumed_base' => (int) $row['consumed_base'],
			'first_at'      => (string) $row['first_at'],
			'demand_days'   => (int) $row['demand_days'],
		) : array(
			'consumed_base' => 0,
			'first_at'      => '',
			'demand_days'   => 0,
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
