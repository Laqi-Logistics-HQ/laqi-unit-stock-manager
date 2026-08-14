<?php
/**
 * Namespaced inventory-pool policy storage for add-ons.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Extension;

defined( 'ABSPATH' ) || exit;

use InvalidArgumentException;
use LaqiUnitStockManager\Storage\Schema;
use RuntimeException;
use Throwable;
use wpdb;

/** Hides Free's shared policy envelope and table layout from extensions. */
final class PoolPolicyStore {
	/** Database connection.
	 *
	 * @var wpdb
	 */
	private $db;

	/**
	 * Constructor.
	 *
	 * @param wpdb $db Database connection.
	 */
	public function __construct( wpdb $db ) {
		$this->db = $db;
	}

	/**
	 * Read one extension-owned policy namespace.
	 *
	 * @param int    $pool_id   Pool ID.
	 * @param string $key Stable namespace key.
	 * @return array<string,mixed>
	 * @throws InvalidArgumentException When identifiers are invalid.
	 */
	public function get( int $pool_id, string $key ): array {
		$this->assert_identifiers( $pool_id, $key );
		$envelope = $this->read_envelope( $pool_id );
		$value    = $envelope[ $key ] ?? array();
		return is_array( $value ) ? $value : array();
	}

	/**
	 * List pool IDs that have an extension-owned policy namespace.
	 *
	 * The shared JSON envelope and table scan remain internal to Free. Add-ons can
	 * combine these IDs with the public pool repository when they need scheduled
	 * evaluation or an administration index.
	 *
	 * @param string $key Stable namespace key.
	 * @return int[]
	 * @throws InvalidArgumentException When the namespace is invalid.
	 */
	public function configured_ids( string $key ): array {
		$this->assert_key( $key );
		$ids    = array();
		$offset = 0;
		do {
			$rows = $this->db->get_results(
				$this->db->prepare(
					'SELECT id, policy_json FROM ' . Schema::table( 'pools' ) . " WHERE policy_json IS NOT NULL AND policy_json != '' ORDER BY id ASC LIMIT %d OFFSET %d",
					500,
					$offset
				),
				ARRAY_A
			);
			$rows = is_array( $rows ) ? $rows : array();
			foreach ( $rows as $row ) {
				$envelope = json_decode( (string) $row['policy_json'], true );
				if ( is_array( $envelope ) && isset( $envelope[ $key ] ) && is_array( $envelope[ $key ] ) ) {
					$ids[] = (int) $row['id'];
				}
			}
			$batch_size = count( $rows );
			$offset    += $batch_size;
		} while ( 500 === $batch_size );
		return $ids;
	}

	/**
	 * Replace one extension-owned policy namespace atomically.
	 *
	 * @param int                 $pool_id   Pool ID.
	 * @param string              $key       Stable namespace key.
	 * @param array<string,mixed> $policy    JSON-serializable policy values.
	 * @return void
	 * @throws InvalidArgumentException When identifiers or values are invalid.
	 * @throws RuntimeException When persistence fails.
	 * @throws Throwable When a database operation fails.
	 */
	public function put( int $pool_id, string $key, array $policy ): void {
		$this->assert_identifiers( $pool_id, $key );
		$encoded_policy = wp_json_encode( $policy );
		if ( false === $encoded_policy ) {
			throw new InvalidArgumentException( 'The pool policy cannot be encoded.' );
		}

		$this->db->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		try {
			$row = $this->db->get_row( $this->db->prepare( 'SELECT id, policy_json FROM ' . Schema::table( 'pools' ) . ' WHERE id = %d FOR UPDATE', $pool_id ), ARRAY_A );
			if ( ! is_array( $row ) ) {
				throw new InvalidArgumentException( 'Unknown inventory pool.' );
			}
			$envelope         = json_decode( (string) $row['policy_json'], true );
			$envelope         = is_array( $envelope ) ? $envelope : array();
			$envelope[ $key ] = json_decode( $encoded_policy, true );
			$updated          = $this->db->update(
				Schema::table( 'pools' ),
				array( 'policy_json' => wp_json_encode( $envelope ) ),
				array( 'id' => $pool_id ),
				array( '%s' ),
				array( '%d' )
			);
			if ( false === $updated ) {
				throw new RuntimeException( 'Could not save the pool policy.' );
			}
			$this->db->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		} catch ( Throwable $error ) {
			$this->db->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			throw $error;
		}
	}

	/**
	 * Validate public identifiers.
	 *
	 * @param int    $pool_id Pool ID.
	 * @param string $key     Namespace key.
	 * @return void
	 * @throws InvalidArgumentException When identifiers are invalid.
	 */
	private function assert_identifiers( int $pool_id, string $key ): void {
		if ( $pool_id < 1 ) {
			throw new InvalidArgumentException( 'The pool policy identifiers are invalid.' );
		}
		$this->assert_key( $key );
	}

	/**
	 * Validate a public policy namespace.
	 *
	 * @param string $key Namespace key.
	 * @return void
	 * @throws InvalidArgumentException When the namespace is invalid.
	 */
	private function assert_key( string $key ): void {
		if ( ! preg_match( '/^[a-z][a-z0-9_]{1,49}$/', $key ) ) {
			throw new InvalidArgumentException( 'The pool policy identifiers are invalid.' );
		}
	}

	/**
	 * Read the internal envelope.
	 *
	 * @param int $pool_id Pool ID.
	 * @return array<string,mixed>
	 * @throws InvalidArgumentException When the pool does not exist.
	 */
	private function read_envelope( int $pool_id ): array {
		$row = $this->db->get_row( $this->db->prepare( 'SELECT id, policy_json FROM ' . Schema::table( 'pools' ) . ' WHERE id = %d', $pool_id ), ARRAY_A );
		if ( ! is_array( $row ) ) {
			throw new InvalidArgumentException( 'Unknown inventory pool.' );
		}
		$decoded = json_decode( (string) $row['policy_json'], true );
		return is_array( $decoded ) ? $decoded : array();
	}
}
