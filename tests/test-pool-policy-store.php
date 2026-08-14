<?php
/** Pool policy extension API tests. @package LaqiUnitStockManager */

use LaqiUnitStockManager\Container;
use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Storage\Schema;

/** Verifies add-ons can own policy namespaces without table knowledge. */
class Test_Pool_Policy_Store extends WP_UnitTestCase {
	/** @var Container */
	private $container;

	/** @var int */
	private $pool_id;

	/** Create one policy-bearing pool. */
	public function set_up(): void {
		parent::set_up();
		$this->container = new Container();
		$this->pool_id   = $this->container->pool_repository()->create( 'Policy API pool ' . wp_generate_uuid4(), new Quantity( 'count', 10 ), 'unit', 'unit' )->id();
	}

	/** Remove the pool fixture. */
	public function tear_down(): void {
		global $wpdb;
		$wpdb->delete( Schema::table( 'pools' ), array( 'id' => $this->pool_id ), array( '%d' ) );
		parent::tear_down();
	}

	/** Namespaces replace independently without exposing the shared envelope. */
	public function test_namespaced_policies_are_isolated(): void {
		$store = $this->container->pool_policy_store();
		$store->put( $this->pool_id, 'forecast', array( 'window_days' => 60 ) );
		$store->put( $this->pool_id, 'alerts', array( 'threshold_base' => 4 ) );
		$store->put( $this->pool_id, 'forecast', array( 'window_days' => 90 ) );

		$this->assertSame( array( 'window_days' => 90 ), $store->get( $this->pool_id, 'forecast' ) );
		$this->assertSame( array( 'threshold_base' => 4 ), $store->get( $this->pool_id, 'alerts' ) );
		$this->assertSame( array(), $store->get( $this->pool_id, 'unknown_policy' ) );
	}

	/** Policy enumeration exposes IDs without leaking the shared envelope. */
	public function test_configured_ids_are_namespaced(): void {
		$store = $this->container->pool_policy_store();
		$store->put( $this->pool_id, 'alerts', array( 'threshold_base' => 4 ) );

		$this->assertContains( $this->pool_id, $store->configured_ids( 'alerts' ) );
		$this->assertNotContains( $this->pool_id, $store->configured_ids( 'forecast' ) );
	}

	/** Invalid namespace keys are rejected before persistence. */
	public function test_invalid_namespace_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->container->pool_policy_store()->put( $this->pool_id, 'Forecast Settings', array() );
	}

	/** Invalid enumeration namespaces are rejected before querying. */
	public function test_invalid_enumeration_namespace_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->container->pool_policy_store()->configured_ids( 'Forecast Settings' );
	}
}
