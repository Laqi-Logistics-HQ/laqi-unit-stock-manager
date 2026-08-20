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
	 * @param string $key Stable namespace key.
	 * @return int[]
	 * @throws InvalidArgumentException When the namespace is invalid.
	 */
	public function configured_ids( string $key ): array {
		$this->assert_key( $key );
		$ids = $this->db->get_col( $this->db->prepare( 'SELECT pool_id FROM ' . Schema::table( 'pool_policies' ) . ' WHERE policy_key = %s ORDER BY pool_id ASC', $key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Keyed index read.

		return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
	}

	/**
	 * Count the pools that have an extension-owned policy namespace.
	 *
	 * @param string $key Stable namespace key.
	 * @return int
	 * @throws InvalidArgumentException When the namespace is invalid.
	 */
	public function count_configured( string $key ): int {
		$this->assert_key( $key );

		return (int) $this->db->get_var( $this->db->prepare( 'SELECT COUNT(*) FROM ' . Schema::table( 'pool_policies' ) . ' WHERE policy_key = %s', $key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Keyed index read.
	}

	/**
	 * Read one page of pool IDs that have an extension-owned policy namespace.
	 *
	 * @param string $key    Stable namespace key.
	 * @param int    $limit  Maximum rows.
	 * @param int    $offset Row offset.
	 * @return int[]
	 * @throws InvalidArgumentException When the namespace is invalid.
	 */
	public function configured_ids_page( string $key, int $limit, int $offset = 0 ): array {
		$this->assert_key( $key );
		$ids = $this->db->get_col( $this->db->prepare( 'SELECT pool_id FROM ' . Schema::table( 'pool_policies' ) . ' WHERE policy_key = %s ORDER BY pool_id ASC LIMIT %d OFFSET %d', $key, max( 1, min( 500, $limit ) ), max( 0, $offset ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Keyed index read.

		return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
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
			$this->db->query( $this->db->prepare( 'INSERT IGNORE INTO ' . Schema::table( 'pool_policies' ) . ' (pool_id, policy_key) VALUES (%d, %s)', $pool_id, $key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Index maintained inside the policy transaction.
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
