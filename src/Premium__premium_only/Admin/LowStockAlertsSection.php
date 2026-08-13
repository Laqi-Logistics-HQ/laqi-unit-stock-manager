<?php
/**
 * Paid low-stock alerts screen.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Admin;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Admin\ScreenSectionInterface;
use LaqiUnitStockManager\Admin\UnitStockPage;
use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Premium\Alerts\LowStockPolicyRepository;
use LaqiUnitStockManager\Presentation\QuantityFormatter;
use LaqiUnitStockManager\Storage\PoolRepository;

/** Configures alerts and shows current pool alert status. */
final class LowStockAlertsSection implements ScreenSectionInterface {
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
	/** Quantity display.
	 *
	 * @var QuantityFormatter
	 */
	private $formatter;

	/** Constructor.
	 *
	 * @param LowStockPolicyRepository $policies  Policies.
	 * @param PoolRepository           $pools     Pools.
	 * @param QuantityFormatter        $formatter Formatter.
	 */
	public function __construct( LowStockPolicyRepository $policies, PoolRepository $pools, QuantityFormatter $formatter ) {
		$this->policies  = $policies;
		$this->pools     = $pools;
		$this->formatter = $formatter;
	}

	/** Section ID. @return string */
	public function id(): string {
		return 'alerts';
	}

	/** Section title. @return string */
	public function title(): string {
		return __( 'Alerts', 'laqi-unit-stock-manager' );
	}

	/** Render settings and status. @return void */
	public function render(): void {
		$pool_id = isset( $_GET['pool_id'] ) ? absint( $_GET['pool_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$pool    = $pool_id > 0 ? $this->pools->find( $pool_id ) : null;
		$policy  = null !== $pool ? $this->policies->find( $pool_id ) : null;
		$this->notice();
		?>
		<div class="laqi-lusm-setup-grid">
		<section class="card"><h2><?php esc_html_e( 'Configure low-stock alert', 'laqi-unit-stock-manager' ); ?></h2><p><?php esc_html_e( 'Warning and critical emails follow the site timezone. Optional reminders repeat while stock remains low, outside quiet hours.', 'laqi-unit-stock-manager' ); ?></p><form method="get"><input type="hidden" name="page" value="<?php echo esc_attr( UnitStockPage::SLUG ); ?>" /><input type="hidden" name="section" value="alerts" /><label for="laqi-lusm-alert-pool"><?php esc_html_e( 'Inventory pool', 'laqi-unit-stock-manager' ); ?></label><select id="laqi-lusm-alert-pool" name="pool_id" class="laqi-lusm-pool-search" data-placeholder="<?php esc_attr_e( 'Search inventory pools', 'laqi-unit-stock-manager' ); ?>" required>
		<?php
		if ( null !== $pool ) :
			?>
			<option value="<?php echo esc_attr( (string) $pool->id() ); ?>" selected><?php echo esc_html( $pool->name() . ' (' . $pool->display_unit() . ')' ); ?></option><?php endif; ?></select><?php submit_button( __( 'Choose pool', 'laqi-unit-stock-manager' ), 'secondary', '', false ); ?></form>
		<?php
		if ( null !== $pool ) :
			$threshold          = null !== $policy ? $this->formatter->decimal( new Quantity( $pool->quantity()->family(), (int) $policy['threshold_base'] ), $pool->display_unit() ) : '';
			$critical_threshold = null !== $policy && isset( $policy['critical_base'] ) ? $this->formatter->decimal( new Quantity( $pool->quantity()->family(), (int) $policy['critical_base'] ), $pool->display_unit() ) : '0';
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="laqi_lusm_save_low_stock_alert" /><input type="hidden" name="pool_id" value="<?php echo esc_attr( (string) $pool->id() ); ?>" /><?php wp_nonce_field( 'laqi_lusm_save_low_stock_alert_' . $pool->id() ); ?><label for="laqi-lusm-alert-threshold"><?php echo esc_html( sprintf( /* translators: %s: unit symbol. */ __( 'Warning threshold (%s)', 'laqi-unit-stock-manager' ), $pool->display_unit() ) ); ?></label><input id="laqi-lusm-alert-threshold" name="threshold" inputmode="decimal" value="<?php echo esc_attr( $threshold ); ?>" required /><label for="laqi-lusm-alert-critical"><?php echo esc_html( sprintf( /* translators: %s: unit symbol. */ __( 'Critical threshold (%s)', 'laqi-unit-stock-manager' ), $pool->display_unit() ) ); ?></label><input id="laqi-lusm-alert-critical" name="critical_threshold" inputmode="decimal" value="<?php echo esc_attr( $critical_threshold ); ?>" required /><label for="laqi-lusm-alert-recipients"><?php esc_html_e( 'Email recipients', 'laqi-unit-stock-manager' ); ?></label><textarea id="laqi-lusm-alert-recipients" name="recipients" required><?php echo esc_textarea( null !== $policy ? implode( "\n", (array) $policy['recipients'] ) : get_option( 'admin_email' ) ); ?></textarea><label for="laqi-lusm-alert-reminder"><?php esc_html_e( 'Reminder interval (hours)', 'laqi-unit-stock-manager' ); ?></label><input type="number" min="0" id="laqi-lusm-alert-reminder" name="reminder_hours" value="<?php echo esc_attr( null !== $policy && isset( $policy['reminder_hours'] ) ? (string) $policy['reminder_hours'] : '24' ); ?>" /><label for="laqi-lusm-alert-quiet-start"><?php esc_html_e( 'Quiet hours start (0-23)', 'laqi-unit-stock-manager' ); ?></label><input type="number" min="0" max="23" id="laqi-lusm-alert-quiet-start" name="quiet_start" value="<?php echo esc_attr( null !== $policy && isset( $policy['quiet_start'] ) && (int) $policy['quiet_start'] >= 0 ? (string) $policy['quiet_start'] : '' ); ?>" /><label for="laqi-lusm-alert-quiet-end"><?php esc_html_e( 'Quiet hours end (0-23)', 'laqi-unit-stock-manager' ); ?></label><input type="number" min="0" max="23" id="laqi-lusm-alert-quiet-end" name="quiet_end" value="<?php echo esc_attr( null !== $policy && isset( $policy['quiet_end'] ) && (int) $policy['quiet_end'] >= 0 ? (string) $policy['quiet_end'] : '' ); ?>" /><?php submit_button( __( 'Save alert', 'laqi-unit-stock-manager' ) ); ?></form><?php endif; ?></section>
		<section class="card laqi-lusm-setup-wide"><h2><?php esc_html_e( 'Configured pool alerts', 'laqi-unit-stock-manager' ); ?></h2><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Inventory pool', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Balance', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Warning', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Critical', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Status', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Last sent', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Recipients', 'laqi-unit-stock-manager' ); ?></th></tr></thead><tbody>
		<?php
		foreach ( $this->policies->configured() as $row ) :
			$saved = json_decode( (string) $row['policy_json'], true )['low_stock'];
			?>
			<tr><td><a href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'page'    => UnitStockPage::SLUG,
						'section' => 'alerts',
						'pool_id' => (int) $row['id'],
					),
					admin_url( 'admin.php' )
				)
			);
			?>
"><?php echo esc_html( $row['name'] ); ?></a></td><td><?php echo esc_html( $this->formatter->format( new Quantity( $row['family'], (int) $row['quantity_base'] ), $row['display_unit'] ) ); ?></td><td><?php echo esc_html( $this->formatter->format( new Quantity( $row['family'], (int) $saved['threshold_base'] ), $row['display_unit'] ) ); ?></td><td><?php echo esc_html( $this->formatter->format( new Quantity( $row['family'], isset( $saved['critical_base'] ) ? (int) $saved['critical_base'] : 0 ), $row['display_unit'] ) ); ?></td><td><?php echo esc_html( ucfirst( isset( $saved['severity'] ) ? $saved['severity'] : ( ! empty( $saved['is_low'] ) ? 'warning' : 'healthy' ) ) ); ?></td><td><?php echo ! empty( $saved['last_sent_at'] ) ? esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $saved['last_sent_at'] ) ) : esc_html__( 'Never', 'laqi-unit-stock-manager' ); ?></td><td><?php echo esc_html( implode( ', ', (array) $saved['recipients'] ) ); ?></td></tr><?php endforeach; ?></tbody></table></section>
		</div>
		<?php
	}

	/** Render redirect result. @return void */
	private function notice(): void {
		$result = isset( $_GET['laqi_lusm_alert_result'] ) ? sanitize_key( wp_unslash( $_GET['laqi_lusm_alert_result'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'alert_saved' === $result ) {
			wp_admin_notice(
				__( 'Low-stock alert saved.', 'laqi-unit-stock-manager' ),
				array(
					'type'        => 'success',
					'dismissible' => true,
				)
			);
		} elseif ( 'alert_error' === $result ) {
			wp_admin_notice( __( 'The low-stock alert could not be saved. Check the threshold and recipients.', 'laqi-unit-stock-manager' ), array( 'type' => 'error' ) );
		}
	}
}
