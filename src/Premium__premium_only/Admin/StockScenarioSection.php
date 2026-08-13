<?php
/**
 * Paid stock scenario screen.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Admin;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Admin\ScreenSectionInterface;
use LaqiUnitStockManager\Admin\UnitStockPage;
use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Premium\Planning\StockScenarioPlanner;
use LaqiUnitStockManager\Presentation\QuantityFormatter;
use LaqiUnitStockManager\Storage\PoolRepository;

/** Renders a non-mutating promotion and allocation planner. */
final class StockScenarioSection implements ScreenSectionInterface {
	/** Pools.
	 *
	 * @var PoolRepository
	 */ private $pools;
	/** Planner.
	 *
	 * @var StockScenarioPlanner
	 */ private $planner;
	/** Formatter.
	 *
	 * @var QuantityFormatter
	 */ private $formatter;
	/** Constructor.
	 *
	 * @param PoolRepository       $pools Pools.
	 * @param StockScenarioPlanner $planner Planner.
	 * @param QuantityFormatter    $formatter Formatter.
	 */
	public function __construct( PoolRepository $pools, StockScenarioPlanner $planner, QuantityFormatter $formatter ) {
		$this->pools     = $pools;
		$this->planner   = $planner;
		$this->formatter = $formatter; }
	/** ID. @return string */ public function id(): string {
		return 'planner'; }
	/** Title. @return string */ public function title(): string {
		return __( 'Planner', 'laqi-unit-stock-manager' ); }
	/** Render. @return void */
	public function render(): void {
		$pool_id = isset( $_GET['pool_id'] ) ? absint( $_GET['pool_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$pool    = $pool_id > 0 ? $this->pools->find( $pool_id ) : null;
		?>
		<section class="card"><h2><?php esc_html_e( 'Stock scenario planner', 'laqi-unit-stock-manager' ); ?></h2><p><?php esc_html_e( 'Model a promotion or sales mix without changing stock, mappings, or forecasts.', 'laqi-unit-stock-manager' ); ?></p>
		<form method="get"><input type="hidden" name="page" value="<?php echo esc_attr( UnitStockPage::SLUG ); ?>" /><input type="hidden" name="section" value="planner" /><label for="laqi-lusm-planner-pool"><?php esc_html_e( 'Inventory pool', 'laqi-unit-stock-manager' ); ?></label><select id="laqi-lusm-planner-pool" name="pool_id" class="laqi-lusm-pool-search" data-placeholder="<?php esc_attr_e( 'Search inventory pools', 'laqi-unit-stock-manager' ); ?>" required>
		<?php
		if ( null !== $pool ) :
			?>
			<option value="<?php echo esc_attr( (string) $pool->id() ); ?>" selected><?php echo esc_html( $pool->name() ); ?></option><?php endif; ?></select><?php submit_button( __( 'Choose pool', 'laqi-unit-stock-manager' ), 'secondary', '', false ); ?></form></section>
		<?php
		if ( null !== $pool ) {
			$this->render_scenario( $pool ); }
	}
	/** Render the selected pool scenario.
	 *
	 * @param \LaqiUnitStockManager\Domain\Pool $pool Pool.
	 * @return void
	 */
	private function render_scenario( \LaqiUnitStockManager\Domain\Pool $pool ): void {
		$mappings = $this->planner->mappings_for_pool( $pool->id() );
		$raw      = isset( $_GET['units'] ) && is_array( $_GET['units'] ) ? wp_unslash( $_GET['units'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each key and value is normalized with absint below.
		$units    = array();
		foreach ( $raw as $id => $value ) {
			$units[ absint( $id ) ] = min( 1000000, absint( $value ) ); }
		$uplift = isset( $_GET['uplift'] ) ? min( 1000, absint( $_GET['uplift'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$result = isset( $_GET['calculate'] ) ? $this->planner->calculate( $pool->id(), $units, $uplift ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<section class="card"><h2><?php echo esc_html( sprintf( /* translators: %s: pool name. */ __( 'Scenario for %s', 'laqi-unit-stock-manager' ), $pool->name() ) ); ?></h2><p><?php echo esc_html( sprintf( /* translators: %s: formatted stock. */ __( 'Current on hand: %s', 'laqi-unit-stock-manager' ), $this->formatter->format( $pool->quantity(), $pool->display_unit() ) ) ); ?></p>
		<?php
		if ( array() === $mappings ) :
			?>
			<p><?php esc_html_e( 'This pool has no active product mappings to model.', 'laqi-unit-stock-manager' ); ?></p>
			<?php
else :
	?>
			<form method="get"><input type="hidden" name="page" value="<?php echo esc_attr( UnitStockPage::SLUG ); ?>" /><input type="hidden" name="section" value="planner" /><input type="hidden" name="pool_id" value="<?php echo esc_attr( (string) $pool->id() ); ?>" /><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Product or variation', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Use per sale', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Expected sales', 'laqi-unit-stock-manager' ); ?></th></tr></thead><tbody>
			<?php
			foreach ( $mappings as $mapping ) :
				$purchasable_id = $mapping->variation_id() > 0 ? $mapping->variation_id() : $mapping->product_id();
				$product        = wc_get_product( $purchasable_id );
				$consumption    = (int) $this->planner->consumption_for_pool( $mapping, $pool->id() );
				?>
	<tr><td><?php echo esc_html( $product ? $product->get_formatted_name() : '#' . $purchasable_id ); ?></td><td><?php echo esc_html( $this->formatter->format( new Quantity( $pool->quantity()->family(), $consumption ), $pool->display_unit() ) ); ?></td><td><input type="number" min="0" max="1000000" name="units[<?php echo esc_attr( (string) $mapping->id() ); ?>]" value="<?php echo esc_attr( (string) ( $units[ $mapping->id() ] ?? 0 ) ); ?>" /></td></tr><?php endforeach; ?></tbody></table><label for="laqi-lusm-uplift"><?php esc_html_e( 'Promotion uplift (%)', 'laqi-unit-stock-manager' ); ?></label><input id="laqi-lusm-uplift" type="number" min="0" max="1000" name="uplift" value="<?php echo esc_attr( (string) $uplift ); ?>" /><?php submit_button( __( 'Calculate scenario', 'laqi-unit-stock-manager' ), 'primary', 'calculate' ); ?></form><?php endif; ?>
		<?php
		if ( is_array( $result ) ) :
			?>
			<div class="notice inline <?php echo $result['enough_stock'] ? 'notice-success' : 'notice-error'; ?>"><p><?php echo esc_html( $result['enough_stock'] ? __( 'The current stock covers this scenario.', 'laqi-unit-stock-manager' ) : __( 'This scenario exceeds current stock.', 'laqi-unit-stock-manager' ) ); ?></p></div><dl><dt><?php esc_html_e( 'Scenario demand', 'laqi-unit-stock-manager' ); ?></dt><dd><?php echo esc_html( $this->formatter->format( new Quantity( $pool->quantity()->family(), $result['demand_base'] ), $pool->display_unit() ) ); ?></dd><dt><?php esc_html_e( 'Projected balance', 'laqi-unit-stock-manager' ); ?></dt><dd><?php echo esc_html( $this->formatter->format( new Quantity( $pool->quantity()->family(), $result['remaining_base'] ), $pool->display_unit() ) ); ?></dd>
			<?php
			if ( null !== $result['projected_days_cover'] ) :
				?>
			<dt><?php esc_html_e( 'Projected days remaining', 'laqi-unit-stock-manager' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $result['projected_days_cover'], 1 ) ); ?></dd><?php endif; ?></dl><?php endif; ?></section>
		<?php
	}
}
