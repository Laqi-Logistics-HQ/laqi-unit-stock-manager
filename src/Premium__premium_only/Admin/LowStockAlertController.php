<?php
/**
 * Paid low-stock alert settings controller.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Admin;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Admin\UnitStockPage;
use LaqiUnitStockManager\Premium\Alerts\LowStockPolicyRepository;
use LaqiUnitStockManager\Storage\PoolRepository;
use LaqiUnitStockManager\Unit\UnitRegistry;
use Throwable;

/** Validates threshold policies at the admin boundary. */
final class LowStockAlertController {
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
	/** Unit conversion.
	 *
	 * @var UnitRegistry
	 */
	private $units;

	/** Constructor.
	 *
	 * @param LowStockPolicyRepository $policies Policies.
	 * @param PoolRepository           $pools    Pools.
	 * @param UnitRegistry             $units    Units.
	 */
	public function __construct( LowStockPolicyRepository $policies, PoolRepository $pools, UnitRegistry $units ) {
		$this->policies = $policies;
		$this->pools    = $pools;
		$this->units    = $units;
	}

	/** Register settings endpoint. @return void */
	public function register(): void {
		add_action( 'admin_post_laqi_lusm_save_low_stock_alert', array( $this, 'handle' ) );
	}

	/** Save a policy.
	 *
	 * @return void
	 * @throws \InvalidArgumentException When submitted settings are invalid.
	 */
	public function handle(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage stock alerts.', 'laqi-unit-stock-manager' ) );
		}
		$pool_id = isset( $_POST['pool_id'] ) ? absint( $_POST['pool_id'] ) : 0;
		check_admin_referer( 'laqi_lusm_save_low_stock_alert_' . $pool_id );
		try {
			$pool = $this->pools->find( $pool_id );
			if ( null === $pool ) {
				throw new \InvalidArgumentException( 'Unknown pool.' );
			}
			$value          = isset( $_POST['threshold'] ) ? sanitize_text_field( wp_unslash( $_POST['threshold'] ) ) : '';
			$quantity       = $this->units->normalize( $value, $pool->display_unit() );
			$critical_value = isset( $_POST['critical_threshold'] ) ? sanitize_text_field( wp_unslash( $_POST['critical_threshold'] ) ) : '0';
			$critical       = $this->units->normalize( $critical_value, $pool->display_unit() );
			$raw            = isset( $_POST['recipients'] ) ? sanitize_textarea_field( wp_unslash( $_POST['recipients'] ) ) : '';
			$emails         = preg_split( '/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY );
			$emails         = array_values( array_unique( array_filter( array_map( 'sanitize_email', is_array( $emails ) ? $emails : array() ), 'is_email' ) ) );
			$reminder_hours = isset( $_POST['reminder_hours'] ) ? absint( $_POST['reminder_hours'] ) : 0;
			$quiet_start    = isset( $_POST['quiet_start'] ) && '' !== $_POST['quiet_start'] ? min( 23, absint( $_POST['quiet_start'] ) ) : -1;
			$quiet_end      = isset( $_POST['quiet_end'] ) && '' !== $_POST['quiet_end'] ? min( 23, absint( $_POST['quiet_end'] ) ) : -1;
			if ( $quantity->amount() < 0 || $critical->amount() < 0 || $critical->amount() > $quantity->amount() || array() === $emails ) {
				throw new \InvalidArgumentException( 'A non-negative threshold and recipient are required.' );
			}
			$this->policies->save( $pool_id, $quantity->amount(), $emails, $critical->amount(), $reminder_hours, $quiet_start, $quiet_end );
			do_action( 'laqi_lusm_stock_mutated', array( $pool_id ), array() );
			$this->redirect( $pool_id, 'alert_saved' );
		} catch ( Throwable $error ) {
			unset( $error );
			$this->redirect( $pool_id, 'alert_error' );
		}
	}

	/** Redirect to Alerts.
	 *
	 * @param int    $pool_id Pool ID.
	 * @param string $result  Result.
	 * @return void
	 */
	private function redirect( int $pool_id, string $result ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                   => UnitStockPage::SLUG,
					'section'                => 'alerts',
					'pool_id'                => $pool_id,
					'laqi_lusm_alert_result' => $result,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
