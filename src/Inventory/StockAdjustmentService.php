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

	/**
	 * Record an absolute mobile stock count through the authoritative mutation path.
	 *
	 * @param int    $pool_id         Pool ID.
	 * @param string $value           Counted decimal quantity.
	 * @param string $unit            Pool display unit.
	 * @param string $reason          Audit reason.
	 * @param int    $actor_id        WordPress user ID.
	 * @param string $idempotency_key Stable request key.
	 * @return MovementResult
	 * @throws InvalidArgumentException When stocktake input is invalid.
	 */
	public function stocktake( int $pool_id, string $value, string $unit, string $reason, int $actor_id, string $idempotency_key ): MovementResult {
		$pool = $this->pools->find( $pool_id );
		if ( null === $pool ) {
			throw new InvalidArgumentException( 'Unknown inventory pool.' );
		}
		$quantity = $this->units->normalize( $value, $unit );
		if ( $quantity->family() !== $pool->quantity()->family() || $unit !== $pool->display_unit() ) {
			throw new InvalidArgumentException( 'The stocktake unit does not match the inventory pool.' );
		}
		if ( '' === trim( $reason ) ) {
			throw new InvalidArgumentException( 'A stocktake reason is required.' );
		}

		return $this->mutations->set_balance(
			$pool_id,
			$quantity->amount(),
			'manual_set',
			$idempotency_key,
			array(
				'source_type' => 'mobile_stocktake',
				'actor_id'    => $actor_id,
				'reason'      => $reason,
			)
		);
	}

	/** Apply a typed relative change for a registered operational module.
	 *
	 * @param int    $pool_id         Pool ID.
	 * @param int    $direction       Exactly 1 or -1.
	 * @param string $value           Decimal quantity.
	 * @param string $unit            Registered unit key.
	 * @param string $movement_type   Stable movement type key.
	 * @param string $source_type     Stable source type key.
	 * @param string $reason          Audit reason.
	 * @param int    $actor_id        WordPress user ID.
	 * @param string $idempotency_key Stable request key.
	 * @return MovementResult
	 * @throws InvalidArgumentException When input is invalid.
	 */
	public function change( int $pool_id, int $direction, string $value, string $unit, string $movement_type, string $source_type, string $reason, int $actor_id, string $idempotency_key ): MovementResult {
		if ( ! in_array( $direction, array( -1, 1 ), true ) || ! preg_match( '/^[a-z][a-z0-9_]*$/', $movement_type ) || ! preg_match( '/^[a-z][a-z0-9_]*$/', $source_type ) ) {
			throw new InvalidArgumentException( 'Invalid typed stock change.' );
		}
		$pool = $this->pools->find( $pool_id );
		if ( null === $pool ) {
			throw new InvalidArgumentException( 'Unknown inventory pool.' );
		}
		$quantity = $this->units->normalize( $value, $unit );
		if ( $quantity->family() !== $pool->quantity()->family() || $unit !== $pool->display_unit() ) {
			throw new InvalidArgumentException( 'The adjustment unit does not match the inventory pool.' );
		}
		return $this->mutations->apply(
			$pool_id,
			$direction * $quantity->amount(),
			$movement_type,
			$idempotency_key,
			array(
				'source_type' => $source_type,
				'actor_id'    => $actor_id,
				'reason'      => $reason,
			)
		);
	}
}
