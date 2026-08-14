<?php
/**
 * Sensitive adjustment authorization policy.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Approvals;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Storage\PoolRepository;

/** Supplies reusable reasons and restricts material balance changes. */
final class AdjustmentPolicy {
	/**
	 * Policy settings.
	 *
	 * @var AdjustmentPolicyRepository
	 */
	private $settings;
	/**
	 * Pool reads.
	 *
	 * @var PoolRepository
	 */
	private $pools;

	/**
	 * Constructor.
	 *
	 * @param AdjustmentPolicyRepository $settings Policy settings.
	 * @param PoolRepository             $pools    Pool reads.
	 */
	public function __construct( AdjustmentPolicyRepository $settings, PoolRepository $pools ) {
		$this->settings = $settings;
		$this->pools    = $pools;
	}

	/** Register shared policy filters. @return void */
	public function register(): void {
		add_filter( 'laqi_lusm_adjustment_reason_templates', array( $this, 'templates' ) );
		add_filter( 'laqi_lusm_adjustment_authorized', array( $this, 'authorize' ), 10, 6 );
	}

	/**
	 * Merge reusable reason labels.
	 *
	 * @param string[] $templates Existing labels.
	 * @return string[]
	 */
	public function templates( array $templates ): array {
		return array_values( array_unique( array_merge( $templates, $this->settings->get()['templates'] ) ) );
	}

	/**
	 * Require the configured approver capability for sensitive changes.
	 *
	 * @param bool   $authorized Whether an earlier policy authorized the change.
	 * @param int    $pool_id   Pool ID.
	 * @param int    $delta     Signed normalized change.
	 * @param string $type      Movement type.
	 * @param int    $actor_id  WordPress user ID.
	 * @param string $reason    Audit reason.
	 * @return bool
	 */
	public function authorize( bool $authorized, int $pool_id, int $delta, string $type, int $actor_id, string $reason ): bool {
		unset( $type, $reason );
		if ( ! $authorized || 0 === $delta || $actor_id < 1 ) {
			return $authorized;
		}
		$pool = $this->pools->find( $pool_id );
		if ( null === $pool ) {
			return false;
		}
		$settings = $this->settings->get();
		$ratio    = (float) $settings['sensitive_ratio'];
		if ( $ratio <= 0 ) {
			return true;
		}
		$baseline  = max( 1.0, abs( (float) $pool->quantity()->amount() ) );
		$sensitive = abs( (float) $delta ) >= $baseline * $ratio;
		return ! $sensitive || user_can( $actor_id, (string) $settings['approver_capability'] );
	}
}
