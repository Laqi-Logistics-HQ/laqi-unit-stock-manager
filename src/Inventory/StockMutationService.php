<?php
/**
 * Atomic pooled-stock mutation service.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Inventory;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Storage\Schema;
use RuntimeException;
use wpdb;

/**
 * The only production service allowed to change an inventory-pool balance.
 */
final class StockMutationService {

	/**
	 * WordPress database connection.
	 *
	 * @var wpdb
	 */
	private $db;

	/**
	 * Constructor.
	 *
	 * @param wpdb $db WordPress database connection.
	 */
	public function __construct( wpdb $db ) {
		$this->db = $db;
	}

	/**
	 * Apply one idempotent movement atomically.
	 *
	 * @param int    $pool_id         Pool ID.
	 * @param int    $delta           Signed normalized quantity.
	 * @param string $type            Registered movement type.
	 * @param string $idempotency_key Stable event key.
	 * @param array  $context         Optional source/reason/metadata fields.
	 * @return MovementResult
	 * @throws \InvalidArgumentException When required movement data is invalid.
	 * @throws InsufficientStockException When a decrement cannot be satisfied.
	 * @throws RuntimeException When persistence fails.
	 * @throws \Throwable When transactional work fails and is rolled back.
	 */
	public function apply( int $pool_id, int $delta, string $type, string $idempotency_key, array $context = array() ): MovementResult {
		$results = $this->apply_batch(
			array(
				array(
					'pool_id'         => $pool_id,
					'delta'           => $delta,
					'type'            => $type,
					'idempotency_key' => $idempotency_key,
					'context'         => $context,
				),
			)
		);

		return $results[0];
	}

	/**
	 * Set one pool to an absolute balance under the same transaction lock.
	 *
	 * @param int    $pool_id         Pool ID.
	 * @param int    $target          Target normalized balance.
	 * @param string $type            Registered movement type.
	 * @param string $idempotency_key Stable event key.
	 * @param array  $context         Optional source/reason/metadata fields.
	 * @return MovementResult
	 * @throws \InvalidArgumentException When required movement data is invalid.
	 * @throws InsufficientStockException When a negative target is prohibited.
	 * @throws RuntimeException When persistence fails.
	 * @throws \Throwable When transactional work fails and is rolled back.
	 */
	public function set_balance( int $pool_id, int $target, string $type, string $idempotency_key, array $context = array() ): MovementResult {
		if ( $pool_id < 1 || '' === $type || '' === $idempotency_key ) {
			throw new \InvalidArgumentException( 'An absolute stock movement requires a pool, type, and idempotency key.' );
		}

		$pools = Schema::table( 'pools' );
		$moves = Schema::table( 'movements' );
		$this->query_or_fail( 'START TRANSACTION' );

		try {
			$existing = $this->db->get_row(
				$this->db->prepare( "SELECT id, balance_base FROM {$moves} WHERE idempotency_key = %s FOR UPDATE", $idempotency_key ),
				ARRAY_A
			);
			if ( is_array( $existing ) ) {
				$this->query_or_fail( 'COMMIT' );
				return new MovementResult( (int) $existing['id'], (int) $existing['balance_base'], true );
			}

			$pool = $this->db->get_row( $this->db->prepare( "SELECT quantity_base, allow_backorders FROM {$pools} WHERE id = %d FOR UPDATE", $pool_id ), ARRAY_A );
			if ( ! is_array( $pool ) ) {
				throw new RuntimeException( 'The inventory pool does not exist.' );
			}
			if ( $target < 0 && ! (bool) $pool['allow_backorders'] ) {
				throw new InsufficientStockException( 'This inventory pool does not allow a negative balance.' );
			}

			$current = (int) $pool['quantity_base'];
			if ( ( $current < 0 && $target > PHP_INT_MAX + $current ) || ( $current > 0 && $target < PHP_INT_MIN + $current ) ) {
				throw new \InvalidArgumentException( 'The absolute stock adjustment is too large to record safely.' );
			}
			$delta = $target - $current;
			if ( false === $this->db->query( $this->db->prepare( "UPDATE {$pools} SET quantity_base = %d, version = version + 1, updated_at = UTC_TIMESTAMP() WHERE id = %d", $target, $pool_id ) ) ) {
				throw new RuntimeException( 'Could not set the inventory-pool balance.' );
			}

			$result = $this->insert_movement( $moves, $pool_id, $delta, $target, $type, $idempotency_key, $context );
			$this->query_or_fail( 'COMMIT' );
			return $result;
		} catch ( \Throwable $error ) {
			$this->db->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			throw $error;
		}
	}

	/**
	 * Apply several pool movements in one database transaction.
	 *
	 * Pools are locked in ID order to keep concurrent order batches from
	 * deadlocking. A retry must contain either all-new or all-existing keys;
	 * partial prior application indicates corrupted external usage and fails.
	 *
	 * @param array<int, array<string, mixed>> $movements Movement commands.
	 * @return MovementResult[]
	 * @throws \InvalidArgumentException When a command is invalid.
	 * @throws InsufficientStockException When any decrement cannot be satisfied.
	 * @throws RuntimeException When persistence or idempotency state is invalid.
	 * @throws \Throwable When transactional work fails and is rolled back.
	 */
	public function apply_batch( array $movements ): array {
		if ( array() === $movements ) {
			throw new \InvalidArgumentException( 'A stock movement batch cannot be empty.' );
		}

		usort(
			$movements,
			static function ( array $left, array $right ): int {
				return (int) ( $left['pool_id'] ?? 0 ) <=> (int) ( $right['pool_id'] ?? 0 );
			}
		);

		$pools = Schema::table( 'pools' );
		$moves = Schema::table( 'movements' );
		$this->query_or_fail( 'START TRANSACTION' );

		try {
			$existing_results = array();
			foreach ( $movements as $command ) {
				$this->validate_command( $command );
				$existing = $this->db->get_row(
					$this->db->prepare(
						"SELECT id, balance_base FROM {$moves} WHERE idempotency_key = %s FOR UPDATE",
						$command['idempotency_key']
					),
					ARRAY_A
				);
				if ( is_array( $existing ) ) {
					$existing_results[] = new MovementResult( (int) $existing['id'], (int) $existing['balance_base'], true );
				}
			}

			if ( count( $existing_results ) === count( $movements ) ) {
				$this->query_or_fail( 'COMMIT' );
				return $existing_results;
			}
			if ( array() !== $existing_results ) {
				throw new RuntimeException( 'The stock movement batch was only partially recorded.' );
			}

			$results = array();
			foreach ( $movements as $command ) {
				$pool_id = (int) $command['pool_id'];
				$delta   = (int) $command['delta'];
				$updated = $this->db->query(
					$this->db->prepare(
						"UPDATE {$pools}
						SET quantity_base = quantity_base + %d, version = version + 1, updated_at = UTC_TIMESTAMP()
						WHERE id = %d AND ( %d > 0 OR allow_backorders = 1 OR quantity_base >= %d )",
						$delta,
						$pool_id,
						$delta,
						abs( $delta )
					)
				);

				if ( 1 !== $updated ) {
					throw new InsufficientStockException( 'An inventory pool does not contain enough available stock.' );
				}

				$balance   = (int) $this->db->get_var( $this->db->prepare( "SELECT quantity_base FROM {$pools} WHERE id = %d", $pool_id ) );
				$context   = isset( $command['context'] ) && is_array( $command['context'] ) ? $command['context'] : array();
				$results[] = $this->insert_movement( $moves, $pool_id, $delta, $balance, (string) $command['type'], (string) $command['idempotency_key'], $context );
			}

			$this->query_or_fail( 'COMMIT' );
			return $results;
		} catch ( \Throwable $error ) {
			$this->db->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			throw $error;
		}
	}

	/**
	 * Persist one ledger row.
	 *
	 * @param string $table           Movement table.
	 * @param int    $pool_id         Pool ID.
	 * @param int    $delta           Signed normalized change.
	 * @param int    $balance         Resulting balance.
	 * @param string $type            Movement type.
	 * @param string $idempotency_key Stable event key.
	 * @param array  $context         Optional movement context.
	 * @return MovementResult
	 * @throws RuntimeException When persistence fails.
	 */
	private function insert_movement( string $table, int $pool_id, int $delta, int $balance, string $type, string $idempotency_key, array $context ): MovementResult {
		$inserted = $this->db->insert(
			$table,
			array(
				'pool_id'         => $pool_id,
				'batch_id'        => isset( $context['batch_id'] ) ? (int) $context['batch_id'] : 0,
				'type'            => $type,
				'delta_base'      => $delta,
				'balance_base'    => $balance,
				'source_type'     => isset( $context['source_type'] ) ? (string) $context['source_type'] : '',
				'source_id'       => isset( $context['source_id'] ) ? (int) $context['source_id'] : 0,
				'idempotency_key' => $idempotency_key,
				'actor_id'        => isset( $context['actor_id'] ) ? (int) $context['actor_id'] : 0,
				'reason'          => isset( $context['reason'] ) ? (string) $context['reason'] : null,
				'metadata_json'   => isset( $context['metadata'] ) ? wp_json_encode( $context['metadata'] ) : null,
				'created_at'      => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%d', '%d', '%s', '%d', '%s', '%d', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			throw new RuntimeException( 'Could not record a stock movement.' );
		}

		return new MovementResult( (int) $this->db->insert_id, $balance );
	}

	/**
	 * Validate one movement command.
	 *
	 * @param array<string, mixed> $command Movement command.
	 * @return void
	 * @throws \InvalidArgumentException When required movement data is invalid.
	 */
	private function validate_command( array $command ): void {
		if (
			(int) ( $command['pool_id'] ?? 0 ) < 1 ||
			0 === (int) ( $command['delta'] ?? 0 ) ||
			'' === (string) ( $command['type'] ?? '' ) ||
			'' === (string) ( $command['idempotency_key'] ?? '' )
		) {
			throw new \InvalidArgumentException( 'A stock movement requires a pool, non-zero delta, type, and idempotency key.' );
		}
	}

	/**
	 * Execute transaction control SQL or fail.
	 *
	 * @param string $sql Transaction statement.
	 * @return void
	 * @throws RuntimeException When transaction control fails.
	 */
	private function query_or_fail( string $sql ): void {
		if ( false === $this->db->query( $sql ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
			throw new RuntimeException( 'Could not complete the inventory transaction.' );
		}
	}
}
