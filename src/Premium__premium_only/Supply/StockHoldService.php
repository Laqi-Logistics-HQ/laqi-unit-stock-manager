<?php
/**
 * Premium stock hold operations.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Supply;

defined( 'ABSPATH' ) || exit;
// phpcs:disable Generic.Commenting.DocComment.MissingShort, Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag
use LaqiUnitStockManager\Inventory\StockMutationService;
use LaqiUnitStockManager\Storage\PoolRepository;
/** Coordinates supply holds with authoritative stock writes. */
final class StockHoldService {
	/** @var StockHoldRepository */ private $holds;
	/** @var PoolRepository */ private $pools;
	/** @var StockMutationService */ private $mutations;
	/** Constructor. */ public function __construct( StockHoldRepository $holds, PoolRepository $pools, StockMutationService $mutations ) {
		$this->holds     = $holds;
		$this->pools     = $pools;
		$this->mutations = $mutations; }
	/** Place hold after availability check. */ public function place( int $pool_id, string $state, int $quantity, string $reason, int $actor_id ): int {
		$pool      = $this->pools->find( $pool_id );
		$available = null === $pool ? 0 : (int) apply_filters( 'laqi_lusm_pool_available_quantity', $pool->quantity()->amount(), $pool_id );
		if ( null === $pool || $quantity > $available ) {
			throw new \InvalidArgumentException( 'Insufficient available stock for this hold.' );
		} return $this->holds->place( $pool_id, $state, $quantity, $reason, $actor_id ); }
	/** Release hold. */ public function release( int $hold_id ): void {
		$this->holds->finish( $hold_id, 'released' ); }
	/** Write held stock off through core mutation path. */ public function write_off( int $hold_id, int $actor_id ): void {
		$hold = $this->holds->find( $hold_id );
		if ( null === $hold ) {
			return;
		} $this->mutations->apply(
			(int) $hold['pool_id'],
			- (int) $hold['quantity_base'],
			'damage',
			'hold-writeoff:' . $hold_id,
			array(
				'source_type' => 'stock_hold',
				'source_id'   => $hold_id,
				'actor_id'    => $actor_id,
				'reason'      => (string) $hold['reason'],
			)
		);
		$this->holds->finish( $hold_id, 'written_off' ); }
	/** Subtract active holds. */ public function available_quantity( int $on_hand, int $pool_id ): int {
		return max( 0, $on_hand - $this->holds->held_quantity( $pool_id ) );}
	/** Register availability. */ public function register(): void {
		add_filter( 'laqi_lusm_pool_available_quantity', array( $this, 'available_quantity' ), 20, 2 );}
}
