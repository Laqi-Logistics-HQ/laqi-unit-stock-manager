<?php
/**
 * Quarantined and damaged stock persistence.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Supply;

defined( 'ABSPATH' ) || exit;
// phpcs:disable Generic.Commenting.DocComment.MissingShort, Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag

use RuntimeException;
use wpdb;

/** Stores auditable non-saleable quantities without changing on-hand stock. */
final class StockHoldRepository {
	const SCHEMA_OPTION = 'laqi_lusm_supply_schema_version';
	const VERSION       = 1;
	/** @var wpdb */ private $db;
	/** @param wpdb $db Database. */ public function __construct( wpdb $db ) {
		$this->db = $db; }
	/** Install schema. */ public function install(): void {
		if ( self::VERSION === (int) get_option( self::SCHEMA_OPTION, 0 ) ) {
			return;
		} require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = $this->table();
		$charset = $this->db->get_charset_collate(); dbDelta(
			"CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		pool_id bigint(20) unsigned NOT NULL,
		state varchar(20) NOT NULL,
		quantity_base bigint(20) unsigned NOT NULL,
		status varchar(20) NOT NULL DEFAULT 'active',
		reason varchar(191) NOT NULL DEFAULT '',
		actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY pool_status_state (pool_id,status,state)
	) {$charset};"
		);
		update_option( self::SCHEMA_OPTION, self::VERSION, false ); }
	/** Place an exact non-saleable hold. */ public function place( int $pool_id, string $state, int $quantity, string $reason, int $actor_id ): int {
		if ( ! in_array( $state, array( 'quarantined', 'damaged' ), true ) || $quantity < 1 ) {
			throw new \InvalidArgumentException( 'The stock hold is invalid.' );
		}
		$this->db->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		try {
			$pool     = $this->db->get_row( $this->db->prepare( 'SELECT quantity_base FROM ' . $this->db->prefix . 'laqi_lusm_pools WHERE id = %d FOR UPDATE', $pool_id ), ARRAY_A );
			$reserved = (int) $this->db->get_var( $this->db->prepare( 'SELECT COALESCE(SUM(quantity_base),0) FROM ' . $this->db->prefix . 'laqi_lusm_reservations WHERE pool_id=%d AND status=%s AND expires_at>%s', $pool_id, 'active', current_time( 'mysql', true ) ) );
			if ( ! is_array( $pool ) || (int) $pool['quantity_base'] - $reserved - $this->held_quantity( $pool_id ) < $quantity ) {
				throw new \InvalidArgumentException( 'Insufficient available stock for this hold.' ); }
			$inserted = $this->db->insert(
				$this->table(),
				array(
					'pool_id'       => $pool_id,
					'state'         => $state,
					'quantity_base' => $quantity,
					'reason'        => substr( $reason, 0, 191 ),
					'actor_id'      => $actor_id,
					'created_at'    => current_time( 'mysql', true ),
					'updated_at'    => current_time( 'mysql', true ),
				),
				array( '%d', '%s', '%d', '%s', '%d', '%s', '%s' )
			);
			if ( false === $inserted ) {
				throw new RuntimeException( 'Could not place the stock hold.' ); }
			$hold_id = (int) $this->db->insert_id;
			$this->db->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			return $hold_id;
		} catch ( \Throwable $error ) {
			$this->db->query( 'ROLLBACK' );
			throw $error; } // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	}
	/** Active total for a pool. */ public function held_quantity( int $pool_id ): int {
		return (int) $this->db->get_var( $this->db->prepare( 'SELECT COALESCE(SUM(quantity_base),0) FROM ' . $this->table() . ' WHERE pool_id = %d AND status = %s', $pool_id, 'active' ) ); }
	/** One active hold. @return array<string,mixed>|null */ public function find( int $hold_id ): ?array {
		$row = $this->db->get_row( $this->db->prepare( 'SELECT * FROM ' . $this->table() . ' WHERE id = %d AND status = %s', $hold_id, 'active' ), ARRAY_A );
		return is_array( $row ) ? $row : null; }
	/** Finish a hold idempotently. */ public function finish( int $hold_id, string $status ): void {
		if ( ! in_array( $status, array( 'released', 'written_off' ), true ) ) {
			throw new \InvalidArgumentException( 'Unknown stock hold result.' );
		} $this->db->update(
			$this->table(),
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql', true ),
			),
			array(
				'id'     => $hold_id,
				'status' => 'active',
			),
			array( '%s', '%s' ),
			array( '%d', '%s' )
		); }
	/** Supply-state summary. @return array<int,array<string,mixed>> */ public function summary(): array {
		$p = $this->db->prefix; $rows = $this->db->get_results(
			"SELECT p.id AS pool_id,p.name,p.family,p.display_unit,p.quantity_base,
		COALESCE((SELECT SUM(r.quantity_base) FROM {$p}laqi_lusm_reservations r WHERE r.pool_id=p.id AND r.status='active' AND r.expires_at>UTC_TIMESTAMP()),0) reserved_base,
		COALESCE((SELECT SUM(i.quantity_base) FROM {$p}laqi_lusm_incoming_deliveries i WHERE i.pool_id=p.id AND i.status='pending'),0) incoming_base,
		COALESCE(SUM(CASE WHEN h.status='active' AND h.state='quarantined' THEN h.quantity_base ELSE 0 END),0) quarantined_base,
		COALESCE(SUM(CASE WHEN h.status='active' AND h.state='damaged' THEN h.quantity_base ELSE 0 END),0) damaged_base
		FROM {$p}laqi_lusm_pools p LEFT JOIN {$p}laqi_lusm_stock_holds h ON h.pool_id=p.id GROUP BY p.id,p.name,p.family,p.display_unit,p.quantity_base
		HAVING reserved_base>0 OR incoming_base>0 OR quarantined_base>0 OR damaged_base>0 ORDER BY p.name",
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array(); }
	/** Active holds with pool context. @return array<int,array<string,mixed>> */ public function active(): array {
		$rows = $this->db->get_results( 'SELECT h.*,p.name AS pool_name,p.family,p.display_unit FROM ' . $this->table() . ' h INNER JOIN ' . $this->db->prefix . "laqi_lusm_pools p ON p.id=h.pool_id WHERE h.status='active' ORDER BY h.id DESC", ARRAY_A );
		return is_array( $rows ) ? $rows : array(); }
	/** Table. */ private function table(): string {
		return $this->db->prefix . 'laqi_lusm_stock_holds'; }
}
