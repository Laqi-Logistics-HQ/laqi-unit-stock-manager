<?php
/**
 * Versioned inventory REST API tests.
 *
 * @package LaqiUnitStockManager
 */

use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Storage\PoolRepository;
use LaqiUnitStockManager\Storage\Schema;

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

	/** Anonymous clients cannot read operational inventory. */
	public function test_anonymous_user_is_forbidden(): void {
		wp_set_current_user( 0 );
		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/laqi-lusm/v1/movements' ) );

		$this->assertSame( 401, $response->get_status() );
	}
}
