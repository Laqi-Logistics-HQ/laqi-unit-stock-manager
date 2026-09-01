<?php
/** Shared dataset pagination and filtering tests. @package LaqiUnitStockManager */

use LaqiUnitStockManager\Admin\DatasetFilters;
use LaqiUnitStockManager\Admin\DatasetPage;
use LaqiUnitStockManager\Admin\DatasetView;

/** Verifies the pagination arithmetic and filter contract every table shares. */
class Test_Dataset_Tables extends WP_UnitTestCase {
	/** Restore the query string between tests. */
	public function tear_down(): void {
		$_GET = array();
		parent::tear_down();
	}

	/** An empty dataset still reports one page and no rows. */
	public function test_empty_dataset_reports_a_single_page(): void {
		$page = new DatasetPage( 3, 0 );

		$this->assertSame( 1, $page->number() );
		$this->assertSame( 1, $page->total_pages() );
		$this->assertSame( 0, $page->offset() );
		$this->assertTrue( $page->is_empty() );
	}

	/** Twenty-five rows per page is the shared contract. */
	public function test_pages_at_twenty_five_rows(): void {
		$page = new DatasetPage( 2, 60 );

		$this->assertSame( 25, $page->per_page() );
		$this->assertSame( 3, $page->total_pages() );
		$this->assertSame( 25, $page->offset() );
		$this->assertSame( 'Showing 26-50 of 60 matching batches.', $page->summary( 'Showing %1$d-%2$d of %3$d matching batches.', 25 ) );
	}

	/** A page beyond the end clamps to the last page rather than showing nothing. */
	public function test_clamps_a_page_beyond_the_last(): void {
		$this->assertSame( 3, ( new DatasetPage( 99, 60 ) )->number() );
		$this->assertSame( 1, ( new DatasetPage( 0, 60 ) )->number() );
	}

	/** The requested page is read from the dataset's own query argument. */
	public function test_reads_the_requested_page_from_the_query_string(): void {
		$_GET['batch_page'] = '2';

		$this->assertSame( 2, DatasetPage::from_query( 'batch_page', 60 )->number() );
	}

	/** Only declared choices reach the repository. */
	public function test_rejects_an_undeclared_choice(): void {
		$_GET['batch_state'] = 'DROP TABLE';
		$filters             = $this->batch_filters();

		$this->assertSame( '', $filters->value( 'batch_state' ) );
		$this->assertSame( array(), $filters->to_query() );
		$this->assertFalse( $filters->is_active() );
	}

	/** Dates must be exact calendar dates. */
	public function test_rejects_a_malformed_date(): void {
		$_GET['batch_from'] = '2026-13-45';
		$_GET['batch_to']   = '2026-09-01';
		$filters            = $this->batch_filters();

		$this->assertSame( array( 'to' => '2026-09-01' ), $filters->to_query() );
	}

	/** Filters are handed to the repository under its own names. */
	public function test_maps_query_arguments_to_repository_filters(): void {
		$_GET['batch_supplier'] = '4';
		$_GET['batch_state']    = 'quarantined';
		$_GET['batch_search']   = '  LOT-9  ';
		$filters                = $this->batch_filters();

		$this->assertSame(
			array(
				'supplier_id' => '4',
				'state'       => 'quarantined',
				'search'      => 'LOT-9',
			),
			$filters->to_query()
		);
	}

	/** A defaulted filter still reaches the repository but is not called out as chosen. */
	public function test_a_default_filter_applies_without_counting_as_active(): void {
		$filters = $this->status_filters();

		$this->assertSame( array( 'status' => 'pending' ), $filters->to_query() );
		$this->assertSame( array(), $filters->query_args() );
		$this->assertFalse( $filters->is_active() );
	}

	/** Clearing a defaulted filter survives pagination. */
	public function test_clearing_a_default_filter_is_preserved_in_links(): void {
		$_GET['incoming_status'] = '';
		$filters                 = $this->status_filters();

		$this->assertSame( array(), $filters->to_query() );
		$this->assertSame( array( 'incoming_status' => '' ), $filters->query_args() );
		$this->assertTrue( $filters->is_active() );
		$this->assertSame( array( 'Status: All statuses' ), $filters->active_labels() );
	}

	/** The empty state names each active filter using its human value. */
	public function test_describes_every_active_filter(): void {
		$_GET['batch_supplier'] = '4';
		$_GET['batch_pool']     = '77';
		$filters                = $this->batch_filters();
		$filters->describe( 'batch_pool', 'Bakery flour' );

		$this->assertSame( array( 'Supplier: Miller Ltd', 'Inventory pool: Bakery flour' ), $filters->active_labels() );
	}

	/** Links keep the screen, the filters, and the panel the table lives in. */
	public function test_links_carry_screen_filters_and_panel(): void {
		$_GET['batch_supplier'] = '4';
		$view                   = new DatasetView(
			'batch',
			$this->batch_filters(),
			new DatasetPage( 1, 60 ),
			array(
				'post_type'      => 'product',
				'page'           => 'laqi-unit-stock-manager',
				'section'        => 'receiving',
				'receiving_view' => 'batches',
			),
			'laqi-lusm-receiving-batches'
		);

		$this->assertSame( 'batch_page', $view->page_arg() );
		$this->assertSame( '#laqi-lusm-receiving-batches', $view->fragment() );
		$this->assertArrayHasKey( 'batch_supplier', $view->link_args() );
		$this->assertSame( 'batches', $view->link_args()['receiving_view'] );
		$this->assertStringEndsWith( '#laqi-lusm-receiving-batches', $view->form_action() );
		$this->assertStringNotContainsString( 'batch_supplier', $view->reset_url() );
		$this->assertStringContainsString( 'receiving_view=batches', $view->reset_url() );
	}

	/** Batch filter declaration used across these tests. */
	private function batch_filters(): DatasetFilters {
		return DatasetFilters::read(
			array(
				'batch_supplier' => array(
					'control' => 'select',
					'filter'  => 'supplier_id',
					'label'   => 'Supplier',
					'choices' => array(
						''  => 'All suppliers',
						'4' => 'Miller Ltd',
					),
				),
				'batch_pool'     => array(
					'control' => 'pool',
					'filter'  => 'pool_id',
					'label'   => 'Inventory pool',
				),
				'batch_state'    => array(
					'control' => 'select',
					'filter'  => 'state',
					'label'   => 'State',
					'choices' => array(
						''            => 'All states',
						'quarantined' => 'Quarantined',
					),
				),
				'batch_from'     => array(
					'control' => 'date',
					'filter'  => 'from',
					'label'   => 'Expiry from',
				),
				'batch_to'       => array(
					'control' => 'date',
					'filter'  => 'to',
					'label'   => 'Expiry to',
				),
				'batch_search'   => array(
					'control' => 'search',
					'filter'  => 'search',
					'label'   => 'Search batches',
				),
			)
		);
	}

	/** Delivery status declaration, which carries a non-empty default. */
	private function status_filters(): DatasetFilters {
		return DatasetFilters::read(
			array(
				'incoming_status' => array(
					'control' => 'select',
					'filter'  => 'status',
					'label'   => 'Status',
					'default' => 'pending',
					'choices' => array(
						''         => 'All statuses',
						'pending'  => 'Expected',
						'received' => 'Received',
					),
				),
			)
		);
	}
}
