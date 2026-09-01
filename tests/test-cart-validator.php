<?php
/**
 * Classic and Store API pooled-stock cart validation tests.
 *
 * @package LaqiUnitStockManager
 */

use LaqiUnitStockManager\Consumption\CalculatorRegistry;
use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Storage\MappingRepository;
use LaqiUnitStockManager\Storage\PoolRepository;
use LaqiUnitStockManager\Storage\Schema;
use LaqiUnitStockManager\WooCommerce\CartValidator;

/**
 * Proves every WooCommerce cart channel aggregates shared-pool demand.
 */
class Test_Cart_Validator extends WP_UnitTestCase {

	/** @var int */
	private $pool_id;

	/** @var CartValidator */
	private $validator;

	/** @var WC_Cart|null */
	private $original_cart;

	/** Install tables. */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		Schema::install();
	}

	/** Create a 10 g pool shared by 0.25 g and 2 g variations. */
	public function set_up(): void {
		parent::set_up();
		global $wpdb;

		$mapping_ids = $wpdb->get_col( 'SELECT id FROM ' . Schema::table( 'mappings' ) . ' WHERE product_id = 200' );
		foreach ( $mapping_ids as $mapping_id ) {
			$wpdb->delete( Schema::table( 'mapping_components' ), array( 'mapping_id' => $mapping_id ), array( '%d' ) );
		}
		$wpdb->delete( Schema::table( 'mappings' ), array( 'product_id' => 200 ), array( '%d' ) );

		$pools = new PoolRepository( $wpdb );
		$maps  = new MappingRepository( $wpdb );
		$pool  = $pools->create( 'Cart channel pool', new Quantity( 'mass', 10000000000 ), 'ng', 'g' );
		$maps->create_single_pool( 200, 201, $pool->id(), 250000000 );
		$maps->create_single_pool( 200, 202, $pool->id(), 2000000000 );

		$this->pool_id       = $pool->id();
		$this->validator     = new CartValidator( new \LaqiUnitStockManager\Availability\AvailabilityService( $maps, $pools, new CalculatorRegistry() ) );
		$this->original_cart = WC()->cart;
		wc_clear_notices();
	}

	/** Remove custom rows and restore the WooCommerce cart. */
	public function tear_down(): void {
		global $wpdb;

		$mapping_ids = $wpdb->get_col( 'SELECT id FROM ' . Schema::table( 'mappings' ) . ' WHERE product_id = 200' );
		foreach ( $mapping_ids as $mapping_id ) {
			$wpdb->delete( Schema::table( 'mapping_components' ), array( 'mapping_id' => $mapping_id ), array( '%d' ) );
		}
		$wpdb->delete( Schema::table( 'mappings' ), array( 'product_id' => 200 ), array( '%d' ) );
		$wpdb->delete( Schema::table( 'pools' ), array( 'id' => $this->pool_id ), array( '%d' ) );
		WC()->cart = $this->original_cart;
		wc_clear_notices();
		parent::tear_down();
	}

	/** Classic checkout rejects combined demand that exceeds the shared pool. */
	public function test_classic_cart_reports_combined_variation_shortage(): void {
		WC()->cart = $this->cart( 9, 4 );
		$this->validator->validate_classic_cart();

		$notices = wc_get_notices( 'error' );
		$this->assertCount( 1, $notices );
		$this->assertStringContainsString( 'shared stock', $notices[0]['notice'] );
	}

	/** Store API and Blocks receive a stable machine-readable cart error. */
	public function test_store_api_reports_combined_variation_shortage(): void {
		$errors = new WP_Error();
		$this->validator->validate_store_api_cart( $errors, $this->cart( 9, 4 ) );

		$this->assertSame( array( 'laqi_lusm_insufficient_pool_stock' ), $errors->get_error_codes() );
		$this->assertStringContainsString( 'shared stock', $errors->get_error_message() );
	}

	/** An exact allocation passes both cart channels without an error. */
	public function test_exact_combined_allocation_passes_both_channels(): void {
		$cart       = $this->cart( 8, 4 );
		WC()->cart = $cart;
		$this->validator->validate_classic_cart();
		$errors = new WP_Error();
		$this->validator->validate_store_api_cart( $errors, $cart );

		$this->assertSame( array(), wc_get_notices( 'error' ) );
		$this->assertFalse( $errors->has_errors() );
	}

	/** Build a cart without invoking unrelated WooCommerce product validation. */
	private function cart( int $small_quantity, int $large_quantity ): WC_Cart {
		$cart = new WC_Cart();
		$cart->set_cart_contents(
			array(
				'small' => array( 'product_id' => 200, 'variation_id' => 201, 'quantity' => $small_quantity ),
				'large' => array( 'product_id' => 200, 'variation_id' => 202, 'quantity' => $large_quantity ),
			)
		);
		return $cart;
	}
}
