<?php
/** Supplier receiving integration tests. @package LaqiUnitStockManager */

use LaqiUnitStockManager\Container;
use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Premium\Receiving\ReceivingService;
use LaqiUnitStockManager\Premium\Receiving\SupplierRepository;
use LaqiUnitStockManager\Storage\Schema;

/** Verifies exact, idempotent supplier-pack receiving. */
class Test_Supplier_Receiving extends WP_UnitTestCase {
	/** @var SupplierRepository */ private $suppliers;
	/** @var ReceivingService */ private $receiving;
	/** @var int */ private $pool_id;
	/** @var int */ private $supplier_id;
	/** @var int */ private $pack_id;

	/** Install shared and premium tables. */
	public static function set_up_before_class(): void { parent::set_up_before_class(); Schema::install(); global $wpdb; delete_option( SupplierRepository::SCHEMA_OPTION ); ( new SupplierRepository( $wpdb ) )->install(); }

	/** Create a 10 kg pool and 25 kg supplier sack. */
	public function set_up(): void {
		parent::set_up(); global $wpdb;
		$container = new Container(); $this->suppliers = new SupplierRepository( $wpdb ); $this->receiving = new ReceivingService( $this->suppliers, $container->stock_mutation_service() );
		$this->pool_id = $container->pool_repository()->create( 'Receiving flour', new Quantity( 'mass', 10000000000000 ), 'ng', 'kg' )->id();
		$this->supplier_id = $this->suppliers->create_supplier( 'Miller Ltd', 'orders@example.org', 7 );
		$this->pack_id = $this->suppliers->create_pack( $this->supplier_id, $this->pool_id, '25 kg sack', 25000000000000 );
	}

	/** Remove all test records. */
	public function tear_down(): void {
		global $wpdb;
		$wpdb->delete( Schema::table( 'receipts' ), array( 'pool_id' => $this->pool_id ), array( '%d' ) );
		$wpdb->delete( Schema::table( 'supplier_packs' ), array( 'id' => $this->pack_id ), array( '%d' ) );
		$wpdb->delete( Schema::table( 'suppliers' ), array( 'id' => $this->supplier_id ), array( '%d' ) );
		$wpdb->delete( Schema::table( 'movements' ), array( 'pool_id' => $this->pool_id ), array( '%d' ) );
		$wpdb->delete( Schema::table( 'pools' ), array( 'id' => $this->pool_id ), array( '%d' ) );
		parent::tear_down();
	}

	/** Premium receiving tables are versioned and installed. */
	public function test_installs_receiving_schema(): void {
		global $wpdb;
		foreach ( array( 'suppliers', 'supplier_packs', 'receipts' ) as $suffix ) { $table = Schema::table( $suffix ); $this->assertSame( $table, $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ); }
		$this->assertSame( 1, (int) get_option( SupplierRepository::SCHEMA_OPTION ) );
	}

	/** Receiving packages adds their exact combined normalized quantity. */
	public function test_receives_exact_supplier_pack_quantity(): void {
		global $wpdb;
		$result = $this->receiving->receive( $this->pack_id, 2, 'DEL-100', 42, 'receipt:test:100' );
		$this->assertSame( 60000000000000, $result->balance() );
		$movement = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::table( 'movements' ) . ' WHERE id = %d', $result->movement_id() ), ARRAY_A );
		$this->assertSame( 'supplier_receipt', $movement['type'] );
		$this->assertSame( 'supplier_receipt', $movement['source_type'] );
		$this->assertSame( 'DEL-100', $movement['reason'] );
		$this->assertSame( 42, (int) $movement['actor_id'] );
		$receipt = $this->suppliers->receipts( 1 )[0];
		$this->assertSame( 2, (int) $receipt['pack_count'] );
		$this->assertSame( 50000000000000, (int) $receipt['quantity_base'] );
	}

	/** Retrying an external receipt key does not duplicate stock or history. */
	public function test_receipt_is_idempotent(): void {
		global $wpdb;
		$first = $this->receiving->receive( $this->pack_id, 1, 'DEL-101', 42, 'receipt:test:101' );
		$retry = $this->receiving->receive( $this->pack_id, 1, 'DEL-101', 42, 'receipt:test:101' );
		$this->assertSame( $first->movement_id(), $retry->movement_id() );
		$this->assertTrue( $retry->is_duplicate() );
		$this->assertSame( '35000000000000', $wpdb->get_var( $wpdb->prepare( 'SELECT quantity_base FROM ' . Schema::table( 'pools' ) . ' WHERE id = %d', $this->pool_id ) ) );
		$this->assertSame( 1, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Schema::table( 'receipts' ) . ' WHERE pool_id = %d', $this->pool_id ) ) );
	}

	/** Invalid package counts never create stock. */
	public function test_rejects_zero_packages(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->receiving->receive( $this->pack_id, 0, '', 42, 'receipt:test:zero' );
	}
}
