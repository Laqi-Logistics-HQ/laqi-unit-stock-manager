<?php
/**
 * Unit Stock admin foundation tests.
 *
 * @package LaqiUnitStockManager
 */

use LaqiUnitStockManager\Admin\ScreenSectionCatalog;
use LaqiUnitStockManager\Admin\ScreenSectionInterface;
use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Presentation\PoolPresenter;
use LaqiUnitStockManager\Presentation\QuantityFormatter;
use LaqiUnitStockManager\Unit\UnitRegistry;
use LaqiUnitStockManager\Inventory\MovementRegistry;
use LaqiUnitStockManager\Inventory\MovementType;
use LaqiUnitStockManager\Admin\PaginationRenderer;

/**
 * Verifies stable presentation and extension contracts.
 */
class Test_Admin_Foundation extends WP_UnitTestCase {

	/**
	 * Decimal output never uses floating-point arithmetic.
	 */
	public function test_quantity_formatter_supports_small_metric_values(): void {
		$registry  = new UnitRegistry();
		$formatter = new QuantityFormatter( $registry );

		$this->assertSame( '0.1 g', $formatter->format( new Quantity( 'mass', 100000000 ), 'g' ) );
		$this->assertSame( '0.25 g', $formatter->format( new Quantity( 'mass', 250000000 ), 'g' ) );
		$this->assertSame( '10 kg', $formatter->format( new Quantity( 'mass', 10000000000000 ), 'kg' ) );
		$this->assertSame( '0.25', $formatter->decimal( new Quantity( 'mass', 250000000 ), 'g' ) );
		$registry->register_custom( 'triple_gram', '3', 'g' );
		$this->assertSame( array( 'value' => '1000', 'unit' => 'mg' ), $formatter->editable( new Quantity( 'mass', 1000000000 ), 'triple_gram' ) );
	}

	/**
	 * The shared presenter publishes the version-one row shape.
	 */
	public function test_pool_presenter_shape(): void {
		$pool      = new LaqiUnitStockManager\Domain\Pool( 7, 'Cocoa', new Quantity( 'mass', 250000000 ), 'g', false );
		$presenter = new PoolPresenter( new QuantityFormatter( new UnitRegistry() ) );
		$row       = $presenter->present( $pool );

		$this->assertSame( 7, $row['id'] );
		$this->assertSame( '0.25 g', $row['quantity_display'] );
		$this->assertFalse( $row['allow_backorders'] );
		$this->assertSame( '', $row['internal_sku'] );
		$this->assertSame( 1, $row['version'] );
	}

	/**
	 * Free and Pro modules can contribute sections without editing the page.
	 */
	public function test_screen_section_catalog_accepts_independent_sections(): void {
		$section = new class() implements ScreenSectionInterface {
			public function id(): string { return 'forecast'; }
			public function title(): string { return 'Forecast'; }
			public function render(): void {}
		};
		$catalog = new ScreenSectionCatalog();
		$catalog->register( $section );

		$this->assertSame( $section, $catalog->all()['forecast'] );
	}

	/**
	 * Premium movement types can extend the shared label registry.
	 */
	public function test_movement_type_registry_is_extensible(): void {
		$registry = new MovementRegistry();
		$registry->register( new MovementType( 'receiving', 'Receiving' ) );

		$this->assertSame( 'Receiving', $registry->label( 'receiving' ) );
		$this->assertSame( 'Future Type', $registry->label( 'future_type' ) );
	}

	/** Shared pagination keeps registered tab context and its own page argument. */
	public function test_shared_pagination_renderer_preserves_section_context(): void {
		ob_start();
		( new PaginationRenderer() )->render(
			'Showing 51-100 of 120 stock movements.',
			'Stock movement pages',
			'activity_page',
			array( 'page' => 'laqi-unit-stock-manager', 'section' => 'activity' ),
			2,
			3
		);
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Showing 51-100 of 120 stock movements.', $html );
		$this->assertStringContainsString( 'section=activity', $html );
		$this->assertStringContainsString( 'activity_page=3', $html );
		$this->assertStringContainsString( 'aria-label="Stock movement pages"', $html );
	}
}
