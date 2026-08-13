<?php
/**
 * Versioned inventory REST API tests.
 *
 * @package LaqiUnitStockManager
 */

use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Storage\PoolRepository;
use LaqiUnitStockManager\Storage\Schema;
use LaqiUnitStockManager\Inventory\StockMutationService;

/**
 * Verifies shared inventory reads and idempotent adjustments over REST.
 */
class Test_Inventory_REST_API extends WP_UnitTestCase {

	/** @var int */
	private $pool_id;

	/** @var int */
	private $user_id;

	/** Install tables. */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		Schema::install();
	}

	/** Create an authorized user and count pool. */
	public function set_up(): void {
		parent::set_up();
		global $wpdb;
		$this->user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->user_id );
		$this->pool_id = ( new PoolRepository( $wpdb ) )->create( 'REST pool', new Quantity( 'count', 10 ), 'unit', 'unit' )->id();
	}

	/** Remove custom records. */
	public function tear_down(): void {
		global $wpdb;
		$wpdb->delete( Schema::table( 'movements' ), array( 'pool_id' => $this->pool_id ), array( '%d' ) );
		$wpdb->delete( Schema::table( 'pools' ), array( 'id' => $this->pool_id ), array( '%d' ) );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/** Pool listing uses the versioned normalized presenter shape. */
	public function test_authorized_user_can_list_pools(): void {
		$request = new WP_REST_Request( 'GET', '/laqi-lusm/v1/pools' );
		$request->set_query_params( array( 'search' => 'REST pool' ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 1, $data['version'] );
		$this->assertContains( $this->pool_id, wp_list_pluck( $data['items'], 'id' ) );
	}

	/** Repeating one REST adjustment key returns the original movement. */
	public function test_rest_adjustment_is_idempotent(): void {
		$key     = 'delivery-' . wp_generate_uuid4();
		$request = new WP_REST_Request( 'POST', '/laqi-lusm/v1/pools/' . $this->pool_id . '/adjustments' );
		$request->set_body_params(
			array(
				'mode'            => 'add',
				'quantity'        => '2',
				'unit'            => 'unit',
				'reason'          => 'API delivery',
				'idempotency_key' => $key,
			)
		);
		$first  = rest_get_server()->dispatch( $request );
		$second = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $first->get_status() );
		$this->assertFalse( $first->get_data()['duplicate'] );
		$this->assertTrue( $second->get_data()['duplicate'] );
		$this->assertSame( 12, $second->get_data()['pool']['quantity_base'] );
	}

	/** Movement pages expose pagination metadata and stable recent-first rows. */
	public function test_movement_endpoint_supports_pagination(): void {
		global $wpdb;

		$service = new StockMutationService( $wpdb );
		$first   = $service->apply( $this->pool_id, 1, 'manual_add', 'rest-page-first:' . $this->pool_id );
		$second  = $service->apply( $this->pool_id, 1, 'manual_add', 'rest-page-second:' . $this->pool_id );

		$first_page = new WP_REST_Request( 'GET', '/laqi-lusm/v1/movements' );
		$first_page->set_query_params( array( 'limit' => 1, 'page' => 1 ) );
		$second_page = new WP_REST_Request( 'GET', '/laqi-lusm/v1/movements' );
		$second_page->set_query_params( array( 'limit' => 1, 'page' => 2 ) );
		$first_data  = rest_get_server()->dispatch( $first_page )->get_data();
		$second_data = rest_get_server()->dispatch( $second_page )->get_data();

		$this->assertSame( $second->movement_id(), $first_data['items'][0]['id'] );
		$this->assertSame( $first->movement_id(), $second_data['items'][0]['id'] );
		$this->assertSame( 1, $first_data['pagination']['page'] );
		$this->assertSame( 1, $first_data['pagination']['per_page'] );
		$this->assertGreaterThanOrEqual( 2, $first_data['pagination']['total_items'] );
		$this->assertSame( $first_data['pagination']['total_items'], $first_data['pagination']['total_pages'] );
	}

	/** Anonymous clients cannot read operational inventory. */
	public function test_anonymous_user_is_forbidden(): void {
		wp_set_current_user( 0 );
		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/laqi-lusm/v1/movements' ) );

		$this->assertSame( 401, $response->get_status() );
	}
}
