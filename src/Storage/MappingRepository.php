<?php
/**
 * Product mapping repository.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Storage;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Domain\MappingComponent;
use LaqiUnitStockManager\Domain\ProductMapping;
use RuntimeException;
use wpdb;

/**
 * Persists mappings separately from their consumption components.
 */
final class MappingRepository {

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
	 * Create a Part 1 single-pool mapping.
	 *
	 * @param int $product_id      Parent/simple product ID.
	 * @param int $variation_id    Variation ID or zero.
	 * @param int $pool_id         Pool ID.
	 * @param int $consumption     Normalized consumption per sold item.
	 * @return ProductMapping
	 * @throws \InvalidArgumentException When mapping input is invalid.
	 * @throws RuntimeException When persistence fails.
	 * @throws \Throwable When transactional persistence fails.
	 */
	public function create_single_pool( int $product_id, int $variation_id, int $pool_id, int $consumption ): ProductMapping {
		return $this->save_single_pool( $product_id, $variation_id, $pool_id, $consumption, false );
	}

	/**
	 * Create or update an explicit single-pool mapping.
	 *
	 * @param int  $product_id   Parent/simple product ID.
	 * @param int  $variation_id Variation ID or zero.
	 * @param int  $pool_id      Pool ID.
	 * @param int  $consumption  Normalized consumption per sold item.
	 * @param bool $replace      Whether an existing mapping may be replaced.
	 * @return ProductMapping
	 * @throws \InvalidArgumentException When mapping input is invalid.
	 * @throws RuntimeException When persistence fails or mapping exists without replacement.
	 * @throws \Throwable When transactional persistence fails.
	 */
	public function save_single_pool( int $product_id, int $variation_id, int $pool_id, int $consumption, bool $replace = true ): ProductMapping {
		if ( $product_id < 1 || $pool_id < 1 || $consumption < 1 ) {
			throw new \InvalidArgumentException( 'A mapping requires a product, pool, and positive consumption.' );
		}

		$now = current_time( 'mysql', true );
		$this->db->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		try {
			$mapping_id = (int) $this->db->get_var(
				$this->db->prepare(
					'SELECT id FROM ' . Schema::table( 'mappings' ) . ' WHERE product_id = %d AND variation_id = %d FOR UPDATE',
					$product_id,
					$variation_id
				)
			);
			if ( $mapping_id > 0 && ! $replace ) {
				throw new RuntimeException( 'A mapping already exists for this product or variation.' );
			}
			if ( $mapping_id > 0 ) {
				$updated = $this->db->query( $this->db->prepare( 'UPDATE ' . Schema::table( 'mappings' ) . ' SET calculator_type = %s, active = 1, version = version + 1, updated_at = %s WHERE id = %d', 'single_pool', $now, $mapping_id ) );
				if ( false === $updated ) {
					throw new RuntimeException( 'Could not update the product mapping.' );
				}
				$this->db->delete( Schema::table( 'mapping_components' ), array( 'mapping_id' => $mapping_id ), array( '%d' ) );
			} else {
				$inserted = $this->db->insert(
					Schema::table( 'mappings' ),
					array(
						'product_id'      => $product_id,
						'variation_id'    => $variation_id,
						'calculator_type' => 'single_pool',
						'created_at'      => $now,
						'updated_at'      => $now,
					),
					array( '%d', '%d', '%s', '%s', '%s' )
				);
				if ( false === $inserted ) {
					throw new RuntimeException( 'Could not create the product mapping.' );
				}
				$mapping_id = (int) $this->db->insert_id;
			}
			$inserted = $this->db->insert(
				Schema::table( 'mapping_components' ),
				array(
					'mapping_id'       => $mapping_id,
					'pool_id'          => $pool_id,
					'consumption_base' => $consumption,
					'created_at'       => $now,
					'updated_at'       => $now,
				),
				array( '%d', '%d', '%d', '%s', '%s' )
			);

			if ( false === $inserted ) {
				throw new RuntimeException( 'Could not create the mapping component.' );
			}

			$this->db->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		} catch ( \Throwable $error ) {
			$this->db->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			throw $error;
		}

		$version = (int) $this->db->get_var( $this->db->prepare( 'SELECT version FROM ' . Schema::table( 'mappings' ) . ' WHERE id = %d', $mapping_id ) );

		return new ProductMapping(
			$mapping_id,
			$product_id,
			$variation_id,
			'single_pool',
			array( new MappingComponent( $pool_id, $consumption ) ),
			$version
		);
	}

	/**
	 * Find an active mapping for a purchasable object.
	 *
	 * @param int $product_id   Parent/simple product ID.
	 * @param int $variation_id Variation ID or zero.
	 * @return ProductMapping|null
	 */
	public function find_for_product( int $product_id, int $variation_id = 0 ): ?ProductMapping {
		$mapping = $this->db->get_row(
			$this->db->prepare(
				'SELECT * FROM ' . Schema::table( 'mappings' ) . ' WHERE product_id = %d AND variation_id = %d AND active = 1',
				$product_id,
				$variation_id
			),
			ARRAY_A
		);

		if ( ! is_array( $mapping ) ) {
			return null;
		}

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

	/**
	 * Find active mappings for setup management.
	 *
	 * @param int $limit Maximum mappings.
	 * @return ProductMapping[]
	 */
	public function active( int $limit = 500 ): array {
		$limit = max( 1, min( 500, $limit ) );
		$rows  = $this->db->get_results(
			$this->db->prepare(
				'SELECT product_id, variation_id FROM ' . Schema::table( 'mappings' ) . ' WHERE active = 1 ORDER BY updated_at DESC, id DESC LIMIT %d',
				$limit
			),
			ARRAY_A
		);
		$items = array();
		foreach ( $rows as $row ) {
			$mapping = $this->find_for_product( (int) $row['product_id'], (int) $row['variation_id'] );
			if ( null !== $mapping ) {
				$items[] = $mapping;
			}
		}

		return $items;
	}

	/**
	 * Deactivate a mapping while retaining its versioned record.
	 *
	 * Existing order snapshots remain authoritative for later restoration.
	 *
	 * @param int $mapping_id Mapping ID.
	 * @return ProductMapping
	 * @throws \InvalidArgumentException When the mapping is not active.
	 * @throws RuntimeException When persistence fails.
	 */
	public function deactivate( int $mapping_id ): ProductMapping {
		$row = $this->db->get_row(
			$this->db->prepare( 'SELECT product_id, variation_id FROM ' . Schema::table( 'mappings' ) . ' WHERE id = %d AND active = 1', $mapping_id ),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			throw new \InvalidArgumentException( 'The product mapping is not active.' );
		}

		$mapping = $this->find_for_product( (int) $row['product_id'], (int) $row['variation_id'] );
		if ( null === $mapping ) {
			throw new \InvalidArgumentException( 'The product mapping is not active.' );
		}

		$updated = $this->db->update(
			Schema::table( 'mappings' ),
			array(
				'active'     => 0,
				'version'    => $mapping->version() + 1,
				'updated_at' => current_time( 'mysql', true ),
			),
			array(
				'id'     => $mapping_id,
				'active' => 1,
			),
			array( '%d', '%d', '%s' ),
			array( '%d', '%d' )
		);
		if ( 1 !== $updated ) {
			throw new RuntimeException( 'Could not deactivate the product mapping.' );
		}

		return $mapping;
	}

	/**
	 * Find active mappings consuming one inventory pool.
	 *
	 * @param int $pool_id Pool ID.
	 * @return ProductMapping[]
	 */
	public function find_for_pool( int $pool_id ): array {
		$ids      = $this->db->get_col(
			$this->db->prepare(
				'SELECT DISTINCT m.id FROM ' . Schema::table( 'mappings' ) . ' m INNER JOIN ' . Schema::table( 'mapping_components' ) . ' c ON c.mapping_id = m.id WHERE c.pool_id = %d AND m.active = 1 ORDER BY m.id ASC',
				$pool_id
			)
		);
		$mappings = array();
		foreach ( $ids as $mapping_id ) {
			$row = $this->db->get_row( $this->db->prepare( 'SELECT product_id, variation_id FROM ' . Schema::table( 'mappings' ) . ' WHERE id = %d', $mapping_id ), ARRAY_A );
			if ( is_array( $row ) ) {
				$mapping = $this->find_for_product( (int) $row['product_id'], (int) $row['variation_id'] );
				if ( null !== $mapping ) {
					$mappings[] = $mapping;
				}
			}
		}
		return $mappings;
	}
}
