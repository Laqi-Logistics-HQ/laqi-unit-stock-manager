<?php
/**
 * Available-to-sell safety stock policies.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Supply;

defined( 'ABSPATH' ) || exit;

// Compact repository methods remain explicit through types and names.
// phpcs:disable Generic.Commenting.DocComment.MissingShort, Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag

use LaqiUnitStockManager\Storage\Schema;
use RuntimeException;
use wpdb;

/** Owns the safety-stock portion of each pool policy envelope. */
final class SafetyStockPolicyRepository {
	/** @var wpdb */ private $db;
	/** @param wpdb $db Database. */ public function __construct( wpdb $db ) {
		$this->db = $db; }
	/** Protected normalized quantity. */ public function quantity( int $pool_id ): int {
		$envelope = $this->envelope( $pool_id );
		return max( 0, (int) ( $envelope['availability']['safety_stock_base'] ?? 0 ) ); }
	/** Save without replacing forecasting or reorder policies. */ public function save( int $pool_id, int $quantity ): void {
		$envelope                 = $this->envelope( $pool_id );
		$envelope['availability'] = array( 'safety_stock_base' => max( 0, $quantity ) );
		if ( false === $this->db->update( Schema::table( 'pools' ), array( 'policy_json' => wp_json_encode( $envelope ) ), array( 'id' => $pool_id ), array( '%s' ), array( '%d' ) ) ) {
			throw new RuntimeException( 'Could not save the safety-stock policy.' ); } }
	/** Configured policies with pool context. @return array<int,array<string,mixed>> */ public function configured(): array {
		$rows  = $this->db->get_results( 'SELECT id AS pool_id,name,family,display_unit,quantity_base,policy_json FROM ' . Schema::table( 'pools' ) . " WHERE policy_json IS NOT NULL AND policy_json != '' ORDER BY name", ARRAY_A );
		$items = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$quantity = $this->quantity_from_envelope( json_decode( (string) $row['policy_json'], true ) );
			if ( $quantity > 0 ) {
				$row['safety_stock_base'] = $quantity;
				$items[]                  = $row;
			}
		} return $items; }
	/** Policy envelope. @return array<string,mixed> */ private function envelope( int $pool_id ): array {
		$decoded = json_decode( (string) $this->db->get_var( $this->db->prepare( 'SELECT policy_json FROM ' . Schema::table( 'pools' ) . ' WHERE id=%d', $pool_id ) ), true );
		return is_array( $decoded ) ? $decoded : array(); }
	/** Extract quantity. @param mixed $envelope Envelope. */ private function quantity_from_envelope( $envelope ): int {
		return is_array( $envelope ) ? max( 0, (int) ( $envelope['availability']['safety_stock_base'] ?? 0 ) ) : 0; }
}
