<?php
/**
 * Paid forecast settings controller.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Admin;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Admin\UnitStockPage;
use LaqiUnitStockManager\Premium\Forecasting\ForecastPolicyRepository;
use LaqiUnitStockManager\Storage\PoolRepository;

/** Saves bounded pool demand windows. */
final class ForecastController {
	/** Policies.
	 *
	 * @var ForecastPolicyRepository
	 */
	private $policies;
	/** Pools.
	 *
	 * @var PoolRepository
	 */
	private $pools;
	/** Constructor.
	 *
	 * @param ForecastPolicyRepository $policies Policies.
	 * @param PoolRepository           $pools Pools.
	 */
	public function __construct( ForecastPolicyRepository $policies, PoolRepository $pools ) {
		$this->policies = $policies;
		$this->pools    = $pools; }
	/** Register endpoint. @return void */
	public function register(): void {
		add_action( 'admin_post_laqi_lusm_save_forecast', array( $this, 'handle' ) ); }
	/** Save settings. @return void */
	public function handle(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage stock forecasts.', 'laqi-unit-stock-manager' ) ); }
		$pool_id = isset( $_POST['pool_id'] ) ? absint( $_POST['pool_id'] ) : 0;
		check_admin_referer( 'laqi_lusm_save_forecast_' . $pool_id );
		if ( null === $this->pools->find( $pool_id ) ) {
			$this->redirect( 0, 'forecast_error' ); }
		$days = isset( $_POST['window_days'] ) ? absint( $_POST['window_days'] ) : 30;
		$this->policies->save_window( $pool_id, $days );
		$this->redirect( $pool_id, 'forecast_saved' );
	}
	/** Redirect.
	 *
	 * @param int    $pool_id Pool.
	 * @param string $result Result.
	 * @return void
	 */
	private function redirect( int $pool_id, string $result ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                      => UnitStockPage::SLUG,
					'section'                   => 'forecast',
					'pool_id'                   => $pool_id,
					'laqi_lusm_forecast_result' => $result,
				),
				admin_url( 'admin.php' )
			)
		);
		exit; }
}
