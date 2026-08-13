<?php
/**
 * Scheduled batch expiry alerts.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Batches;

defined( 'ABSPATH' ) || exit;

use InvalidArgumentException;
use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Premium\Alerts\AlertChannelRegistry;
use LaqiUnitStockManager\Premium\Alerts\AlertDeliveryRepository;
use LaqiUnitStockManager\Presentation\QuantityFormatter;

/** Sends one notification per batch when it becomes near-expiry or expired. */
final class BatchExpiryEvaluator {

	const CRON_HOOK = 'laqi_lusm_evaluate_batch_expiry';

	/** Batch storage.
	 *
	 * @var BatchRepository
	 */
	private $batches;

	/** Expiry policy.
	 *
	 * @var BatchExpirySettings
	 */
	private $settings;

	/** Quantity formatter.
	 *
	 * @var QuantityFormatter
	 */
	private $formatter;

	/** Alert channels.
	 *
	 * @var AlertChannelRegistry
	 */
	private $channels;

	/** Delivery history.
	 *
	 * @var AlertDeliveryRepository
	 */
	private $deliveries;

	/**
	 * Constructor.
	 *
	 * @param BatchRepository         $batches Batch storage.
	 * @param BatchExpirySettings     $settings Expiry policy.
	 * @param QuantityFormatter       $formatter Quantity formatter.
	 * @param AlertChannelRegistry    $channels Alert channels.
	 * @param AlertDeliveryRepository $deliveries Delivery history.
	 */
	public function __construct( BatchRepository $batches, BatchExpirySettings $settings, QuantityFormatter $formatter, AlertChannelRegistry $channels, AlertDeliveryRepository $deliveries ) {
		$this->batches    = $batches;
		$this->settings   = $settings;
		$this->formatter  = $formatter;
		$this->channels   = $channels;
		$this->deliveries = $deliveries;
	}

	/** Register daily evaluation. */
	public function register(): void {
		add_action( 'init', array( $this, 'schedule' ) );
		add_action( self::CRON_HOOK, array( $this, 'evaluate' ) );
	}

	/** Schedule the daily evaluation. */
	public function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/** Remove scheduled evaluations. */
	public function unschedule(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/** Evaluate batches and deliver new alerts. */
	public function evaluate(): void {
		$policy = $this->settings->get();
		$today  = current_time( 'Y-m-d' );

		foreach ( $this->batches->expiring( $policy['warning_days'] ) as $batch ) {
			$expired  = $batch['expiry_date'] < $today;
			$state    = $expired ? 'expired' : 'near_expiry';
			$key      = hash( 'sha256', 'batch-expiry|' . $batch['id'] . '|' . $state );
			$identity = '' !== $batch['supplier_lot'] ? $batch['supplier_lot'] : '#' . $batch['id'];

			if ( $expired ) {
				/* translators: %s: supplier lot or batch ID. */
				$subject = sprintf( __( 'Expired batch: %s', 'laqi-unit-stock-manager' ), $identity );
			} else {
				/* translators: %s: supplier lot or batch ID. */
				$subject = sprintf( __( 'Batch nearing expiry: %s', 'laqi-unit-stock-manager' ), $identity );
			}

			try {
				$quantity = $this->formatter->format(
					new Quantity( $batch['family'], (int) $batch['quantity_available_base'] ),
					$batch['display_unit']
				);
			} catch ( InvalidArgumentException $exception ) {
				/* translators: %d: normalized base-unit quantity. */
				$quantity = sprintf( __( '%d base units', 'laqi-unit-stock-manager' ), (int) $batch['quantity_available_base'] );
			}
			$event = array(
				'event_id'    => $key,
				'event'       => 'batch.expiry',
				'pool_id'     => (int) $batch['pool_id'],
				'batch_id'    => (int) $batch['id'],
				'severity'    => $expired ? 'critical' : 'warning',
				'subject'     => $subject,
				/* translators: 1: batch, 2: pool, 3: quantity, 4: expiry date. */
				'message'     => sprintf( __( 'Batch %1$s in %2$s has %3$s remaining and expires on %4$s.', 'laqi-unit-stock-manager' ), $identity, $batch['pool_name'], $quantity, $batch['expiry_date'] ),
				'occurred_at' => gmdate( 'c' ),
			);

			foreach ( $this->channels->enabled( $policy ) as $channel ) {
				if ( $this->deliveries->succeeded( $key, $channel->key() ) ) {
					continue;
				}

				$result = $channel->deliver( $event, $policy );
				$this->deliveries->record( (int) $batch['pool_id'], $key, $channel->key(), $result['success'], $result['message'] );
			}
		}
	}
}
