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

		return new Pool( (int) $this->db->insert_id, $name, $opening_balance, $display_unit, $allow_backorders, $internal_sku );
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
	 * Update operational pool details without changing its normalized balance.
	 *
	 * @param int    $pool_id          Pool ID.
	 * @param string $name             Pool name.
	 * @param string $internal_sku     Operational SKU.
	 * @param string $display_unit     Compatible display unit.
	 * @param int    $expected_version Required current version.
	 * @return Pool
	 * @throws \InvalidArgumentException When the pool or input is invalid.
	 * @throws RuntimeException When the pool changed or persistence fails.
	 */
	public function update_details( int $pool_id, string $name, string $internal_sku, string $display_unit, int $expected_version ): Pool {
		if ( '' === $name || '' === $display_unit || $expected_version < 1 ) {
			throw new \InvalidArgumentException( 'Pool details are incomplete.' );
		}
		$updated = $this->db->query(
			$this->db->prepare(
				'UPDATE ' . Schema::table( 'pools' ) . ' SET name = %s, internal_sku = %s, display_unit = %s, version = version + 1, updated_at = %s WHERE id = %d AND version = %d',
				$name,
				$internal_sku,
				$display_unit,
				current_time( 'mysql', true ),
				$pool_id,
				$expected_version
			)
		);
		if ( 1 !== $updated ) {
			throw new RuntimeException( 'The inventory pool changed before this edit was saved.' );
		}

		$pool = $this->find( $pool_id );
		if ( null === $pool ) {
			throw new RuntimeException( 'Could not reload the inventory pool.' );
		}
		return $pool;
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
	 * @param string $search Optional pool or linked-product search.
	 * @param int    $limit  Maximum rows.
	 * @param int    $offset Number of matching rows to skip.
	 * @return Pool[]
	 */
	public function search( string $search = '', int $limit = 100, int $offset = 0 ): array {
		$table  = Schema::table( 'pools' );
		$limit  = max( 1, min( 500, $limit ) );
		$offset = max( 0, $offset );

		if ( '' === $search ) {
			$rows = $this->db->get_results( $this->db->prepare( "SELECT * FROM {$table} ORDER BY name ASC, id ASC LIMIT %d OFFSET %d", $limit, $offset ), ARRAY_A );
		} else {
			$like       = '%' . $this->db->esc_like( $search ) . '%';
			$mappings   = Schema::table( 'mappings' );
			$components = Schema::table( 'mapping_components' );
			$posts      = $this->db->posts;
			$postmeta   = $this->db->postmeta;
			$rows       = $this->db->get_results(
				$this->db->prepare(
					"SELECT DISTINCT pool.* FROM {$table} pool
					WHERE pool.name LIKE %s OR pool.internal_sku LIKE %s OR EXISTS (
						SELECT 1 FROM {$mappings} mapping
						INNER JOIN {$components} component ON component.mapping_id = mapping.id AND component.pool_id = pool.id
						LEFT JOIN {$posts} product ON product.ID = mapping.product_id
						LEFT JOIN {$posts} variation ON variation.ID = mapping.variation_id
						LEFT JOIN {$postmeta} product_meta ON product_meta.post_id IN (mapping.product_id, mapping.variation_id)
							AND (product_meta.meta_key = '_sku' OR product_meta.meta_key LIKE 'attribute_%%')
						WHERE mapping.active = 1 AND (
							product.post_title LIKE %s OR variation.post_title LIKE %s OR product_meta.meta_value LIKE %s
						)
					)
					ORDER BY pool.name ASC, pool.id ASC LIMIT %d OFFSET %d",
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
		}

		return array_map( array( $this, 'hydrate' ), $rows );
	}

	/**
	 * Count pools matching the stock-management search.
	 *
	 * @param string $search Optional pool or linked-product search.
	 * @return int
	 */
	public function count_search( string $search = '' ): int {
		$table = Schema::table( 'pools' );
		if ( '' === $search ) {
			return (int) $this->db->get_var( "SELECT COUNT(*) FROM {$table}" );
		}

		$like       = '%' . $this->db->esc_like( $search ) . '%';
		$mappings   = Schema::table( 'mappings' );
		$components = Schema::table( 'mapping_components' );
		$posts      = $this->db->posts;
		$postmeta   = $this->db->postmeta;

		return (int) $this->db->get_var(
			$this->db->prepare(
				"SELECT COUNT(*) FROM {$table} pool
				WHERE pool.name LIKE %s OR pool.internal_sku LIKE %s OR EXISTS (
					SELECT 1 FROM {$mappings} mapping
					INNER JOIN {$components} component ON component.mapping_id = mapping.id AND component.pool_id = pool.id
					LEFT JOIN {$posts} product ON product.ID = mapping.product_id
					LEFT JOIN {$posts} variation ON variation.ID = mapping.variation_id
					LEFT JOIN {$postmeta} product_meta ON product_meta.post_id IN (mapping.product_id, mapping.variation_id)
						AND (product_meta.meta_key = '_sku' OR product_meta.meta_key LIKE 'attribute_%%')
					WHERE mapping.active = 1 AND (
						product.post_title LIKE %s OR variation.post_title LIKE %s OR product_meta.meta_value LIKE %s
					)
				)",
				$like,
				$like,
				$like,
				$like,
				$like
			)
		);
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
			(bool) $row['allow_backorders'],
			(string) $row['internal_sku'],
			(int) $row['version']
		);
	}
}
