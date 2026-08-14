<?php
/**
 * Adjustment reason and approval-policy screen.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Admin;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Admin\ScreenSectionInterface;
use LaqiUnitStockManager\Premium\Approvals\AdjustmentPolicyRepository;

/** Renders site-wide reason templates and sensitive-change permissions. */
final class AdjustmentPolicySection implements ScreenSectionInterface {
	/**
	 * Policy settings.
	 *
	 * @var AdjustmentPolicyRepository
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param AdjustmentPolicyRepository $settings Policy settings.
	 */
	public function __construct( AdjustmentPolicyRepository $settings ) {
		$this->settings = $settings;
	}

	/** Section ID. @return string */
	public function id(): string {
		return 'adjustment-policy';
	}

	/** Section title. @return string */
	public function title(): string {
		return __( 'Reasons & approvals', 'laqi-unit-stock-manager' );
	}

	/** Render policy settings. @return void */
	public function render(): void {
		$settings = $this->settings->get();
		$result   = isset( $_GET['laqi_lusm_policy_result'] ) ? sanitize_key( wp_unslash( $_GET['laqi_lusm_policy_result'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'saved' === $result ) {
			wp_admin_notice(
				__( 'Adjustment policy saved.', 'laqi-unit-stock-manager' ),
				array(
					'type'        => 'success',
					'dismissible' => true,
				)
			);
		} elseif ( 'error' === $result ) {
			wp_admin_notice( __( 'The adjustment policy could not be saved.', 'laqi-unit-stock-manager' ), array( 'type' => 'error' ) );
		}
		?>
		<section class="card">
			<h2><?php esc_html_e( 'Adjustment reasons and approvals', 'laqi-unit-stock-manager' ); ?></h2>
			<p><?php esc_html_e( 'Reason templates are suggestions; the selected or typed text remains on the immutable movement. Sensitive changes require the configured approver permission.', 'laqi-unit-stock-manager' ); ?></p>
			<?php if ( current_user_can( 'manage_options' ) ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="laqi_lusm_save_adjustment_policy" /><?php wp_nonce_field( 'laqi_lusm_save_adjustment_policy' ); ?>
				<label for="laqi-lusm-reason-templates"><?php esc_html_e( 'Reusable reasons (one per line)', 'laqi-unit-stock-manager' ); ?></label><textarea id="laqi-lusm-reason-templates" name="templates" rows="8" class="large-text"><?php echo esc_textarea( implode( "\n", $settings['templates'] ) ); ?></textarea>
				<label for="laqi-lusm-sensitive-percent"><?php esc_html_e( 'Sensitive change threshold (% of current balance)', 'laqi-unit-stock-manager' ); ?></label><input id="laqi-lusm-sensitive-percent" name="sensitive_percent" type="number" min="0" max="100" step="0.01" value="<?php echo esc_attr( (string) ( $settings['sensitive_ratio'] * 100 ) ); ?>" required /><p class="description"><?php esc_html_e( 'Use 0 to disable the extra permission check. The comparison uses the absolute normalized change.', 'laqi-unit-stock-manager' ); ?></p>
				<label for="laqi-lusm-approver-capability"><?php esc_html_e( 'Who may approve sensitive changes', 'laqi-unit-stock-manager' ); ?></label><select id="laqi-lusm-approver-capability" name="approver_capability"><option value="manage_options" <?php selected( $settings['approver_capability'], 'manage_options' ); ?>><?php esc_html_e( 'Administrators', 'laqi-unit-stock-manager' ); ?></option><option value="manage_woocommerce" <?php selected( $settings['approver_capability'], 'manage_woocommerce' ); ?>><?php esc_html_e( 'WooCommerce stock managers and administrators', 'laqi-unit-stock-manager' ); ?></option></select>
				<?php submit_button( __( 'Save adjustment policy', 'laqi-unit-stock-manager' ) ); ?>
			</form>
				<?php
			else :
				?>
				<p><?php esc_html_e( 'Only an administrator can change this policy.', 'laqi-unit-stock-manager' ); ?></p><?php endif; ?>
		</section>
		<?php
	}
}
