<?php
/**
 * Creates destination batches inside an atomic transfer.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Batches;

defined( 'ABSPATH' ) || exit;

// Compact listener methods remain explicit through types and names.
// phpcs:disable Generic.Commenting.DocComment.MissingShort, Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag

use LaqiUnitStockManager\Inventory\MovementResult;

/** Materializes transferred batch identity after the destination movement exists. */
final class BatchTransferReceiver {
	/** @var BatchRepository */ private $batches;
	/** Constructor. */ public function __construct( BatchRepository $batches ) {
		$this->batches = $batches; }
	/** Register inside the mutation transaction. */ public function register(): void {
		add_action( 'laqi_lusm_stock_movement_applied', array( $this, 'receive' ), 10, 6 ); }
	/** Create the destination batch for transfer-in movements. @param array<string,mixed> $context Context. */
	public function receive( int $pool_id, int $delta, string $type, string $event_key, array $context, MovementResult $result ): void {
		unset( $event_key );
		if ( 'batch_transfer_in' !== $type || $delta < 1 || ! isset( $context['transfer_batch'] ) || ! is_array( $context['transfer_batch'] ) ) {
			return;
		}
		$this->batches->record_transfer( $pool_id, $result->movement_id(), $delta, $context['transfer_batch'] );
	}
}
