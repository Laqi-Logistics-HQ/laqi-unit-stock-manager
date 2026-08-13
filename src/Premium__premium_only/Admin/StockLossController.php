<?php
/**
 * Paid stock-loss request controller.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Admin;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Admin\UnitStockPage;
use LaqiUnitStockManager\Inventory\StockAdjustmentService;
use LaqiUnitStockManager\Premium\Inventory\StockLossTypeCatalog;
use Throwable;

/** Records typed physical losses through the authoritative mutation service. */
final class StockLossController {
	/**
	 * Shared adjustment service.
	 *
	 * @var StockAdjustmentService
	 */
	private $adjustments;
	/**
	 * Registered loss types.
	 *
	 * @var StockLossTypeCatalog
	 */
	private $types;

	/** Constructor.
	 *
	 * @param StockAdjustmentService $adjustments Adjustments.
	 * @param StockLossTypeCatalog   $types       Loss types.
	 */
	public function __construct( StockAdjustmentService $adjustments, StockLossTypeCatalog $types ) {
		$this->adjustments = $adjustments;
		$this->types       = $types;
	}

	/** Register the authenticated endpoint. @return void */
	public function register(): void {
		add_action( 'admin_post_laqi_lusm_record_loss', array( $this, 'handle' ) );
	}

	/** Validate and record a loss.
	 *
	 * @return void
	 * @throws \InvalidArgumentException When the submitted type is unknown.
	 */
	public function handle(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to record stock losses.', 'laqi-unit-stock-manager' ) );
		}
		$pool_id = isset( $_POST['pool_id'] ) ? absint( $_POST['pool_id'] ) : 0;
		check_admin_referer( 'laqi_lusm_record_loss_' . $pool_id );
		try {
			$type = isset( $_POST['loss_type'] ) ? sanitize_key( wp_unslash( $_POST['loss_type'] ) ) : '';
			if ( ! $this->types->has( $type ) ) {
				throw new \InvalidArgumentException( 'Unknown loss type.' );
			}
			$value  = isset( $_POST['quantity'] ) ? sanitize_text_field( wp_unslash( $_POST['quantity'] ) ) : '';
			$unit   = isset( $_POST['unit'] ) ? sanitize_key( wp_unslash( $_POST['unit'] ) ) : '';
			$reason = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';
			$this->adjustments->change( $pool_id, -1, $value, $unit, $type, 'loss', $reason, get_current_user_id(), 'loss:' . get_current_user_id() . ':' . wp_generate_uuid4() );
			$this->redirect( $pool_id, 'loss_recorded' );
		} catch ( Throwable $error ) {
			unset( $error );
			$this->redirect( $pool_id, 'loss_error' );
		}
	}

	/** Redirect to the loss screen.
	 *
	 * @param int    $pool_id Pool ID.
	 * @param string $result  Result.
	 * @return void
	 */
	private function redirect( int $pool_id, string $result ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                  => UnitStockPage::SLUG,
					'section'               => 'losses',
					'pool_id'               => $pool_id,
					'laqi_lusm_loss_result' => $result,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
