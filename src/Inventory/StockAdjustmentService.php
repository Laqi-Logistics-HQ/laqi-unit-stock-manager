<?php
/**
 * Shared manual stock adjustment application service.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Inventory;

defined( 'ABSPATH' ) || exit;

use InvalidArgumentException;
use LaqiUnitStockManager\Storage\PoolRepository;
use LaqiUnitStockManager\Unit\UnitRegistry;

/**
 * Validates admin, REST, CLI, and future mobile adjustments identically.
 */
final class StockAdjustmentService {

	/**
	 * Pool persistence.
	 *
	 * @var PoolRepository
	 */
	private $pools;

	/**
	 * Unit definitions.
	 *
	 * @var UnitRegistry
	 */
	private $units;

	/**
	 * Authoritative mutation path.
	 *
	 * @var StockMutationService
	 */
	private $mutations;

	/**
	 * Constructor.
	 *
	 * @param PoolRepository       $pools     Pool persistence.
	 * @param UnitRegistry         $units     Unit definitions.
	 * @param StockMutationService $mutations Authoritative mutation path.
	 */
	public function __construct( PoolRepository $pools, UnitRegistry $units, StockMutationService $mutations ) {
		$this->pools     = $pools;
		$this->units     = $units;
		$this->mutations = $mutations;
	}

	/**
	 * Apply an exact adjustment.
	 *
	 * @param int    $pool_id         Pool ID.
	 * @param string $mode            Set, add, or subtract.
	 * @param string $value           Decimal quantity.
	 * @param string $unit            Registered unit key.
	 * @param string $reason          Optional audit reason.
	 * @param int    $actor_id        WordPress user ID.
	 * @param string $idempotency_key Stable request key.
	 * @return MovementResult
	 * @throws InvalidArgumentException When adjustment input is invalid.
	 */
	public function adjust( int $pool_id, string $mode, string $value, string $unit, string $reason, int $actor_id, string $idempotency_key ): MovementResult {
		$pool = $this->pools->find( $pool_id );
		if ( null === $pool ) {
			throw new InvalidArgumentException( 'Unknown inventory pool.' );
		}
		$quantity = $this->units->normalize( $value, $unit );
		if ( $quantity->family() !== $pool->quantity()->family() || $unit !== $pool->display_unit() ) {
			throw new InvalidArgumentException( 'The adjustment unit does not match the inventory pool.' );
		}

		$context = array(
			'source_type' => 'manual',
			'actor_id'    => $actor_id,
			'reason'      => $reason,
		);
		if ( 'set' === $mode ) {
			return $this->mutations->set_balance( $pool_id, $quantity->amount(), 'manual_set', $idempotency_key, $context );
		}
		if ( 'add' === $mode || 'subtract' === $mode ) {
			$direction = 'add' === $mode ? 1 : -1;
			return $this->mutations->apply( $pool_id, $direction * $quantity->amount(), 'manual_' . $mode, $idempotency_key, $context );
		}
		throw new InvalidArgumentException( 'Unknown stock adjustment type.' );
	}
}
