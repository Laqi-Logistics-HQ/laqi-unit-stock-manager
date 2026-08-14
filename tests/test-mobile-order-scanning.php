<?php
/** Woo Mobile order and pool-aware scanning tests. @package LaqiUnitStockManager */

use LaqiUnitStockManager\Container;
use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Premium\Integrations\MobileOrderAdapter;
use LaqiUnitStockManager\Premium\Reservations\OrderReservationService;
use LaqiUnitStockManager\Premium\Reservations\ReservationRepository;
use LaqiUnitStockManager\Storage\Schema;
use LaqiUnitStockManager\WooCommerce\OrderItemSnapshotter;
use LaqiUnitStockManager\WooCommerce\OrderStockLifecycle;

/** Verifies REST-created orders and scan lookups use pooled inventory. */
class Test_Mobile_Order_Scanning extends WP_UnitTestCase {
	/** @var Container */ private $container;
	/** @var ReservationRepository */ private $reservation_rows;
	/** @var MobileOrderAdapter */ private $adapter;
	/** @var WC_Product_Simple */ private $product;
	/** @var WC_Order */ private $order;
	/** @var int */ private $pool_id;
	/** @var int */ private $user_id;

	/** Create a mapped SKU and REST-origin order. */
	public function set_up(): void {
		parent::set_up();
		global $wpdb;
		$this->container        = new Container();
		$this->reservation_rows = new ReservationRepository( $wpdb );
		$this->reservation_rows->install();
		$reservations = new OrderReservationService( $this->reservation_rows );
		$snapshots    = new OrderItemSnapshotter( $this->container->mapping_repository(), $this->container->calculator_registry() );
		$this->adapter = new MobileOrderAdapter( $snapshots, $reservations );
		$this->product = new WC_Product_Simple();
		$this->product->set_name( 'Mobile scan product' );
		$this->product->set_sku( 'MOBILE-' . wp_generate_uuid4() );
		$this->product->set_regular_price( '10' );
		$this->product->save();
		$stale_mapping_ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . Schema::table( 'mappings' ) . ' WHERE product_id = %d', $this->product->get_id() ) );
		foreach ( $stale_mapping_ids as $mapping_id ) {
			$wpdb->delete( Schema::table( 'mapping_components' ), array( 'mapping_id' => $mapping_id ), array( '%d' ) );
		}
		$wpdb->delete( Schema::table( 'mappings' ), array( 'product_id' => $this->product->get_id() ), array( '%d' ) );
		$this->pool_id = $this->container->pool_repository()->create( 'Mobile pool ' . wp_generate_uuid4(), new Quantity( 'count', 100 ), 'unit', 'unit' )->id();
		$this->container->mapping_repository()->create_single_pool( $this->product->get_id(), 0, $this->pool_id, 3 );
		$this->order = wc_create_order( array( 'created_via' => 'rest-api' ) );
		foreach ( array_keys( $this->order->get_items() ) as $item_id ) { $this->order->remove_item( $item_id ); }
		$item = new WC_Order_Item_Product(); $item->set_product_id( $this->product->get_id() ); $item->set_quantity( 2 );
		$item->add_meta_data( OrderItemSnapshotter::META_KEY, array( 'pool_demand' => array( $this->pool_id => 1 ) ), true );
		$this->order->add_item( $item ); $this->order->save();
		$this->user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
	}

	/** Remove fixture data. */
	public function tear_down(): void {
		global $wpdb;
		$wpdb->delete( Schema::table( 'reservations' ), array( 'order_id' => $this->order->get_id() ), array( '%d' ) );
		$this->order->delete( true );
		$mapping = $this->container->mapping_repository()->find_for_product( $this->product->get_id() );
		if ( null !== $mapping ) { $wpdb->delete( Schema::table( 'mapping_components' ), array( 'mapping_id' => $mapping->id() ), array( '%d' ) ); $wpdb->delete( Schema::table( 'mappings' ), array( 'id' => $mapping->id() ), array( '%d' ) ); }
		$wpdb->delete( Schema::table( 'movements' ), array( 'pool_id' => $this->pool_id ), array( '%d' ) );
		$wpdb->delete( Schema::table( 'pools' ), array( 'id' => $this->pool_id ), array( '%d' ) );
		$this->product->delete( true ); wp_set_current_user( 0 ); parent::tear_down();
	}

	/** New mobile orders replace submitted snapshots and reserve exact demand once. */
	public function test_rest_order_is_snapshotted_and_reserved_idempotently(): void {
		$request = new WP_REST_Request( 'POST', '/wc/v3/orders' );
		$this->adapter->prepare( $this->order, $request, true ); $this->adapter->prepare( $this->order, $request, true );
		$item = current( $this->order->get_items() ); $snapshot = $item->get_meta( OrderItemSnapshotter::META_KEY, true );
		$this->assertSame( 'admin', $snapshot['origin'] ); $this->assertSame( 6, $snapshot['pool_demand'][ $this->pool_id ] );
		$this->assertSame( 6, $this->reservation_rows->reserved_quantity( $this->pool_id ) );
	}

	/** REST order updates do not re-prepare existing order stock. */
	public function test_rest_order_update_is_ignored(): void {
		$this->adapter->prepare( $this->order, new WP_REST_Request( 'PUT', '/wc/v3/orders/' . $this->order->get_id() ), false );
		$this->assertSame( 0, $this->reservation_rows->reserved_quantity( $this->pool_id ) );
	}

	/** Authenticated SKU scans expose demand and availability without mutation. */
	public function test_sku_scan_returns_pool_aware_inventory(): void {
		wp_set_current_user( $this->user_id );
		$request = new WP_REST_Request( 'GET', '/laqi-lusm/v1/scan' ); $request->set_query_params( array( 'code' => $this->product->get_sku() ) );
		$response = rest_get_server()->dispatch( $request ); $data = $response->get_data();
		$this->assertSame( 200, $response->get_status() ); $this->assertSame( $this->product->get_id(), $data['product']['id'] );
		$this->assertSame( 33, $data['saleable_quantity'] ); $this->assertSame( 3, $data['pools'][0]['demand_per_product'] ); $this->assertSame( 100, $data['pools'][0]['available_base'] );
	}

	/** Scanner inventory remains private. */
	public function test_anonymous_scan_is_forbidden(): void {
		wp_set_current_user( 0 ); $request = new WP_REST_Request( 'GET', '/laqi-lusm/v1/scan' ); $request->set_query_params( array( 'code' => $this->product->get_sku() ) );
		$this->assertSame( 401, rest_get_server()->dispatch( $request )->get_status() );
	}
}
