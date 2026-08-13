<?php
/**
 * Batch allocation journal persistence.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Batches;

defined( 'ABSPATH' ) || exit;

// Compact repository methods remain explicit through types and names.
// phpcs:disable Generic.Commenting.DocComment.MissingShort, Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag

use RuntimeException;
use wpdb;

/** Maintains immutable movement-to-batch effects inside the pool transaction. */
final class BatchAllocationRepository {
	const SCHEMA_OPTION = 'laqi_lusm_batch_allocation_schema_version';
	const VERSION       = 1;

	/** @var wpdb */ private $db;
	/** @param wpdb $db Database. */ public function __construct( wpdb $db ) {
		$this->db = $db; }

	/** Install the additive allocation journal. */
	public function install(): void {
		if ( self::VERSION === (int) get_option( self::SCHEMA_OPTION, 0 ) ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = $this->table();
		$charset = $this->db->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_key varchar(191) NOT NULL,
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			pool_id bigint(20) unsigned NOT NULL,
			batch_id bigint(20) unsigned NOT NULL DEFAULT 0,
			direction smallint(6) NOT NULL,
			quantity_base bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY event_batch (event_key,batch_id),
			KEY order_pool (order_id,pool_id),
			KEY batch_id (batch_id)
		) {$charset};"
		);
		update_option( self::SCHEMA_OPTION, self::VERSION, false );
	}

	/** Consume dated batches first and record any legacy/unbatched remainder. */
	public function consume( int $pool_id, int $quantity, string $event_key, int $order_id = 0, bool $allow_expired = false ): void {
		$remaining            = $quantity;
		$batch_balance_before = (int) $this->db->get_var( $this->db->prepare( 'SELECT COALESCE(SUM(quantity_available_base),0) FROM ' . $this->batch_table() . ' WHERE pool_id=%d', $pool_id ) );
		$expiry_clause        = $allow_expired ? '' : $this->db->prepare( ' AND (expiry_date IS NULL OR expiry_date>=%s)', current_time( 'Y-m-d' ) );
		$rows                 = $this->db->get_results( $this->db->prepare( 'SELECT id,quantity_available_base FROM ' . $this->batch_table() . ' WHERE pool_id=%d AND status=%s AND quantity_available_base>0' . $expiry_clause . ' ORDER BY CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END,expiry_date ASC,received_at ASC,id ASC FOR UPDATE', $pool_id, 'active' ), ARRAY_A );
		foreach ( is_array( $rows ) ? $rows : array() as $batch ) {
			$used = min( $remaining, (int) $batch['quantity_available_base'] );
			if ( $used < 1 ) {
				break;
			}
			$this->change_batch( (int) $batch['id'], -$used );
			$this->record( $event_key, $order_id, $pool_id, (int) $batch['id'], -1, $used );
			$remaining -= $used;
		}
		if ( $remaining > 0 ) {
			$post_balance = (int) $this->db->get_var( $this->db->prepare( 'SELECT quantity_base FROM ' . $this->db->prefix . 'laqi_lusm_pools WHERE id=%d', $pool_id ) );
			$legacy       = max( 0, $post_balance + $quantity - $batch_balance_before );
			if ( $remaining > $legacy ) {
				throw new RuntimeException( 'The pool cannot satisfy this movement without expired or unavailable batch stock.' );
			}
			$this->record( $event_key, $order_id, $pool_id, 0, -1, $remaining );
		}
	}

	/** Restore an order to its exact consumed batches, most recent allocation first. */
	public function restore( int $order_id, int $pool_id, int $quantity, string $event_key ): void {
		$remaining = $quantity;
		$sql       = 'SELECT batch_id,SUM(CASE WHEN direction=-1 THEN quantity_base ELSE -quantity_base END) outstanding,MAX(id) last_id FROM ' . $this->table() . ' WHERE order_id=%d AND pool_id=%d GROUP BY batch_id HAVING outstanding>0 ORDER BY CASE WHEN batch_id=0 THEN 1 ELSE 0 END,last_id DESC';
		$rows      = $this->db->get_results( $this->db->prepare( $sql, $order_id, $pool_id ), ARRAY_A );
		foreach ( is_array( $rows ) ? $rows : array() as $allocation ) {
			$restored = min( $remaining, (int) $allocation['outstanding'] );
			if ( $restored < 1 ) {
				break;
			}
			$batch_id = (int) $allocation['batch_id'];
			if ( $batch_id > 0 ) {
				$this->change_batch( $batch_id, $restored );
			}
			$this->record( $event_key, $order_id, $pool_id, $batch_id, 1, $restored );
			$remaining -= $restored;
		}
		if ( $remaining > 0 ) {
			$this->record( $event_key, $order_id, $pool_id, 0, 1, $remaining );
		}
	}

	/** Journal rows for an order. @return array<int,array<string,mixed>> */
	public function for_order( int $order_id ): array {
		$rows = $this->db->get_results( $this->db->prepare( 'SELECT a.*,b.supplier_lot,b.expiry_date FROM ' . $this->table() . ' a LEFT JOIN ' . $this->batch_table() . ' b ON b.id=a.batch_id WHERE a.order_id=%d ORDER BY a.id', $order_id ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/** Apply a guarded batch balance delta. */
	private function change_batch( int $batch_id, int $delta ): void {
		if ( $delta < 0 ) {
			$amount  = abs( $delta );
			$updated = $this->db->query( $this->db->prepare( 'UPDATE ' . $this->batch_table() . ' SET status=CASE WHEN quantity_available_base=%d THEN %s ELSE status END,quantity_available_base=quantity_available_base-%d,updated_at=UTC_TIMESTAMP() WHERE id=%d AND quantity_available_base>=%d', $amount, 'depleted', $amount, $batch_id, $amount ) );
		} else {
			$updated = $this->db->query( $this->db->prepare( 'UPDATE ' . $this->batch_table() . ' SET quantity_available_base=quantity_available_base+%d,status=CASE WHEN status=%s THEN %s ELSE status END,updated_at=UTC_TIMESTAMP() WHERE id=%d', $delta, 'depleted', 'active', $batch_id ) );
		}
		if ( 1 !== $updated ) {
			throw new RuntimeException( 'Could not update the allocated batch quantity.' );
		}
	}

	/** Append one idempotent batch effect. */
	private function record( string $event_key, int $order_id, int $pool_id, int $batch_id, int $direction, int $quantity ): void {
		$inserted = $this->db->insert(
			$this->table(),
			array(
				'event_key'     => $event_key,
				'order_id'      => max( 0, $order_id ),
				'pool_id'       => $pool_id,
				'batch_id'      => $batch_id,
				'direction'     => $direction,
				'quantity_base' => $quantity,
				'created_at'    => current_time( 'mysql', true ),
			),
			array( '%s', '%d', '%d', '%d', '%d', '%d', '%s' )
		);
		if ( false === $inserted ) {
			throw new RuntimeException( 'Could not record the batch allocation.' );
		}
	}

	/** Allocation table. */ private function table(): string {
		return $this->db->prefix . 'laqi_lusm_batch_allocations'; }
	/** Batch table. */ private function batch_table(): string {
		return $this->db->prefix . 'laqi_lusm_batches'; }
}
