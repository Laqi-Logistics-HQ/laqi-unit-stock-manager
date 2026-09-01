<?php
/**
 * Prepared, filtered reads for paginated admin datasets.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Storage;

defined( 'ABSPATH' ) || exit;

use wpdb;

/**
 * Builds and runs one filtered read as a placeholder-only prepared statement.
 *
 * Column names and SELECT fragments come from the calling repository and never
 * from request input; every request-supplied value becomes a placeholder.
 */
final class FilteredQuery {
	/**
	 * Database connection.
	 *
	 * @var wpdb
	 */
	private $db;

	/**
	 * Condition fragments.
	 *
	 * @var string[]
	 */
	private $conditions = array();

	/**
	 * Ordered prepared arguments.
	 *
	 * @var array<int, int|string>
	 */
	private $args = array();

	/**
	 * Constructor.
	 *
	 * @param wpdb $db Database connection.
	 */
	public function __construct( wpdb $db ) {
		$this->db = $db;
	}

	/**
	 * Add a repository-owned condition with its own placeholders.
	 *
	 * @param string                 $condition Condition using placeholders only.
	 * @param array<int, int|string> $args      Ordered arguments.
	 * @return self
	 */
	public function raw( string $condition, array $args = array() ): self {
		$this->conditions[] = $condition;
		foreach ( $args as $arg ) {
			$this->args[] = $arg;
		}

		return $this;
	}

	/**
	 * Match an integer column when a positive value was requested.
	 *
	 * @param string $column Column expression.
	 * @param mixed  $value  Requested value.
	 * @return self
	 */
	public function positive_int( string $column, $value ): self {
		$value = (int) $value;

		return $value > 0 ? $this->raw( $column . ' = %d', array( $value ) ) : $this;
	}

	/**
	 * Match a text column exactly when a value was requested.
	 *
	 * @param string $column Column expression.
	 * @param mixed  $value  Requested value.
	 * @return self
	 */
	public function text( string $column, $value ): self {
		$value = (string) $value;

		return '' !== $value ? $this->raw( $column . ' = %s', array( $value ) ) : $this;
	}

	/**
	 * Restrict a date column to values on or after a requested date.
	 *
	 * @param string $column Column expression.
	 * @param mixed  $value  Requested date.
	 * @return self
	 */
	public function from( string $column, $value ): self {
		$value = (string) $value;

		return '' !== $value ? $this->raw( $column . ' >= %s', array( $value ) ) : $this;
	}

	/**
	 * Restrict a date column to values on or before a requested date.
	 *
	 * @param string $column Column expression.
	 * @param mixed  $value  Requested date.
	 * @return self
	 */
	public function to( string $column, $value ): self {
		$value = (string) $value;

		return '' !== $value ? $this->raw( $column . ' <= %s', array( $value ) ) : $this;
	}

	/**
	 * Restrict a column to an explicit set of integer IDs.
	 *
	 * For filters whose source is not a table — settings held in one option, for
	 * example — the caller derives the ID set and the query stays in SQL.
	 *
	 * @param string     $column Column expression.
	 * @param int[]|null $ids    Allowed IDs, or null to skip the restriction.
	 * @return self
	 */
	public function in_ints( string $column, ?array $ids ): self {
		if ( null === $ids ) {
			return $this;
		}
		$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
		if ( array() === $ids ) {
			return $this->raw( '1 = 0' );
		}

		return $this->raw( $column . ' IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')', $ids );
	}

	/**
	 * Exclude an explicit set of integer IDs.
	 *
	 * An empty set excludes nothing, which is the mirror of `in_ints()` matching
	 * nothing for an empty set.
	 *
	 * @param string     $column Column expression.
	 * @param int[]|null $ids    Excluded IDs, or null to skip the restriction.
	 * @return self
	 */
	public function not_in_ints( string $column, ?array $ids ): self {
		if ( null === $ids ) {
			return $this;
		}
		$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
		if ( array() === $ids ) {
			return $this;
		}

		return $this->raw( $column . ' NOT IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')', $ids );
	}

	/**
	 * Match a free-text term against any of the given columns.
	 *
	 * @param string[] $columns Column expressions.
	 * @param mixed    $term    Requested term.
	 * @return self
	 */
	public function search( array $columns, $term ): self {
		$term = trim( (string) $term );
		if ( '' === $term || array() === $columns ) {
			return $this;
		}
		$like      = '%' . $this->db->esc_like( $term ) . '%';
		$fragments = array();
		$args      = array();
		foreach ( $columns as $column ) {
			$fragments[] = $column . ' LIKE %s';
			$args[]      = $like;
		}

		return $this->raw( '(' . implode( ' OR ', $fragments ) . ')', $args );
	}

	/**
	 * Count the matching rows.
	 *
	 * @param string $from FROM clause without the leading keyword.
	 * @return int
	 */
	public function count( string $from ): int {
		$sql = 'SELECT COUNT(*) FROM ' . $from . $this->where();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Conditions carry placeholders only; values are prepared here.
		return (int) $this->db->get_var( array() === $this->args ? $sql : $this->db->prepare( $sql, $this->args ) );
	}

	/**
	 * Read one ordered page of matching rows.
	 *
	 * @param string $select SELECT list.
	 * @param string $from   FROM clause without the leading keyword.
	 * @param string $order  ORDER BY clause without the leading keywords.
	 * @param int    $limit  Maximum rows.
	 * @param int    $offset Row offset.
	 * @return array<int, array<string, mixed>>
	 */
	public function page( string $select, string $from, string $order, int $limit, int $offset ): array {
		$sql  = 'SELECT ' . $select . ' FROM ' . $from . $this->where() . ' ORDER BY ' . $order . ' LIMIT %d OFFSET %d';
		$args = array_merge( $this->args, array( max( 1, min( 200, $limit ) ), max( 0, $offset ) ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Conditions carry placeholders only; values are prepared here.
		$rows = $this->db->get_results( $this->db->prepare( $sql, $args ), ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/** The WHERE clause, or an empty string when unfiltered. @return string */
	private function where(): string {
		return array() === $this->conditions ? '' : ' WHERE ' . implode( ' AND ', $this->conditions );
	}
}
