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
		if ( $pool_id < 1 || 0 === $delta || '' === $type || '' === $idempotency_key ) {
			throw new \InvalidArgumentException( 'A stock movement requires a pool, non-zero delta, type, and idempotency key.' );
		}

		$pools = Schema::table( 'pools' );
		$moves = Schema::table( 'movements' );

		$this->query_or_fail( 'START TRANSACTION' );

		try {
			$existing = $this->db->get_row(
				$this->db->prepare(
					"SELECT id, balance_base FROM {$moves} WHERE idempotency_key = %s FOR UPDATE",
					$idempotency_key
				),
				ARRAY_A
			);

			if ( is_array( $existing ) ) {
				$this->query_or_fail( 'COMMIT' );

				return new MovementResult( (int) $existing['id'], (int) $existing['balance_base'], true );
			}

			$updated = $this->db->query(
				$this->db->prepare(
					"UPDATE {$pools}
					SET quantity_base = quantity_base + %d,
						version = version + 1,
						updated_at = UTC_TIMESTAMP()
					WHERE id = %d
						AND ( %d > 0 OR allow_backorders = 1 OR quantity_base >= %d )",
					$delta,
					$pool_id,
					$delta,
					abs( $delta )
				)
			);

			if ( 1 !== $updated ) {
				throw new InsufficientStockException( 'The inventory pool does not contain enough available stock.' );
			}

			$balance = $this->db->get_var(
				$this->db->prepare( "SELECT quantity_base FROM {$pools} WHERE id = %d", $pool_id )
			);

			$inserted = $this->db->insert(
				$moves,
				array(
					'pool_id'         => $pool_id,
					'batch_id'        => isset( $context['batch_id'] ) ? (int) $context['batch_id'] : 0,
					'type'            => $type,
					'delta_base'      => $delta,
					'balance_base'    => (int) $balance,
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
				throw new RuntimeException( 'Could not record the stock movement.' );
			}

			$movement_id = (int) $this->db->insert_id;
			$this->query_or_fail( 'COMMIT' );

			return new MovementResult( $movement_id, (int) $balance );
		} catch ( \Throwable $error ) {
			$this->db->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			throw $error;
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
