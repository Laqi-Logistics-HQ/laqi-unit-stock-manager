<?php
/**
 * Privacy integration smoke tests.
 *
 * @package LaqiUnitStockManager
 */

/**
 * Ensures every scaffolded plugin remains connected to WordPress' privacy tools.
 */
class Test_Privacy extends WP_UnitTestCase {

	/**
	 * Exporter and eraser callbacks should be registered and callable.
	 */
	public function test_privacy_callbacks_are_registered(): void {
		$privacy   = new \LaqiUnitStockManager\Privacy();
		$exporters = $privacy->register_exporter( array() );
		$erasers   = $privacy->register_eraser( array() );

		$this->assertArrayHasKey( \LaqiUnitStockManager\Privacy::GROUP, $exporters );
		$this->assertArrayHasKey( \LaqiUnitStockManager\Privacy::GROUP, $erasers );
		$this->assertIsCallable( $exporters[ \LaqiUnitStockManager\Privacy::GROUP ]['callback'] );
		$this->assertIsCallable( $erasers[ \LaqiUnitStockManager\Privacy::GROUP ]['callback'] );
	}
}
