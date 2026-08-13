<?php
/** Premium order reservation integration tests. @package LaqiUnitStockManager */

use LaqiUnitStockManager\Container;
use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Premium\Reservations\OrderReservationService;
use LaqiUnitStockManager\Premium\Reservations\ReservationRepository;
use LaqiUnitStockManager\Premium\Supply\SafetyStockPolicyRepository;
use LaqiUnitStockManager\Storage\Schema;

/** Verifies exact, idempotent reservation supply states. */
class Test_Order_Reservations extends WP_UnitTestCase {
	/** @var ReservationRepository */ private $reservations;
	/** @var int */ private $pool_id;
	/** @var int */ private $order_id = 0;
	/** Install schemas. */ public static function set_up_before_class(): void { parent::set_up_before_class(); Schema::install(); global $wpdb; delete_option( ReservationRepository::SCHEMA_OPTION ); ( new ReservationRepository( $wpdb ) )->install(); }
	/** Create ten count units. */ public function set_up(): void { parent::set_up(); global $wpdb; $wpdb->query( 'DELETE FROM ' . Schema::table( 'reservations' ) ); $this->reservations = new ReservationRepository( $wpdb ); $this->pool_id = ( new Container() )->pool_repository()->create( 'Reserved units ' . wp_generate_uuid4(), new Quantity( 'count', 10 ), 'each', 'each' )->id(); }
	/** Clean records. */ public function tear_down(): void { global $wpdb; $wpdb->delete( Schema::table( 'reservations' ), array( 'pool_id' => $this->pool_id ), array( '%d' ) ); $wpdb->delete( Schema::table( 'pools' ), array( 'id' => $this->pool_id ), array( '%d' ) ); if ( $this->order_id > 0 ) { $order = wc_get_order( $this->order_id ); if ( $order ) { $order->delete( true ); } } parent::tear_down(); }
	/** Reservation is idempotent and immediately reduces available-to-sell. */
	public function test_reserves_exact_snapshot_demand_idempotently(): void { $service = new OrderReservationService( $this->reservations ); $expiry = gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS ); $this->reservations->reserve( 900001, array( $this->pool_id => 4 ), $expiry ); $this->reservations->reserve( 900001, array( $this->pool_id => 4 ), $expiry ); $this->assertSame( 4, $this->reservations->reserved_quantity( $this->pool_id ) ); $this->assertSame( 6, $service->available_quantity( 10, $this->pool_id ) ); $this->assertSame( 6, apply_filters( 'laqi_lusm_pool_available_quantity', 10, $this->pool_id ) ); $this->assertCount( 1, $this->reservations->for_order( 900001 ) ); }
	/** Service aggregates the immutable snapshots saved on an order. */
	public function test_service_reserves_saved_order_snapshots(): void { $order = wc_create_order(); $this->order_id = $order->get_id(); $item = new WC_Order_Item_Product(); $item->set_name( 'Snapshot-only test line' ); $item->set_quantity( 1 ); $item->update_meta_data( '_laqi_lusm_stock_snapshot', array( 'item_quantity' => 1, 'pool_demand' => array( $this->pool_id => 3 ) ) ); $order->add_item( $item ); $order->save(); $order = wc_get_order( $this->order_id ); ( new OrderReservationService( $this->reservations ) )->reserve_order( $order ); $this->assertSame( 3, $this->reservations->reserved_quantity( $this->pool_id ) ); }
	/** Competing orders cannot reserve beyond on-hand stock. */
	public function test_rejects_reservation_oversell(): void { $this->reservations->reserve( 100001, array( $this->pool_id => 7 ), gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS ) ); $this->expectException( RuntimeException::class ); $this->reservations->reserve( 100002, array( $this->pool_id => 4 ), gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS ) ); }
	/** Reservations cannot consume the protected safety buffer. */
	public function test_reservations_respect_safety_stock(): void { global $wpdb; ( new SafetyStockPolicyRepository( $wpdb ) )->save( $this->pool_id, 3 ); $this->expectException( RuntimeException::class ); $this->reservations->reserve( 100003, array( $this->pool_id => 8 ), gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS ) ); }
	/** Reduction conversion and cancellation release stop withholding supply. */
	public function test_transitions_release_available_supply(): void { $service = new OrderReservationService( $this->reservations ); $this->reservations->reserve( 200001, array( $this->pool_id => 3 ), gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS ) ); $this->assertSame( 7, $service->available_quantity( 10, $this->pool_id ) ); $this->reservations->transition( 200001, 'converted' ); $this->assertSame( 10, $service->available_quantity( 10, $this->pool_id ) ); $this->reservations->reserve( 200002, array( $this->pool_id => 2 ), gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS ) ); $this->reservations->transition( 200002, 'released' ); $this->assertSame( 10, $service->available_quantity( 10, $this->pool_id ) ); }
	/** Elapsed reservations expire and cannot block availability. */
	public function test_expires_elapsed_reservations(): void { global $wpdb; $this->reservations->reserve( 300001, array( $this->pool_id => 5 ), gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS ) ); $wpdb->update( Schema::table( 'reservations' ), array( 'expires_at' => '2000-01-01 00:00:00' ), array( 'order_id' => 300001 ), array( '%s' ), array( '%d' ) ); $this->assertSame( 0, $this->reservations->reserved_quantity( $this->pool_id ) ); $this->assertGreaterThanOrEqual( 1, $this->reservations->expire() ); $this->assertSame( 'expired', $this->reservations->for_order( 300001 )[0]['status'] ); }
}
