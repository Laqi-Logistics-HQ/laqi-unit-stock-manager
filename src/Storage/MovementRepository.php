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



	/** Columns every ledger read selects. */
	const COLUMNS = 'm.id, m.pool_id, m.type, m.delta_base, m.balance_base, m.source_type, m.source_id, m.actor_id, m.reason, m.created_at, p.name AS pool_name, p.family, p.display_unit, u.display_name AS actor_name';

	/**
	 * Count the movements matching the given filters.
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @return int
	 */
	public function count( array $filters = array() ): int {
		return $this->query( $filters )->count( $this->ledger_from() );
	}

	/**
	 * Read one ordered page of movements matching the given filters.
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @param int                  $limit   Maximum rows.
	 * @param int                  $offset  Row offset.
	 * @return array<int, array<string, mixed>>
	 */
	public function page( array $filters, int $limit = 25, int $offset = 0 ): array {
		return $this->query( $filters )->page( self::COLUMNS, $this->ledger_from(), 'm.id DESC', $limit, $offset );
	}

	/**
	 * Get the latest correctness movements.
	 *
	 * @param int $limit  Maximum rows.
	 * @param int $offset Number of recent rows to skip.
	 * @return array<int, array<string, mixed>>
	 */
	public function recent( int $limit = 50, int $offset = 0 ): array {
		return $this->page( array(), $limit, $offset );
	}

	/**
	 * Get the latest movements for a set of pools.
	 *
	 * @param int[] $pool_ids Pool IDs.
	 * @param int   $limit    Maximum rows.
	 * @param int   $offset   Number of recent rows to skip.
	 * @return array<int, array<string, mixed>>
	 */
	public function recent_for_pools( array $pool_ids, int $limit = 50, int $offset = 0 ): array {
		return array() === $this->pool_ids( $pool_ids ) ? array() : $this->page( array( 'pool_ids' => $pool_ids ), $limit, $offset );
	}

	/**
	 * Count the movements recorded against a set of pools.
	 *
	 * @param int[] $pool_ids Pool IDs.
	 * @return int
	 */
	public function count_for_pools( array $pool_ids ): int {
		return array() === $this->pool_ids( $pool_ids ) ? 0 : $this->count( array( 'pool_ids' => $pool_ids ) );
	}

	/**
	 * Search movements across pool, type, source, reason, and actor.
	 *
	 * @param string $term   Search term.
	 * @param int    $limit  Maximum rows.
	 * @param int    $offset Row offset.
	 * @return array<int, array<string, mixed>>
	 */
	public function search( string $term, int $limit = 50, int $offset = 0 ): array {
		return $this->page( array( 'search' => $term ), $limit, $offset );
	}

	/**
	 * Count the movements matching a search term.
	 *
	 * @param string $term Search term.
	 * @return int
	 */
	public function count_search( string $term ): int {
		return $this->count( array( 'search' => $term ) );
	}

	/**
	 * Movement types that appear in the ledger, for building filter choices.
	 *
	 * @return string[]
	 */
	public function used_types(): array {
		$rows = $this->db->get_col( 'SELECT DISTINCT type FROM ' . Schema::table( 'movements' ) . " WHERE type != '' ORDER BY type ASC" );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Movement sources that appear in the ledger, for building filter choices.
	 *
	 * @return string[]
	 */
	public function used_sources(): array {
		$rows = $this->db->get_col( 'SELECT DISTINCT source_type FROM ' . Schema::table( 'movements' ) . " WHERE source_type != '' ORDER BY source_type ASC" );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Actors who recorded at least one movement, for building filter choices.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function used_actors(): array {
		$rows = $this->db->get_results( 'SELECT DISTINCT m.actor_id AS id, u.display_name AS name FROM ' . Schema::table( 'movements' ) . ' m LEFT JOIN ' . $this->db->users . ' u ON u.ID = m.actor_id WHERE m.actor_id > 0 ORDER BY u.display_name ASC', ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Convert a site-local calendar boundary to the stored UTC timestamp.
	 *
	 * Movements are recorded in UTC, so a date chosen in the site's timezone
	 * has to be translated or the last day of a range would be cut short.
	 *
	 * @param string $date Calendar date, or an empty string.
	 * @param string $time Boundary time of day.
	 * @return string
	 */
	private static function utc_boundary( string $date, string $time ): string {
		return '' === $date ? '' : get_gmt_from_date( $date . ' ' . $time );
	}

	/**
	 * Shared ledger FROM clause.
	 *
	 * @return string
	 */
	private function ledger_from(): string {
		return Schema::table( 'movements' ) . ' m LEFT JOIN ' . Schema::table( 'pools' ) . ' p ON p.id = m.pool_id LEFT JOIN ' . $this->db->users . ' u ON u.ID = m.actor_id';
	}

	/**
	 * Normalize a requested pool ID list.
	 *
	 * @param mixed $pool_ids Requested IDs.
	 * @return int[]
	 */
	private function pool_ids( $pool_ids ): array {
		return is_array( $pool_ids ) ? array_values( array_unique( array_filter( array_map( 'absint', $pool_ids ) ) ) ) : array();
	}

	/**
	 * Ledger conditions.
	 *
	 * An actor filter of "0" means system-recorded movements, which is a real
	 * choice rather than an absent one, so it is applied separately from the
	 * integer filters that treat zero as unset.
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @return FilteredQuery
	 */
	private function query( array $filters ): FilteredQuery {
		$pool_ids = $this->pool_ids( $filters['pool_ids'] ?? null );
		$query    = ( new FilteredQuery( $this->db ) )
			->in_ints( 'm.pool_id', array() === $pool_ids ? null : $pool_ids )
			->positive_int( 'm.pool_id', $filters['pool_id'] ?? 0 )
			->text( 'm.type', $filters['type'] ?? '' )
			->text( 'm.source_type', $filters['source_type'] ?? '' )
			->from( 'm.created_at', self::utc_boundary( (string) ( $filters['from'] ?? '' ), '00:00:00' ) )
			->to( 'm.created_at', self::utc_boundary( (string) ( $filters['to'] ?? '' ), '23:59:59' ) )
			->search( array( 'm.reason' ), $filters['reason'] ?? '' )
			->search( array( 'p.name', 'm.type', 'm.source_type', 'm.reason', 'u.display_name', 'u.user_login' ), $filters['search'] ?? '' );
		$actor    = (string) ( $filters['actor'] ?? '' );

		return '' === $actor ? $query : $query->raw( 'm.actor_id = %d', array( (int) $actor ) );
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
