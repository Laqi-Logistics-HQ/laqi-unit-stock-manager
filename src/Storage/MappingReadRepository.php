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

	/**
	 * Build one bulk product-list projection for the requested parent products.
	 *
	 * @param int[] $product_ids WooCommerce parent/simple product IDs.
	 * @return array<int, array<string, mixed>> Summaries keyed by product ID.
	 */
	public function summaries_for_products( array $product_ids ): array {
		$product_ids = array_values( array_unique( array_filter( array_map( 'absint', $product_ids ) ) ) );
		if ( array() === $product_ids ) {
			return array();
		}

		$placeholders = implode( ', ', array_fill( 0, count( $product_ids ), '%d' ) );
		$sql          = 'SELECT m.product_id,
			COUNT(DISTINCT m.id) AS mapping_count,
			COUNT(DISTINCT CASE WHEN m.variation_id > 0 THEN m.id END) AS variation_mapping_count,
			COUNT(DISTINCT CASE WHEN m.calculator_type = "recipe" THEN m.id END) AS recipe_count,
			COUNT(DISTINCT CASE WHEN m.calculator_type = "recipe" THEN c.id END) AS recipe_component_count,
			COUNT(DISTINCT CASE WHEN linked.ID IS NULL OR c.id IS NULL OR p.id IS NULL OR stock.meta_value = "yes" THEN m.id END) AS warning_count,
			GROUP_CONCAT(DISTINCT CASE WHEN m.calculator_type = "single_pool" THEN p.name END ORDER BY p.name SEPARATOR "||") AS pool_names,
			(SELECT COUNT(*) FROM ' . $this->db->posts . ' v WHERE v.post_parent = m.product_id AND v.post_type = "product_variation" AND v.post_status NOT IN ("trash", "auto-draft")) AS variation_count
		FROM ' . Schema::table( 'mappings' ) . ' m
		LEFT JOIN ' . Schema::table( 'mapping_components' ) . ' c ON c.mapping_id = m.id
		LEFT JOIN ' . Schema::table( 'pools' ) . ' p ON p.id = c.pool_id
		LEFT JOIN ' . $this->db->posts . ' linked ON linked.ID = CASE WHEN m.variation_id > 0 THEN m.variation_id ELSE m.product_id END AND linked.post_status NOT IN ("trash", "auto-draft")
		LEFT JOIN ' . $this->db->postmeta . ' stock ON stock.post_id = CASE WHEN m.variation_id > 0 THEN m.variation_id ELSE m.product_id END AND stock.meta_key = "_manage_stock"
		WHERE m.active = 1 AND m.product_id IN (' . $placeholders . ')
		GROUP BY m.product_id';
		$rows         = $this->db->get_results( $this->db->prepare( $sql, ...$product_ids ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic placeholders are generated from validated integer IDs.
		$summaries    = array();
		foreach ( $rows as $row ) {
			$product_id               = (int) $row['product_id'];
			$summaries[ $product_id ] = array(
				'mapping_count'           => (int) $row['mapping_count'],
				'variation_mapping_count' => (int) $row['variation_mapping_count'],
				'variation_count'         => (int) $row['variation_count'],
				'recipe_count'            => (int) $row['recipe_count'],
				'recipe_component_count'  => (int) $row['recipe_component_count'],
				'warning_count'           => (int) $row['warning_count'],
				'pool_names'              => '' === (string) $row['pool_names'] ? array() : explode( '||', (string) $row['pool_names'] ),
			);
		}
		return $summaries;
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
