<?php
/** Premium FEFO batch allocation tests. @package LaqiUnitStockManager */

use LaqiUnitStockManager\Container;
use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Premium\Batches\BatchAllocationRepository;
use LaqiUnitStockManager\Premium\Batches\BatchRepository;
use LaqiUnitStockManager\Premium\Reservations\ReservationRepository;
use LaqiUnitStockManager\Storage\Schema;

/** Verifies movements and batch quantities commit as one traceable operation. */
class Test_Batch_Allocation extends WP_UnitTestCase {
	/** @var int */ private $pool_id;
	/** @var BatchRepository */ private $batches;
	/** @var BatchAllocationRepository */ private $allocations;
	/** @var \LaqiUnitStockManager\Inventory\StockMutationService */ private $mutations;
	/** @var string */ private $event_namespace;
	/** @var int */ private $order_seed;

	/** Install journals and create three legacy units plus two dated batches. */
	public function set_up(): void {
		parent::set_up();
		global $wpdb;
		$container         = new Container();
		$this->batches     = new BatchRepository( $wpdb );
		$this->allocations = new BatchAllocationRepository( $wpdb );
		$this->batches->install();
		$this->allocations->install();
		$this->mutations       = $container->stock_mutation_service();
		$this->event_namespace = wp_generate_uuid4();
		$this->order_seed      = abs( crc32( $this->event_namespace ) ) + 1000;
		$this->pool_id         = $container->pool_repository()->create( 'FEFO pool ' . $this->event_namespace, new Quantity( 'count', 3 ), 'each', 'each' )->id();
		$wpdb->delete( Schema::table( 'batch_allocations' ), array( 'pool_id' => $this->pool_id ), array( '%d' ) );
		$wpdb->delete( Schema::table( 'batches' ), array( 'pool_id' => $this->pool_id ), array( '%d' ) );
		$early = $this->mutations->apply( $this->pool_id, 4, 'supplier_receipt', 'batch:early:' . $this->event_namespace );
		$this->batches->record_receipt( $this->pool_id, 0, $early->movement_id(), 4, 'EARLY', '2027-01-31' );
		$late = $this->mutations->apply( $this->pool_id, 6, 'supplier_receipt', 'batch:late:' . $this->event_namespace );
		$this->batches->record_receipt( $this->pool_id, 0, $late->movement_id(), 6, 'LATE', '2027-12-31' );
	}

	/** Remove journal, batches, movements, and pool. */
	public function tear_down(): void {
		global $wpdb;
		$wpdb->delete( Schema::table( 'batch_allocations' ), array( 'pool_id' => $this->pool_id ), array( '%d' ) );
		$wpdb->delete( Schema::table( 'batches' ), array( 'pool_id' => $this->pool_id ), array( '%d' ) );
		$wpdb->delete( Schema::table( 'movements' ), array( 'pool_id' => $this->pool_id ), array( '%d' ) );
		$wpdb->delete( Schema::table( 'pools' ), array( 'id' => $this->pool_id ), array( '%d' ) );
		parent::tear_down();
	}

	/** Allocation journal schema is installed independently of receipt batches. */
	public function test_installs_allocation_schema(): void {
		global $wpdb;
		$this->assertSame( Schema::table( 'batch_allocations' ), $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', Schema::table( 'batch_allocations' ) ) ) );
		$this->assertSame( BatchAllocationRepository::VERSION, (int) get_option( BatchAllocationRepository::SCHEMA_OPTION ) );
	}

	/** Order demand consumes the earliest expiry before later stock. */
	public function test_order_movement_allocates_fefo_and_records_lots(): void {
		$order_id = $this->order_seed + 1;
		$this->mutations->apply( $this->pool_id, -7, 'order_reduction', 'order:reduce:' . $this->event_namespace, array( 'source_type' => 'order', 'source_id' => $order_id ) );
		$rows = $this->allocations->for_order( $order_id );
		$this->assertSame( array( 'EARLY', 'LATE' ), wp_list_pluck( $rows, 'supplier_lot' ) );
		$this->assertSame( array( 4, 3 ), array_map( 'intval', wp_list_pluck( $rows, 'quantity_base' ) ) );
		$this->assertSame( array( -1, -1 ), array_map( 'intval', wp_list_pluck( $rows, 'direction' ) ) );
		$this->assertSame( array( 0, 3 ), $this->batch_balances() );
	}

	/** Recall lookup includes only the quantity still held by each affected order. */
	public function test_affected_orders_excludes_restored_quantity(): void {
		$order_id = $this->order_seed + 20;
		$this->mutations->apply( $this->pool_id, -5, 'order_reduction', 'order:recall-reduce:' . $this->event_namespace, array( 'source_type' => 'order', 'source_id' => $order_id ) );
		$early_id = (int) $this->allocations->for_order( $order_id )[0]['batch_id'];
		$this->assertSame( 4, (int) $this->allocations->affected_orders( $early_id )[0]['quantity_base'] );
		$this->mutations->apply( $this->pool_id, 2, 'refund_restore', 'order:recall-restore:' . $this->event_namespace, array( 'source_type' => 'order', 'source_id' => $order_id ) );
		$this->assertSame( 3, (int) $this->allocations->affected_orders( $early_id )[0]['quantity_base'] );
		$this->mutations->apply( $this->pool_id, 3, 'order_restore', 'order:recall-full:' . $this->event_namespace, array( 'source_type' => 'order', 'source_id' => $order_id ) );
		$this->assertSame( array(), $this->allocations->affected_orders( $early_id ) );
	}

	/** Partial and full restoration return exact quantities to consumed batches. */
	public function test_order_restoration_reverses_exact_allocations_idempotently(): void {
		$order_id = $this->order_seed + 2;
		$this->mutations->apply( $this->pool_id, -7, 'order_reduction', 'order:reduce:' . $this->event_namespace, array( 'source_type' => 'order', 'source_id' => $order_id ) );
		$this->mutations->apply( $this->pool_id, 3, 'refund_restore', 'order:refund:' . $this->event_namespace, array( 'source_type' => 'order', 'source_id' => $order_id ) );
		$this->assertSame( array( 0, 6 ), $this->batch_balances() );
		$this->mutations->apply( $this->pool_id, 4, 'order_restore', 'order:restore:' . $this->event_namespace, array( 'source_type' => 'order', 'source_id' => $order_id ) );
		$this->mutations->apply( $this->pool_id, 4, 'order_restore', 'order:restore:' . $this->event_namespace, array( 'source_type' => 'order', 'source_id' => $order_id ) );
		$this->assertSame( array( 4, 6 ), $this->batch_balances() );
		$this->assertCount( 4, $this->allocations->for_order( $order_id ) );
	}

	/** Pre-batch inventory remains saleable and is explicitly journaled as unbatched. */
	public function test_legacy_unbatched_quantity_is_a_safe_fallback(): void {
		$order_id = $this->order_seed + 3;
		$this->mutations->apply( $this->pool_id, -12, 'order_reduction', 'order:reduce:' . $this->event_namespace, array( 'source_type' => 'order', 'source_id' => $order_id ) );
		$rows      = $this->allocations->for_order( $order_id );
		$unbatched = array_values( array_filter( $rows, static function ( array $row ): bool { return 0 === (int) $row['batch_id']; } ) );
		$this->assertCount( 1, $unbatched );
		$this->assertSame( 2, (int) $unbatched[0]['quantity_base'] );
		$this->assertSame( 1, $this->balance() );
	}

	/** A paid allocation failure rolls back both the pool and batch changes. */
	public function test_extension_failure_rolls_back_the_authoritative_movement(): void {
		global $wpdb;
		$order_id = $this->order_seed + 4;
		$key      = 'order:failure:' . $this->event_namespace;
		$failure = static function (): void { throw new RuntimeException( 'Simulated allocation failure.' ); };
		add_action( 'laqi_lusm_stock_movement_applying', $failure, 99 );
		try {
			$this->mutations->apply( $this->pool_id, -5, 'order_reduction', $key, array( 'source_type' => 'order', 'source_id' => $order_id ) );
			$this->fail( 'Expected the transaction extension to fail.' );
		} catch ( RuntimeException $error ) {
			$this->assertSame( 'Simulated allocation failure.', $error->getMessage() );
		} finally {
			remove_action( 'laqi_lusm_stock_movement_applying', $failure, 99 );
		}
		$this->assertSame( 13, $this->balance() );
		$this->assertSame( array( 4, 6 ), $this->batch_balances() );
		$this->assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Schema::table( 'movements' ) . ' WHERE idempotency_key=%s', $key ) ) );
		$this->assertCount( 0, $this->allocations->for_order( $order_id ) );
	}

	/** Expired lots are neither saleable nor reclassified as legacy stock. */
	public function test_expired_batch_is_excluded_from_availability_and_allocation(): void {
		global $wpdb;
		$expired = $this->mutations->apply( $this->pool_id, 2, 'supplier_receipt', 'batch:expired:' . $this->event_namespace );
		$this->batches->record_receipt( $this->pool_id, 0, $expired->movement_id(), 2, 'EXPIRED', '2000-01-01' );
		$this->assertSame( 13, apply_filters( 'laqi_lusm_pool_available_quantity', 15, $this->pool_id ) );
		try {
			$this->mutations->apply( $this->pool_id, -14, 'order_reduction', 'order:expired:' . $this->event_namespace, array( 'source_type' => 'order', 'source_id' => $this->order_seed + 5 ) );
			$this->fail( 'Expected expired-only capacity to be rejected.' );
		} catch ( RuntimeException $error ) {
			$this->assertStringContainsString( 'expired or unavailable batch stock', $error->getMessage() );
		}
		$this->assertSame( 15, $this->balance() );
		$this->assertSame( array( 4, 6, 2 ), $this->batch_balances() );
		$this->expectException( RuntimeException::class );
		( new ReservationRepository( $wpdb ) )->reserve( $this->order_seed + 6, array( $this->pool_id => 14 ), gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS ) );
	}

	/** Current batch balances ordered by ID. @return int[] */
	private function batch_balances(): array {
		global $wpdb;
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT quantity_available_base FROM ' . Schema::table( 'batches' ) . ' WHERE pool_id=%d ORDER BY id', $this->pool_id ) ) );
	}

	/** Pool balance. */
	private function balance(): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT quantity_base FROM ' . Schema::table( 'pools' ) . ' WHERE id=%d', $this->pool_id ) );
	}
}
