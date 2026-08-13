<?php
/**
 * Safety stock availability policy.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Supply;

defined( 'ABSPATH' ) || exit;

// Compact policy methods remain explicit through types and names.
// phpcs:disable Generic.Commenting.DocComment.MissingShort, Squiz.Commenting.FunctionComment
/** Protects a merchant-defined buffer from storefront availability. */
final class SafetyStockAvailability {
	/** @var SafetyStockPolicyRepository */ private $policies;
	/** Constructor. */ public function __construct( SafetyStockPolicyRepository $policies ) {
		$this->policies = $policies;}
	/** Register policy. */ public function register(): void {
		add_filter( 'laqi_lusm_pool_available_quantity', array( $this, 'available_quantity' ), 30, 2 );}
	/** Subtract protected buffer after reservations and holds. */ public function available_quantity( int $available, int $pool_id ): int {
		return max( 0, $available - $this->policies->quantity( $pool_id ) );}
}
