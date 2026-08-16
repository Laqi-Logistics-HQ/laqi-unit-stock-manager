<?php
/**
 * Product-context discoverability tests.
 *
 * @package LaqiUnitStockManager
 */

use LaqiUnitStockManager\Domain\MappingComponent;
use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Storage\MappingRepository;
use LaqiUnitStockManager\Storage\PoolRepository;
use LaqiUnitStockManager\Storage\Schema;

/** Verifies bulk product summaries used by WooCommerce's product directory. */
class Test_Product_Context extends WP_UnitTestCase {

	/** Install custom tables. */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		Schema::install();
	}

	/** Product summaries include variation progress, recipes, pools, and warnings in one query. */
	public function test_product_summaries_are_bulk_loaded(): void {
		global $wpdb;

		$product_id = self::factory()->post->create( array( 'post_type' => 'product' ) );
		$first      = self::factory()->post->create( array( 'post_type' => 'product_variation', 'post_parent' => $product_id ) );
		$second     = self::factory()->post->create( array( 'post_type' => 'product_variation', 'post_parent' => $product_id ) );
		$old_ids    = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . Schema::table( 'mappings' ) . ' WHERE product_id = %d', $product_id ) );
		foreach ( $old_ids as $old_id ) {
			$wpdb->delete( Schema::table( 'mapping_components' ), array( 'mapping_id' => $old_id ), array( '%d' ) );
		}
		$wpdb->delete( Schema::table( 'mappings' ), array( 'product_id' => $product_id ), array( '%d' ) );
		$pool       = ( new PoolRepository( $wpdb ) )->create( 'Product context flour', new Quantity( 'mass', 1000000000 ), 'ng', 'kg' );
		$mappings   = new MappingRepository( $wpdb );
		$mappings->save_components(
			$product_id,
			$first,
			'recipe',
			array(
				new MappingComponent( $pool->id(), 250000000, 'contents' ),
				new MappingComponent( $pool->id(), 1, 'packaging' ),
			)
		);
		update_post_meta( $first, '_manage_stock', 'yes' );

		$before    = $wpdb->num_queries;
		$summaries = $mappings->summaries_for_products( array( $product_id, 987654321 ) );

		$this->assertSame( 1, $wpdb->num_queries - $before );
		$this->assertSame( 1, $summaries[ $product_id ]['mapping_count'] );
		$this->assertSame( 1, $summaries[ $product_id ]['variation_mapping_count'] );
		$this->assertSame( 2, $summaries[ $product_id ]['variation_count'] );
		$this->assertSame( 1, $summaries[ $product_id ]['recipe_count'] );
		$this->assertSame( 2, $summaries[ $product_id ]['recipe_component_count'] );
		$this->assertSame( 1, $summaries[ $product_id ]['warning_count'] );
		$this->assertArrayNotHasKey( 987654321, $summaries );
	}
}
