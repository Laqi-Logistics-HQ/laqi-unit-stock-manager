<?php
/**
 * Edition-safe uninstall integration test.
 *
 * @package LaqiUnitStockManager
 */

use LaqiUnitStockManager\Storage\Schema;

/**
 * Confirms deleting one edition cannot remove another edition's shared data.
 */
class Test_Uninstall_Guard extends WP_UnitTestCase {

	/** @var string */
	private $sibling_dir = '';

	/** Create a sibling paid-edition stand-in. */
	public function set_up(): void {
		parent::set_up();
		Schema::install();
		$this->sibling_dir = wp_normalize_path( WP_PLUGIN_DIR . '/laqi-unit-stock-manager-pro-test' );
		if ( ! is_dir( $this->sibling_dir ) ) {
			mkdir( $this->sibling_dir, 0777, true );
		}
		file_put_contents( $this->sibling_dir . '/laqi-unit-stock-manager.php', "<?php\n// Paid-edition test stand-in.\n" );
	}

	/** Remove the sibling stand-in. */
	public function tear_down(): void {
		if ( file_exists( $this->sibling_dir . '/laqi-unit-stock-manager.php' ) ) {
			unlink( $this->sibling_dir . '/laqi-unit-stock-manager.php' );
		}
		if ( is_dir( $this->sibling_dir ) ) {
			rmdir( $this->sibling_dir );
		}
		parent::tear_down();
	}

	/** Shared tables and version remain while another edition is installed. */
	public function test_uninstall_stops_when_sibling_edition_exists(): void {
		global $wpdb;

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', true );
		}
		include dirname( __DIR__ ) . '/uninstall.php';

		$table = Schema::table( 'pools' );
		$this->assertSame( $table, $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) );
		$this->assertSame( Schema::VERSION, (int) get_option( Schema::VERSION_OPTION ) );
	}

	/** A final-edition uninstall removes Free-owned state and recurring jobs. */
	public function test_final_edition_uninstall_cleans_options_and_schedules(): void {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', true );
		}
		unlink( $this->sibling_dir . '/laqi-unit-stock-manager.php' );
		rmdir( $this->sibling_dir );

		$hooks = array(
			'laqi_lusm_evaluate_stock_alerts',
			'laqi_lusm_send_stock_report',
			'laqi_lusm_expire_reservations',
			'laqi_lusm_evaluate_batch_expiry',
		);
		foreach ( $hooks as $hook ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', $hook );
		}

		include dirname( __DIR__ ) . '/uninstall.php';

		foreach ( $hooks as $hook ) {
			$this->assertFalse( wp_next_scheduled( $hook ), $hook . ' remains scheduled.' );
		}

		// Restore the base schema for tests that run after this destructive check.
		Schema::install();
	}
}
