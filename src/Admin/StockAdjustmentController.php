<?php
/**
 * Manual stock adjustment request controller.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Admin;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Inventory\StockAdjustmentService;
use Throwable;

/**
 * Validates admin requests and routes every balance change through mutations.
 */
final class StockAdjustmentController {

	/**
	 * Shared adjustment service.
	 *
	 * @var StockAdjustmentService
	 */
	private $adjustments;

	/**
	 * Constructor.
	 *
	 * @param StockAdjustmentService $adjustments Shared adjustment service.
	 */
	public function __construct( StockAdjustmentService $adjustments ) {
		$this->adjustments = $adjustments;
	}

	/** Register the adjustment endpoint. @return void */
	public function register(): void {
		add_action( 'admin_post_laqi_lusm_adjust_stock', array( $this, 'handle' ) );
	}

	/**
	 * Validate and apply an adjustment request.
	 *
	 * @return void
	 * @throws \InvalidArgumentException When submitted stock data is invalid.
	 */
	public function handle(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to adjust unit stock.', 'laqi-unit-stock-manager' ) );
		}

		$pool_id = isset( $_POST['pool_id'] ) ? absint( $_POST['pool_id'] ) : 0;
		check_admin_referer( 'laqi_lusm_adjust_stock_' . $pool_id );

		try {
			$mode   = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
			$unit   = isset( $_POST['unit'] ) ? sanitize_key( wp_unslash( $_POST['unit'] ) ) : '';
			$raw    = isset( $_POST['quantity'] ) ? sanitize_text_field( wp_unslash( $_POST['quantity'] ) ) : '';
			$reason = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';
			$this->adjustments->adjust( $pool_id, $mode, $raw, $unit, $reason, get_current_user_id(), 'admin:' . get_current_user_id() . ':' . wp_generate_uuid4() );

			$this->redirect( 'updated' );
		} catch ( Throwable $error ) {
			unset( $error );
			$this->redirect( 'error' );
		}
	}

	/**
	 * Return to the stock screen with a result notice.
	 *
	 * @param string $result Result code.
	 * @return void
	 */
	private function redirect( string $result ): void {
		$url = add_query_arg(
			array(
				'page'             => UnitStockPage::SLUG,
				'laqi_lusm_result' => $result,
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}
}
