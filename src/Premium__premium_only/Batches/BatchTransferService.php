<?php
/**
 * Atomic batch transfers.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Batches;

defined( 'ABSPATH' ) || exit;

// Compact service methods remain explicit through types and names.
// phpcs:disable Generic.Commenting.DocComment.MissingShort, Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag

use InvalidArgumentException;
use LaqiUnitStockManager\Inventory\StockMutationService;
use LaqiUnitStockManager\Storage\PoolRepository;

/** Moves a traceable quantity between compatible pools in one transaction. */
final class BatchTransferService {
	/** @var BatchRepository */ private $batches;
	/** @var PoolRepository */ private $pools;
	/** @var StockMutationService */ private $mutations;
	/** Constructor. */ public function __construct( BatchRepository $batches, PoolRepository $pools, StockMutationService $mutations ) {
		$this->batches   = $batches;
		$this->pools     = $pools;
		$this->mutations = $mutations; }
	/** Transfer exact normalized quantity. */
	public function transfer( int $batch_id, int $destination_pool_id, int $quantity, int $actor_id, string $reason, string $event_key ): void {
		$batch       = $this->batches->find( $batch_id );
		$destination = $this->pools->find( $destination_pool_id );
		$reason      = trim( $reason );
		if ( null === $batch || null === $destination || $quantity < 1 || (int) $batch['pool_id'] === $destination_pool_id || 'recalled' === $batch['status'] || '' === $reason || '' === $event_key ) {
			throw new InvalidArgumentException( 'The batch transfer is invalid.' );
		}
		if ( $batch['family'] !== $destination->quantity()->family() ) {
			throw new InvalidArgumentException( 'Batch transfers require pools from the same measurement family.' );
		}
		$context = array(
			'source_type' => 'batch_transfer',
			'source_id'   => $batch_id,
			'actor_id'    => $actor_id,
			'reason'      => $reason,
		);
		$this->mutations->apply_batch(
			array(
				array(
					'pool_id'         => (int) $batch['pool_id'],
					'delta'           => -$quantity,
					'type'            => 'batch_transfer_out',
					'idempotency_key' => $event_key . ':out',
					'context'         => $context + array(
						'batch_id' => $batch_id,
						'metadata' => array( 'destination_pool_id' => $destination_pool_id ),
					),
				),
				array(
					'pool_id'         => $destination_pool_id,
					'delta'           => $quantity,
					'type'            => 'batch_transfer_in',
					'idempotency_key' => $event_key . ':in',
					'context'         => $context + array(
						'transfer_batch' => $batch,
						'metadata'       => array(
							'source_pool_id'  => (int) $batch['pool_id'],
							'source_batch_id' => $batch_id,
						),
					),
				),
			)
		);
	}
}
