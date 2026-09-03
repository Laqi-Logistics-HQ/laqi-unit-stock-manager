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
		$before         = has_action( 'plugins_loaded' );
		$before_version = LAQI_LUSM_VERSION;
		include dirname( __DIR__ ) . '/laqi-unit-stock-manager.php';

		$this->assertSame( $before, has_action( 'plugins_loaded' ) );
		$this->assertNotFalse( has_action( 'admin_notices', 'laqi_lusm_render_duplicate_edition_notice' ) );
		// Read the constant rather than a literal: the claim is that the second
		// include left it alone, not that the plugin is on any given version.
		$this->assertSame( $before_version, LAQI_LUSM_VERSION );
	}
}
