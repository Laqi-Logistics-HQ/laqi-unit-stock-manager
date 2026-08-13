<?php
/**
 * Paid supplier and pack persistence.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Receiving;

defined( 'ABSPATH' ) || exit;

// Identity lookup parameter names are intentionally self-documenting.
// phpcs:disable Squiz.Commenting.FunctionComment.MissingParamTag

use InvalidArgumentException;
use RuntimeException;
use wpdb;

/** Stores suppliers and exact pool-specific supplier packs. */
final class SupplierRepository {
	const SCHEMA_OPTION = 'laqi_lusm_receiving_schema_version';

	/** Database.
	 *
	 * @var wpdb
	 */
	private $db;

	/** Constructor.
	 *
	 * @param wpdb $db Database.
	 */
	public function __construct( wpdb $db ) {
		$this->db = $db;
	}

	/** Install premium receiving tables. @return void */
	public function install(): void {
		if ( 2 === (int) get_option( self::SCHEMA_OPTION, 0 ) ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset   = $this->db->get_charset_collate();
		$suppliers = $this->table( 'suppliers' );
		$packs     = $this->table( 'supplier_packs' );
		$receipts  = $this->table( 'receipts' );
		$incoming  = $this->table( 'incoming_deliveries' );
		dbDelta(
			"CREATE TABLE {$suppliers} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL,
			email varchar(191) NOT NULL DEFAULT '',
			lead_time_days smallint(5) unsigned NOT NULL DEFAULT 0,
			active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY active_name (active,name)
		) {$charset};"
		);
		dbDelta(
			"CREATE TABLE {$packs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			supplier_id bigint(20) unsigned NOT NULL,
			pool_id bigint(20) unsigned NOT NULL,
			name varchar(191) NOT NULL,
			quantity_base bigint(20) unsigned NOT NULL,
			active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY supplier_active (supplier_id,active),
			KEY pool_active (pool_id,active)
		) {$charset};"
		);
		dbDelta(
			"CREATE TABLE {$receipts} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			supplier_id bigint(20) unsigned NOT NULL,
			pack_id bigint(20) unsigned NOT NULL,
			pool_id bigint(20) unsigned NOT NULL,
			pack_count bigint(20) unsigned NOT NULL,
			quantity_base bigint(20) unsigned NOT NULL,
			movement_id bigint(20) unsigned NOT NULL,
			actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			reference varchar(191) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY movement_id (movement_id),
			KEY pool_created (pool_id,created_at)
		) {$charset};"
		);
		dbDelta(
			"CREATE TABLE {$incoming} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			supplier_id bigint(20) unsigned NOT NULL,
			pack_id bigint(20) unsigned NOT NULL,
			pool_id bigint(20) unsigned NOT NULL,
			pack_count bigint(20) unsigned NOT NULL,
			quantity_base bigint(20) unsigned NOT NULL,
			expected_date date NOT NULL,
			reference varchar(191) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'pending',
			movement_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			received_at datetime NULL,
			PRIMARY KEY  (id),
			KEY pool_status_date (pool_id,status,expected_date),
			KEY status_date (status,expected_date)
		) {$charset};"
		);
		update_option( self::SCHEMA_OPTION, 2, false );
	}

	/** Create a supplier.
	 *
	 * @param string $name Name.
	 * @param string $email Email.
	 * @param int    $lead_time_days Lead time.
	 * @return int
	 * @throws InvalidArgumentException For invalid input.
	 * @throws RuntimeException When persistence fails.
	 */
	public function create_supplier( string $name, string $email, int $lead_time_days ): int {
		$name = trim( $name );
		if ( '' === $name || $lead_time_days < 0 || $lead_time_days > 365 || ( '' !== $email && ! is_email( $email ) ) ) {
			throw new InvalidArgumentException( 'The supplier details are invalid.' );
		}
		$now = current_time( 'mysql', true );
		if ( false === $this->db->insert(
			$this->table( 'suppliers' ),
			array(
				'name'           => $name,
				'email'          => $email,
				'lead_time_days' => $lead_time_days,
				'created_at'     => $now,
				'updated_at'     => $now,
			),
			array( '%s', '%s', '%d', '%s', '%s' )
		) ) {
			throw new RuntimeException( 'Could not create the supplier.' );
		}
		return (int) $this->db->insert_id;
	}

	/** Create a supplier pack.
	 *
	 * @param int    $supplier_id Supplier ID.
	 * @param int    $pool_id Pool ID.
	 * @param string $name Name.
	 * @param int    $quantity_base Quantity.
	 * @return int
	 * @throws InvalidArgumentException For invalid input.
	 * @throws RuntimeException When persistence fails.
	 */
	public function create_pack( int $supplier_id, int $pool_id, string $name, int $quantity_base ): int {
		if ( $supplier_id < 1 || $pool_id < 1 || '' === trim( $name ) || $quantity_base < 1 || null === $this->supplier( $supplier_id ) ) {
			throw new InvalidArgumentException( 'The supplier pack details are invalid.' );
		}
		$now = current_time( 'mysql', true );
		if ( false === $this->db->insert(
			$this->table( 'supplier_packs' ),
			array(
				'supplier_id'   => $supplier_id,
				'pool_id'       => $pool_id,
				'name'          => trim( $name ),
				'quantity_base' => $quantity_base,
				'created_at'    => $now,
				'updated_at'    => $now,
			),
			array( '%d', '%d', '%s', '%d', '%s', '%s' )
		) ) {
			throw new RuntimeException( 'Could not create the supplier pack.' );
		}
		return (int) $this->db->insert_id;
	}

	/** One active supplier.
	 *
	 * @param int $supplier_id Supplier ID.
	 * @return array<string,mixed>|null
	 */
	public function supplier( int $supplier_id ): ?array {
		$row = $this->db->get_row( $this->db->prepare( 'SELECT * FROM ' . $this->table( 'suppliers' ) . ' WHERE id = %d AND active = 1', $supplier_id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/** One active pack with supplier context.
	 *
	 * @param int $pack_id Pack ID.
	 * @return array<string,mixed>|null
	 */
	public function pack( int $pack_id ): ?array {
		$row = $this->db->get_row( $this->db->prepare( 'SELECT p.*, s.name AS supplier_name, s.lead_time_days FROM ' . $this->table( 'supplier_packs' ) . ' p INNER JOIN ' . $this->table( 'suppliers' ) . ' s ON s.id = p.supplier_id AND s.active = 1 WHERE p.id = %d AND p.active = 1', $pack_id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/** Active suppliers. @return array<int,array<string,mixed>> */
	public function suppliers(): array {
		$rows = $this->db->get_results( 'SELECT * FROM ' . $this->table( 'suppliers' ) . ' WHERE active = 1 ORDER BY name ASC', ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/** Find an active supplier by exact name. @param string $name Name. @return array<string,mixed>|null */
	public function supplier_by_name( string $name ): ?array {
		$row = $this->db->get_row( $this->db->prepare( 'SELECT * FROM ' . $this->table( 'suppliers' ) . ' WHERE name = %s AND active = 1 ORDER BY id ASC LIMIT 1', $name ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/** Active packs with names. @return array<int,array<string,mixed>> */
	public function packs(): array {
		$rows = $this->db->get_results( 'SELECT p.*, p.name AS pack_name, s.name AS supplier_name, s.lead_time_days, i.name AS pool_name, i.display_unit, i.family FROM ' . $this->table( 'supplier_packs' ) . ' p INNER JOIN ' . $this->table( 'suppliers' ) . ' s ON s.id = p.supplier_id INNER JOIN ' . $this->db->prefix . 'laqi_lusm_pools i ON i.id = p.pool_id WHERE p.active = 1 AND s.active = 1 ORDER BY s.name ASC, p.name ASC', ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/** Active packs for one pool.
	 *
	 * @param int $pool_id Pool ID.
	 * @return array<int,array<string,mixed>>
	 */
	public function packs_for_pool( int $pool_id ): array {
		return array_values(
			array_filter(
				$this->packs(),
				static function ( array $pack ) use ( $pool_id ): bool {
					return $pool_id === (int) $pack['pool_id'];
				}
			)
		);
	}

	/** Find a pack by supplier, pool, and exact name. @return array<string,mixed>|null */
	public function pack_by_identity( int $supplier_id, int $pool_id, string $name ): ?array {
		$row = $this->db->get_row( $this->db->prepare( 'SELECT id FROM ' . $this->table( 'supplier_packs' ) . ' WHERE supplier_id = %d AND pool_id = %d AND name = %s AND active = 1 ORDER BY id ASC LIMIT 1', $supplier_id, $pool_id, $name ), ARRAY_A );
		return is_array( $row ) ? $this->pack( (int) $row['id'] ) : null;
	}

	/** Record a receipt once per movement.
	 *
	 * @param array<string,mixed> $pack Pack.
	 * @param int                 $pack_count Pack count.
	 * @param int                 $quantity_base Quantity.
	 * @param int                 $movement_id Movement ID.
	 * @param int                 $actor_id Actor ID.
	 * @param string              $reference Reference.
	 * @return void
	 * @throws RuntimeException When persistence fails.
	 */
	public function record_receipt( array $pack, int $pack_count, int $quantity_base, int $movement_id, int $actor_id, string $reference ): void {
		$existing = $this->db->get_var( $this->db->prepare( 'SELECT id FROM ' . $this->table( 'receipts' ) . ' WHERE movement_id = %d', $movement_id ) );
		if ( null !== $existing ) {
			return;
		}
		if ( false === $this->db->insert(
			$this->table( 'receipts' ),
			array(
				'supplier_id'   => (int) $pack['supplier_id'],
				'pack_id'       => (int) $pack['id'],
				'pool_id'       => (int) $pack['pool_id'],
				'pack_count'    => $pack_count,
				'quantity_base' => $quantity_base,
				'movement_id'   => $movement_id,
				'actor_id'      => $actor_id,
				'reference'     => substr( $reference, 0, 191 ),
				'created_at'    => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s' )
		) ) {
			throw new RuntimeException( 'Could not record the supplier receipt.' );
		}
	}

	/** Recent receipts.
	 *
	 * @param int $limit Limit.
	 * @return array<int,array<string,mixed>>
	 */
	public function receipts( int $limit = 25 ): array {
		$rows = $this->db->get_results( $this->db->prepare( 'SELECT r.*, s.name AS supplier_name, p.name AS pack_name, i.name AS pool_name, i.display_unit, i.family FROM ' . $this->table( 'receipts' ) . ' r INNER JOIN ' . $this->table( 'suppliers' ) . ' s ON s.id = r.supplier_id INNER JOIN ' . $this->table( 'supplier_packs' ) . ' p ON p.id = r.pack_id INNER JOIN ' . $this->db->prefix . 'laqi_lusm_pools i ON i.id = r.pool_id ORDER BY r.id DESC LIMIT %d', max( 1, min( 100, $limit ) ) ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/** Schedule expected supplier stock.
	 *
	 * @param int    $pack_id Pack ID.
	 * @param int    $pack_count Pack count.
	 * @param string $expected_date Expected local date.
	 * @param string $reference Delivery reference.
	 * @return int
	 * @throws InvalidArgumentException For invalid input.
	 * @throws RuntimeException When persistence fails.
	 */
	public function create_incoming( int $pack_id, int $pack_count, string $expected_date, string $reference ): int {
		$pack = $this->pack( $pack_id );
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $expected_date );
		if ( null === $pack || $pack_count < 1 || $pack_count > 1000000 || false === $date || $date->format( 'Y-m-d' ) !== $expected_date ) {
			throw new InvalidArgumentException( 'The incoming delivery is invalid.' );
		}
		$pack_quantity = (int) $pack['quantity_base'];
		if ( $pack_quantity > intdiv( PHP_INT_MAX, $pack_count ) ) {
			throw new InvalidArgumentException( 'The incoming delivery is too large.' );
		}
		$inserted = $this->db->insert(
			$this->table( 'incoming_deliveries' ),
			array(
				'supplier_id'   => (int) $pack['supplier_id'],
				'pack_id'       => $pack_id,
				'pool_id'       => (int) $pack['pool_id'],
				'pack_count'    => $pack_count,
				'quantity_base' => $pack_quantity * $pack_count,
				'expected_date' => $expected_date,
				'reference'     => substr( $reference, 0, 191 ),
				'created_at'    => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s' )
		);
		if ( false === $inserted ) {
			throw new RuntimeException( 'Could not schedule the incoming delivery.' );
		}
		return (int) $this->db->insert_id;
	}

	/** One pending incoming delivery.
	 *
	 * @param int $incoming_id Incoming ID.
	 * @return array<string,mixed>|null
	 */
	public function incoming( int $incoming_id ): ?array {
		$row = $this->db->get_row( $this->db->prepare( 'SELECT * FROM ' . $this->table( 'incoming_deliveries' ) . ' WHERE id = %d AND status = %s', $incoming_id, 'pending' ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/** Pending incoming deliveries. @return array<int,array<string,mixed>> */
	public function incoming_deliveries(): array {
		$rows = $this->db->get_results( 'SELECT d.*, s.name AS supplier_name, p.name AS pack_name, i.name AS pool_name, i.display_unit, i.family FROM ' . $this->table( 'incoming_deliveries' ) . ' d INNER JOIN ' . $this->table( 'suppliers' ) . ' s ON s.id = d.supplier_id INNER JOIN ' . $this->table( 'supplier_packs' ) . ' p ON p.id = d.pack_id INNER JOIN ' . $this->db->prefix . "laqi_lusm_pools i ON i.id = d.pool_id WHERE d.status = 'pending' ORDER BY d.expected_date ASC, d.id ASC", ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/** Whether a pending delivery reference already exists. @param string $reference Reference. @return bool */
	public function has_incoming_reference( string $reference ): bool {
		return '' !== $reference && (bool) $this->db->get_var( $this->db->prepare( 'SELECT 1 FROM ' . $this->table( 'incoming_deliveries' ) . ' WHERE reference = %s AND status = %s LIMIT 1', $reference, 'pending' ) );
	}

	/** Exact pending incoming quantity for a pool.
	 *
	 * @param int $pool_id Pool ID.
	 * @return int
	 */
	public function incoming_quantity( int $pool_id ): int {
		return (int) $this->db->get_var( $this->db->prepare( 'SELECT COALESCE(SUM(quantity_base), 0) FROM ' . $this->table( 'incoming_deliveries' ) . ' WHERE pool_id = %d AND status = %s', $pool_id, 'pending' ) );
	}

	/** Mark expected stock as received.
	 *
	 * @param int $incoming_id Incoming ID.
	 * @param int $movement_id Movement ID.
	 * @return void
	 * @throws RuntimeException When state cannot be changed.
	 */
	public function mark_incoming_received( int $incoming_id, int $movement_id ): void {
		$updated = $this->db->update(
			$this->table( 'incoming_deliveries' ),
			array(
				'status'      => 'received',
				'movement_id' => $movement_id,
				'received_at' => current_time( 'mysql', true ),
			),
			array(
				'id'     => $incoming_id,
				'status' => 'pending',
			),
			array( '%s', '%d', '%s' ),
			array( '%d', '%s' )
		);
		if ( 1 !== $updated ) {
			throw new RuntimeException( 'The incoming delivery is no longer pending.' );
		}
	}

	/** Resolve a premium receiving table.
	 *
	 * @param string $suffix Suffix.
	 * @return string
	 */
	private function table( string $suffix ): string {
		return $this->db->prefix . 'laqi_lusm_' . $suffix;
	}
}
