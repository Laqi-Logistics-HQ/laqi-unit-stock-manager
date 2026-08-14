<?php
/**
 * Product mapping read persistence.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Storage;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Domain\MappingComponent;
use LaqiUnitStockManager\Domain\ProductMapping;
use wpdb;

/** Hydrates mapping aggregates and read projections. */
final class MappingReadRepository {
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

	/** Find an active mapping for a purchasable object.
	 *
	 * @param int $product_id Product ID.
	 * @param int $variation_id Variation ID or zero.
	 */
	public function find_for_product( int $product_id, int $variation_id = 0 ): ?ProductMapping {
		$mapping = $this->db->get_row(
			$this->db->prepare( 'SELECT * FROM ' . Schema::table( 'mappings' ) . ' WHERE product_id = %d AND variation_id = %d AND active = 1', $product_id, $variation_id ),
			ARRAY_A
		);
		return is_array( $mapping ) ? $this->hydrate( $mapping ) : null;
	}

	/** Find active mappings for setup management.
	 *
	 * @param int $limit Maximum mappings.
	 * @param int $offset Mappings to skip.
	 * @return ProductMapping[]
	 */
	public function active( int $limit = 500, int $offset = 0 ): array {
		$rows  = $this->db->get_results(
			$this->db->prepare(
				'SELECT * FROM ' . Schema::table( 'mappings' ) . ' WHERE active = 1 ORDER BY updated_at DESC, id DESC LIMIT %d OFFSET %d',
				max( 1, min( 500, $limit ) ),
				max( 0, $offset )
			),
			ARRAY_A
		);
		$items = array();
		foreach ( $rows as $row ) {
			$items[] = $this->hydrate( $row );
		}
		return $items;
	}

	/** Count active product mappings. */
	public function count_active(): int {
		return (int) $this->db->get_var( 'SELECT COUNT(*) FROM ' . Schema::table( 'mappings' ) . ' WHERE active = 1' );
	}

	/** Find one active mapping by its stable ID.
	 *
	 * @param int $mapping_id Mapping ID.
	 */
	public function find_active( int $mapping_id ): ?ProductMapping {
		$row = $this->db->get_row( $this->db->prepare( 'SELECT * FROM ' . Schema::table( 'mappings' ) . ' WHERE id = %d AND active = 1', $mapping_id ), ARRAY_A );
		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	/** Find active mappings consuming one inventory pool.
	 *
	 * @param int $pool_id Pool ID.
	 * @return ProductMapping[]
	 */
	public function find_for_pool( int $pool_id ): array {
		$rows = $this->db->get_results(
			$this->db->prepare(
				'SELECT DISTINCT m.* FROM ' . Schema::table( 'mappings' ) . ' m INNER JOIN ' . Schema::table( 'mapping_components' ) . ' c ON c.mapping_id = m.id WHERE c.pool_id = %d AND m.active = 1 ORDER BY m.id ASC',
				$pool_id
			),
			ARRAY_A
		);
		return array_map( array( $this, 'hydrate' ), $rows );
	}

	/** Turn one mapping row into its aggregate.
	 *
	 * @param array<string,mixed> $mapping Mapping row.
	 */
	private function hydrate( array $mapping ): ProductMapping {
		$rows       = $this->db->get_results(
			$this->db->prepare(
				'SELECT pool_id, consumption_base, role_key FROM ' . Schema::table( 'mapping_components' ) . ' WHERE mapping_id = %d ORDER BY position ASC, id ASC',
				$mapping['id']
			),
			ARRAY_A
		);
		$components = array();
		foreach ( $rows as $row ) {
			$components[] = new MappingComponent( (int) $row['pool_id'], (int) $row['consumption_base'], (string) $row['role_key'] );
		}
		return new ProductMapping(
			(int) $mapping['id'],
			(int) $mapping['product_id'],
			(int) $mapping['variation_id'],
			(string) $mapping['calculator_type'],
			$components,
			(int) $mapping['version']
		);
	}
}
