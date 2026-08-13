<?php
/**
 * Versioned schema tests.
 *
 * @package LaqiUnitStockManager
 */

use LaqiUnitStockManager\Storage\Schema;

/**
 * Tests all Part 1 tables are installed together.
 */
class Test_Schema extends WP_UnitTestCase {

	/**
	 * Install the schema for this suite.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		Schema::install();
	}

	/**
	 * Every stable table identity exists.
	 */
	public function test_schema_installs_all_core_tables(): void {
		global $wpdb;

		foreach ( array( 'pools', 'units', 'mappings', 'mapping_components', 'movements' ) as $suffix ) {
			$table = Schema::table( $suffix );
			$this->assertSame( $table, $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) );
		}

		$this->assertSame( Schema::VERSION, (int) get_option( Schema::VERSION_OPTION ) );
	}
}
