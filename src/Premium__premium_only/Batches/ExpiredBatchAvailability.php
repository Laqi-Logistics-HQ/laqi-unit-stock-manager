<?php
/**
 * Expired batch availability policy.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Batches;

defined( 'ABSPATH' ) || exit;

// Compact policy methods remain explicit through types and names.
// phpcs:disable Generic.Commenting.DocComment.MissingShort, Squiz.Commenting.FunctionComment

/** Prevents expired physical stock from being offered for sale. */
final class ExpiredBatchAvailability {
	/** @var BatchRepository */ private $batches;
	/** @param BatchRepository $batches Batches. */ public function __construct( BatchRepository $batches ) {
		$this->batches = $batches; }
	/** Register after other available-to-sell policies. */ public function register(): void {
		add_filter( 'laqi_lusm_pool_available_quantity', array( $this, 'available_quantity' ), 40, 2 ); }
	/** Subtract expired active lots. */ public function available_quantity( int $available, int $pool_id ): int {
		return max( 0, $available - $this->batches->expired_quantity( $pool_id ) ); }
}
