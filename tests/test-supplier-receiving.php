<?php
/** Supplier receiving integration tests. @package LaqiUnitStockManager */

use LaqiUnitStockManager\Container;
use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Premium\Batches\BatchRepository;
use LaqiUnitStockManager\Premium\Costing\MaterialCostRepository;
use LaqiUnitStockManager\Premium\Costing\MaterialEconomicsService;
use LaqiUnitStockManager\Premium\Receiving\ReceivingService;
use LaqiUnitStockManager\Premium\Receiving\SupplierRepository;
use LaqiUnitStockManager\Storage\Schema;

/** Verifies exact, idempotent supplier-pack receiving. */
class Test_Supplier_Receiving extends WP_UnitTestCase {
	/** @var SupplierRepository */ private $suppliers;
	/** @var ReceivingService */ private $receiving;
	/** @var MaterialCostRepository */ private $costs;
	/** @var BatchRepository */ private $batches;
	/** @var int */ private $pool_id;
	/** @var int */ private $supplier_id;
	/** @var int */ private $pack_id;
	/** @var int */ private $mapping_id = 0;
	/** @var int */ private $product_id = 0;

	/** Install shared and premium tables. */
	public static function set_up_before_class(): void { parent::set_up_before_class(); Schema::install(); global $wpdb; delete_option( SupplierRepository::SCHEMA_OPTION ); ( new SupplierRepository( $wpdb ) )->install(); delete_option( MaterialCostRepository::SCHEMA_OPTION ); ( new MaterialCostRepository( $wpdb ) )->install(); delete_option( BatchRepository::SCHEMA_OPTION ); ( new BatchRepository( $wpdb ) )->install(); }

	/** Create a 10 kg pool and 25 kg supplier sack. */
	public function set_up(): void {
		parent::set_up(); global $wpdb;
		$container = new Container(); $this->suppliers = new SupplierRepository( $wpdb ); $this->costs = new MaterialCostRepository( $wpdb ); $this->batches = new BatchRepository( $wpdb ); $this->receiving = new ReceivingService( $this->suppliers, $container->stock_mutation_service(), $this->costs, $this->batches );
		$this->pool_id = $container->pool_repository()->create( 'Receiving flour', new Quantity( 'mass', 10000000000000 ), 'ng', 'kg' )->id();
		$this->supplier_id = $this->suppliers->create_supplier( 'Miller Ltd', 'orders@example.org', 7 );
		$this->pack_id = $this->suppliers->create_pack( $this->supplier_id, $this->pool_id, '25 kg sack', 25000000000000 );
	}

	/** Remove all test records. */
	public function tear_down(): void {
		global $wpdb;
		$wpdb->delete( Schema::table( 'batches' ), array( 'pool_id' => $this->pool_id ), array( '%d' ) );
		if ( $this->mapping_id > 0 ) { $wpdb->delete( Schema::table( 'mapping_components' ), array( 'mapping_id' => $this->mapping_id ), array( '%d' ) ); $wpdb->delete( Schema::table( 'mappings' ), array( 'id' => $this->mapping_id ), array( '%d' ) ); }
		if ( $this->product_id > 0 ) { wp_delete_post( $this->product_id, true ); }
		$wpdb->delete( Schema::table( 'incoming_deliveries' ), array( 'pool_id' => $this->pool_id ), array( '%d' ) );
		$wpdb->delete( Schema::table( 'receipt_costs' ), array( 'pool_id' => $this->pool_id ), array( '%d' ) );
		$wpdb->delete( Schema::table( 'pool_costs' ), array( 'pool_id' => $this->pool_id ), array( '%d' ) );
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
		foreach ( array( 'suppliers', 'supplier_packs', 'receipts', 'incoming_deliveries' ) as $suffix ) { $table = Schema::table( $suffix ); $this->assertSame( $table, $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ); }
		$this->assertSame( Schema::table( 'batches' ), $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', Schema::table( 'batches' ) ) ) );
		$this->assertSame( 2, (int) get_option( SupplierRepository::SCHEMA_OPTION ) );
		$this->assertSame( BatchRepository::VERSION, (int) get_option( BatchRepository::SCHEMA_OPTION ) );
	}

	/** Receiving packages adds their exact combined normalized quantity. */
	public function test_receives_exact_supplier_pack_quantity(): void {
		global $wpdb;
		$result = $this->receiving->receive( $this->pack_id, 2, 'DEL-100', 42, 'receipt:test:100:' . $this->pool_id );
		$this->assertSame( 60000000000000, $result->balance() );
		$movement = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::table( 'movements' ) . ' WHERE id = %d', $result->movement_id() ), ARRAY_A );
		$this->assertSame( 'supplier_receipt', $movement['type'] );
		$this->assertSame( 'supplier_receipt', $movement['source_type'] );
		$this->assertSame( 'DEL-100', $movement['reason'] );
		$this->assertSame( 42, (int) $movement['actor_id'] );
		$receipt = $this->suppliers->receipts( 1 )[0];
		$this->assertSame( 2, (int) $receipt['pack_count'] );
		$this->assertSame( 50000000000000, (int) $receipt['quantity_base'] );
		$this->assertCount( 1, $this->batches->allocatable( $this->pool_id ) );
	}

	/** Receipt metadata creates one traceable batch and links its movement. */
	public function test_receipt_creates_traceable_batch_with_expiry_and_cost(): void {
		global $wpdb;
		$result = $this->receiving->receive( $this->pack_id, 2, 'LOT-DELIVERY', 42, 'receipt:lot:' . $this->pool_id, 12500, 'EUR', 'MILL-2026-42', '2027-03-31' );
		$batch  = $this->batches->allocatable( $this->pool_id )[0];
		$this->assertSame( 'MILL-2026-42', $batch['supplier_lot'] );
		$this->assertSame( '2027-03-31', $batch['expiry_date'] );
		$this->assertSame( 50000000000000, (int) $batch['quantity_received_base'] );
		$this->assertSame( 50000000000000, (int) $batch['quantity_available_base'] );
		$this->assertSame( 12500, (int) $batch['total_cost_minor'] );
		$this->assertSame( 'EUR', $batch['currency'] );
		$this->assertSame( (int) $batch['id'], (int) $wpdb->get_var( $wpdb->prepare( 'SELECT batch_id FROM ' . Schema::table( 'movements' ) . ' WHERE id = %d', $result->movement_id() ) ) );
	}

	/** Allocation candidates are expiry-first with undated receipts last. */
	public function test_batch_candidates_are_ordered_for_fefo(): void {
		$this->receiving->receive( $this->pack_id, 1, 'UNDATED', 42, 'receipt:undated:' . $this->pool_id );
		$this->receiving->receive( $this->pack_id, 1, 'LATE', 42, 'receipt:late:' . $this->pool_id, 0, '', 'LOT-LATE', '2027-12-31' );
		$this->receiving->receive( $this->pack_id, 1, 'EARLY', 42, 'receipt:early:' . $this->pool_id, 0, '', 'LOT-EARLY', '2027-01-31' );
		$candidates = $this->batches->allocatable( $this->pool_id );
		$this->assertSame( array( 'LOT-EARLY', 'LOT-LATE', '' ), wp_list_pluck( $candidates, 'supplier_lot' ) );
	}

	/** Retrying an external receipt key does not duplicate stock or history. */
	public function test_receipt_is_idempotent(): void {
		global $wpdb;
		$key   = 'receipt:test:101:' . $this->pool_id;
		$first = $this->receiving->receive( $this->pack_id, 1, 'DEL-101', 42, $key );
		$retry = $this->receiving->receive( $this->pack_id, 1, 'DEL-101', 42, $key );
		$this->assertSame( $first->movement_id(), $retry->movement_id() );
		$this->assertTrue( $retry->is_duplicate() );
		$this->assertSame( '35000000000000', $wpdb->get_var( $wpdb->prepare( 'SELECT quantity_base FROM ' . Schema::table( 'pools' ) . ' WHERE id = %d', $this->pool_id ) ) );
		$this->assertSame( 1, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Schema::table( 'receipts' ) . ' WHERE pool_id = %d', $this->pool_id ) ) );
		$this->assertSame( 1, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Schema::table( 'batches' ) . ' WHERE pool_id = %d', $this->pool_id ) ) );
	}

	/** An idempotency key cannot silently replace the original lot snapshot. */
	public function test_receipt_retry_rejects_different_batch_metadata(): void {
		$key = 'receipt:metadata:' . $this->pool_id;
		$this->receiving->receive( $this->pack_id, 1, 'ORIGINAL', 42, $key, 0, '', 'LOT-A', '2027-01-31' );
		$this->expectException( RuntimeException::class );
		$this->receiving->receive( $this->pack_id, 1, 'RETRY', 42, $key, 0, '', 'LOT-B', '2027-01-31' );
	}

	/** Invalid expiry metadata cannot mutate stock. */
	public function test_rejects_invalid_batch_expiry_before_receiving(): void {
		global $wpdb;
		try {
			$this->receiving->receive( $this->pack_id, 1, 'BAD-DATE', 42, 'receipt:bad-date:' . $this->pool_id, 0, '', 'LOT-X', '2027-02-30' );
			$this->fail( 'Expected invalid batch metadata to be rejected.' );
		} catch ( InvalidArgumentException $error ) {
			$this->assertSame( 'The batch expiry date is invalid.', $error->getMessage() );
		}
		$this->assertSame( '10000000000000', $wpdb->get_var( $wpdb->prepare( 'SELECT quantity_base FROM ' . Schema::table( 'pools' ) . ' WHERE id = %d', $this->pool_id ) ) );
	}

	/** Priced receipts maintain one pool-level weighted average and retry safely. */
	public function test_priced_receipts_update_weighted_average_idempotently(): void {
		$first = $this->receiving->receive( $this->pack_id, 2, 'COST-1', 42, 'receipt:cost:1:' . $this->pool_id, 10000, 'EUR' );
		$this->assertSame( 20, $this->costs->consumption_cost_minor( $this->pool_id, 100000000000 ) );
		$this->receiving->receive( $this->pack_id, 1, 'COST-2', 42, 'receipt:cost:2:' . $this->pool_id, 10000, 'EUR' );
		$this->assertSame( 26, $this->costs->consumption_cost_minor( $this->pool_id, 100000000000 ) );
		$this->receiving->receive( $this->pack_id, 2, 'COST-1', 42, 'receipt:cost:1:' . $this->pool_id, 10000, 'EUR' );
		$this->assertSame( 26, $this->costs->consumption_cost_minor( $this->pool_id, 100000000000 ) );
		$this->assertNotNull( $this->costs->pool_cost( $this->pool_id ) );
		$this->assertGreaterThan( 0, $first->movement_id() );
	}

	/** Existing weighted averages cannot combine different currencies. */
	public function test_rejects_mixed_receipt_currencies(): void {
		global $wpdb;
		$this->receiving->receive( $this->pack_id, 1, 'COST-EUR', 42, 'receipt:cost:eur:' . $this->pool_id, 5000, 'EUR' );
		try {
			$this->receiving->receive( $this->pack_id, 1, 'COST-USD', 42, 'receipt:cost:usd:' . $this->pool_id, 5000, 'USD' );
			$this->fail( 'Expected a mixed-currency receipt to be rejected.' );
		} catch ( InvalidArgumentException $error ) {
			$this->assertSame( 'Receipt currency must match the pool cost currency.', $error->getMessage() );
		}
		$this->assertSame( '35000000000000', $wpdb->get_var( $wpdb->prepare( 'SELECT quantity_base FROM ' . Schema::table( 'pools' ) . ' WHERE id = %d', $this->pool_id ) ) );
	}

	/** Linked-product economics reuse normalized consumption and never alter price. */
	public function test_calculates_linked_product_material_margin_without_repricing(): void {
		$container = new Container();
		$product = new WC_Product_Simple();
		$product->set_name( 'Costed flour bag' );
		$product->set_regular_price( '5.00' );
		$this->product_id = $product->save();
		$mapping = $container->mapping_repository()->save_single_pool( $this->product_id, 0, $this->pool_id, 1000000000000 );
		$this->mapping_id = $mapping->id();
		$this->receiving->receive( $this->pack_id, 2, 'COST-MARGIN', 42, 'receipt:cost:margin:' . $this->pool_id, 10000, 'EUR' );
		$result = ( new MaterialEconomicsService( $this->costs ) )->calculate( $mapping );
		$this->assertEqualsWithDelta( 2.0, (float) $result['material_cost'], 0.001 );
		$this->assertEqualsWithDelta( 5.0, (float) $result['price'], 0.001 );
		$this->assertEqualsWithDelta( 60.0, (float) $result['margin'], 0.001 );
		$this->assertSame( '5.00', wc_get_product( $this->product_id )->get_regular_price() );
	}

	/** Invalid package counts never create stock. */
	public function test_rejects_zero_packages(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->receiving->receive( $this->pack_id, 0, '', 42, 'receipt:test:zero:' . $this->pool_id );
	}

	/** Incoming stock remains separate until its arrival is confirmed. */
	public function test_schedules_and_receives_incoming_stock(): void {
		global $wpdb;
		$incoming_id = $this->suppliers->create_incoming( $this->pack_id, 3, '2026-09-01', 'PO-200' );
		$matches     = array_values( array_filter( $this->suppliers->incoming_deliveries(), static function ( array $delivery ) use ( $incoming_id ): bool { return $incoming_id === (int) $delivery['id']; } ) );
		$incoming    = $matches[0];
		$this->assertSame( $incoming_id, (int) $incoming['id'] );
		$this->assertSame( 75000000000000, (int) $incoming['quantity_base'] );
		$this->assertSame( 75000000000000, $this->suppliers->incoming_quantity( $this->pool_id ) );
		$this->assertSame( '10000000000000', $wpdb->get_var( $wpdb->prepare( 'SELECT quantity_base FROM ' . Schema::table( 'pools' ) . ' WHERE id = %d', $this->pool_id ) ) );
		$result = $this->receiving->receive_incoming( $incoming_id, 42 );
		$this->assertSame( 85000000000000, $result->balance() );
		$this->assertNull( $this->suppliers->incoming( $incoming_id ) );
		$this->assertSame( 0, $this->suppliers->incoming_quantity( $this->pool_id ) );
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT status, movement_id FROM ' . Schema::table( 'incoming_deliveries' ) . ' WHERE id = %d', $incoming_id ), ARRAY_A );
		$this->assertSame( 'received', $row['status'] );
		$this->assertSame( $result->movement_id(), (int) $row['movement_id'] );
	}

	/** A confirmed arrival cannot be added to stock a second time. */
	public function test_incoming_delivery_can_only_be_received_once(): void {
		global $wpdb;
		$incoming_id = $this->suppliers->create_incoming( $this->pack_id, 1, '2026-09-02', 'PO-201' );
		$this->receiving->receive_incoming( $incoming_id, 42 );
		try {
			$this->receiving->receive_incoming( $incoming_id, 42 );
			$this->fail( 'Expected a completed incoming delivery to be rejected.' );
		} catch ( InvalidArgumentException $error ) {
			$this->assertSame( 'The incoming delivery is not pending.', $error->getMessage() );
		}
		$this->assertSame( '35000000000000', $wpdb->get_var( $wpdb->prepare( 'SELECT quantity_base FROM ' . Schema::table( 'pools' ) . ' WHERE id = %d', $this->pool_id ) ) );
		$this->assertSame( 1, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Schema::table( 'movements' ) . ' WHERE idempotency_key = %s', 'incoming:' . $incoming_id ) ) );
	}

	/** Invalid expected dates are rejected. */
	public function test_rejects_invalid_expected_date(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->suppliers->create_incoming( $this->pack_id, 1, '2026-02-30', 'PO-invalid' );
	}
}
