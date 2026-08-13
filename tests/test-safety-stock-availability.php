<?php
/** Premium safety-stock availability tests. @package LaqiUnitStockManager */

use LaqiUnitStockManager\Container;
use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Premium\Supply\SafetyStockAvailability;
use LaqiUnitStockManager\Premium\Supply\SafetyStockPolicyRepository;
use LaqiUnitStockManager\Premium\Supply\StockHoldRepository;
use LaqiUnitStockManager\Premium\Supply\SupplyProjectionService;
use LaqiUnitStockManager\Storage\Schema;

/** Verifies the protected buffer composes with operational supply states. */
class Test_Safety_Stock_Availability extends WP_UnitTestCase {
	/** @var SafetyStockPolicyRepository */ private $policies;
	/** @var int */ private $pool_id;

	/** Create a ten-unit pool. */
	public function set_up(): void {
		parent::set_up();
		global $wpdb;
		$this->policies = new SafetyStockPolicyRepository( $wpdb );
		$this->pool_id  = ( new Container() )->pool_repository()->create( 'Safety stock ' . wp_generate_uuid4(), new Quantity( 'count', 10 ), 'each', 'each' )->id();
	}

	/** Remove fixture records. */
	public function tear_down(): void {
		global $wpdb;
		$wpdb->delete( Schema::table( 'stock_holds' ), array( 'pool_id' => $this->pool_id ), array( '%d' ) );
		$wpdb->delete( Schema::table( 'pools' ), array( 'id' => $this->pool_id ), array( '%d' ) );
		parent::tear_down();
	}

	/** Safety stock reduces availability and preserves unrelated policies. */
	public function test_policy_preserves_envelope_and_reduces_availability(): void {
		global $wpdb;
		$wpdb->update( Schema::table( 'pools' ), array( 'policy_json' => wp_json_encode( array( 'forecast' => array( 'window_days' => 30 ) ) ) ), array( 'id' => $this->pool_id ) );
		$this->policies->save( $this->pool_id, 3 );
		$stored = json_decode( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT policy_json FROM ' . Schema::table( 'pools' ) . ' WHERE id = %d', $this->pool_id ) ), true );
		$this->assertSame( 30, $stored['forecast']['window_days'] );
		$this->assertSame( 3, $stored['availability']['safety_stock_base'] );
		$this->assertSame( 7, ( new SafetyStockAvailability( $this->policies ) )->available_quantity( 10, $this->pool_id ) );
	}

	/** Projection exposes safety-only pools and incoming replenishment. */
	public function test_projection_includes_available_and_incoming_quantities(): void {
		global $wpdb;
		$this->policies->save( $this->pool_id, 3 );
		$rows = ( new SupplyProjectionService( new StockHoldRepository( $wpdb ), $this->policies ) )->rows();
		$row  = array_values( array_filter( $rows, function ( array $item ): bool { return $this->pool_id === (int) $item['pool_id']; } ) )[0];
		$this->assertSame( 3, (int) $row['safety_stock_base'] );
		$this->assertSame( 7, (int) $row['available_base'] );
		$this->assertSame( 7, (int) $row['projected_base'] );
	}
}
