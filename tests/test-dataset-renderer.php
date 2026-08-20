<?php
/** Shared dataset chrome tests. @package LaqiUnitStockManager */

use LaqiUnitStockManager\Admin\DatasetFilters;
use LaqiUnitStockManager\Admin\DatasetPage;
use LaqiUnitStockManager\Admin\DatasetRenderer;
use LaqiUnitStockManager\Admin\DatasetView;
use LaqiUnitStockManager\Admin\PaginationRenderer;

/**
 * Verifies the filter form keeps the screen state a table is being read in.
 *
 * The Activity ledger can be scoped to one product's pools, and add-on screens
 * carry the workspace panel a table lives in. Both travel as query arguments
 * rather than as filter controls, so the form has to resubmit them or filtering
 * silently widens the result.
 */
class Test_Dataset_Renderer extends WP_UnitTestCase {
	/** Restore the query string between tests. */
	public function tear_down(): void {
		$_GET = array();
		parent::tear_down();
	}

	/** Context that is not a filter control is resubmitted with the filters. */
	public function test_filter_form_carries_screen_context(): void {
		$html = $this->render_filters(
			array(
				'post_type' => 'product',
				'page'      => 'laqi-unit-stock-manager',
				'section'   => 'activity',
				'pool_ids'  => '7,9',
				'some_view' => 'batches',
			)
		);

		$hidden = $this->hidden_inputs( $html );
		$this->assertSame( 'product', $hidden['post_type'] );
		$this->assertSame( 'laqi-unit-stock-manager', $hidden['page'] );
		$this->assertSame( 'activity', $hidden['section'] );
		$this->assertSame( '7,9', $hidden['pool_ids'], 'Product context survives filtering.' );
		$this->assertSame( 'batches', $hidden['some_view'], 'The panel a table lives in survives filtering.' );
	}

	/** A filter with its own control is never also a hidden input. */
	public function test_filter_controls_are_not_duplicated_as_hidden_inputs(): void {
		$_GET['activity_type'] = 'manual_set';
		$html                  = $this->render_filters(
			array(
				'post_type' => 'product',
				'page'      => 'laqi-unit-stock-manager',
				'section'   => 'activity',
			)
		);

		$this->assertArrayNotHasKey( 'activity_type', $this->hidden_inputs( $html ), 'The select already submits this value.' );
		$this->assertStringContainsString( 'name="activity_type"', $html );
		$this->assertStringContainsString( 'Clear filters', $html );
	}

	/**
	 * Render the filter form for a view over one declared filter.
	 *
	 * @param array<string, string> $screen_args Screen arguments.
	 * @return string
	 */
	private function render_filters( array $screen_args ): string {
		$filters = DatasetFilters::read(
			array(
				'activity_type' => array(
					'control' => 'select',
					'filter'  => 'type',
					'label'   => 'Movement',
					'choices' => array(
						''            => 'All movements',
						'manual_set'  => 'Manual stock count',
					),
				),
			)
		);
		$view    = new DatasetView( 'activity', $filters, new DatasetPage( 1, 60 ), $screen_args );

		ob_start();
		( new DatasetRenderer( new PaginationRenderer() ) )->filters( $view, 'Filter stock movements' );

		return (string) ob_get_clean();
	}

	/**
	 * Collect the form's hidden inputs.
	 *
	 * @param string $html Rendered form.
	 * @return array<string, string>
	 */
	private function hidden_inputs( string $html ): array {
		preg_match_all( '/<input type="hidden" name="([^"]+)" value="([^"]*)"/', $html, $matches, PREG_SET_ORDER );
		$hidden = array();
		foreach ( $matches as $match ) {
			$hidden[ $match[1] ] = $match[2];
		}

		return $hidden;
	}
}
