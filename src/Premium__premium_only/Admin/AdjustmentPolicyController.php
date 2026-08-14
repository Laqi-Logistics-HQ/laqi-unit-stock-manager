<?php
/**
 * Adjustment-policy settings controller.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Admin;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Admin\UnitStockPage;
use LaqiUnitStockManager\Premium\Approvals\AdjustmentPolicyRepository;
use Throwable;

/** Saves reusable reasons and sensitive-adjustment permission settings. */
final class AdjustmentPolicyController {
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

	/** Register the authenticated action. @return void */
	public function register(): void {
		add_action( 'admin_post_laqi_lusm_save_adjustment_policy', array( $this, 'save' ) );
	}

	/** Save submitted settings. @return void */
	public function save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to configure adjustment approvals.', 'laqi-unit-stock-manager' ) );
		}
		check_admin_referer( 'laqi_lusm_save_adjustment_policy' );
		try {
			$raw_templates = isset( $_POST['templates'] ) ? sanitize_textarea_field( wp_unslash( $_POST['templates'] ) ) : '';
			$percent       = isset( $_POST['sensitive_percent'] ) ? (float) sanitize_text_field( wp_unslash( $_POST['sensitive_percent'] ) ) : 0;
			$capability    = isset( $_POST['approver_capability'] ) ? sanitize_key( wp_unslash( $_POST['approver_capability'] ) ) : '';
			$templates     = preg_split( '/\R/', $raw_templates );
			$this->settings->save( is_array( $templates ) ? $templates : array(), $percent / 100, $capability );
			$this->redirect( 'saved' );
		} catch ( Throwable $error ) {
			unset( $error );
			$this->redirect( 'error' );
		}
	}

	/**
	 * Redirect to policy settings.
	 *
	 * @param string $result Result key.
	 * @return void
	 */
	private function redirect( string $result ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                    => UnitStockPage::SLUG,
					'section'                 => 'adjustment-policy',
					'laqi_lusm_policy_result' => $result,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
