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

	/** Add the Products submenu. @return void */
	public function add_menu(): void {
		add_submenu_page(
			'edit.php?post_type=product',
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
				<?php $this->render_navigation( $sections, $active ); ?>
			<?php endif; ?>
			<?php $sections[ $active ]->render(); ?>
		</div>
		<?php
	}

	/**
	 * Render direct section tabs or extension-provided workspace groups.
	 *
	 * @param array<string, ScreenSectionInterface> $sections Registered sections.
	 * @param string                                $active   Active section ID.
	 * @return void
	 */
	private function render_navigation( array $sections, string $active ): void {
		/**
		 * Groups Unit Stock sections into top-level workspaces.
		 *
		 * Each group contains a translated label and an ordered list of registered
		 * section IDs. Sections omitted by extensions remain available under More.
		 *
		 * @param array<string, array{label:string,sections:array<int,string>}> $groups Navigation groups.
		 */
		$groups = apply_filters( 'laqi_lusm_admin_navigation_groups', array() );
		$groups = $this->normalize_groups( is_array( $groups ) ? $groups : array(), $sections );
		if ( array() === $groups ) {
			$this->render_section_tabs( $sections, $active, __( 'Unit Stock sections', 'laqi-unit-stock-manager' ) );
			return;
		}

		$active_group = (string) array_key_first( $groups );
		foreach ( $groups as $group_id => $group ) {
			if ( in_array( $active, $group['sections'], true ) ) {
				$active_group = $group_id;
				break;
			}
		}
		?>
		<nav class="nav-tab-wrapper laqi-lusm-workspace-tabs" aria-label="<?php esc_attr_e( 'Unit Stock workspaces', 'laqi-unit-stock-manager' ); ?>">
			<?php foreach ( $groups as $group_id => $group ) : ?>
				<a class="nav-tab <?php echo $group_id === $active_group ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( $this->section_url( $group['sections'][0] ) ); ?>" <?php echo $group_id === $active_group ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $group['label'] ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php
		$group_sections = array_intersect_key( $sections, array_flip( $groups[ $active_group ]['sections'] ) );
		$this->render_section_tabs( $group_sections, $active, __( 'Workspace sections', 'laqi-unit-stock-manager' ), 'laqi-lusm-workspace-section-tabs' );
	}

	/**
	 * Normalize extension groups and retain ungrouped registered sections.
	 *
	 * @param array<string, mixed>                  $groups   Proposed groups.
	 * @param array<string, ScreenSectionInterface> $sections Registered sections.
	 * @return array<string, array{label:string,sections:array<int,string>}>
	 */
	private function normalize_groups( array $groups, array $sections ): array {
		$normalized = array();
		$assigned   = array();
		foreach ( $groups as $id => $group ) {
			$id = sanitize_key( (string) $id );
			if ( '' === $id || ! is_array( $group ) || empty( $group['label'] ) || ! isset( $group['sections'] ) || ! is_array( $group['sections'] ) ) {
				continue;
			}
			$group_sections = array();
			foreach ( $group['sections'] as $section_id ) {
				$section_id = sanitize_key( (string) $section_id );
				if ( isset( $sections[ $section_id ] ) && ! isset( $assigned[ $section_id ] ) ) {
					$group_sections[]        = $section_id;
					$assigned[ $section_id ] = true;
				}
			}
			if ( array() !== $group_sections ) {
				$normalized[ $id ] = array(
					'label'    => sanitize_text_field( (string) $group['label'] ),
					'sections' => $group_sections,
				);
			}
		}
		$remaining = array_values( array_diff( array_keys( $sections ), array_keys( $assigned ) ) );
		if ( array() !== $remaining && array() !== $normalized ) {
			$normalized['more'] = array(
				'label'    => __( 'More', 'laqi-unit-stock-manager' ),
				'sections' => $remaining,
			);
		}
		return $normalized;
	}

	/**
	 * Render a row of section links.
	 *
	 * @param array<string, ScreenSectionInterface> $sections    Sections in this row.
	 * @param string                                $active      Active section ID.
	 * @param string                                $aria_label  Navigation label.
	 * @param string                                $extra_class Optional navigation class.
	 * @return void
	 */
	private function render_section_tabs( array $sections, string $active, string $aria_label, string $extra_class = '' ): void {
		?>
		<nav class="nav-tab-wrapper <?php echo esc_attr( $extra_class ); ?>" aria-label="<?php echo esc_attr( $aria_label ); ?>">
			<?php foreach ( $sections as $id => $section ) : ?>
				<a class="nav-tab <?php echo $id === $active ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( $this->section_url( $id ) ); ?>" <?php echo $id === $active ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $section->title() ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
	 * Build a canonical section URL.
	 *
	 * @param string $section Section ID.
	 * @return string
	 */
	private function section_url( string $section ): string {
		return add_query_arg(
			array(
				'page'    => self::SLUG,
				'section' => $section,
			),
			admin_url( 'edit.php?post_type=product' )
		);
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
		} elseif ( 'pool_updated' === $result ) {
			wp_admin_notice(
				__( 'Inventory pool details updated.', 'laqi-unit-stock-manager' ),
				array(
					'type'        => 'success',
					'dismissible' => true,
				)
			);
		} elseif ( 'pool_update_error' === $result ) {
			wp_admin_notice( __( 'Inventory pool details could not be updated.', 'laqi-unit-stock-manager' ), array( 'type' => 'error' ) );
		} elseif ( 'mapping_saved' === $result ) {
			wp_admin_notice(
				__( 'Product stock mapping saved.', 'laqi-unit-stock-manager' ),
				array(
					'type'        => 'success',
					'dismissible' => true,
				)
			);
		} elseif ( 'mapping_unlinked' === $result ) {
			wp_admin_notice(
				__( 'Product stock mapping unlinked.', 'laqi-unit-stock-manager' ),
				array(
					'type'        => 'success',
					'dismissible' => true,
				)
			);
		} elseif ( 'mapping_updated' === $result ) {
			wp_admin_notice(
				__( 'Product stock mapping updated.', 'laqi-unit-stock-manager' ),
				array(
					'type'        => 'success',
					'dismissible' => true,
				)
			);
		} elseif ( 'unit_created' === $result ) {
			wp_admin_notice(
				__( 'Custom stock unit created.', 'laqi-unit-stock-manager' ),
				array(
					'type'        => 'success',
					'dismissible' => true,
				)
			);
		} elseif ( 'unit_retired' === $result ) {
			wp_admin_notice(
				__( 'Custom stock unit retired.', 'laqi-unit-stock-manager' ),
				array(
					'type'        => 'success',
					'dismissible' => true,
				)
			);
		} elseif ( 'unit_in_use' === $result ) {
			wp_admin_notice( __( 'The custom stock unit is still in use and could not be retired.', 'laqi-unit-stock-manager' ), array( 'type' => 'error' ) );
		} elseif ( 'setup_error' === $result ) {
			wp_admin_notice( __( 'The setup could not be saved. Check that the quantities and units are compatible.', 'laqi-unit-stock-manager' ), array( 'type' => 'error' ) );
		} elseif ( 'error' === $result ) {
			wp_admin_notice( __( 'Inventory stock could not be updated. Check the quantity and try again.', 'laqi-unit-stock-manager' ), array( 'type' => 'error' ) );
		}
	}
}
