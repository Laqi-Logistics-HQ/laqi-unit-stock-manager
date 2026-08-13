<?php
/**
 * WooCommerce Unit Stock admin page.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the menu and delegates rendering to registered sections.
 */
final class UnitStockPage {

	const SLUG = 'laqi-unit-stock-manager';

	/**
	 * Screen extensions.
	 *
	 * @var ScreenSectionCatalog
	 */
	private $sections;

	/**
	 * Constructor.
	 *
	 * @param ScreenSectionCatalog $sections Screen sections.
	 */
	public function __construct( ScreenSectionCatalog $sections ) {
		$this->sections = $sections;
	}

	/** Register WordPress hooks. @return void */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
	}

	/** Add the WooCommerce submenu. @return void */
	public function add_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Unit Stock', 'laqi-unit-stock-manager' ),
			__( 'Unit Stock', 'laqi-unit-stock-manager' ),
			'manage_woocommerce',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/** Render the active screen section. @return void */
	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage unit stock.', 'laqi-unit-stock-manager' ) );
		}

		$sections = $this->sections->all();
		$active   = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : 'stock'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $sections[ $active ] ) ) {
			$active = (string) array_key_first( $sections );
		}
		?>
		<div class="wrap laqi-lusm-wrap">
			<h1><?php esc_html_e( 'Unit Stock', 'laqi-unit-stock-manager' ); ?></h1>
			<?php $this->render_notice(); ?>
			<?php if ( count( $sections ) > 1 ) : ?>
				<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Unit Stock sections', 'laqi-unit-stock-manager' ); ?>">
				<?php foreach ( $sections as $id => $section ) : ?>
					<a class="nav-tab <?php echo $id === $active ? 'nav-tab-active' : ''; ?>" href="
					<?php
					echo esc_url(
						add_query_arg(
							array(
								'page'    => self::SLUG,
								'section' => $id,
							),
							admin_url( 'admin.php' )
						)
					);
					?>
										"><?php echo esc_html( $section->title() ); ?></a>
				<?php endforeach; ?>
				</nav>
			<?php endif; ?>
			<?php $sections[ $active ]->render(); ?>
		</div>
		<?php
	}

	/**
	 * Render a redirected stock-adjustment result.
	 *
	 * @return void
	 */
	private function render_notice(): void {
		$result = isset( $_GET['laqi_lusm_result'] ) ? sanitize_key( wp_unslash( $_GET['laqi_lusm_result'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'updated' === $result ) {
			wp_admin_notice(
				__( 'Inventory stock updated.', 'laqi-unit-stock-manager' ),
				array(
					'type'        => 'success',
					'dismissible' => true,
				)
			);
		} elseif ( 'pool_created' === $result ) {
			wp_admin_notice(
				__( 'Inventory pool created.', 'laqi-unit-stock-manager' ),
				array(
					'type'        => 'success',
					'dismissible' => true,
				)
			);
		} elseif ( 'mapping_saved' === $result ) {
			wp_admin_notice(
				__( 'Product stock mapping saved.', 'laqi-unit-stock-manager' ),
				array(
					'type'        => 'success',
					'dismissible' => true,
				)
			);
		} elseif ( 'setup_error' === $result ) {
			wp_admin_notice( __( 'The setup could not be saved. Check that the quantities and units are compatible.', 'laqi-unit-stock-manager' ), array( 'type' => 'error' ) );
		} elseif ( 'error' === $result ) {
			wp_admin_notice( __( 'Inventory stock could not be updated. Check the quantity and try again.', 'laqi-unit-stock-manager' ), array( 'type' => 'error' ) );
		}
	}
}
