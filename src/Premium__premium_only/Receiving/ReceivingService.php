<?php
/**
 * Paid receiving service.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Receiving;

defined( 'ABSPATH' ) || exit;

use InvalidArgumentException;
use LaqiUnitStockManager\Inventory\MovementResult;
use LaqiUnitStockManager\Inventory\StockMutationService;
use OverflowException;

/** Receives exact supplier packs through the authoritative mutation path. */
final class ReceivingService {
	/** Suppliers.
	 *
	 * @var SupplierRepository
	 */ private $suppliers;
	/** Mutations.
	 *
	 * @var StockMutationService
	 */ private $mutations;
	/** Constructor.
	 *
	 * @param SupplierRepository   $suppliers Suppliers.
	 * @param StockMutationService $mutations Mutations.
	 */
	public function __construct( SupplierRepository $suppliers, StockMutationService $mutations ) {
		$this->suppliers = $suppliers;
		$this->mutations = $mutations; }
	/** Receive supplier packages.
	 *
	 * @param int    $pack_id Pack ID.
	 * @param int    $pack_count Pack count.
	 * @param string $reference Reference.
	 * @param int    $actor_id Actor ID.
	 * @param string $idempotency_key Stable key.
	 * @return MovementResult
	 * @throws InvalidArgumentException For invalid input.
	 * @throws OverflowException For excessive quantities.
	 */
	public function receive( int $pack_id, int $pack_count, string $reference, int $actor_id, string $idempotency_key ): MovementResult {
		$pack = $this->suppliers->pack( $pack_id );
		if ( null === $pack || $pack_count < 1 || $pack_count > 1000000 || '' === $idempotency_key ) {
			throw new InvalidArgumentException( 'The supplier receipt is invalid.' ); }
		$pack_quantity = (int) $pack['quantity_base'];
		if ( $pack_quantity > intdiv( PHP_INT_MAX, $pack_count ) ) {
			throw new OverflowException( 'The supplier receipt is too large.' ); }
		$quantity = $pack_quantity * $pack_count;
		$result   = $this->mutations->apply(
			(int) $pack['pool_id'],
			$quantity,
			'supplier_receipt',
			$idempotency_key,
			array(
				'source_type' => 'supplier_receipt',
				'source_id'   => $pack_id,
				'actor_id'    => $actor_id,
				'reason'      => $reference,
				'metadata'    => array(
					'supplier_id' => (int) $pack['supplier_id'],
					'pack_id'     => $pack_id,
					'pack_count'  => $pack_count,
				),
			)
		);
		$this->suppliers->record_receipt( $pack, $pack_count, $quantity, $result->movement_id(), $actor_id, $reference );
		return $result;
	}
}
