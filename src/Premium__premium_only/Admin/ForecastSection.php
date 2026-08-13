<?php
/**
 * Paid stock forecast screen.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Admin;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Admin\PaginationRenderer;
use LaqiUnitStockManager\Admin\ScreenSectionInterface;
use LaqiUnitStockManager\Admin\UnitStockPage;
use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Premium\Forecasting\ForecastPolicyRepository;
use LaqiUnitStockManager\Premium\Forecasting\StockForecastService;
use LaqiUnitStockManager\Presentation\QuantityFormatter;
use LaqiUnitStockManager\Storage\PoolRepository;

/** Shows explainable per-pool sales-demand forecasts. */
final class ForecastSection implements ScreenSectionInterface {
	const PER_PAGE = 25;
	/** Pool reads.
	 *
	 * @var PoolRepository
	 */ private $pools;
	/** Policies.
	 *
	 * @var ForecastPolicyRepository
	 */ private $policies;
	/** Forecasts.
	 *
	 * @var StockForecastService
	 */ private $forecasts;
	/** Quantity display.
	 *
	 * @var QuantityFormatter
	 */ private $formatter;
	/** Pagination.
	 *
	 * @var PaginationRenderer
	 */ private $pagination;
	/** Constructor.
	 *
	 * @param PoolRepository           $pools Pools.
	 * @param ForecastPolicyRepository $policies Policies.
	 * @param StockForecastService     $forecasts Forecasts.
	 * @param QuantityFormatter        $formatter Formatter.
	 * @param PaginationRenderer       $pagination Pagination.
	 */
	public function __construct( PoolRepository $pools, ForecastPolicyRepository $policies, StockForecastService $forecasts, QuantityFormatter $formatter, PaginationRenderer $pagination ) {
		$this->pools      = $pools;
		$this->policies   = $policies;
		$this->forecasts  = $forecasts;
		$this->formatter  = $formatter;
		$this->pagination = $pagination; }
	/** ID. @return string */ public function id(): string {
		return 'forecast'; }
	/** Title. @return string */ public function title(): string {
		return __( 'Forecast', 'laqi-unit-stock-manager' ); }
	/** Render. @return void */
	public function render(): void {
		$search      = isset( $_GET['forecast_search'] ) ? sanitize_text_field( wp_unslash( $_GET['forecast_search'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$total       = $this->pools->count_search( $search );
		$pages       = max( 1, intdiv( $total + self::PER_PAGE - 1, self::PER_PAGE ) );
		$page        = isset( $_GET['forecast_page'] ) ? max( 1, absint( $_GET['forecast_page'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page        = min( $page, $pages );
		$offset      = ( $page - 1 ) * self::PER_PAGE;
		$selected_id = isset( $_GET['pool_id'] ) ? absint( $_GET['pool_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$selected    = $selected_id > 0 ? $this->pools->find( $selected_id ) : null;
		$this->notice(); ?>
		<section class="card"><h2><?php esc_html_e( 'Demand window', 'laqi-unit-stock-manager' ); ?></h2><p><?php esc_html_e( 'Forecasts use completed sales consumption only. Restocks, restores, manual adjustments, and physical losses are excluded.', 'laqi-unit-stock-manager' ); ?></p><form method="get"><input type="hidden" name="page" value="<?php echo esc_attr( UnitStockPage::SLUG ); ?>" /><input type="hidden" name="section" value="forecast" /><label for="laqi-lusm-forecast-pool"><?php esc_html_e( 'Inventory pool', 'laqi-unit-stock-manager' ); ?></label><select id="laqi-lusm-forecast-pool" name="pool_id" class="laqi-lusm-pool-search" data-placeholder="<?php esc_attr_e( 'Search inventory pools', 'laqi-unit-stock-manager' ); ?>" required>
		<?php
		if ( null !== $selected ) :
			?>
			<option value="<?php echo esc_attr( (string) $selected->id() ); ?>" selected><?php echo esc_html( $selected->name() ); ?></option><?php endif; ?></select><?php submit_button( __( 'Choose pool', 'laqi-unit-stock-manager' ), 'secondary', '', false ); ?></form>
			<?php
			if ( null !== $selected ) :
				?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="laqi_lusm_save_forecast" /><input type="hidden" name="pool_id" value="<?php echo esc_attr( (string) $selected->id() ); ?>" /><?php wp_nonce_field( 'laqi_lusm_save_forecast_' . $selected->id() ); ?><label for="laqi-lusm-forecast-window"><?php esc_html_e( 'Demand window (days)', 'laqi-unit-stock-manager' ); ?></label><input type="number" min="7" max="365" id="laqi-lusm-forecast-window" name="window_days" value="<?php echo esc_attr( (string) $this->policies->window( $selected->id() ) ); ?>" required /><?php submit_button( __( 'Save forecast window', 'laqi-unit-stock-manager' ) ); ?></form><?php endif; ?></section>
		<form method="get" class="laqi-lusm-search"><input type="hidden" name="page" value="<?php echo esc_attr( UnitStockPage::SLUG ); ?>" /><input type="hidden" name="section" value="forecast" /><label class="screen-reader-text" for="laqi-lusm-forecast-search"><?php esc_html_e( 'Search forecasts', 'laqi-unit-stock-manager' ); ?></label><input type="search" id="laqi-lusm-forecast-search" name="forecast_search" value="<?php echo esc_attr( $search ); ?>" /><?php submit_button( __( 'Search', 'laqi-unit-stock-manager' ), 'secondary', '', false ); ?></form><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Inventory pool', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Demand window', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Average daily use', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Days remaining', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Estimated stock-out', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Confidence', 'laqi-unit-stock-manager' ); ?></th></tr></thead><tbody>
		<?php
		foreach ( $this->pools->search( $search, self::PER_PAGE, $offset ) as $pool ) :
			$window   = $this->policies->window( $pool->id() );
			$forecast = $this->forecasts->forecast( $pool, $window );
			?>
			<tr><td><a href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'page'    => UnitStockPage::SLUG,
						'section' => 'forecast',
						'pool_id' => $pool->id(),
					),
					admin_url( 'admin.php' )
				)
			);
			?>
"><?php echo esc_html( $pool->name() ); ?></a></td><td><?php echo esc_html( sprintf( /* translators: %d: days. */ __( '%d days', 'laqi-unit-stock-manager' ), $window ) ); ?></td><?php $this->render_forecast_cells( $pool, $forecast ); ?></tr><?php endforeach; ?></tbody></table>
		<?php
		if ( $total > 0 ) {
			$this->pagination->render(
				sprintf( /* translators: 1: first, 2: last, 3: total. */ __( 'Showing %1$d-%2$d of %3$d forecasts.', 'laqi-unit-stock-manager' ), $offset + 1, min( $offset + self::PER_PAGE, $total ), $total ),
				__( 'Forecast pages', 'laqi-unit-stock-manager' ),
				'forecast_page',
				array(
					'page'            => UnitStockPage::SLUG,
					'section'         => 'forecast',
					'forecast_search' => $search,
				),
				$page,
				$pages
			); }
	}
	/** Render state-aware cells.
	 *
	 * @param \LaqiUnitStockManager\Domain\Pool $pool Pool.
	 * @param array<string,mixed>               $forecast Forecast.
	 * @return void
	 */
	private function render_forecast_cells( \LaqiUnitStockManager\Domain\Pool $pool, array $forecast ): void {
		if ( 'insufficient_data' === $forecast['state'] ) {
			echo '<td colspan="4">' . esc_html__( 'Insufficient data: at least 7 observed days and 3 sales days are required.', 'laqi-unit-stock-manager' ) . '</td>';
			return;
		} if ( 'no_demand' === $forecast['state'] ) {
			echo '<td colspan="4">' . esc_html__( 'No sales demand in the observed window.', 'laqi-unit-stock-manager' ) . '</td>';
			return; }
		?>
	<td><?php echo esc_html( $this->formatter->format( new Quantity( $pool->quantity()->family(), (int) round( $forecast['daily_average_base'] ) ), $pool->display_unit() ) ); ?></td><td><?php echo esc_html( number_format_i18n( $forecast['days_cover'], 1 ) ); ?></td><td><?php echo esc_html( wp_date( get_option( 'date_format' ), $forecast['stockout_at'] ) ); ?></td><td><?php echo esc_html( ucfirst( $forecast['confidence'] ) ); ?></td>
		<?php
	}
	/** Notice. @return void */ private function notice(): void {
		$result = isset( $_GET['laqi_lusm_forecast_result'] ) ? sanitize_key( wp_unslash( $_GET['laqi_lusm_forecast_result'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'forecast_saved' === $result ) {
			wp_admin_notice(
				__( 'Forecast window saved.', 'laqi-unit-stock-manager' ),
				array(
					'type'        => 'success',
					'dismissible' => true,
				)
			);
		} elseif ( 'forecast_error' === $result ) {
			wp_admin_notice( __( 'The forecast settings could not be saved.', 'laqi-unit-stock-manager' ), array( 'type' => 'error' ) ); } }
}
