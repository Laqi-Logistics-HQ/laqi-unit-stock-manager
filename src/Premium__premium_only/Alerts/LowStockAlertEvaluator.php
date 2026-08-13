<?php
/**
 * Paid low-stock threshold evaluator.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Alerts;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Presentation\QuantityFormatter;
use LaqiUnitStockManager\Storage\PoolRepository;

/** Sends one alert when a pool crosses low, then rearms after recovery. */
final class LowStockAlertEvaluator {
	/** Alert policies.
	 *
	 * @var LowStockPolicyRepository
	 */
	private $policies;
	/** Pool reads.
	 *
	 * @var PoolRepository
	 */
	private $pools;
	/** Quantity display.
	 *
	 * @var QuantityFormatter
	 */
	private $formatter;

	/** Constructor.
	 *
	 * @param LowStockPolicyRepository $policies  Policies.
	 * @param PoolRepository           $pools     Pools.
	 * @param QuantityFormatter        $formatter Formatter.
	 */
	public function __construct( LowStockPolicyRepository $policies, PoolRepository $pools, QuantityFormatter $formatter ) {
		$this->policies  = $policies;
		$this->pools     = $pools;
		$this->formatter = $formatter;
	}

	/** Register mutation evaluation. @return void */
	public function register(): void {
		add_action( 'laqi_lusm_stock_mutated', array( $this, 'evaluate' ), 20, 1 );
	}

	/** Evaluate changed pools.
	 *
	 * @param array<int,int> $pool_ids Pool IDs.
	 * @return void
	 */
	public function evaluate( array $pool_ids ): void {
		foreach ( array_unique( array_map( 'absint', $pool_ids ) ) as $pool_id ) {
			$policy = $this->policies->find( $pool_id );
			$pool   = $this->pools->find( $pool_id );
			if ( null === $policy || null === $pool ) {
				continue;
			}
			$is_low  = $pool->quantity()->amount() <= (int) $policy['threshold_base'];
			$was_low = ! empty( $policy['is_low'] );
			if ( $is_low && ! $was_low ) {
				$recipients = array_filter( (array) $policy['recipients'], 'is_email' );
				if ( array() !== $recipients ) {
					/* translators: %s: inventory pool name. */
					$subject = sprintf( __( 'Low stock: %s', 'laqi-unit-stock-manager' ), $pool->name() );
					$message = sprintf( /* translators: 1: pool name, 2: current balance. */ __( '%1$s has reached a low-stock balance of %2$s.', 'laqi-unit-stock-manager' ), $pool->name(), $this->formatter->format( $pool->quantity(), $pool->display_unit() ) );
					wp_mail( $recipients, $subject, $message );
				}
			}
			if ( $is_low !== $was_low ) {
				$this->policies->set_low_state( $pool_id, $is_low );
				do_action( 'laqi_lusm_pool_threshold_crossed', $pool_id, $is_low, $policy );
			}
		}
	}
}
