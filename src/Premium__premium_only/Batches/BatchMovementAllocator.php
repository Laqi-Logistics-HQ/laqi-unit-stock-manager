<?php
/**
 * Transactional batch movement allocation.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Batches;

defined( 'ABSPATH' ) || exit;

// Compact policy methods remain explicit through types and names.
// phpcs:disable Generic.Commenting.DocComment.MissingShort, Squiz.Commenting.FunctionComment

/** Projects authoritative pool movements onto receipt batches. */
final class BatchMovementAllocator {
	/** @var BatchAllocationRepository */ private $allocations;
	/** @param BatchAllocationRepository $allocations Allocations. */ public function __construct( BatchAllocationRepository $allocations ) {
		$this->allocations = $allocations; }
	/** Register inside the stock mutation transaction. */ public function register(): void {
		add_action( 'laqi_lusm_stock_movement_applying', array( $this, 'apply' ), 10, 5 ); }
	/** Apply a movement to batches. @param array<string,mixed> $context Context. */
	public function apply( int $pool_id, int $delta, string $type, string $event_key, array $context ): void {
		unset( $type );
		$order_id = 'order' === ( $context['source_type'] ?? '' ) ? (int) ( $context['source_id'] ?? 0 ) : 0;
		$batch_id = (int) ( $context['batch_id'] ?? 0 );
		if ( $batch_id > 0 ) {
			$this->allocations->adjust_batch( $pool_id, $batch_id, $delta, $event_key );
			return;
		}
		if ( $delta < 0 ) {
			$this->allocations->consume( $pool_id, abs( $delta ), $event_key, $order_id, 0 === $order_id );
		} elseif ( $delta > 0 && $order_id > 0 ) {
			$this->allocations->restore( $order_id, $pool_id, $delta, $event_key );
		}
	}
}
