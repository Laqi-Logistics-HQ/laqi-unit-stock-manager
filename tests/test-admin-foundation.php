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

/**
 * Verifies stable presentation and extension contracts.
 */
class Test_Admin_Foundation extends WP_UnitTestCase {

	/**
	 * Decimal output never uses floating-point arithmetic.
	 */
	public function test_quantity_formatter_supports_small_metric_values(): void {
		$formatter = new QuantityFormatter( new UnitRegistry() );

		$this->assertSame( '0.1 g', $formatter->format( new Quantity( 'mass', 100000000 ), 'g' ) );
		$this->assertSame( '0.25 g', $formatter->format( new Quantity( 'mass', 250000000 ), 'g' ) );
		$this->assertSame( '10 kg', $formatter->format( new Quantity( 'mass', 10000000000000 ), 'kg' ) );
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
}
