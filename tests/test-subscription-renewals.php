<?php
/** WooCommerce Subscriptions renewal integration tests. @package LaqiUnitStockManager */

use LaqiUnitStockManager\Container;
use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Premium\Integrations\SubscriptionRenewalAdapter;
use LaqiUnitStockManager\Premium\Reservations\OrderReservationService;
use LaqiUnitStockManager\Premium\Reservations\ReservationRepository;
use LaqiUnitStockManager\Storage\Schema;
use LaqiUnitStockManager\WooCommerce\OrderItemSnapshotter;
use LaqiUnitStockManager\WooCommerce\OrderStockLifecycle;

/** Verifies renewal orders use current mappings and normal stock lifecycle. */
class Test_Subscription_Renewals extends WP_UnitTestCase {
	/** @var Container */ private $container;
	/** @var ReservationRepository */ private $reservation_rows;
	/** @var OrderReservationService */ private $reservations;
	/** @var SubscriptionRenewalAdapter */ private $adapter;
	/** @var OrderItemSnapshotter */ private $snapshots;
	/** @var WC_Order */ private $order;
	/** @var int */ private $pool_id;
	/** @var int */ private $product_id;

	/** Build a renewal containing an inherited stale stock snapshot. */
	public function set_up(): void {
		parent::set_up();
		global $wpdb;
		$this->container        = new Container();
		$this->reservation_rows = new ReservationRepository( $wpdb );
		$this->reservation_rows->install();
		$this->reservations = new OrderReservationService( $this->reservation_rows );
		$this->snapshots    = new OrderItemSnapshotter( $this->container->mapping_repository(), $this->container->calculator_registry() );
		$this->adapter      = new SubscriptionRenewalAdapter( $this->snapshots, $this->reservations );
		$product            = new WC_Product_Simple();
		$product->set_name( 'Renewal product' );
		$product->set_regular_price( '10' );
		$product->save();
		$this->product_id = $product->get_id();
		$stale_mapping_ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . Schema::table( 'mappings' ) . ' WHERE product_id = %d', $this->product_id ) );
		foreach ( $stale_mapping_ids as $mapping_id ) {
			$wpdb->delete( Schema::table( 'mapping_components' ), array( 'mapping_id' => $mapping_id ), array( '%d' ) );
		}
		$wpdb->delete( Schema::table( 'mappings' ), array( 'product_id' => $this->product_id ), array( '%d' ) );
		$this->pool_id    = $this->container->pool_repository()->create( 'Renewal pool', new Quantity( 'count', 100 ), 'each', 'each' )->id();
		$wpdb->delete( $wpdb->prefix . 'laqi_lusm_reservations', array( 'pool_id' => $this->pool_id ), array( '%d' ) );
		$this->container->mapping_repository()->create_single_pool( $this->product_id, 0, $this->pool_id, 3 );

		$this->order = wc_create_order();
		foreach ( array_keys( $this->order->get_items() ) as $existing_item_id ) {
			$this->order->remove_item( $existing_item_id );
		}
		$item        = new WC_Order_Item_Product();
		$item->set_product_id( $this->product_id );
		$item->set_quantity( 2 );
		$item->add_meta_data( OrderItemSnapshotter::META_KEY, array( 'origin' => 'checkout', 'item_quantity' => 1, 'pool_demand' => array( $this->pool_id => 1 ) ), true );
		$this->order->add_item( $item );
		$this->order->save();
	}

	/** Remove committed repository and order rows. */
	public function tear_down(): void {
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'laqi_lusm_reservations', array( 'order_id' => $this->order->get_id() ), array( '%d' ) );
		$this->order->delete( true );
		$mapping_ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . Schema::table( 'mappings' ) . ' WHERE product_id = %d', $this->product_id ) );
		foreach ( $mapping_ids as $mapping_id ) {
			$wpdb->delete( Schema::table( 'mapping_components' ), array( 'mapping_id' => $mapping_id ), array( '%d' ) );
		}
		$wpdb->delete( Schema::table( 'mappings' ), array( 'product_id' => $this->product_id ), array( '%d' ) );
		$wpdb->delete( Schema::table( 'movements' ), array( 'pool_id' => $this->pool_id ), array( '%d' ) );
		$wpdb->delete( Schema::table( 'pools' ), array( 'id' => $this->pool_id ), array( '%d' ) );
		wp_delete_post( $this->product_id, true );
		parent::tear_down();
	}

	/** Copied metadata is replaced by current mapping demand and reserved. */
	public function test_renewal_replaces_inherited_snapshot_and_reserves_current_demand(): void {
		$this->adapter->prepare( $this->order, null );
		$items    = $this->order->get_items();
		$item     = current( $items );
		$snapshot = $item->get_meta( OrderItemSnapshotter::META_KEY, true );
		$this->assertSame( 'admin', $snapshot['origin'] );
		$this->assertSame( 6, $snapshot['pool_demand'][ $this->pool_id ] );
		$this->assertSame( 6, $this->reservation_rows->reserved_quantity( $this->pool_id ) );
	}

	/** Repeated creation callbacks cannot silently change prepared demand. */
	public function test_renewal_preparation_is_idempotent_after_mapping_changes(): void {
		$this->adapter->prepare( $this->order, null );
		$mapping = $this->container->mapping_repository()->find_for_product( $this->product_id );
		$this->container->mapping_repository()->save_single_pool( $this->product_id, 0, $this->pool_id, 4, true, $mapping->version() );
		$this->adapter->prepare( $this->order, null );
		$items = $this->order->get_items();
		$item  = current( $items );
		$this->assertSame( 6, $item->get_meta( OrderItemSnapshotter::META_KEY, true )['pool_demand'][ $this->pool_id ] );
		$this->assertSame( 6, $this->reservation_rows->reserved_quantity( $this->pool_id ) );
	}

	/** Standard Woo order reduction and restoration consume the renewal snapshot. */
	public function test_renewal_uses_standard_reduction_and_restoration(): void {
		$this->adapter->prepare( $this->order, null );
		$lifecycle = new OrderStockLifecycle( $this->container->stock_mutation_service(), $this->snapshots );
		$lifecycle->reduce( $this->order );
		$this->reservations->convert_order( $this->order );
		$this->assertSame( 94, $this->balance() );
		$this->assertSame( 0, $this->reservation_rows->reserved_quantity( $this->pool_id ) );
		$lifecycle->restore( $this->order );
		$this->assertSame( 100, $this->balance() );
	}

	/** Failed renewals release prepared reservations without a stock movement. */
	public function test_failed_renewal_releases_reservation(): void {
		$this->adapter->prepare( $this->order, null );
		$this->reservations->release_order( $this->order );
		$this->assertSame( 0, $this->reservation_rows->reserved_quantity( $this->pool_id ) );
		$this->assertSame( 100, $this->balance() );
	}

	/** Current pool balance. @return int */
	private function balance(): int {
		return $this->container->pool_repository()->find( $this->pool_id )->quantity()->amount();
	}
}
