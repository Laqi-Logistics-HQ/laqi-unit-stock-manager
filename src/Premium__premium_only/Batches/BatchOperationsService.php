<?php
/**
 * Batch operations.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Batches;

defined( 'ABSPATH' ) || exit;

// Compact operations remain explicit through types and names.
// phpcs:disable Generic.Commenting.DocComment.MissingShort, Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag

use InvalidArgumentException;
use LaqiUnitStockManager\Inventory\StockMutationService;

/** Coordinates batch states with the authoritative pool ledger. */
final class BatchOperationsService {
	/** @var BatchRepository */ private $batches;
	/** @var StockMutationService */ private $mutations;
	/** Constructor. */ public function __construct( BatchRepository $batches, StockMutationService $mutations ) {
		$this->batches   = $batches;
		$this->mutations = $mutations; }
	/** Quarantine saleable batch quantity without changing physical on-hand. */ public function quarantine( int $batch_id, int $actor_id = 0, string $reason = '' ): void {
		$this->batches->set_status( $batch_id, 'active', 'quarantined', $actor_id, $reason ); }
	/** Release quarantined quantity. */ public function release( int $batch_id, int $actor_id = 0, string $reason = '' ): void {
		$this->batches->set_status( $batch_id, 'quarantined', 'active', $actor_id, $reason ); }
	/** Confirm a merchant-reviewed recall without contacting customers. */ public function recall( int $batch_id, int $actor_id, string $reason ): void {
		$this->batches->recall( $batch_id, $actor_id, $reason ); }
	/** Permanently remove the remaining selected batch quantity. */ public function write_off( int $batch_id, int $actor_id, string $type = 'loss_damage', string $reason = 'Batch write-off' ): void {
		$batch    = $this->required( $batch_id );
		$quantity = (int) $batch['quantity_available_base'];
		if ( $quantity < 1 ) {
			return; }
		$this->mutations->apply(
			(int) $batch['pool_id'],
			-$quantity,
			$type,
			'batch-writeoff:' . $type . ':' . $batch_id,
			array(
				'source_type' => 'batch',
				'source_id'   => $batch_id,
				'batch_id'    => $batch_id,
				'actor_id'    => $actor_id,
				'reason'      => $reason,
			)
		); }
	/** Set the counted remaining quantity and reconcile the pool by the same delta. */ public function stocktake( int $batch_id, int $target, int $actor_id ): void {
		$batch = $this->required( $batch_id );
		if ( $target < 0 ) {
			throw new InvalidArgumentException( 'The batch count cannot be negative.' ); }
		$delta = $target - (int) $batch['quantity_available_base'];
		if ( 0 === $delta ) {
			return; }
		$this->mutations->apply(
			(int) $batch['pool_id'],
			$delta,
			'manual_set',
			'batch-stocktake:' . $batch_id . ':' . wp_generate_uuid4(),
			array(
				'source_type' => 'batch',
				'source_id'   => $batch_id,
				'batch_id'    => $batch_id,
				'actor_id'    => $actor_id,
				'reason'      => 'Batch stocktake',
			)
		); }
	/** Required batch. @return array<string,mixed> */ private function required( int $batch_id ): array {
		$batch = $this->batches->find( $batch_id );
		if ( null === $batch ) {
			throw new InvalidArgumentException( 'Unknown batch.' );
		} return $batch; }
}
