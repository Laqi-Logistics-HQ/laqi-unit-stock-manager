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

/** Delivers severity crossings and scheduled reminders outside quiet hours. */
final class LowStockAlertEvaluator {
	const CRON_HOOK = 'laqi_lusm_evaluate_stock_alerts';
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

	/** Register mutation and scheduled evaluation. @return void */
	public function register(): void {
		add_action( 'laqi_lusm_stock_mutated', array( $this, 'evaluate' ), 20, 1 );
		add_action( self::CRON_HOOK, array( $this, 'evaluate_scheduled' ) );
		add_action( 'init', array( $this, 'schedule' ) );
	}

	/** Ensure hourly scheduled evaluation exists. @return void */
	public function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::CRON_HOOK );
		}
	}

	/** Remove scheduled evaluation. @return void */
	public function unschedule(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/** Evaluate all configured policies for reminders. @return void */
	public function evaluate_scheduled(): void {
		$this->evaluate( $this->policies->configured_ids() );
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
			$severity        = $this->severity( $pool->quantity()->amount(), $policy );
			$previous        = isset( $policy['severity'] ) ? (string) $policy['severity'] : ( ! empty( $policy['is_low'] ) ? 'warning' : 'healthy' );
			$last_sent       = isset( $policy['last_sent_at'] ) ? (int) $policy['last_sent_at'] : 0;
			$reminder_hours  = isset( $policy['reminder_hours'] ) ? absint( $policy['reminder_hours'] ) : 0;
			$is_reminder_due = 'healthy' !== $severity && $reminder_hours > 0 && time() >= $last_sent + ( $reminder_hours * HOUR_IN_SECONDS );
			$should_send     = 'healthy' !== $severity && ( $severity !== $previous || 0 === $last_sent || $is_reminder_due );
			if ( $should_send && ! $this->is_quiet_time( $policy ) ) {
				$recipients = array_filter( (array) $policy['recipients'], 'is_email' );
				if ( array() !== $recipients ) {
					/* translators: 1: severity, 2: inventory pool name. */
					$subject = sprintf( __( '%1$s stock: %2$s', 'laqi-unit-stock-manager' ), ucfirst( $severity ), $pool->name() );
					$message = sprintf( /* translators: 1: pool name, 2: severity, 3: current balance. */ __( '%1$s is at %2$s stock with a current balance of %3$s.', 'laqi-unit-stock-manager' ), $pool->name(), $severity, $this->formatter->format( $pool->quantity(), $pool->display_unit() ) );
					if ( wp_mail( $recipients, $subject, $message ) ) {
						$last_sent = time();
					}
				}
			}
			$this->policies->set_evaluation_state( $pool_id, $severity, $last_sent );
			if ( $severity !== $previous ) {
				do_action( 'laqi_lusm_pool_threshold_crossed', $pool_id, $severity, $policy );
			}
		}
	}

	/** Resolve current severity.
	 *
	 * @param int                  $balance Balance.
	 * @param array<string, mixed> $policy  Policy.
	 * @return string
	 */
	private function severity( int $balance, array $policy ): string {
		$warning  = (int) $policy['threshold_base'];
		$critical = isset( $policy['critical_base'] ) ? (int) $policy['critical_base'] : 0;
		if ( $balance <= $critical ) {
			return 'critical';
		}
		return $balance <= $warning ? 'warning' : 'healthy';
	}

	/** Whether the site-local hour is inside the configured quiet window.
	 *
	 * @param array<string, mixed> $policy Policy.
	 * @return bool
	 */
	private function is_quiet_time( array $policy ): bool {
		$start = isset( $policy['quiet_start'] ) ? (int) $policy['quiet_start'] : -1;
		$end   = isset( $policy['quiet_end'] ) ? (int) $policy['quiet_end'] : -1;
		if ( $start < 0 || $end < 0 || $start === $end ) {
			return false;
		}
		$hour = (int) wp_date( 'G' );
		return $start < $end ? $hour >= $start && $hour < $end : $hour >= $start || $hour < $end;
	}
}
