<?php
/**
 * Free and paid edition coexistence tests.
 *
 * @package LaqiUnitStockManager
 */

/**
 * Verifies that loading a second edition stops before registering runtime code.
 */
class Test_Edition_Coexistence extends WP_UnitTestCase {

	/**
	 * A duplicate main file registers one warning and leaves existing constants.
	 */
	public function test_duplicate_edition_stops_at_bootstrap_guard(): void {
		$before = has_action( 'plugins_loaded' );
		include dirname( __DIR__ ) . '/laqi-unit-stock-manager.php';

		$this->assertSame( $before, has_action( 'plugins_loaded' ) );
		$this->assertNotFalse( has_action( 'admin_notices', 'laqi_lusm_render_duplicate_edition_notice' ) );
		$this->assertSame( '0.1.0', LAQI_LUSM_VERSION );
	}
}
