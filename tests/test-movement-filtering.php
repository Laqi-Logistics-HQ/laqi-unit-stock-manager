<?php
/** Shared movement filtering tests. @package LaqiUnitStockManager */

use LaqiUnitStockManager\Container;
use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Storage\MovementRepository;
use LaqiUnitStockManager\Storage\Schema;

/**
 * Verifies the one filtered ledger read that Free and registered Pro tabs share.
 *
 * The repository used to carry three near-identical read pairs — all rows, rows
 * for a pool set, and a search — each repeating the same SELECT and JOIN. They
 * are now thin delegations to a single filtered query, so these tests cover both
 * the new filters and the compatibility of the older methods.
 */
class Test_Movement_Filtering extends WP_UnitTestCase {
	/** @var Container */ private $container;
	/** @var MovementRepository */ private $movements;
	/** @var int */ private $flour_id = 0;
	/** @var int */ private $sugar_id = 0;
	/** @var int */ private $actor_id = 0;

	/** Two pools with movements of different types, sources, and actors. */
	public function set_up(): void {
		parent::set_up();
		global $wpdb;
		$this->container = new Container();
		$this->movements = $this->container->movement_repository();
		$pools           = $this->container->pool_repository();
		$this->flour_id  = $pools->create( 'Filter flour ' . wp_generate_uuid4(), new Quantity( 'count', 0 ), 'unit', 'unit' )->id();
		$this->sugar_id  = $pools->create( 'Filter sugar ' . wp_generate_uuid4(), new Quantity( 'count', 0 ), 'unit', 'unit' )->id();
		$this->actor_id  = self::factory()->user->create( array( 'display_name' => 'Filter Actor' ) );

		$this->record( $this->flour_id, 'manual_set', 'admin', $this->actor_id, 'counted the shelf', '2031-03-01 09:00:00' );
		$this->record( $this->flour_id, 'order_reduction', 'order', 0, 'sold', '2031-03-05 09:00:00' );
		$this->record( $this->sugar_id, 'manual_set', 'admin', 0, 'system correction', '2031-03-10 09:00:00' );
	}

	/** Remove every record this class creates. */
	public function tear_down(): void {
		global $wpdb;
		foreach ( array( $this->flour_id, $this->sugar_id ) as $pool_id ) {
			$wpdb->delete( Schema::table( 'movements' ), array( 'pool_id' => $pool_id ), array( '%d' ) );
			$wpdb->delete( Schema::table( 'pools' ), array( 'id' => $pool_id ), array( '%d' ) );
		}
		parent::tear_down();
	}

	/** One pool narrows the ledger. */
	public function test_filters_by_pool(): void {
		$this->assertSame( 2, $this->movements->count( array( 'pool_id' => $this->flour_id ) ) );
		$this->assertSame( 1, $this->movements->count( array( 'pool_id' => $this->sugar_id ) ) );
	}

	/** Movement type and source are categorical filters. */
	public function test_filters_by_type_and_source(): void {
		$this->assertSame(
			1,
			$this->movements->count(
				array(
					'pool_id' => $this->flour_id,
					'type'    => 'manual_set',
				)
			)
		);
		$this->assertSame(
			1,
			$this->movements->count(
				array(
					'pool_id'     => $this->flour_id,
					'source_type' => 'order',
				)
			)
		);
	}

	/** An actor of zero means system-recorded, which is a choice not an absence. */
	public function test_filters_by_actor_including_the_system(): void {
		$this->assertSame(
			1,
			$this->movements->count(
				array(
					'pool_id' => $this->flour_id,
					'actor'   => (string) $this->actor_id,
				)
			)
		);
		$this->assertSame(
			1,
			$this->movements->count(
				array(
					'pool_id' => $this->flour_id,
					'actor'   => '0',
				)
			),
			'Filtering on the system actor must not be treated as no filter.'
		);
		$this->assertSame( 2, $this->movements->count( array( 'pool_id' => $this->flour_id ) ) );
	}

	/** A date range covers whole local days despite UTC storage. */
	public function test_filters_by_date_range(): void {
		$this->assertSame(
			1,
			$this->movements->count(
				array(
					'pool_id' => $this->flour_id,
					'from'    => '2031-03-04',
					'to'      => '2031-03-06',
				)
			)
		);
		$this->assertSame(
			1,
			$this->movements->count(
				array(
					'pool_id' => $this->flour_id,
					'from'    => '2031-03-05',
					'to'      => '2031-03-05',
				)
			),
			'A single-day range includes that whole day.'
		);
	}

	/** Reason is filtered on its own column, separately from the broad search. */
	public function test_filters_by_reason(): void {
		$this->assertSame(
			1,
			$this->movements->count(
				array(
					'pool_id' => $this->flour_id,
					'reason'  => 'shelf',
				)
			)
		);
		$this->assertSame(
			0,
			$this->movements->count(
				array(
					'pool_id' => $this->flour_id,
					'reason'  => 'Filter flour',
				)
			),
			'A reason filter must not match the pool name.'
		);
	}

	/** Filters combine, and the page carries pool and actor context. */
	public function test_reads_a_filtered_page(): void {
		$rows = $this->movements->page(
			array(
				'pool_id' => $this->flour_id,
				'type'    => 'manual_set',
			),
			25,
			0
		);

		$this->assertCount( 1, $rows );
		$this->assertSame( 'counted the shelf', $rows[0]['reason'] );
		$this->assertSame( 'Filter Actor', $rows[0]['actor_name'], 'Every ledger read now carries the actor name.' );
		$this->assertArrayHasKey( 'pool_name', $rows[0] );
	}

	/** Only values actually present become filter choices. */
	public function test_offers_used_values_as_choices(): void {
		$this->assertContains( 'manual_set', $this->movements->used_types() );
		$this->assertContains( 'order', $this->movements->used_sources() );
		$this->assertContains( $this->actor_id, array_map( 'intval', wp_list_pluck( $this->movements->used_actors(), 'id' ) ) );
	}

	/** The older read methods still behave, now as delegations. */
	public function test_existing_reads_still_work(): void {
		$this->assertSame( 2, $this->movements->count_for_pools( array( $this->flour_id ) ) );
		$this->assertCount( 2, $this->movements->recent_for_pools( array( $this->flour_id ) ) );
		$this->assertSame( 0, $this->movements->count_for_pools( array() ) );
		$this->assertSame( array(), $this->movements->recent_for_pools( array() ) );
		$this->assertSame( 1, $this->movements->count_search( 'counted the shelf' ) );
		$this->assertCount( 1, $this->movements->search( 'counted the shelf' ) );
		$this->assertGreaterThanOrEqual( 3, $this->movements->count() );
	}

	/**
	 * Record one movement directly, so its timestamp and actor are exact.
	 *
	 * @param int    $pool_id     Pool ID.
	 * @param string $type        Movement type.
	 * @param string $source_type Source type.
	 * @param int    $actor_id    Actor ID.
	 * @param string $reason      Recorded reason.
	 * @param string $created_at  UTC timestamp.
	 * @return void
	 */
	private function record( int $pool_id, string $type, string $source_type, int $actor_id, string $reason, string $created_at ): void {
		global $wpdb;
		$wpdb->insert(
			Schema::table( 'movements' ),
			array(
				'pool_id'         => $pool_id,
				'type'            => $type,
				'delta_base'      => 1,
				'balance_base'    => 1,
				'source_type'     => $source_type,
				'source_id'       => 0,
				'actor_id'        => $actor_id,
				'reason'          => $reason,
				'idempotency_key' => wp_generate_uuid4(),
				'created_at'      => $created_at,
			),
			array( '%d', '%s', '%d', '%d', '%s', '%d', '%d', '%s', '%s', '%s' )
		);
	}
}
