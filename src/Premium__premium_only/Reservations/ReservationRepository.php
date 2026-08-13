<?php
/**
 * Order reservation persistence.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Reservations;

defined( 'ABSPATH' ) || exit;

// Compact repository methods remain explicit through types and names.
// phpcs:disable Generic.Commenting.DocComment.MissingShort, Squiz.Commenting.FunctionComment
// phpcs:disable Squiz.Commenting.FunctionCommentThrowTag

use RuntimeException;
use wpdb;

/** Stores one exact reservation row per order and pool. */
final class ReservationRepository {
	const SCHEMA_OPTION = 'laqi_lusm_reservation_schema_version';
	const VERSION       = 1;
	/** @var wpdb */ private $db;
	/** @param wpdb $db Database. */ public function __construct( wpdb $db ) {
		$this->db = $db; }
	/** Install schema. */
	public function install(): void {
		if ( self::VERSION === (int) get_option( self::SCHEMA_OPTION, 0 ) ) {
			return; }
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = $this->table();
		$charset = $this->db->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint(20) unsigned NOT NULL,
			pool_id bigint(20) unsigned NOT NULL,
			quantity_base bigint(20) unsigned NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			expires_at datetime NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY order_pool (order_id,pool_id),
			KEY pool_status_expiry (pool_id,status,expires_at),
			KEY status_expiry (status,expires_at)
		) {$charset};"
		);
		update_option( self::SCHEMA_OPTION, self::VERSION, false );
	}
	/** Active reserved quantity for a pool. */ public function reserved_quantity( int $pool_id ): int {
		return (int) $this->db->get_var( $this->db->prepare( 'SELECT COALESCE(SUM(quantity_base),0) FROM ' . $this->table() . ' WHERE pool_id = %d AND status = %s AND expires_at > %s', $pool_id, 'active', current_time( 'mysql', true ) ) ); }
	/** Atomically reserve an order's pool demand. @param array<int,int> $demand Demand. */
	public function reserve( int $order_id, array $demand, string $expires_at ): void {
		if ( $order_id < 1 || array() === $demand ) {
			return; }
		$this->db->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		try {
			foreach ( $demand as $pool_id => $quantity ) {
				$pool = $this->db->get_row( $this->db->prepare( 'SELECT quantity_base, allow_backorders, policy_json FROM ' . $this->db->prefix . 'laqi_lusm_pools WHERE id = %d FOR UPDATE', $pool_id ), ARRAY_A );
				if ( ! is_array( $pool ) || (int) $pool['allow_backorders'] ) {
					continue; }
				$existing = $this->db->get_row( $this->db->prepare( 'SELECT quantity_base, status FROM ' . $this->table() . ' WHERE order_id = %d AND pool_id = %d', $order_id, $pool_id ), ARRAY_A );
				if ( is_array( $existing ) ) {
					if ( (int) $existing['quantity_base'] === (int) $quantity && in_array( $existing['status'], array( 'active', 'converted' ), true ) ) {
						continue;
					} throw new RuntimeException( 'The order reservation already has different demand.' ); }
				$held    = (int) $this->db->get_var( $this->db->prepare( 'SELECT COALESCE(SUM(quantity_base),0) FROM ' . $this->db->prefix . 'laqi_lusm_stock_holds WHERE pool_id = %d AND status = %s', $pool_id, 'active' ) );
				$safety  = $this->safety_stock( (string) $pool['policy_json'] );
				$expired = (int) $this->db->get_var( $this->db->prepare( 'SELECT COALESCE(SUM(quantity_available_base),0) FROM ' . $this->db->prefix . 'laqi_lusm_batches WHERE pool_id=%d AND (status=%s OR (status=%s AND expiry_date IS NOT NULL AND expiry_date<%s))', $pool_id, 'quarantined', 'active', current_time( 'Y-m-d' ) ) );
				if ( (int) $pool['quantity_base'] - $this->reserved_quantity( (int) $pool_id ) - $held - $safety - $expired < (int) $quantity ) {
					throw new RuntimeException( 'The pooled stock cannot satisfy this reservation.' ); }
				$now      = current_time( 'mysql', true );
				$inserted = $this->db->insert(
					$this->table(),
					array(
						'order_id'      => $order_id,
						'pool_id'       => $pool_id,
						'quantity_base' => $quantity,
						'expires_at'    => $expires_at,
						'created_at'    => $now,
						'updated_at'    => $now,
					),
					array( '%d', '%d', '%d', '%s', '%s', '%s' )
				);
				if ( false === $inserted ) {
					throw new RuntimeException( 'Could not create the order reservation.' ); }
			}
			$this->db->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		} catch ( \Throwable $error ) {
			$this->db->query( 'ROLLBACK' );
			throw $error; } // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	}
	/** Transition active rows for an order. */ public function transition( int $order_id, string $status ): void {
		if ( ! in_array( $status, array( 'converted', 'released' ), true ) ) {
			throw new \InvalidArgumentException( 'Unknown reservation state.' );
		} $this->db->update(
			$this->table(),
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql', true ),
			),
			array(
				'order_id' => $order_id,
				'status'   => 'active',
			),
			array( '%s', '%s' ),
			array( '%d', '%s' )
		); }
	/** Expire elapsed rows. */ public function expire(): int {
		return (int) $this->db->query( $this->db->prepare( 'UPDATE ' . $this->table() . ' SET status = %s, updated_at = %s WHERE status = %s AND expires_at <= %s', 'expired', current_time( 'mysql', true ), 'active', current_time( 'mysql', true ) ) ); }
	/** Rows for an order. @return array<int,array<string,mixed>> */ public function for_order( int $order_id ): array {
		$rows = $this->db->get_results( $this->db->prepare( 'SELECT * FROM ' . $this->table() . ' WHERE order_id = %d ORDER BY pool_id', $order_id ), ARRAY_A );
		return is_array( $rows ) ? $rows : array(); }
	/** Active reservation totals with pool context. @return array<int,array<string,mixed>> */ public function active_summary(): array {
		$rows = $this->db->get_results( 'SELECT p.id AS pool_id, p.name, p.family, p.display_unit, p.quantity_base, COALESCE(SUM(r.quantity_base),0) AS reserved_base, COUNT(r.id) AS reservation_count FROM ' . $this->db->prefix . 'laqi_lusm_pools p INNER JOIN ' . $this->table() . " r ON r.pool_id = p.id AND r.status = 'active' AND r.expires_at > UTC_TIMESTAMP() GROUP BY p.id, p.name, p.family, p.display_unit, p.quantity_base ORDER BY p.name", ARRAY_A );
		return is_array( $rows ) ? $rows : array(); }
	/** Table name. */ private function table(): string {
		return $this->db->prefix . 'laqi_lusm_reservations'; }
	/** Safety stock from the shared policy envelope. */ private function safety_stock( string $json ): int {
		$policy = json_decode( $json, true );
		return is_array( $policy ) ? max( 0, (int) ( $policy['availability']['safety_stock_base'] ?? 0 ) ) : 0; }
}
