<?php
/**
 * Paid reorder suggestion screen.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Admin;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Admin\ScreenSectionInterface;
use LaqiUnitStockManager\Admin\UnitStockPage;
use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Premium\Receiving\SupplierRepository;
use LaqiUnitStockManager\Premium\Replenishment\ReorderPolicyRepository;
use LaqiUnitStockManager\Premium\Replenishment\ReorderSuggestionService;
use LaqiUnitStockManager\Presentation\QuantityFormatter;
use LaqiUnitStockManager\Storage\PoolRepository;

/** Configures and explains supplier-pack reorder suggestions. */
final class ReorderSection implements ScreenSectionInterface {
	/** Pools. @var PoolRepository
	 *
	 * @var PoolRepository
	 */ private $pools;
	/** Suppliers. @var SupplierRepository
	 *
	 * @var SupplierRepository
	 */ private $suppliers;
	/** Policies. @var ReorderPolicyRepository
	 *
	 * @var ReorderPolicyRepository
	 */ private $policies;
	/** Suggestions. @var ReorderSuggestionService
	 *
	 * @var ReorderSuggestionService
	 */ private $suggestions;
	/** Formatter. @var QuantityFormatter
	 *
	 * @var QuantityFormatter
	 */ private $formatter;
	/** Constructor.
	 *
	 * @param PoolRepository           $pools Pools.
	 * @param SupplierRepository       $suppliers Suppliers.
	 * @param ReorderPolicyRepository  $policies Policies.
	 * @param ReorderSuggestionService $suggestions Suggestions.
	 * @param QuantityFormatter        $formatter Formatter.
	 */
	public function __construct( PoolRepository $pools, SupplierRepository $suppliers, ReorderPolicyRepository $policies, ReorderSuggestionService $suggestions, QuantityFormatter $formatter ) {
		$this->pools       = $pools;
		$this->suppliers   = $suppliers;
		$this->policies    = $policies;
		$this->suggestions = $suggestions;
		$this->formatter   = $formatter; }
	/** ID. @return string */ public function id(): string {
		return 'reorder'; }
	/** Title. @return string */ public function title(): string {
		return __( 'Reorder', 'laqi-unit-stock-manager' ); }
	/** Render. @return void */
	public function render(): void {
		$pool_id = isset( $_GET['pool_id'] ) ? absint( $_GET['pool_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$pool    = $pool_id > 0 ? $this->pools->find( $pool_id ) : null;
		$this->notice(); ?>
		<section class="card"><h2><?php esc_html_e( 'Reorder policy', 'laqi-unit-stock-manager' ); ?></h2><p><?php esc_html_e( 'Suggestions cover forecast demand during supplier lead time plus your safety stock, then subtract on-hand and incoming stock.', 'laqi-unit-stock-manager' ); ?></p><form method="get"><input type="hidden" name="page" value="<?php echo esc_attr( UnitStockPage::SLUG ); ?>" /><input type="hidden" name="section" value="reorder" /><label for="laqi-lusm-reorder-pool"><?php esc_html_e( 'Inventory pool', 'laqi-unit-stock-manager' ); ?></label><select id="laqi-lusm-reorder-pool" name="pool_id" class="laqi-lusm-pool-search" required>
		<?php
		if ( null !== $pool ) :
			?>
			<option value="<?php echo esc_attr( (string) $pool->id() ); ?>" selected><?php echo esc_html( $pool->name() ); ?></option><?php endif; ?></select><?php submit_button( __( 'Choose pool', 'laqi-unit-stock-manager' ), 'secondary', '', false ); ?></form>
		<?php
		if ( null !== $pool ) :
			$policy = $this->policies->find( $pool->id() );
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="laqi_lusm_save_reorder_policy" /><input type="hidden" name="pool_id" value="<?php echo esc_attr( (string) $pool->id() ); ?>" /><?php wp_nonce_field( 'laqi_lusm_save_reorder_policy_' . $pool->id() ); ?><label for="laqi-lusm-reorder-pack"><?php esc_html_e( 'Preferred supplier pack', 'laqi-unit-stock-manager' ); ?></label><select id="laqi-lusm-reorder-pack" name="pack_id" required>
			<?php
			foreach ( $this->suppliers->packs_for_pool( $pool->id() ) as $pack ) :
				?>
			<option value="<?php echo esc_attr( (string) $pack['id'] ); ?>" <?php selected( $policy['preferred_pack_id'] ?? 0, $pack['id'] ); ?>><?php echo esc_html( $pack['supplier_name'] . ' — ' . $pack['pack_name'] . ' (' . $pack['lead_time_days'] . ' days)' ); ?></option><?php endforeach; ?></select><label for="laqi-lusm-safety-stock"><?php echo esc_html( sprintf( /* translators: %s: unit. */ __( 'Safety stock (%s)', 'laqi-unit-stock-manager' ), $pool->display_unit() ) ); ?></label><input id="laqi-lusm-safety-stock" name="safety_stock" inputmode="decimal" value="<?php echo esc_attr( null === $policy ? '0' : $this->formatter->decimal( new Quantity( $pool->quantity()->family(), $policy['safety_stock_base'] ), $pool->display_unit() ) ); ?>" required /><?php submit_button( __( 'Save reorder policy', 'laqi-unit-stock-manager' ) ); ?></form><?php endif; ?></section>
		<section class="card"><h2><?php esc_html_e( 'Reorder suggestions', 'laqi-unit-stock-manager' ); ?></h2><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Pool', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Supplier pack', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'On hand', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Incoming', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Lead-time demand', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Safety stock', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Suggestion', 'laqi-unit-stock-manager' ); ?></th></tr></thead><tbody>
		<?php
		foreach ( $this->policies->configured_ids() as $configured_id ) :
			$suggestion = $this->suggestions->suggest( $configured_id );
			$item       = $suggestion['pool'];
			?>
			<tr><td><?php echo esc_html( $item->name() ); ?></td><td><?php echo esc_html( $suggestion['pack']['supplier_name'] . ' — ' . $suggestion['pack']['name'] ); ?></td><td><?php echo esc_html( $this->formatter->format( $item->quantity(), $item->display_unit() ) ); ?></td><td><?php echo esc_html( $this->formatter->format( new Quantity( $item->quantity()->family(), $suggestion['incoming_base'] ), $item->display_unit() ) ); ?></td>
			<?php
			if ( 'forecast' !== $suggestion['forecast_state'] ) :
				?>
			<td colspan="3"><?php esc_html_e( 'Insufficient demand history for a reorder suggestion.', 'laqi-unit-stock-manager' ); ?></td>
				<?php
else :
	?>
	<td><?php echo esc_html( $this->formatter->format( new Quantity( $item->quantity()->family(), $suggestion['lead_demand_base'] ), $item->display_unit() ) ); ?></td><td><?php echo esc_html( $this->formatter->format( new Quantity( $item->quantity()->family(), $suggestion['safety_stock_base'] ), $item->display_unit() ) ); ?></td><td><strong><?php echo esc_html( sprintf( /* translators: 1: pack count, 2: pack name. */ __( '%1$d × %2$s', 'laqi-unit-stock-manager' ), $suggestion['pack_count'], $suggestion['pack']['name'] ) ); ?></strong></td><?php endif; ?></tr><?php endforeach; ?></tbody></table></section>
		<?php
	}
	/** Notice. @return void */ private function notice(): void {
		$result = isset( $_GET['laqi_lusm_reorder_result'] ) ? sanitize_key( wp_unslash( $_GET['laqi_lusm_reorder_result'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'reorder_saved' === $result ) {
			wp_admin_notice(
				__( 'Reorder policy saved.', 'laqi-unit-stock-manager' ),
				array(
					'type'        => 'success',
					'dismissible' => true,
				)
			);
		} elseif ( 'reorder_error' === $result ) {
			wp_admin_notice( __( 'The reorder policy could not be saved.', 'laqi-unit-stock-manager' ), array( 'type' => 'error' ) ); } }
}
