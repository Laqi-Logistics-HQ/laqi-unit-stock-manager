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
use LaqiUnitStockManager\Premium\Costing\MaterialCostRepository;
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
	/** Costs.
	 *
	 * @var MaterialCostRepository
	 */ private $costs;
	/** Constructor.
	 *
	 * @param SupplierRepository     $suppliers Suppliers.
	 * @param StockMutationService   $mutations Mutations.
	 * @param MaterialCostRepository $costs Costs.
	 */
	public function __construct( SupplierRepository $suppliers, StockMutationService $mutations, MaterialCostRepository $costs ) {
		$this->suppliers = $suppliers;
		$this->mutations = $mutations;
		$this->costs     = $costs; }
	/** Receive supplier packages.
	 *
	 * @param int    $pack_id Pack ID.
	 * @param int    $pack_count Pack count.
	 * @param string $reference Reference.
	 * @param int    $actor_id Actor ID.
	 * @param string $idempotency_key Stable key.
	 * @param int    $total_cost_minor Optional receipt cost in minor units.
	 * @param string $currency Currency for a priced receipt.
	 * @return MovementResult
	 * @throws InvalidArgumentException For invalid input.
	 * @throws OverflowException For excessive quantities.
	 */
	public function receive( int $pack_id, int $pack_count, string $reference, int $actor_id, string $idempotency_key, int $total_cost_minor = 0, string $currency = '' ): MovementResult {
		$pack = $this->suppliers->pack( $pack_id );
		if ( null === $pack || $pack_count < 1 || $pack_count > 1000000 || '' === $idempotency_key ) {
			throw new InvalidArgumentException( 'The supplier receipt is invalid.' ); }
		$pack_quantity = (int) $pack['quantity_base'];
		if ( $pack_quantity > intdiv( PHP_INT_MAX, $pack_count ) ) {
			throw new OverflowException( 'The supplier receipt is too large.' ); }
		$quantity     = $pack_quantity * $pack_count;
		$current_cost = $this->costs->pool_cost( (int) $pack['pool_id'] );
		if ( $total_cost_minor > 0 && null !== $current_cost && strtoupper( $currency ) !== $current_cost['currency'] ) {
			throw new InvalidArgumentException( 'Receipt currency must match the pool cost currency.' );
		}
		$result = $this->mutations->apply(
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
		if ( $total_cost_minor > 0 ) {
			$this->costs->record_receipt( $result->movement_id(), (int) $pack['pool_id'], $quantity, $result->balance(), $total_cost_minor, $currency );
		}
		return $result;
	}

	/** Convert pending incoming stock into on-hand stock.
	 *
	 * @param int $incoming_id Incoming delivery ID.
	 * @param int $actor_id Actor ID.
	 * @return MovementResult
	 * @throws InvalidArgumentException When delivery is no longer pending.
	 */
	public function receive_incoming( int $incoming_id, int $actor_id ): MovementResult {
		$incoming = $this->suppliers->incoming( $incoming_id );
		if ( null === $incoming ) {
			throw new InvalidArgumentException( 'The incoming delivery is not pending.' );
		}
		$result = $this->receive( (int) $incoming['pack_id'], (int) $incoming['pack_count'], (string) $incoming['reference'], $actor_id, 'incoming:' . $incoming_id );
		$this->suppliers->mark_incoming_received( $incoming_id, $result->movement_id() );
		return $result;
	}
}
