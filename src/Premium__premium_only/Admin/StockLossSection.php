<?php
/**
 * Paid stock-loss admin section.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Admin;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Admin\ScreenSectionInterface;
use LaqiUnitStockManager\Admin\UnitStockPage;
use LaqiUnitStockManager\Premium\Inventory\StockLossTypeCatalog;
use LaqiUnitStockManager\Presentation\QuantityFormatter;
use LaqiUnitStockManager\Storage\PoolRepository;

/** Renders a focused, scalable loss-recording workflow. */
final class StockLossSection implements ScreenSectionInterface {
	/**
	 * Pool persistence.
	 *
	 * @var PoolRepository
	 */
	private $pools;
	/**
	 * Quantity display.
	 *
	 * @var QuantityFormatter
	 */
	private $formatter;
	/**
	 * Registered loss types.
	 *
	 * @var StockLossTypeCatalog
	 */
	private $types;

	/** Constructor.
	 *
	 * @param PoolRepository       $pools     Pools.
	 * @param QuantityFormatter    $formatter Formatter.
	 * @param StockLossTypeCatalog $types     Types.
	 */
	public function __construct( PoolRepository $pools, QuantityFormatter $formatter, StockLossTypeCatalog $types ) {
		$this->pools     = $pools;
		$this->formatter = $formatter;
		$this->types     = $types;
	}

	/** Section ID. @return string */
	public function id(): string {
		return 'losses';
	}

	/** Section title. @return string */
	public function title(): string {
		return __( 'Record loss', 'laqi-unit-stock-manager' );
	}

	/** Render the pool picker and loss form. @return void */
	public function render(): void {
		$pool_id          = isset( $_GET['pool_id'] ) ? absint( $_GET['pool_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$pool             = $pool_id > 0 ? $this->pools->find( $pool_id ) : null;
		$reason_templates = apply_filters( 'laqi_lusm_adjustment_reason_templates', array(), 'loss' );
		$this->notice();
		?>
		<section class="card laqi-lusm-loss-card">
			<h2><?php esc_html_e( 'Record physical stock loss', 'laqi-unit-stock-manager' ); ?></h2>
			<p><?php esc_html_e( 'Choose a pool, then record why a measurable quantity can no longer be sold.', 'laqi-unit-stock-manager' ); ?></p>
			<form method="get"><input type="hidden" name="page" value="<?php echo esc_attr( UnitStockPage::SLUG ); ?>" /><input type="hidden" name="section" value="losses" /><label for="laqi-lusm-loss-pool"><?php esc_html_e( 'Inventory pool', 'laqi-unit-stock-manager' ); ?></label><select id="laqi-lusm-loss-pool" name="pool_id" class="laqi-lusm-pool-search" data-placeholder="<?php esc_attr_e( 'Search inventory pools', 'laqi-unit-stock-manager' ); ?>" required>
			<?php
			if ( null !== $pool ) :
				?>
				<option value="<?php echo esc_attr( (string) $pool->id() ); ?>" selected><?php echo esc_html( $pool->name() . ' (' . $pool->display_unit() . ')' ); ?></option><?php endif; ?></select><?php submit_button( __( 'Choose pool', 'laqi-unit-stock-manager' ), 'secondary', '', false ); ?></form>
			<?php if ( null !== $pool ) : ?>
				<p><strong><?php esc_html_e( 'Current balance:', 'laqi-unit-stock-manager' ); ?></strong> <?php echo esc_html( $this->formatter->format( $pool->quantity(), $pool->display_unit() ) ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="laqi_lusm_record_loss" /><input type="hidden" name="pool_id" value="<?php echo esc_attr( (string) $pool->id() ); ?>" /><input type="hidden" name="unit" value="<?php echo esc_attr( $pool->display_unit() ); ?>" /><?php wp_nonce_field( 'laqi_lusm_record_loss_' . $pool->id() ); ?><label for="laqi-lusm-loss-type"><?php esc_html_e( 'Loss type', 'laqi-unit-stock-manager' ); ?></label><select id="laqi-lusm-loss-type" name="loss_type" required>
				<?php
				foreach ( $this->types->all() as $key => $label ) :
					?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select><label for="laqi-lusm-loss-quantity"><?php echo esc_html( sprintf( /* translators: %s: unit symbol. */ __( 'Quantity lost (%s)', 'laqi-unit-stock-manager' ), $pool->display_unit() ) ); ?></label><input id="laqi-lusm-loss-quantity" name="quantity" inputmode="decimal" required /><label for="laqi-lusm-loss-reason"><?php esc_html_e( 'Notes', 'laqi-unit-stock-manager' ); ?></label><input id="laqi-lusm-loss-reason" name="reason" maxlength="255" <?php echo ! empty( $reason_templates ) ? 'list="laqi-lusm-loss-reasons"' : ''; ?> />
					<?php
					if ( ! empty( $reason_templates ) ) :
						?>
						<datalist id="laqi-lusm-loss-reasons">
						<?php
						foreach ( $reason_templates as $template ) :
							?>
						<option value="<?php echo esc_attr( $template ); ?>"></option><?php endforeach; ?></datalist><?php endif; ?><?php submit_button( __( 'Record stock loss', 'laqi-unit-stock-manager' ) ); ?></form>
			<?php endif; ?>
		</section>
		<?php
	}

	/** Render a loss-specific redirect notice. @return void */
	private function notice(): void {
		$result = isset( $_GET['laqi_lusm_loss_result'] ) ? sanitize_key( wp_unslash( $_GET['laqi_lusm_loss_result'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'loss_recorded' === $result ) {
			wp_admin_notice(
				__( 'Stock loss recorded.', 'laqi-unit-stock-manager' ),
				array(
					'type'        => 'success',
					'dismissible' => true,
				)
			);
		} elseif ( 'loss_error' === $result ) {
			wp_admin_notice( __( 'The stock loss could not be recorded. Check the quantity and try again.', 'laqi-unit-stock-manager' ), array( 'type' => 'error' ) );
		}
	}
}
