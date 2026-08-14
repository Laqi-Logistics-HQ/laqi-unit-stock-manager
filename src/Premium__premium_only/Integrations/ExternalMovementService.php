<?php
/**
 * External inventory movement import service.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Integrations;

defined( 'ABSPATH' ) || exit;

use InvalidArgumentException;
use LaqiUnitStockManager\Inventory\MovementResult;
use LaqiUnitStockManager\Inventory\StockMutationService;
use LaqiUnitStockManager\Storage\PoolRepository;
use LaqiUnitStockManager\Unit\UnitRegistry;

/** Converts an ERP/WMS event into one atomic typed movement batch. */
final class ExternalMovementService {
	const MAX_MOVEMENTS = 100;

	/** Inventory pools.
	 *
	 * @var PoolRepository */
	private $pools;

	/** Exact unit definitions.
	 *
	 * @var UnitRegistry */
	private $units;

	/** Authoritative mutation path.
	 *
	 * @var StockMutationService */
	private $mutations;

	/**
	 * Constructor.
	 *
	 * @param PoolRepository       $pools     Inventory pools.
	 * @param UnitRegistry         $units     Exact units.
	 * @param StockMutationService $mutations Authoritative mutations.
	 */
	public function __construct( PoolRepository $pools, UnitRegistry $units, StockMutationService $mutations ) {
		$this->pools     = $pools;
		$this->units     = $units;
		$this->mutations = $mutations;
	}

	/**
	 * Import one external event.
	 *
	 * @param string                           $integration Stable integration key.
	 * @param string                           $event_id    External event identifier.
	 * @param array<int, array<string, mixed>> $movements   Relative movement rows.
	 * @param int                              $actor_id    WordPress actor ID.
	 * @return MovementResult[]
	 * @throws InvalidArgumentException When the event is invalid.
	 */
	public function import( string $integration, string $event_id, array $movements, int $actor_id ): array {
		if ( ! preg_match( '/^[a-z][a-z0-9_-]{1,49}$/', $integration ) || '' === $event_id || strlen( $event_id ) > 100 ) {
			throw new InvalidArgumentException( 'External movements require a valid integration and event ID.' );
		}
		if ( array() === $movements || count( $movements ) > self::MAX_MOVEMENTS ) {
			throw new InvalidArgumentException( 'An external event requires between 1 and 100 movements.' );
		}

		$commands   = array();
		$event_hash = hash( 'sha256', $event_id );
		foreach ( array_values( $movements ) as $position => $movement ) {
			if ( ! is_array( $movement ) ) {
				throw new InvalidArgumentException( 'Every external movement must be an object.' );
			}
			$pool_sku  = trim( (string) ( $movement['pool_sku'] ?? '' ) );
			$direction = (string) ( $movement['direction'] ?? '' );
			$unit      = sanitize_key( (string) ( $movement['unit'] ?? '' ) );
			$pool      = $this->pools->find_by_external_sku( $pool_sku );
			if ( null === $pool || ! in_array( $direction, array( 'add', 'subtract' ), true ) ) {
				throw new InvalidArgumentException( 'Every external movement requires a known pool SKU and add/subtract direction.' );
			}
			$quantity = $this->units->normalize( (string) ( $movement['quantity'] ?? '' ), $unit );
			if ( $quantity->amount() < 1 || $quantity->family() !== $pool->quantity()->family() ) {
				throw new InvalidArgumentException( 'The external movement quantity does not match the inventory pool.' );
			}
			$commands[] = array(
				'pool_id'         => $pool->id(),
				'delta'           => ( 'add' === $direction ? 1 : -1 ) * $quantity->amount(),
				'type'            => 'external_' . $direction,
				'idempotency_key' => 'external:' . $integration . ':' . $event_hash . ':' . $position,
				'context'         => array(
					'source_type' => 'external',
					'actor_id'    => $actor_id,
					'reason'      => sanitize_text_field( (string) ( $movement['reason'] ?? '' ) ),
					'metadata'    => array(
						'integration' => $integration,
						'event_id'    => $event_id,
						'reference'   => sanitize_text_field( (string) ( $movement['reference'] ?? '' ) ),
						'pool_sku'    => $pool_sku,
					),
				),
			);
		}
		return $this->mutations->apply_batch( $commands );
	}
}
