<?php
/**
 * Weighted-average material cost persistence.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Costing;

defined( 'ABSPATH' ) || exit;

// Compact repository methods remain explicit through types and names.
// phpcs:disable Generic.Commenting.DocComment.MissingShort, Squiz.Commenting.FunctionComment
// phpcs:disable Squiz.Commenting.FunctionCommentThrowTag

use InvalidArgumentException;
use RuntimeException;
use wpdb;

/** Stores receipt costs separately from stock quantities and retail prices. */
final class MaterialCostRepository {
	const SCHEMA_OPTION = 'laqi_lusm_cost_schema_version';
	const VERSION       = 1;

	/** @var wpdb */ private $db;

	/** @param wpdb $db Database. */
	public function __construct( wpdb $db ) {
		$this->db = $db; }

	/** Install premium cost tables. */
	public function install(): void {
		if ( self::VERSION === (int) get_option( self::SCHEMA_OPTION, 0 ) ) {
			return; }
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset  = $this->db->get_charset_collate();
		$costs    = $this->table( 'pool_costs' );
		$receipts = $this->table( 'receipt_costs' );
		dbDelta(
			"CREATE TABLE {$costs} (
			pool_id bigint(20) unsigned NOT NULL,
			average_minor_per_base decimal(30,20) unsigned NOT NULL DEFAULT 0,
			currency char(3) NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (pool_id)
		) {$charset};"
		);
		dbDelta(
			"CREATE TABLE {$receipts} (
			movement_id bigint(20) unsigned NOT NULL,
			pool_id bigint(20) unsigned NOT NULL,
			quantity_base bigint(20) unsigned NOT NULL,
			total_cost_minor bigint(20) unsigned NOT NULL,
			currency char(3) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (movement_id),
			KEY pool_created (pool_id,created_at)
		) {$charset};"
		);
		update_option( self::SCHEMA_OPTION, self::VERSION, false );
	}

	/** Record one priced receipt and update its pool's weighted average.
	 *
	 * @param int    $movement_id Movement ID.
	 * @param int    $pool_id Pool ID.
	 * @param int    $quantity_base Received quantity.
	 * @param int    $balance_base Balance after receipt.
	 * @param int    $total_cost_minor Total receipt cost in minor currency units.
	 * @param string $currency ISO currency code.
	 * @return void
	 */
	public function record_receipt( int $movement_id, int $pool_id, int $quantity_base, int $balance_base, int $total_cost_minor, string $currency ): void {
		$currency = strtoupper( $currency );
		if ( $movement_id < 1 || $pool_id < 1 || $quantity_base < 1 || $balance_base < 1 || $total_cost_minor < 1 || 1 !== preg_match( '/^[A-Z]{3}$/', $currency ) ) {
			throw new InvalidArgumentException( 'The material cost is invalid.' ); }
		if ( null !== $this->db->get_var( $this->db->prepare( 'SELECT movement_id FROM ' . $this->table( 'receipt_costs' ) . ' WHERE movement_id = %d', $movement_id ) ) ) {
			return; }
		$current      = $this->pool_cost( $pool_id );
		$old_quantity = max( 0, $balance_base - $quantity_base );
		if ( null !== $current && $old_quantity > 0 && $currency !== $current['currency'] ) {
			throw new InvalidArgumentException( 'Receipt currency must match the pool cost currency.' ); }
		$old_value = null === $current ? 0.0 : $old_quantity * $current['average_minor_per_base'];
		$average   = null === $current || 0 === $old_quantity ? $total_cost_minor / $quantity_base : ( $old_value + $total_cost_minor ) / $balance_base;
		$now       = current_time( 'mysql', true );
		$this->db->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		try {
			$stored  = $this->db->replace(
				$this->table( 'pool_costs' ),
				array(
					'pool_id'                => $pool_id,
					'average_minor_per_base' => number_format( $average, 20, '.', '' ),
					'currency'               => $currency,
					'updated_at'             => $now,
				),
				array( '%d', '%s', '%s', '%s' )
			);
			$receipt = $this->db->insert(
				$this->table( 'receipt_costs' ),
				array(
					'movement_id'      => $movement_id,
					'pool_id'          => $pool_id,
					'quantity_base'    => $quantity_base,
					'total_cost_minor' => $total_cost_minor,
					'currency'         => $currency,
					'created_at'       => $now,
				),
				array( '%d', '%d', '%d', '%d', '%s', '%s' )
			);
			if ( false === $stored || false === $receipt ) {
				throw new RuntimeException( 'Could not record the material cost.' ); }
			$this->db->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		} catch ( \Throwable $error ) {
			$this->db->query( 'ROLLBACK' );
			throw $error; } // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	/** Find a pool cost. @return array{average_minor_per_base:float,currency:string}|null */
	public function pool_cost( int $pool_id ): ?array {
		$row = $this->db->get_row( $this->db->prepare( 'SELECT average_minor_per_base, currency FROM ' . $this->table( 'pool_costs' ) . ' WHERE pool_id = %d', $pool_id ), ARRAY_A );
		return is_array( $row ) ? array(
			'average_minor_per_base' => (float) $row['average_minor_per_base'],
			'currency'               => (string) $row['currency'],
		) : null;
	}

	/** Material cost in minor units for normalized consumption. */
	public function consumption_cost_minor( int $pool_id, int $consumption_base ): ?int {
		$cost = $this->pool_cost( $pool_id );
		return null === $cost ? null : (int) round( $cost['average_minor_per_base'] * $consumption_base );
	}

	/** @param string $suffix Table suffix. */
	private function table( string $suffix ): string {
		return $this->db->prefix . 'laqi_lusm_' . $suffix; }
}
