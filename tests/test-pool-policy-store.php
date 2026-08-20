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

	/** Configured pools are found through a keyed index, not a JSON scan. */
	public function test_lists_configured_pools_from_the_index(): void {
		global $wpdb;
		$store = $this->container->pool_policy_store();
		$store->put( $this->pool_id, 'reorder', array( 'safety_stock_base' => 5 ) );

		$this->assertContains( $this->pool_id, $store->configured_ids( 'reorder' ) );
		$this->assertNotContains( $this->pool_id, $store->configured_ids( 'alerts' ) );
		$this->assertSame(
			'1',
			$wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Schema::table( 'pool_policies' ) . ' WHERE pool_id = %d AND policy_key = %s', $this->pool_id, 'reorder' ) ),
			'Saving a policy indexes it exactly once.'
		);

		$store->put( $this->pool_id, 'reorder', array( 'safety_stock_base' => 9 ) );
		$this->assertSame(
			'1',
			$wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Schema::table( 'pool_policies' ) . ' WHERE pool_id = %d AND policy_key = %s', $this->pool_id, 'reorder' ) ),
			'Replacing a policy does not duplicate its index row.'
		);
	}

	/** Configured pools can be counted and paged without loading every policy. */
	public function test_counts_and_pages_configured_pools(): void {
		$store   = $this->container->pool_policy_store();
		$pools   = $this->container->pool_repository();
		$key     = 'reorder';
		$extra   = $pools->create( 'Policy API pool ' . wp_generate_uuid4(), new Quantity( 'count', 10 ), 'unit', 'unit' )->id();
		$store->put( $this->pool_id, $key, array( 'safety_stock_base' => 5 ) );
		$store->put( $extra, $key, array( 'safety_stock_base' => 7 ) );

		$this->assertGreaterThanOrEqual( 2, $store->count_configured( $key ) );
		$this->assertSame( $store->count_configured( $key ), count( $store->configured_ids( $key ) ) );

		$first = $store->configured_ids_page( $key, 1, 0 );
		$this->assertCount( 1, $first, 'One ID per page, taken in SQL.' );
		$this->assertNotSame( $first, $store->configured_ids_page( $key, 1, 1 ) );

		global $wpdb;
		$wpdb->delete( Schema::table( 'pool_policies' ), array( 'pool_id' => $extra ), array( '%d' ) );
		$wpdb->delete( Schema::table( 'pools' ), array( 'id' => $extra ), array( '%d' ) );
	}

	/** Policies stored before the index existed are backfilled on upgrade. */
	public function test_backfills_policies_stored_before_the_index(): void {
		global $wpdb;
		$store = $this->container->pool_policy_store();
		$store->put( $this->pool_id, 'forecast', array( 'window_days' => 30 ) );
		$wpdb->delete( Schema::table( 'pool_policies' ), array( 'pool_id' => $this->pool_id ), array( '%d' ) );
		$this->assertNotContains( $this->pool_id, $store->configured_ids( 'forecast' ) );

		Schema::install();

		$this->assertContains( $this->pool_id, $store->configured_ids( 'forecast' ), 'Installing the schema rebuilds the index from the stored envelopes.' );
	}

	/** Membership is answered from the index, so an unknown pool is not an error. */
	public function test_reports_whether_one_pool_owns_a_policy(): void {
		$store = $this->container->pool_policy_store();
		$store->put( $this->pool_id, 'reorder', array( 'safety_stock_base' => 5 ) );

		$this->assertTrue( $store->has_configured( 'reorder', $this->pool_id ) );
		$this->assertFalse( $store->has_configured( 'alerts', $this->pool_id ) );
		$this->assertFalse( $store->has_configured( 'reorder', 999999 ), 'An unknown pool is simply not configured.' );
		$this->assertFalse( $store->has_configured( 'reorder', 0 ) );
	}
}
