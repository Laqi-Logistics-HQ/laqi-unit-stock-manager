<?php
/**
 * Activity ledger: the Actor column, the search filter, and the actions hook.
 *
 * These three moved out of the Pro Ledger tab so that one movements table can
 * serve both editions. The tests exist so the fork cannot quietly return, the
 * way Test_Purchasable_Resolution guards the earlier one.
 *
 * @package LaqiUnitStockManager
 */

use LaqiUnitStockManager\Admin\ActivitySection;
use LaqiUnitStockManager\Admin\DatasetRenderer;
use LaqiUnitStockManager\Admin\PaginationRenderer;
use LaqiUnitStockManager\Container;
use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Storage\Schema;

/**
 * Covers what the merged Activity table must offer without Pro installed.
 */
class Test_Activity_Actor_And_Actions extends WP_UnitTestCase {
	/** @var Container */ private $container;
	/** @var int */ private $pool_id = 0;
	/** @var int */ private $actor_id = 0;

	/** One pool with a manual movement by a named user and an order movement. */
	public function set_up(): void {
		parent::set_up();
		$this->container = new Container();
		$pools           = $this->container->pool_repository();
		$this->pool_id   = $pools->create( 'Actor pool ' . wp_generate_uuid4(), new Quantity( 'count', 0 ), 'unit', 'unit' )->id();
		$this->actor_id  = self::factory()->user->create( array( 'display_name' => 'Dana Keeper' ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->record( 'manual_set', '', 0, $this->actor_id, 'counted the shelf' );
		$this->record( 'order_reduction', 'order', 4242, 0, 'sold' );
	}

	/** Remove every record this class creates. */
	public function tear_down(): void {
		global $wpdb;
		$wpdb->delete( Schema::table( 'movements' ), array( 'pool_id' => $this->pool_id ), array( '%d' ) );
		$wpdb->delete( Schema::table( 'pools' ), array( 'id' => $this->pool_id ), array( '%d' ) );
		unset( $GLOBALS['current_screen'] );
		remove_all_actions( 'laqi_lusm_activity_actions' );
		$_GET = array();
		parent::tear_down();
	}

	/**
	 * Insert one movement.
	 *
	 * @param string $type        Movement type.
	 * @param string $source_type Source type, empty for none.
	 * @param int    $source_id   Source ID.
	 * @param int    $actor_id    Acting user, 0 for system.
	 * @param string $reason      Recorded reason.
	 * @return void
	 */
	private function record( string $type, string $source_type, int $source_id, int $actor_id, string $reason ): void {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Test fixture.
			Schema::table( 'movements' ),
			array(
				'pool_id'         => $this->pool_id,
				'type'            => $type,
				'delta_base'      => 1,
				'balance_base'    => 1,
				'source_type'     => $source_type,
				'source_id'       => $source_id,
				'actor_id'        => $actor_id,
				'reason'          => $reason,
				'idempotency_key' => wp_generate_uuid4(),
				'created_at'      => '2031-04-01 09:00:00',
			),
			array( '%d', '%s', '%d', '%d', '%s', '%d', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Render the Activity section.
	 *
	 * @return string
	 */
	private function render(): string {
		// Scope to this class's pool. Repositories commit explicitly, so rows
		// from other tests survive rollback and would otherwise be rendered
		// here - including ones whose custom unit has since been removed.
		$_GET['pool_ids'] = (string) $this->pool_id;
		$section          = new ActivitySection(
			$this->container->movement_repository(),
			$this->container->movement_presenter(),
			new DatasetRenderer( new PaginationRenderer() ),
			$this->container->movement_registry(),
			$this->container->pool_repository()
		);
		ob_start();
		$section->render();
		return (string) ob_get_clean();
	}

	/** The table carries an Actor column naming the user who acted. */
	public function test_actor_column_names_the_acting_user(): void {
		$html = $this->render();

		$this->assertStringContainsString( '>Actor<', $html );
		$this->assertStringContainsString( 'Dana Keeper', $html );
	}

	/** A movement with no acting user says so rather than showing an empty cell. */
	public function test_actor_column_labels_system_movements(): void {
		$this->assertStringContainsString( 'System or deleted user', $this->render() );
	}

	/**
	 * Source answers what caused the movement, not who performed it.
	 *
	 * source_label() used to return the acting user's display name, which made a
	 * manual adjustment read as though a person were its source and would print
	 * the same name twice now that Actor exists.
	 */
	public function test_source_column_no_longer_reports_the_actor(): void {
		$html = $this->render();

		// Assert on the cells, not on how often the name appears: the actor
		// filter dropdown has always listed actors by name, so a raw count
		// would be 2 whatever Source does.
		$this->assertMatchesRegularExpression( '/data-label="Source">\s*System\s*</', $html, 'A manual adjustment has no source, so Source must not name the actor.' );
		$this->assertMatchesRegularExpression( '/data-label="Actor">\s*Dana Keeper\s*</', $html );
		$this->assertMatchesRegularExpression( '/data-label="Source">\s*order #4242\s*</', $html );
	}

	/** The free-text search filter is offered, and narrows the rows. */
	public function test_search_filter_is_offered_and_narrows_rows(): void {
		$this->assertStringContainsString( 'activity_search', $this->render() );

		$_GET['activity_search'] = 'Dana';
		$narrowed                = $this->render();

		$this->assertStringContainsString( 'counted the shelf', $narrowed );
		$this->assertStringNotContainsString( 'order #4242', $narrowed );
	}

	/** Extensions can render their own actions beside the filter bar. */
	public function test_actions_hook_receives_the_filters_and_total(): void {
		$seen = array();
		add_action(
			'laqi_lusm_activity_actions',
			function ( $filters, $pool_ids, $total ) use ( &$seen ) {
				$seen = array( 'filters' => $filters, 'pool_ids' => $pool_ids, 'total' => $total );
				echo '<a class="laqi-lusmp-export">Export</a>';
			},
			10,
			3
		);

		$html = $this->render();

		$this->assertStringContainsString( 'laqi-lusmp-export', $html );
		$this->assertInstanceOf( '\LaqiUnitStockManager\Admin\DatasetFilters', $seen['filters'] );
		$this->assertSame( array( $this->pool_id ), $seen['pool_ids'] );
		$this->assertSame( 2, $seen['total'] );
	}
}
