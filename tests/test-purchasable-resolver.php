<?php
/**
 * WooCommerce purchasable resolver tests.
 *
 * @package LaqiUnitStockManager
 */

use LaqiUnitStockManager\WooCommerce\PurchasableResolver;

/**
 * Verifies stable mapping identities from AJAX product-search results.
 */
class Test_Purchasable_Resolver extends WP_UnitTestCase {

	/** Simple products map to themselves with no variation ID. */
	public function test_simple_product_resolution(): void {
		$product = new WC_Product_Simple();
		$product->set_name( 'Resolver simple product' );
		$product->set_regular_price( '5' );
		$product->save();

		$this->assertSame(
			array( 'product_id' => $product->get_id(), 'variation_id' => 0 ),
			( new PurchasableResolver() )->resolve( $product->get_id() )
		);
		$product->delete( true );
	}

	/** Variations resolve to their parent and exact variation IDs. */
	public function test_variation_resolution(): void {
		$parent = new WC_Product_Variable();
		$parent->set_name( 'Resolver variable product' );
		$parent->save();
		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $parent->get_id() );
		$variation->set_regular_price( '5' );
		$variation->save();

		$this->assertSame(
			array( 'product_id' => $parent->get_id(), 'variation_id' => $variation->get_id() ),
			( new PurchasableResolver() )->resolve( $variation->get_id() )
		);
		$variation->delete( true );
		$parent->delete( true );
	}

	/** Variable parents and missing IDs are not directly mappable. */
	public function test_unsupported_product_is_rejected(): void {
		$product = new WC_Product_Variable();
		$product->set_name( 'Resolver unsupported parent' );
		$product->save();

		$this->expectException( InvalidArgumentException::class );
		try {
			( new PurchasableResolver() )->resolve( $product->get_id() );
		} finally {
			$product->delete( true );
		}
	}
}
