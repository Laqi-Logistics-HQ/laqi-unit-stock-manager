<?php
/**
 * Unified pool and linked-product search tests.
 *
 * @package LaqiUnitStockManager
 */

use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Storage\MappingRepository;
use LaqiUnitStockManager\Storage\PoolRepository;
use LaqiUnitStockManager\Storage\Schema;

/**
 * Verifies that the Stock tab finds pools through WooCommerce catalog data.
 */
class Test_Pool_Search extends WP_UnitTestCase {

	/** @var PoolRepository */
	private $pools;

	/** @var int */
	private $pool_id;

	/** @var WC_Product_Variable */
	private $product;

	/** @var WC_Product_Variation */
	private $variation;

	/** @var string */
	private $variation_sku;

	/** @var string */
	private $product_name;

	/** @var string */
	private $attribute_value;

	/** @var string */
	private $pool_sku;

	/** Install plugin tables. */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		Schema::install();
	}

	/** Create a pool linked to a named, attributed, SKU-bearing variation. */
	public function set_up(): void {
		parent::set_up();
		global $wpdb;
		$stale_pool_ids = $wpdb->get_col( "SELECT id FROM " . Schema::table( 'pools' ) . " WHERE name = 'Bulk ingredient'" );
		foreach ( $stale_pool_ids as $stale_pool_id ) {
			$stale_mapping_ids = $wpdb->get_col( $wpdb->prepare( 'SELECT mapping_id FROM ' . Schema::table( 'mapping_components' ) . ' WHERE pool_id = %d', $stale_pool_id ) );
			foreach ( $stale_mapping_ids as $stale_mapping_id ) {
				$wpdb->delete( Schema::table( 'mapping_components' ), array( 'mapping_id' => $stale_mapping_id ), array( '%d' ) );
				$wpdb->delete( Schema::table( 'mappings' ), array( 'id' => $stale_mapping_id ), array( '%d' ) );
			}
			$wpdb->delete( Schema::table( 'pools' ), array( 'id' => $stale_pool_id ), array( '%d' ) );
		}

		$token                 = wp_generate_uuid4();
		$this->product_name    = 'Heritage Syrup ' . $token;
		$this->attribute_value = 'quarter-' . $token;
		$this->pool_sku        = 'RAW-' . $token;
		$this->product         = new WC_Product_Variable();
		$this->product->set_name( $this->product_name );
		$this->product->save();

		$this->variation = new WC_Product_Variation();
		$this->variation->set_parent_id( $this->product->get_id() );
		$this->variation->set_regular_price( '10' );
		$this->variation_sku = 'PACK-025-' . wp_generate_uuid4();
		$this->variation->set_sku( $this->variation_sku );
		$this->variation->set_attributes( array( 'size' => $this->attribute_value ) );
		$this->variation->save();

		$stale_mappings = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT id FROM ' . Schema::table( 'mappings' ) . ' WHERE product_id IN (%d, %d) OR variation_id IN (%d, %d)',
				$this->product->get_id(),
				$this->variation->get_id(),
				$this->product->get_id(),
				$this->variation->get_id()
			)
		);
		foreach ( $stale_mappings as $stale_mapping ) {
			$wpdb->delete( Schema::table( 'mapping_components' ), array( 'mapping_id' => $stale_mapping ), array( '%d' ) );
			$wpdb->delete( Schema::table( 'mappings' ), array( 'id' => $stale_mapping ), array( '%d' ) );
		}

		$this->pools = new PoolRepository( $wpdb );
		$pool        = $this->pools->create( 'Bulk ingredient', new Quantity( 'mass', 10000000000 ), 'ng', 'g', false, $this->pool_sku );
		$this->pool_id = $pool->id();
		( new MappingRepository( $wpdb ) )->create_single_pool( $this->product->get_id(), $this->variation->get_id(), $this->pool_id, 250000000 );
	}

	/** Remove custom and WooCommerce records. */
	public function tear_down(): void {
		global $wpdb;

		$mapping_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . Schema::table( 'mappings' ) . ' WHERE product_id = %d', $this->product->get_id() ) );
		$wpdb->delete( Schema::table( 'mapping_components' ), array( 'mapping_id' => $mapping_id ), array( '%d' ) );
		$wpdb->delete( Schema::table( 'mappings' ), array( 'id' => $mapping_id ), array( '%d' ) );
		$wpdb->delete( Schema::table( 'pools' ), array( 'id' => $this->pool_id ), array( '%d' ) );
		$this->variation->delete( true );
		$this->product->delete( true );
		parent::tear_down();
	}

	/** Linked product title, variation SKU, and attribute each find the pool. */
	public function test_linked_catalog_fields_find_inventory_pool(): void {
		foreach ( array( $this->product_name, $this->variation_sku, $this->attribute_value, $this->pool_sku ) as $search ) {
			$results = $this->pools->search( $search );
			$this->assertSame( array( $this->pool_id ), array_map( static function ( $pool ): int { return $pool->id(); }, $results ), 'Search failed for ' . $search );
		}
	}

	/** Unrelated catalog text does not leak an inventory pool into results. */
	public function test_unrelated_search_returns_no_pool(): void {
		$this->assertSame( array(), $this->pools->search( 'unrelated package' ) );
	}
}
