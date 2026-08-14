<?php
/** ERP/WMS external movement integration tests. @package LaqiUnitStockManager */

use LaqiUnitStockManager\Container;
use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Premium\Integrations\ExternalMovementService;
use LaqiUnitStockManager\Storage\Schema;

/** Verifies external events use atomic, typed, idempotent movements. */
class Test_External_Movements extends WP_UnitTestCase {
	/** @var Container */ private $container;
	/** @var ExternalMovementService */ private $service;
	/** @var int[] */ private $pool_ids = array();
	/** @var string[] */ private $pool_skus = array();
	/** @var int */ private $user_id;
	/** @var string */ private $event_namespace;

	/** Create two externally addressable pools. */
	public function set_up(): void {
		parent::set_up();
		$this->container = new Container();
		$token                 = wp_generate_uuid4();
		$this->event_namespace = $token;
		foreach ( array( 'A', 'B' ) as $suffix ) {
			$sku = 'ERP-' . $suffix . '-' . $token;
			$pool = $this->container->pool_repository()->create( 'ERP pool ' . $suffix . ' ' . $token, new Quantity( 'count', 10 ), 'unit', 'unit', false, $sku );
			$this->pool_ids[] = $pool->id();
			$this->pool_skus[] = $sku;
		}
		$this->service = new ExternalMovementService( $this->container->pool_repository(), $this->container->unit_registry(), $this->container->stock_mutation_service() );
		$this->user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
	}

	/** Remove external movement fixtures. */
	public function tear_down(): void {
		global $wpdb;
		foreach ( $this->pool_ids as $pool_id ) {
			$wpdb->delete( Schema::table( 'movements' ), array( 'pool_id' => $pool_id ), array( '%d' ) );
			$wpdb->delete( Schema::table( 'pools' ), array( 'id' => $pool_id ), array( '%d' ) );
		}
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/** One external event changes several pools atomically and replays safely. */
	public function test_external_event_is_atomic_and_idempotent(): void {
		$rows = array(
			array( 'pool_sku' => $this->pool_skus[0], 'direction' => 'add', 'quantity' => '4', 'unit' => 'unit', 'reference' => 'receipt-1' ),
			array( 'pool_sku' => $this->pool_skus[1], 'direction' => 'subtract', 'quantity' => '3', 'unit' => 'unit', 'reason' => 'shipment' ),
		);
		$event = 'event-' . $this->event_namespace;
		$first = $this->service->import( 'warehouse_one', $event, $rows, $this->user_id );
		$retry = $this->service->import( 'warehouse_one', $event, $rows, $this->user_id );
		$this->assertSame( array( 14, 7 ), $this->balances() );
		$this->assertFalse( $first[0]->is_duplicate() );
		$this->assertTrue( $retry[0]->is_duplicate() );
		$this->assertTrue( $retry[1]->is_duplicate() );
	}

	/** Retrying an event with changed stock demand is rejected. */
	public function test_changed_idempotent_event_is_rejected(): void {
		$rows = array( array( 'pool_sku' => $this->pool_skus[0], 'direction' => 'add', 'quantity' => '2', 'unit' => 'unit' ) );
		$event = 'immutable-' . $this->event_namespace;
		$this->service->import( 'erp', $event, $rows, $this->user_id );
		$rows[0]['quantity'] = '3';
		$this->expectException( RuntimeException::class );
		$this->service->import( 'erp', $event, $rows, $this->user_id );
	}

	/** A failing decrement rolls the whole multi-pool event back. */
	public function test_insufficient_event_rolls_back_every_pool(): void {
		$this->expectException( LaqiUnitStockManager\Inventory\InsufficientStockException::class );
		try {
			$this->service->import(
				'wms',
				'oversold-' . $this->event_namespace,
				array(
					array( 'pool_sku' => $this->pool_skus[0], 'direction' => 'add', 'quantity' => '5', 'unit' => 'unit' ),
					array( 'pool_sku' => $this->pool_skus[1], 'direction' => 'subtract', 'quantity' => '11', 'unit' => 'unit' ),
				),
				$this->user_id
			);
		} finally {
			$this->assertSame( array( 10, 10 ), $this->balances() );
		}
	}

	/** Authorized REST clients receive movement IDs and duplicates. */
	public function test_external_movement_rest_endpoint(): void {
		wp_set_current_user( $this->user_id );
		$request = new WP_REST_Request( 'POST', '/laqi-lusm/v1/external-movements' );
		$request->set_body_params(
			array(
				'integration' => 'erp',
				'event_id'    => 'rest-' . $this->event_namespace,
				'movements'   => array( array( 'pool_sku' => $this->pool_skus[0], 'direction' => 'add', 'quantity' => '1', 'unit' => 'unit' ) ),
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( $response->get_data()['items'][0]['duplicate'] );
		$this->assertSame( 11, $response->get_data()['items'][0]['balance_base'] );
	}

	/** Duplicate pool SKUs are rejected instead of selecting an arbitrary pool. */
	public function test_ambiguous_pool_sku_is_rejected(): void {
		$duplicate = $this->container->pool_repository()->create( 'Duplicate ERP pool', new Quantity( 'count', 10 ), 'unit', 'unit', false, $this->pool_skus[0] );
		$this->pool_ids[] = $duplicate->id();
		$this->expectException( RuntimeException::class );
		$this->service->import( 'erp', 'ambiguous-' . $this->event_namespace, array( array( 'pool_sku' => $this->pool_skus[0], 'direction' => 'add', 'quantity' => '1', 'unit' => 'unit' ) ), $this->user_id );
	}

	/** Anonymous systems cannot submit inventory movements. */
	public function test_anonymous_external_movement_is_forbidden(): void {
		wp_set_current_user( 0 );
		$request = new WP_REST_Request( 'POST', '/laqi-lusm/v1/external-movements' );
		$this->assertSame( 401, rest_get_server()->dispatch( $request )->get_status() );
	}

	/** Current balances in fixture order. @return int[] */
	private function balances(): array {
		return array_map(
			function ( int $pool_id ): int {
				return $this->container->pool_repository()->find( $pool_id )->quantity()->amount();
			},
			$this->pool_ids
		);
	}
}
