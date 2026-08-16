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
	 * Mapping read projections.
	 *
	 * @var MappingReadRepository
	 */
	private $reader;

	/**
	 * Constructor.
	 *
	 * @param wpdb $db WordPress database connection.
	 */
	public function __construct( wpdb $db ) {
		$this->db     = $db;
		$this->reader = new MappingReadRepository( $db );
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
	 * Persist a mapping whose calculator consumes several pool components.
	 *
	 * This shared persistence seam lets physically removable calculators define
	 * their own component rules without teaching Free code about paid types.
	 *
	 * @param int                $product_id       Parent/simple product ID.
	 * @param int                $variation_id     Variation ID or zero.
	 * @param string             $calculator_type  Registered calculator type.
	 * @param MappingComponent[] $components       Positive component definitions.
	 * @param bool               $replace          Whether an existing mapping may be replaced.
	 * @param int|null           $expected_version Optional version required for an edit.
	 * @return ProductMapping
	 * @throws \InvalidArgumentException When mapping input is invalid.
	 * @throws RuntimeException When persistence fails or optimistic locking fails.
	 * @throws \Throwable When transactional persistence fails.
	 */
	public function save_components( int $product_id, int $variation_id, string $calculator_type, array $components, bool $replace = true, ?int $expected_version = null ): ProductMapping {
		if ( $product_id < 1 || '' === $calculator_type || array() === $components ) {
			throw new \InvalidArgumentException( 'A component mapping requires a product, calculator, and components.' );
		}

		$seen = array();
		foreach ( $components as $component ) {
			if ( ! $component instanceof MappingComponent || $component->pool_id() < 1 || $component->consumption() < 1 || '' === $component->role() ) {
				throw new \InvalidArgumentException( 'Every mapping component requires a pool, positive consumption, and role.' );
			}
			$key = $component->pool_id() . ':' . $component->role();
			if ( isset( $seen[ $key ] ) ) {
				throw new \InvalidArgumentException( 'A mapping cannot repeat the same pool and component role.' );
			}
			$seen[ $key ] = true;
		}

		$now = current_time( 'mysql', true );
		$this->db->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		try {
			$row        = $this->db->get_row(
				$this->db->prepare( 'SELECT id, version FROM ' . Schema::table( 'mappings' ) . ' WHERE product_id = %d AND variation_id = %d FOR UPDATE', $product_id, $variation_id ),
				ARRAY_A
			);
			$mapping_id = is_array( $row ) ? (int) $row['id'] : 0;
			if ( $mapping_id > 0 && ! $replace ) {
				throw new RuntimeException( 'A mapping already exists for this product or variation.' );
			}
			if ( null !== $expected_version && ( 0 === $mapping_id || (int) $row['version'] !== $expected_version ) ) {
				throw new RuntimeException( 'The product mapping changed before this edit was saved.' );
			}
			if ( $mapping_id > 0 ) {
				$updated = $this->db->query( $this->db->prepare( 'UPDATE ' . Schema::table( 'mappings' ) . ' SET calculator_type = %s, active = 1, version = version + 1, updated_at = %s WHERE id = %d', $calculator_type, $now, $mapping_id ) );
				if ( false === $updated || false === $this->db->delete( Schema::table( 'mapping_components' ), array( 'mapping_id' => $mapping_id ), array( '%d' ) ) ) {
					throw new RuntimeException( 'Could not update the product mapping.' );
				}
			} else {
				$inserted = $this->db->insert(
					Schema::table( 'mappings' ),
					array(
						'product_id'      => $product_id,
						'variation_id'    => $variation_id,
						'calculator_type' => $calculator_type,
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

			foreach ( array_values( $components ) as $position => $component ) {
				$inserted = $this->db->insert(
					Schema::table( 'mapping_components' ),
					array(
						'mapping_id'       => $mapping_id,
						'pool_id'          => $component->pool_id(),
						'consumption_base' => $component->consumption(),
						'role_key'         => $component->role(),
						'position'         => $position,
						'created_at'       => $now,
						'updated_at'       => $now,
					),
					array( '%d', '%d', '%d', '%s', '%d', '%s', '%s' )
				);
				if ( false === $inserted ) {
					throw new RuntimeException( 'Could not create a mapping component.' );
				}
			}
			$this->db->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		} catch ( \Throwable $error ) {
			$this->db->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			throw $error;
		}

		$version = (int) $this->db->get_var( $this->db->prepare( 'SELECT version FROM ' . Schema::table( 'mappings' ) . ' WHERE id = %d', $mapping_id ) );
		return new ProductMapping( $mapping_id, $product_id, $variation_id, $calculator_type, array_values( $components ), $version );
	}

	/**
	 * Create or update an explicit single-pool mapping.
	 *
	 * @param int      $product_id       Parent/simple product ID.
	 * @param int      $variation_id     Variation ID or zero.
	 * @param int      $pool_id          Pool ID.
	 * @param int      $consumption      Normalized consumption per sold item.
	 * @param bool     $replace          Whether an existing mapping may be replaced.
	 * @param int|null $expected_version Optional version required for an edit.
	 * @return ProductMapping
	 * @throws \InvalidArgumentException When mapping input is invalid.
	 * @throws RuntimeException When persistence fails or mapping exists without replacement.
	 * @throws \Throwable When transactional persistence fails.
	 */
	public function save_single_pool( int $product_id, int $variation_id, int $pool_id, int $consumption, bool $replace = true, ?int $expected_version = null ): ProductMapping {
		return $this->save_components( $product_id, $variation_id, 'single_pool', array( new MappingComponent( $pool_id, $consumption ) ), $replace, $expected_version );
	}

	/**
	 * Find an active mapping for a purchasable object.
	 *
	 * @param int $product_id   Parent/simple product ID.
	 * @param int $variation_id Variation ID or zero.
	 * @return ProductMapping|null
	 */
	public function find_for_product( int $product_id, int $variation_id = 0 ): ?ProductMapping {
		return $this->reader->find_for_product( $product_id, $variation_id );
	}

	/**
	 * Find active mappings for setup management.
	 *
	 * @param int $limit  Maximum mappings.
	 * @param int $offset Number of active mappings to skip.
	 * @return ProductMapping[]
	 */
	public function active( int $limit = 500, int $offset = 0 ): array {
		return $this->reader->active( $limit, $offset );
	}

	/** Count active product mappings. @return int */
	public function count_active(): int {
		return $this->reader->count_active();
	}

	/**
	 * Find one active mapping by its stable ID.
	 *
	 * @param int $mapping_id Mapping ID.
	 * @return ProductMapping|null
	 */
	public function find_active( int $mapping_id ): ?ProductMapping {
		return $this->reader->find_active( $mapping_id );
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
		$mapping = $this->find_active( $mapping_id );
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
		return $this->reader->find_for_pool( $pool_id );
	}

	/**
	 * Build bulk product-list summaries without per-row mapping queries.
	 *
	 * @param int[] $product_ids Parent/simple product IDs.
	 * @return array<int, array<string, mixed>> Summaries keyed by product ID.
	 */
	public function summaries_for_products( array $product_ids ): array {
		return $this->reader->summaries_for_products( $product_ids );
	}
}
