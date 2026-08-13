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
	 * @return Pool
	 * @throws RuntimeException When persistence fails.
	 */
	public function create( string $name, Quantity $opening_balance, string $base_unit, string $display_unit, bool $allow_backorders = false ): Pool {
		$now      = current_time( 'mysql', true );
		$inserted = $this->db->insert(
			Schema::table( 'pools' ),
			array(
				'name'             => $name,
				'family'           => $opening_balance->family(),
				'base_unit'        => $base_unit,
				'display_unit'     => $display_unit,
				'quantity_base'    => $opening_balance->amount(),
				'allow_backorders' => $allow_backorders ? 1 : 0,
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			throw new RuntimeException( 'Could not create the inventory pool.' );
		}

		return new Pool( (int) $this->db->insert_id, $name, $opening_balance, $display_unit, $allow_backorders );
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

		return new Pool(
			(int) $row['id'],
			(string) $row['name'],
			new Quantity( (string) $row['family'], (int) $row['quantity_base'] ),
			(string) $row['display_unit'],
			(bool) $row['allow_backorders']
		);
	}
}
