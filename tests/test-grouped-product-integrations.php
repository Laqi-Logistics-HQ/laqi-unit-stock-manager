<?php
/** Product Bundles and Composite Products adapter tests. @package LaqiUnitStockManager */

use LaqiUnitStockManager\Premium\Integrations\GroupedProductAdapter;

/** Verifies container exclusion without suppressing mapped child lines. */
class Test_Grouped_Product_Integrations extends WP_UnitTestCase {
	/** @var GroupedProductAdapter */ private $adapter;

	/** Create the adapter. */
	public function set_up(): void {
		parent::set_up();
		$this->adapter = new GroupedProductAdapter();
	}

	/** Product Bundle containers are excluded while bundled children remain. */
	public function test_bundle_cart_container_is_excluded_but_child_is_included(): void {
		$cart = WC()->cart;
		$this->assertFalse( $this->adapter->include_cart_item( true, array( 'bundled_items' => array( 'child-key' ) ), 'parent', $cart ) );
		$this->assertTrue( $this->adapter->include_cart_item( true, array( 'bundled_by' => 'parent' ), 'child-key', $cart ) );
	}

	/** Composite containers are excluded while composited children remain. */
	public function test_composite_cart_container_is_excluded_but_child_is_included(): void {
		$cart = WC()->cart;
		$this->assertFalse( $this->adapter->include_cart_item( true, array( 'composite_children' => array( 'child-key' ) ), 'parent', $cart ) );
		$this->assertTrue( $this->adapter->include_cart_item( true, array( 'composite_parent' => 'parent' ), 'child-key', $cart ) );
	}

	/** Checkout uses the source cart relationship data before item meta is saved. */
	public function test_checkout_container_is_excluded(): void {
		$item  = new WC_Order_Item_Product();
		$order = wc_create_order();
		$this->assertFalse( $this->adapter->include_checkout_item( true, $item, array( 'bundled_items' => array( 'child' ) ), 'parent', $order ) );
		$this->assertTrue( $this->adapter->include_checkout_item( true, $item, array( 'bundled_by' => 'parent' ), 'child', $order ) );
		$order->delete( true );
	}

	/** Persisted container meta is excluded from admin-order snapshotting. */
	public function test_order_container_is_excluded_but_child_is_included(): void {
		$container = new WC_Order_Item_Product();
		$container->add_meta_data( '_composite_children', array( 'child' ), true );
		$child = new WC_Order_Item_Product();
		$child->add_meta_data( '_composite_parent', 'parent', true );
		$this->assertFalse( $this->adapter->include_order_item( true, $container ) );
		$this->assertTrue( $this->adapter->include_order_item( true, $child ) );
	}

	/** An earlier exclusion decision is never overridden. */
	public function test_adapter_preserves_prior_exclusion(): void {
		$this->assertFalse( $this->adapter->include_order_item( false, new WC_Order_Item_Product() ) );
	}
}
