<?php
/**
 * Atomic stock mutation integration tests.
 *
 * @package LaqiUnitStockManager
 */

use LaqiUnitStockManager\Inventory\InsufficientStockException;
use LaqiUnitStockManager\Inventory\StockMutationService;
use LaqiUnitStockManager\Storage\Schema;
use LaqiUnitStockManager\Storage\MovementRepository;
use LaqiUnitStockManager\Container;
use LaqiUnitStockManager\Premium\Alerts\LowStockPolicyRepository;
use LaqiUnitStockManager\Premium\Alerts\LowStockAlertEvaluator;

/**
 * Tests the single authoritative stock mutation path.
 */
class Test_Stock_Mutation_Service extends WP_UnitTestCase {

	/** @var int */
	private $pool_id;

	/**
	 * Install tables once.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		Schema::install();
	}

	/**
	 * Create a fresh 10 kg pool before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		global $wpdb;

		$now = current_time( 'mysql', true );
		$wpdb->insert(
			Schema::table( 'pools' ),
			array(
				'name'          => 'Ingredient A',
				'family'        => 'mass',
				'base_unit'     => 'ng',
				'display_unit'  => 'kg',
				'quantity_base' => 10000000000000,
				'created_at'    => $now,
				'updated_at'    => $now,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
		$this->pool_id = (int) $wpdb->insert_id;
	}

	/**
	 * Remove custom-table rows after each test.
	 */
	public function tear_down(): void {
		global $wpdb;

		$wpdb->delete( Schema::table( 'movements' ), array( 'pool_id' => $this->pool_id ), array( '%d' ) );
		$wpdb->delete( Schema::table( 'pools' ), array( 'id' => $this->pool_id ), array( '%d' ) );
		parent::tear_down();
	}

	/**
	 * A sale decrements the pool and records the resulting balance.
	 */
	public function test_decrement_updates_pool_and_records_movement(): void {
		global $wpdb;

		$result = ( new StockMutationService( $wpdb ) )->apply(
			$this->pool_id,
			-250000000,
			'order_reduction',
			'order-item:10:reduce:' . $this->pool_id
		);

		$this->assertSame( 9999750000000, $result->balance() );
		$this->assertFalse( $result->is_duplicate() );
		$this->assertSame(
			'9999750000000',
			$wpdb->get_var( $wpdb->prepare( 'SELECT quantity_base FROM ' . Schema::table( 'pools' ) . ' WHERE id = %d', $this->pool_id ) )
		);
	}

	/**
	 * Repeating the same event returns its first result without decrementing.
	 */
	public function test_idempotency_key_prevents_duplicate_decrement(): void {
		global $wpdb;

		$service = new StockMutationService( $wpdb );
		$key     = 'order-item:11:reduce:' . $this->pool_id;
		$first   = $service->apply( $this->pool_id, -1000000000, 'order_reduction', $key );
		$second  = $service->apply( $this->pool_id, -1000000000, 'order_reduction', $key );

		$this->assertSame( $first->movement_id(), $second->movement_id() );
		$this->assertTrue( $second->is_duplicate() );
		$this->assertSame( 9999000000000, $second->balance() );
	}

	/**
	 * A rejected decrement leaves both balance and ledger unchanged.
	 */
	public function test_insufficient_stock_rolls_back(): void {
		global $wpdb;

		try {
			( new StockMutationService( $wpdb ) )->apply(
				$this->pool_id,
				-10000000000001,
				'order_reduction',
				'order-item:12:reduce:' . $this->pool_id
			);
			$this->fail( 'Expected insufficient stock exception.' );
		} catch ( InsufficientStockException $error ) {
			$this->assertSame( 10000000000000, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT quantity_base FROM ' . Schema::table( 'pools' ) . ' WHERE id = %d', $this->pool_id ) ) );
			$this->assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Schema::table( 'movements' ) . ' WHERE pool_id = %d', $this->pool_id ) ) );
		}
	}

	/**
	 * Failure in one pool rolls back every pool in the batch.
	 */
	public function test_batch_is_atomic_across_pools(): void {
		global $wpdb;
		$second = $this->pool_id + 100000;
		$wpdb->query( $wpdb->prepare( 'INSERT INTO ' . Schema::table( 'pools' ) . ' (id,name,family,base_unit,display_unit,quantity_base,created_at,updated_at) VALUES (%d,%s,%s,%s,%s,%d,UTC_TIMESTAMP(),UTC_TIMESTAMP())', $second, 'Second', 'mass', 'ng', 'g', 100 ) );

		try {
			( new StockMutationService( $wpdb ) )->apply_batch(
				array(
					array( 'pool_id' => $this->pool_id, 'delta' => -100, 'type' => 'test', 'idempotency_key' => 'batch-a:' . $this->pool_id ),
					array( 'pool_id' => $second, 'delta' => -101, 'type' => 'test', 'idempotency_key' => 'batch-b:' . $this->pool_id ),
				)
			);
			$this->fail( 'Expected insufficient stock exception.' );
		} catch ( InsufficientStockException $error ) {
			$this->assertSame( 10000000000000, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT quantity_base FROM ' . Schema::table( 'pools' ) . ' WHERE id = %d', $this->pool_id ) ) );
			$this->assertSame( 100, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT quantity_base FROM ' . Schema::table( 'pools' ) . ' WHERE id = %d', $second ) ) );
		}
		$wpdb->delete( Schema::table( 'pools' ), array( 'id' => $second ), array( '%d' ) );
	}

	/**
	 * Absolute adjustments calculate their ledger delta while holding the lock.
	 */
	public function test_set_balance_records_exact_delta_and_is_idempotent(): void {
		global $wpdb;

		$service = new StockMutationService( $wpdb );
		$key     = 'manual-set:' . $this->pool_id;
		$first   = $service->set_balance( $this->pool_id, 5000000000000, 'manual_set', $key, array( 'reason' => 'Counted stock' ) );
		$second  = $service->set_balance( $this->pool_id, 4000000000000, 'manual_set', $key );

		$this->assertSame( 5000000000000, $first->balance() );
		$this->assertTrue( $second->is_duplicate() );
		$this->assertSame( -5000000000000, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT delta_base FROM ' . Schema::table( 'movements' ) . ' WHERE id = %d', $first->movement_id() ) ) );
		$this->assertSame( 'Counted stock', $wpdb->get_var( $wpdb->prepare( 'SELECT reason FROM ' . Schema::table( 'movements' ) . ' WHERE id = %d', $first->movement_id() ) ) );
	}

	/**
	 * Completed mutations are readable through the shared activity repository.
	 */
	public function test_movement_repository_reads_ledger_context(): void {
		global $wpdb;

		$result = ( new StockMutationService( $wpdb ) )->apply(
			$this->pool_id,
			100,
			'manual_add',
			'activity:' . $this->pool_id,
			array( 'source_type' => 'manual', 'reason' => 'Delivery correction' )
		);
		$rows = ( new MovementRepository( $wpdb ) )->recent( 100 );
		$row  = current( array_filter( $rows, static function ( array $candidate ) use ( $result ): bool { return (int) $candidate['id'] === $result->movement_id(); } ) );

		$this->assertIsArray( $row );
		$this->assertSame( 'manual_add', $row['type'] );
		$this->assertSame( 'Delivery correction', $row['reason'] );
		$this->assertSame( 'Ingredient A', $row['pool_name'] );
	}

	/** Ledger reads expose stable recent-first offsets and a complete count. */
	public function test_movement_repository_paginates_the_complete_ledger(): void {
		global $wpdb;

		$repository = new MovementRepository( $wpdb );
		$before     = $repository->count();
		$service    = new StockMutationService( $wpdb );
		$first      = $service->apply( $this->pool_id, 10, 'manual_add', 'page-first:' . $this->pool_id );
		$second     = $service->apply( $this->pool_id, 20, 'manual_add', 'page-second:' . $this->pool_id );

		$this->assertSame( $before + 2, $repository->count() );
		$this->assertSame( $second->movement_id(), (int) $repository->recent( 1, 0 )[0]['id'] );
		$this->assertSame( $first->movement_id(), (int) $repository->recent( 1, 1 )[0]['id'] );
	}

	/** Operational modules can search ledger context without another data path. */
	public function test_movement_repository_searches_pool_type_source_and_reason(): void {
		global $wpdb;
		$actor   = self::factory()->user->create( array( 'display_name' => 'Warehouse Operator' ) );
		$service = new StockMutationService( $wpdb );
		$result  = $service->apply( $this->pool_id, 10, 'manual_add', 'search-ledger:' . $this->pool_id, array( 'source_type' => 'delivery', 'actor_id' => $actor, 'reason' => 'Damaged replacement' ) );
		$repo    = new MovementRepository( $wpdb );
		foreach ( array( 'Ingredient A', 'manual_add', 'delivery', 'Warehouse Operator', 'Damaged replacement' ) as $term ) {
			$this->assertSame( $result->movement_id(), (int) $repo->search( $term, 1 )[0]['id'] );
			$this->assertGreaterThanOrEqual( 1, $repo->count_search( $term ) );
		}
		$this->assertSame( 'Warehouse Operator', $repo->search( 'Warehouse Operator', 1 )[0]['actor_name'] );
	}

	/** Operational modules can record typed relative changes through shared validation. */
	public function test_typed_stock_change_records_exact_loss_context(): void {
		global $wpdb;
		$service = ( new Container() )->stock_adjustment_service();
		$result  = $service->change( $this->pool_id, -1, '0.25', 'kg', 'loss_damage', 'loss', 'Broken container', 7, 'loss-test:' . $this->pool_id );
		$row     = ( new MovementRepository( $wpdb ) )->recent( 1 )[0];
		$this->assertSame( -250000000000, (int) $row['delta_base'] );
		$this->assertSame( 9750000000000, $result->balance() );
		$this->assertSame( 'loss_damage', $row['type'] );
		$this->assertSame( 'loss', $row['source_type'] );
		$this->assertSame( 'Broken container', $row['reason'] );
		$this->assertSame( 7, (int) $row['actor_id'] );
	}

	/** Low-stock email fires once per crossing and rearms after recovery. */
	public function test_low_stock_alert_fires_once_per_threshold_crossing(): void {
		global $wpdb;
		$sent = 0;
		add_filter(
			'pre_wp_mail',
			static function () use ( &$sent ): bool {
				++$sent;
				return true;
			}
		);
		$policies = new LowStockPolicyRepository( $wpdb );
		$wpdb->update( Schema::table( 'pools' ), array( 'policy_json' => wp_json_encode( array( 'allocation' => 'future-strategy' ) ) ), array( 'id' => $this->pool_id ) );
		$policies->save( $this->pool_id, 9000000000000, array( 'stock@example.com' ) );
		$envelope = json_decode( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT policy_json FROM ' . Schema::table( 'pools' ) . ' WHERE id = %d', $this->pool_id ) ), true );
		$this->assertSame( 'future-strategy', $envelope['allocation'] );
		$service = new StockMutationService( $wpdb );
		$service->apply( $this->pool_id, -2000000000000, 'manual_subtract', 'alert-low:' . $this->pool_id );
		$service->apply( $this->pool_id, -1000000000000, 'manual_subtract', 'alert-still-low:' . $this->pool_id );
		$this->assertSame( 1, $sent );
		$this->assertTrue( (bool) $policies->find( $this->pool_id )['is_low'] );
		$service->apply( $this->pool_id, 4000000000000, 'manual_add', 'alert-recover:' . $this->pool_id );
		$service->apply( $this->pool_id, -3000000000000, 'manual_subtract', 'alert-low-again:' . $this->pool_id );
		$this->assertSame( 2, $sent );
	}

	/** Alert severity escalates and scheduled reminders respect delivery state. */
	public function test_low_stock_alert_escalates_and_sends_due_reminder(): void {
		global $wpdb;
		$sent = 0;
		add_filter(
			'pre_wp_mail',
			static function () use ( &$sent ): bool {
				++$sent;
				return true;
			}
		);
		$container = new Container();
		$policies  = new LowStockPolicyRepository( $wpdb );
		$policies->save( $this->pool_id, 9000000000000, array( 'stock@example.com' ), 5000000000000, 24 );
		$service = new StockMutationService( $wpdb );
		$service->apply( $this->pool_id, -2000000000000, 'manual_subtract', 'severity-warning:' . $this->pool_id );
		$this->assertSame( 'warning', $policies->find( $this->pool_id )['severity'] );
		$service->apply( $this->pool_id, -4000000000000, 'manual_subtract', 'severity-critical:' . $this->pool_id );
		$this->assertSame( 'critical', $policies->find( $this->pool_id )['severity'] );
		$this->assertSame( 2, $sent );
		$policies->set_evaluation_state( $this->pool_id, 'critical', time() - ( 25 * HOUR_IN_SECONDS ) );
		$evaluator = new LowStockAlertEvaluator( $policies, $container->pool_repository(), $container->quantity_formatter() );
		$evaluator->evaluate( array( $this->pool_id ) );
		$this->assertSame( 3, $sent );
		$evaluator->schedule();
		$this->assertNotFalse( wp_next_scheduled( LowStockAlertEvaluator::CRON_HOOK ) );
		$evaluator->unschedule();
	}

	/** Quiet hours defer a crossing until a later evaluation. */
	public function test_low_stock_quiet_hours_defer_delivery(): void {
		global $wpdb;
		$sent = 0;
		add_filter(
			'pre_wp_mail',
			static function () use ( &$sent ): bool {
				++$sent;
				return true;
			}
		);
		$hour       = (int) wp_date( 'G' );
		$policies   = new LowStockPolicyRepository( $wpdb );
		$container  = new Container();
		$evaluator  = new LowStockAlertEvaluator( $policies, $container->pool_repository(), $container->quantity_formatter() );
		$policies->save( $this->pool_id, 9000000000000, array( 'stock@example.com' ), 5000000000000, 24, $hour, ( $hour + 1 ) % 24 );
		( new StockMutationService( $wpdb ) )->apply( $this->pool_id, -2000000000000, 'manual_subtract', 'quiet-warning:' . $this->pool_id );
		$this->assertSame( 0, $sent );
		$this->assertSame( 'warning', $policies->find( $this->pool_id )['severity'] );
		$policies->save( $this->pool_id, 9000000000000, array( 'stock@example.com' ), 5000000000000, 24 );
		$evaluator->evaluate( array( $this->pool_id ) );
		$this->assertSame( 1, $sent );
	}
}
