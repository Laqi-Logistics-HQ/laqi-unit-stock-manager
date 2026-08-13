<?php
/**
 * Privacy integration smoke tests.
 *
 * @package LaqiUnitStockManager
 */

use LaqiUnitStockManager\Inventory\StockMutationService;
use LaqiUnitStockManager\Storage\MovementRepository;
use LaqiUnitStockManager\Storage\Schema;

/**
 * Ensures every scaffolded plugin remains connected to WordPress' privacy tools.
 */
class Test_Privacy extends WP_UnitTestCase {

	/** Install plugin tables. */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		Schema::install();
	}

	/**
	 * Exporter and eraser callbacks should be registered and callable.
	 */
	public function test_privacy_callbacks_are_registered(): void {
		global $wpdb;
		$privacy   = new \LaqiUnitStockManager\Privacy( new MovementRepository( $wpdb ) );
		$exporters = $privacy->register_exporter( array() );
		$erasers   = $privacy->register_eraser( array() );

		$this->assertArrayHasKey( \LaqiUnitStockManager\Privacy::GROUP, $exporters );
		$this->assertArrayHasKey( \LaqiUnitStockManager\Privacy::GROUP, $erasers );
		$this->assertIsCallable( $exporters[ \LaqiUnitStockManager\Privacy::GROUP ]['callback'] );
		$this->assertIsCallable( $erasers[ \LaqiUnitStockManager\Privacy::GROUP ]['callback'] );
	}

	/** Export and erasure expose then anonymize an adjustment actor. */
	public function test_movement_actor_is_exported_and_anonymized(): void {
		global $wpdb;

		$user_id = self::factory()->user->create( array( 'user_email' => 'stock-manager@example.com' ) );
		$now     = current_time( 'mysql', true );
		$wpdb->insert(
			Schema::table( 'pools' ),
			array(
				'name'          => 'Privacy pool',
				'family'        => 'count',
				'base_unit'     => 'each',
				'display_unit'  => 'each',
				'quantity_base' => 10,
				'created_at'    => $now,
				'updated_at'    => $now,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
		$pool_id = (int) $wpdb->insert_id;
		( new StockMutationService( $wpdb ) )->apply( $pool_id, 1, 'manual_add', 'privacy:' . $pool_id, array( 'actor_id' => $user_id, 'reason' => 'Counted delivery' ) );

		$privacy = new \LaqiUnitStockManager\Privacy( new MovementRepository( $wpdb ) );
		$export  = $privacy->export( 'stock-manager@example.com' );
		$this->assertCount( 1, $export['data'] );
		$this->assertSame( 'movement-', substr( $export['data'][0]['item_id'], 0, 9 ) );
		$this->assertTrue( $export['done'] );

		$erased = $privacy->erase( 'stock-manager@example.com' );
		$this->assertTrue( $erased['items_removed'] );
		$this->assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT actor_id FROM ' . Schema::table( 'movements' ) . ' WHERE pool_id = %d', $pool_id ) ) );

		$wpdb->delete( Schema::table( 'movements' ), array( 'pool_id' => $pool_id ), array( '%d' ) );
		$wpdb->delete( Schema::table( 'pools' ), array( 'id' => $pool_id ), array( '%d' ) );
		wp_delete_user( $user_id );
	}
}
