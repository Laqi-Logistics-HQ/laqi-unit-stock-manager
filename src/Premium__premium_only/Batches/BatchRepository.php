<?php
/**
 * Batch and lot persistence.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Batches;

defined( 'ABSPATH' ) || exit;

// Compact repository methods remain explicit through types and names.
// phpcs:disable Generic.Commenting.DocComment.MissingShort, Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag

use InvalidArgumentException;
use RuntimeException;
use Throwable;
use wpdb;

/** Stores receipt-backed quantities for later FEFO allocation and traceability. */
final class BatchRepository {
	const SCHEMA_OPTION = 'laqi_lusm_batch_schema_version';
	const VERSION       = 2;

	/** @var wpdb */
	private $db;

	/** @param wpdb $db Database. */
	public function __construct( wpdb $db ) {
		$this->db = $db;
	}

	/** Install the additive paid batch schema. */
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
			pool_id bigint(20) unsigned NOT NULL,
			supplier_id bigint(20) unsigned NOT NULL DEFAULT 0,
			receipt_movement_id bigint(20) unsigned NOT NULL,
			supplier_lot varchar(191) NOT NULL DEFAULT '',
			quantity_received_base bigint(20) unsigned NOT NULL,
			quantity_available_base bigint(20) unsigned NOT NULL,
			total_cost_minor bigint(20) unsigned NOT NULL DEFAULT 0,
			currency varchar(3) NOT NULL DEFAULT '',
			received_at datetime NOT NULL,
			expiry_date date NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY receipt_movement_id (receipt_movement_id),
			KEY pool_status_expiry (pool_id,status,expiry_date),
			KEY supplier_lot (supplier_id,supplier_lot)
			) {$charset};"
		);
		$events = $this->events_table();
		dbDelta(
			"CREATE TABLE {$events} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			batch_id bigint(20) unsigned NOT NULL,
			event_type varchar(20) NOT NULL,
			quantity_base bigint(20) unsigned NOT NULL,
			actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			reason varchar(191) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY batch_created (batch_id,created_at)
		) {$charset};"
		);
		update_option( self::SCHEMA_OPTION, self::VERSION, false );
	}

	/**
	 * Create one immutable receipt batch, or return it on an idempotent retry.
	 *
	 * @return int Batch ID.
	 */
	public function record_receipt( int $pool_id, int $supplier_id, int $movement_id, int $quantity, string $supplier_lot = '', string $expiry_date = '', int $total_cost_minor = 0, string $currency = '' ): int {
		$this->validate_expiry_date( $expiry_date );
		if ( $pool_id < 1 || $movement_id < 1 || $quantity < 1 ) {
			throw new InvalidArgumentException( 'The receipt batch is invalid.' );
		}
		$supplier_lot = substr( trim( $supplier_lot ), 0, 191 );
		$currency     = substr( strtoupper( $currency ), 0, 3 );
		$existing     = $this->for_movement( $movement_id );
		if ( is_array( $existing ) ) {
			if ( $pool_id !== (int) $existing['pool_id'] || max( 0, $supplier_id ) !== (int) $existing['supplier_id'] || $quantity !== (int) $existing['quantity_received_base'] || $supplier_lot !== $existing['supplier_lot'] || ( '' === $expiry_date ? null : $expiry_date ) !== $existing['expiry_date'] || max( 0, $total_cost_minor ) !== (int) $existing['total_cost_minor'] || $currency !== $existing['currency'] ) {
				throw new RuntimeException( 'The receipt batch already has different metadata.' );
			}
			return (int) $existing['id'];
		}
		$now = current_time( 'mysql', true );
		$this->db->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		try {
			$inserted = $this->db->insert(
				$this->table(),
				array(
					'pool_id'                 => $pool_id,
					'supplier_id'             => max( 0, $supplier_id ),
					'receipt_movement_id'     => $movement_id,
					'supplier_lot'            => $supplier_lot,
					'quantity_received_base'  => $quantity,
					'quantity_available_base' => $quantity,
					'total_cost_minor'        => max( 0, $total_cost_minor ),
					'currency'                => $currency,
					'received_at'             => $now,
					'expiry_date'             => '' === $expiry_date ? null : $expiry_date,
					'created_at'              => $now,
					'updated_at'              => $now,
				),
				array( '%d', '%d', '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
			);
			if ( false === $inserted ) {
				throw new RuntimeException( 'Could not record the receipt batch.' );
			}
			$batch_id = (int) $this->db->insert_id;
			$linked   = $this->db->update( $this->db->prefix . 'laqi_lusm_movements', array( 'batch_id' => $batch_id ), array( 'id' => $movement_id ), array( '%d' ), array( '%d' ) );
			if ( 1 !== $linked ) {
				throw new RuntimeException( 'Could not link the receipt movement to its batch.' );
			}
			$this->db->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			return $batch_id;
		} catch ( Throwable $error ) {
			$this->db->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			throw $error;
		}
	}

	/** Reject invalid receipt metadata before the pool mutation is attempted. */
	public function validate_expiry_date( string $date ): void {
		if ( ! $this->valid_date( $date ) ) {
			throw new InvalidArgumentException( 'The batch expiry date is invalid.' );
		}
	}

	/** Active and depleted receipt batches with pool/supplier context. @return array<int,array<string,mixed>> */
	public function batches( int $limit = 100 ): array {
		$rows = $this->db->get_results( $this->db->prepare( 'SELECT b.*, p.name AS pool_name, p.family, p.display_unit, COALESCE(s.name, %s) AS supplier_name FROM ' . $this->table() . ' b INNER JOIN ' . $this->db->prefix . 'laqi_lusm_pools p ON p.id=b.pool_id LEFT JOIN ' . $this->db->prefix . 'laqi_lusm_suppliers s ON s.id=b.supplier_id ORDER BY CASE WHEN b.expiry_date IS NULL THEN 1 ELSE 0 END, b.expiry_date ASC, b.id ASC LIMIT %d', '', max( 1, min( 500, $limit ) ) ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/** Active FEFO candidates for one pool; undated quantities are last. @return array<int,array<string,mixed>> */
	public function allocatable( int $pool_id ): array {
		$rows = $this->db->get_results( $this->db->prepare( 'SELECT * FROM ' . $this->table() . ' WHERE pool_id = %d AND status = %s AND quantity_available_base > 0 AND (expiry_date IS NULL OR expiry_date >= %s) ORDER BY CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END, expiry_date ASC, received_at ASC, id ASC', $pool_id, 'active', current_time( 'Y-m-d' ) ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/** Expired active quantity that remains physically on hand. */
	public function expired_quantity( int $pool_id ): int {
		return (int) $this->db->get_var( $this->db->prepare( 'SELECT COALESCE(SUM(quantity_available_base),0) FROM ' . $this->table() . ' WHERE pool_id=%d AND status=%s AND expiry_date IS NOT NULL AND expiry_date<%s', $pool_id, 'active', current_time( 'Y-m-d' ) ) );
	}

	/** One batch with pool context. @return array<string,mixed>|null */
	public function find( int $batch_id ): ?array {
		$row = $this->db->get_row( $this->db->prepare( 'SELECT b.*,p.family,p.display_unit FROM ' . $this->table() . ' b INNER JOIN ' . $this->db->prefix . 'laqi_lusm_pools p ON p.id=b.pool_id WHERE b.id=%d', $batch_id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/** Move a non-empty batch between active and quarantined and journal the transition. */
	public function set_status( int $batch_id, string $from, string $to, int $actor_id = 0, string $reason = '' ): void {
		if ( ! in_array( $from, array( 'active', 'quarantined' ), true ) || ! in_array( $to, array( 'active', 'quarantined' ), true ) || $from === $to ) {
			throw new InvalidArgumentException( 'The batch status transition is invalid.' );
		}
		$this->db->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		try {
			$quantity = $this->db->get_var( $this->db->prepare( 'SELECT quantity_available_base FROM ' . $this->table() . ' WHERE id=%d AND status=%s AND quantity_available_base>0 FOR UPDATE', $batch_id, $from ) );
			if ( null === $quantity ) {
				throw new RuntimeException( 'The batch status could not be changed.' );
			}
			$now     = current_time( 'mysql', true );
			$updated = $this->db->update(
				$this->table(),
				array(
					'status'     => $to,
					'updated_at' => $now,
				),
				array(
					'id'     => $batch_id,
					'status' => $from,
				),
				array( '%s', '%s' ),
				array( '%d', '%s' )
			);
			$logged  = $this->db->insert(
				$this->events_table(),
				array(
					'batch_id'      => $batch_id,
					'event_type'    => 'quarantined' === $to ? 'quarantine' : 'release',
					'quantity_base' => (int) $quantity,
					'actor_id'      => max( 0, $actor_id ),
					'reason'        => substr( trim( $reason ), 0, 191 ),
					'created_at'    => $now,
				),
				array( '%d', '%s', '%d', '%d', '%s', '%s' )
			);
			if ( 1 !== $updated || false === $logged ) {
				throw new RuntimeException( 'The batch status could not be changed.' );
			}
			$this->db->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		} catch ( Throwable $error ) {
			$this->db->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			throw $error;
		}
	}

	/** Status history for one batch, oldest first. @return array<int,array<string,mixed>> */
	public function events( int $batch_id ): array {
		$rows = $this->db->get_results( $this->db->prepare( 'SELECT * FROM ' . $this->events_table() . ' WHERE batch_id=%d ORDER BY id ASC', $batch_id ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/** Confirm a recall, make remaining stock unavailable, and journal who confirmed it. */
	public function recall( int $batch_id, int $actor_id, string $reason ): void {
		$reason = substr( trim( $reason ), 0, 191 );
		if ( '' === $reason ) {
			throw new InvalidArgumentException( 'A recall reason is required.' );
		}
		$this->db->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		try {
			$batch = $this->db->get_row( $this->db->prepare( 'SELECT status,quantity_available_base FROM ' . $this->table() . ' WHERE id=%d FOR UPDATE', $batch_id ), ARRAY_A );
			if ( ! is_array( $batch ) ) {
				throw new InvalidArgumentException( 'Unknown batch.' );
			}
			if ( 'recalled' === $batch['status'] ) {
				$this->db->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				return;
			}
			$now     = current_time( 'mysql', true );
			$updated = $this->db->update(
				$this->table(),
				array(
					'status'     => 'recalled',
					'updated_at' => $now,
				),
				array( 'id' => $batch_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			$logged  = $this->db->insert(
				$this->events_table(),
				array(
					'batch_id'      => $batch_id,
					'event_type'    => 'recall',
					'quantity_base' => (int) $batch['quantity_available_base'],
					'actor_id'      => max( 0, $actor_id ),
					'reason'        => $reason,
					'created_at'    => $now,
				),
				array( '%d', '%s', '%d', '%d', '%s', '%s' )
			);
			if ( 1 !== $updated || false === $logged ) {
				throw new RuntimeException( 'The batch recall could not be confirmed.' );
			}
			$this->db->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		} catch ( Throwable $error ) {
			$this->db->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			throw $error;
		}
	}

	/** Quarantined and expired quantity excluded from sale. */
	public function unavailable_quantity( int $pool_id ): int {
		return (int) $this->db->get_var( $this->db->prepare( 'SELECT COALESCE(SUM(quantity_available_base),0) FROM ' . $this->table() . ' WHERE pool_id=%d AND quantity_available_base>0 AND (status IN (%s,%s) OR (status=%s AND expiry_date IS NOT NULL AND expiry_date<%s))', $pool_id, 'quarantined', 'recalled', 'active', current_time( 'Y-m-d' ) ) );
	}

	/** Batch for an idempotent receipt movement. @return array<string,mixed>|null */
	private function for_movement( int $movement_id ): ?array {
		$row = $this->db->get_row( $this->db->prepare( 'SELECT * FROM ' . $this->table() . ' WHERE receipt_movement_id = %d', $movement_id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/** Blank or exact calendar date. */
	private function valid_date( string $date ): bool {
		if ( '' === $date ) {
			return true;
		}
		$parsed = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
		return false !== $parsed && $date === $parsed->format( 'Y-m-d' );
	}

	/** Table name. */
	private function table(): string {
		return $this->db->prefix . 'laqi_lusm_batches';
	}

	/** Event table name. */
	private function events_table(): string {
		return $this->db->prefix . 'laqi_lusm_batch_events';
	}
}
