<?php
/**
 * Inventory pool repository.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Storage;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Domain\Pool;
use LaqiUnitStockManager\Domain\Quantity;
use RuntimeException;
use wpdb;

/**
 * Persists and hydrates inventory pools.
 */
final class PoolRepository {

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
	 * Create a pool.
	 *
	 * @param string   $name             Pool name.
	 * @param Quantity $opening_balance  Exact opening balance.
	 * @param string   $base_unit        Canonical unit key.
	 * @param string   $display_unit     Preferred display unit.
	 * @param bool     $allow_backorders Whether negative stock is allowed.
	 * @param string   $internal_sku     Optional merchant-facing pool SKU.
	 * @return Pool
	 * @throws RuntimeException When persistence fails.
	 */
	public function create( string $name, Quantity $opening_balance, string $base_unit, string $display_unit, bool $allow_backorders = false, string $internal_sku = '' ): Pool {
		$now      = current_time( 'mysql', true );
		$inserted = $this->db->insert(
			Schema::table( 'pools' ),
			array(
				'name'             => $name,
				'internal_sku'     => $internal_sku,
				'family'           => $opening_balance->family(),
				'base_unit'        => $base_unit,
				'display_unit'     => $display_unit,
				'quantity_base'    => $opening_balance->amount(),
				'allow_backorders' => $allow_backorders ? 1 : 0,
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			throw new RuntimeException( 'Could not create the inventory pool.' );
		}

		return new Pool( (int) $this->db->insert_id, $name, $opening_balance, $display_unit, $allow_backorders );
	}

	/**
	 * Create a pool with a zero balance for mutation-service initialization.
	 *
	 * @param string $name             Pool name.
	 * @param string $family           Measurement family.
	 * @param string $base_unit        Canonical unit key.
	 * @param string $display_unit     Preferred display unit.
	 * @param bool   $allow_backorders Whether negative stock is allowed.
	 * @param string $internal_sku     Optional merchant-facing pool SKU.
	 * @return Pool
	 */
	public function create_empty( string $name, string $family, string $base_unit, string $display_unit, bool $allow_backorders = false, string $internal_sku = '' ): Pool {
		return $this->create( $name, new Quantity( $family, 0 ), $base_unit, $display_unit, $allow_backorders, $internal_sku );
	}

	/**
	 * Find a pool.
	 *
	 * @param int $pool_id Pool ID.
	 * @return Pool|null
	 */
	public function find( int $pool_id ): ?Pool {
		$row = $this->db->get_row(
			$this->db->prepare( 'SELECT * FROM ' . Schema::table( 'pools' ) . ' WHERE id = %d', $pool_id ),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return null;
		}

		return $this->hydrate( $row );
	}

	/**
	 * Find pools for the stock-management screen.
	 *
	 * @param string $search Optional name or internal SKU search.
	 * @param int    $limit  Maximum rows.
	 * @return Pool[]
	 */
	public function search( string $search = '', int $limit = 100 ): array {
		$table = Schema::table( 'pools' );
		$limit = max( 1, min( 500, $limit ) );

		if ( '' === $search ) {
			$rows = $this->db->get_results( $this->db->prepare( "SELECT * FROM {$table} ORDER BY name ASC, id ASC LIMIT %d", $limit ), ARRAY_A );
		} else {
			$like = '%' . $this->db->esc_like( $search ) . '%';
			$rows = $this->db->get_results(
				$this->db->prepare( "SELECT * FROM {$table} WHERE name LIKE %s OR internal_sku LIKE %s ORDER BY name ASC, id ASC LIMIT %d", $like, $like, $limit ),
				ARRAY_A
			);
		}

		return array_map( array( $this, 'hydrate' ), $rows );
	}

	/**
	 * Hydrate a pool row.
	 *
	 * @param array<string, mixed> $row Database row.
	 * @return Pool
	 */
	private function hydrate( array $row ): Pool {
		return new Pool(
			(int) $row['id'],
			(string) $row['name'],
			new Quantity( (string) $row['family'], (int) $row['quantity_base'] ),
			(string) $row['display_unit'],
			(bool) $row['allow_backorders']
		);
	}
}
