<?php
/**
 * Supply states screen.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Admin;

defined( 'ABSPATH' ) || exit;
// phpcs:disable Generic.Commenting.DocComment.MissingShort, Squiz.Commenting.FunctionComment, WordPress.Security.NonceVerification.Recommended
use LaqiUnitStockManager\Admin\ScreenSectionInterface;
use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Premium\Supply\StockHoldRepository;
use LaqiUnitStockManager\Premium\Supply\SafetyStockPolicyRepository;
use LaqiUnitStockManager\Premium\Supply\SupplyProjectionService;
use LaqiUnitStockManager\Presentation\QuantityFormatter;
use LaqiUnitStockManager\Storage\PoolRepository;
/** Shows all quantities that affect available-to-sell stock. */
final class ReservationsSection implements ScreenSectionInterface {
	/** @var StockHoldRepository */ private $holds;
	/** @var PoolRepository */ private $pools;
	/** @var QuantityFormatter */ private $formatter;
	/** @var SafetyStockPolicyRepository */ private $safety_stock;
	/** @var SupplyProjectionService */ private $projections;
	/** Constructor. */ public function __construct( StockHoldRepository $holds, PoolRepository $pools, QuantityFormatter $formatter, SafetyStockPolicyRepository $safety_stock, SupplyProjectionService $projections ) {
		$this->holds        = $holds;
		$this->pools        = $pools;
		$this->formatter    = $formatter;
		$this->safety_stock = $safety_stock;
		$this->projections  = $projections;}
	/** ID. */ public function id(): string {
		return 'reservations';
	} /** Title. */ public function title(): string {
		return __( 'Supply states', 'laqi-unit-stock-manager' );}
	/** Render. */ public function render(): void {
		$pool_id = isset( $_GET['pool_id'] ) ? absint( $_GET['pool_id'] ) : 0;
		$pool    = $pool_id > 0 ? $this->pools->find( $pool_id ) : null;
		$rows    = $this->projections->rows(); ?>
	<section class="card"><h2><?php esc_html_e( 'Place stock on hold', 'laqi-unit-stock-manager' ); ?></h2><form method="get"><input type="hidden" name="page" value="laqi-unit-stock-manager"/><input type="hidden" name="section" value="reservations"/><label for="laqi-lusm-supply-pool"><?php esc_html_e( 'Inventory pool', 'laqi-unit-stock-manager' ); ?></label><select id="laqi-lusm-supply-pool" name="pool_id" class="laqi-lusm-pool-search" required>
		<?php
		if ( null !== $pool ) :
			?>
		<option value="<?php echo esc_attr( (string) $pool->id() ); ?>" selected><?php echo esc_html( $pool->name() ); ?></option><?php endif; ?></select><?php submit_button( __( 'Choose pool', 'laqi-unit-stock-manager' ), 'secondary', '', false ); ?></form>
		<?php
		if ( null !== $pool ) :
			?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="laqi_lusm_place_stock_hold"/><input type="hidden" name="pool_id" value="<?php echo esc_attr( (string) $pool->id() ); ?>"/><?php wp_nonce_field( 'laqi_lusm_place_stock_hold_' . $pool->id() ); ?><label for="laqi-lusm-hold-state"><?php esc_html_e( 'State', 'laqi-unit-stock-manager' ); ?></label><select id="laqi-lusm-hold-state" name="state"><option value="quarantined"><?php esc_html_e( 'Quarantined', 'laqi-unit-stock-manager' ); ?></option><option value="damaged"><?php esc_html_e( 'Damaged', 'laqi-unit-stock-manager' ); ?></option></select><label for="laqi-lusm-hold-quantity"><?php echo esc_html( sprintf( /* translators: %s: unit. */ __( 'Quantity (%s)', 'laqi-unit-stock-manager' ), $pool->display_unit() ) ); ?></label><input id="laqi-lusm-hold-quantity" name="quantity" inputmode="decimal" required/><label for="laqi-lusm-hold-reason"><?php esc_html_e( 'Reason', 'laqi-unit-stock-manager' ); ?></label><input id="laqi-lusm-hold-reason" name="reason" maxlength="191" required/><?php submit_button( __( 'Place on hold', 'laqi-unit-stock-manager' ) ); ?></form><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="laqi_lusm_save_safety_stock"/><input type="hidden" name="pool_id" value="<?php echo esc_attr( (string) $pool->id() ); ?>"/><?php wp_nonce_field( 'laqi_lusm_save_safety_stock_' . $pool->id() ); ?><label for="laqi-lusm-ats-safety"><?php echo esc_html( sprintf( /* translators: %s: unit. */ __( 'Protected safety stock (%s)', 'laqi-unit-stock-manager' ), $pool->display_unit() ) ); ?></label><input id="laqi-lusm-ats-safety" name="safety_stock" inputmode="decimal" value="<?php echo esc_attr( $this->formatter->decimal( new Quantity( $pool->quantity()->family(), $this->safety_stock->quantity( $pool->id() ) ), $pool->display_unit() ) ); ?>" required/><p class="description"><?php esc_html_e( 'This buffer is excluded from storefront availability but remains physically on hand.', 'laqi-unit-stock-manager' ); ?></p><?php submit_button( __( 'Save safety stock', 'laqi-unit-stock-manager' ) ); ?></form><?php endif; ?></section>
	<section class="card"><h2><?php esc_html_e( 'Supply state summary', 'laqi-unit-stock-manager' ); ?></h2><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Pool', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'On hand', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Reserved', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Quarantined', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Damaged', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Safety stock', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Incoming', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Available to sell', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Projected after incoming', 'laqi-unit-stock-manager' ); ?></th></tr></thead><tbody>
		<?php
		foreach ( $rows as $row ) :
			$on = (int) $row['quantity_base'];
			?>
		<tr><td><?php echo esc_html( $row['name'] ); ?></td>
			<?php
			foreach ( array( $on, $row['reserved_base'], $row['quarantined_base'], $row['damaged_base'], $row['safety_stock_base'], $row['incoming_base'], $row['available_base'], $row['projected_base'] ) as $amount ) :
				?>
		<td><?php echo esc_html( $this->formatter->format( new Quantity( $row['family'], (int) $amount ), $row['display_unit'] ) ); ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></section>
	<section class="card"><h2><?php esc_html_e( 'Active stock holds', 'laqi-unit-stock-manager' ); ?></h2>
		<?php
		foreach ( $this->holds->active() as $hold ) :
			$state_label = 'quarantined' === $hold['state'] ? __( 'Quarantined', 'laqi-unit-stock-manager' ) : __( 'Damaged', 'laqi-unit-stock-manager' );
			?>
		<div><p><?php echo esc_html( $hold['pool_name'] . ' — ' . $state_label . ' — ' . $this->formatter->format( new Quantity( $hold['family'], (int) $hold['quantity_base'] ), $hold['display_unit'] ) . ' — ' . $hold['reason'] ); ?></p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="laqi_lusm_release_stock_hold"/><input type="hidden" name="pool_id" value="<?php echo esc_attr( (string) $hold['pool_id'] ); ?>"/><input type="hidden" name="hold_id" value="<?php echo esc_attr( (string) $hold['id'] ); ?>"/><?php wp_nonce_field( 'laqi_lusm_release_stock_hold_' . $hold['id'] ); ?><?php submit_button( __( 'Release to available stock', 'laqi-unit-stock-manager' ), 'secondary small', '', false ); ?></form><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="laqi_lusm_write_off_stock_hold"/><input type="hidden" name="pool_id" value="<?php echo esc_attr( (string) $hold['pool_id'] ); ?>"/><input type="hidden" name="hold_id" value="<?php echo esc_attr( (string) $hold['id'] ); ?>"/><?php wp_nonce_field( 'laqi_lusm_write_off_stock_hold_' . $hold['id'] ); ?><?php submit_button( __( 'Write off permanently', 'laqi-unit-stock-manager' ), 'secondary small', '', false ); ?></form></div><?php endforeach; ?></section>
		<?php
	}
}
