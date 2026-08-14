<?php
/**
 * Paid recipe setup screen.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Admin;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Admin\ScreenSectionInterface;
use LaqiUnitStockManager\Unit\UnitRegistry;

/** Renders explicit ingredient and packaging component rows. */
final class RecipeSection implements ScreenSectionInterface {
	/**
	 * Unit definitions.
	 *
	 * @var UnitRegistry
	 */
	private $units;

	/**
	 * Constructor.
	 *
	 * @param UnitRegistry $units Units.
	 */
	public function __construct( UnitRegistry $units ) {
		$this->units = $units;
	}

	/** Section ID. @return string */
	public function id(): string {
		return 'recipes';
	}

	/** Section title. @return string */
	public function title(): string {
		return __( 'Recipes', 'laqi-unit-stock-manager' );
	}

	/** Render recipe form. @return void */
	public function render(): void {
		$this->notice();
		?>
		<section class="card laqi-lusm-setup-wide">
			<h2><?php esc_html_e( 'Create or replace a product recipe', 'laqi-unit-stock-manager' ); ?></h2>
			<p><?php esc_html_e( 'Define every ingredient and packaging pool consumed by one sold item. Saving the same product or variation replaces its future recipe; existing order snapshots remain unchanged.', 'laqi-unit-stock-manager' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="laqi_lusm_save_recipe" />
				<?php wp_nonce_field( 'laqi_lusm_save_recipe' ); ?>
				<label for="laqi-lusm-recipe-product"><?php esc_html_e( 'Product or variation', 'laqi-unit-stock-manager' ); ?></label>
				<select id="laqi-lusm-recipe-product" class="wc-product-search" name="purchasable_id" data-placeholder="<?php esc_attr_e( 'Search for a product or variation', 'laqi-unit-stock-manager' ); ?>" data-action="woocommerce_json_search_products_and_variations" data-allow_clear="true" required></select>
				<table class="widefat striped laqi-lusm-recipe-components">
					<thead><tr><th><?php esc_html_e( 'Role', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Inventory pool', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Quantity per sold item', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Unit', 'laqi-unit-stock-manager' ); ?></th></tr></thead>
					<tbody>
					<?php for ( $index = 0; $index < 8; $index++ ) : ?>
						<?php
						$row_number = $index + 1;
						/* translators: %d: recipe component row number. */
						$role_label = sprintf( __( 'Component %d role', 'laqi-unit-stock-manager' ), $row_number );
						/* translators: %d: recipe component row number. */
						$pool_label = sprintf( __( 'Component %d inventory pool', 'laqi-unit-stock-manager' ), $row_number );
						/* translators: %d: recipe component row number. */
						$quantity_label = sprintf( __( 'Component %d quantity per sold item', 'laqi-unit-stock-manager' ), $row_number );
						/* translators: %d: recipe component row number. */
						$unit_label = sprintf( __( 'Component %d unit', 'laqi-unit-stock-manager' ), $row_number );
						?>
						<tr>
							<td><select name="component_role[]" aria-label="<?php echo esc_attr( $role_label ); ?>"><option value="contents"><?php esc_html_e( 'Contents', 'laqi-unit-stock-manager' ); ?></option><option value="container"><?php esc_html_e( 'Container', 'laqi-unit-stock-manager' ); ?></option><option value="closure"><?php esc_html_e( 'Closure', 'laqi-unit-stock-manager' ); ?></option><option value="label"><?php esc_html_e( 'Label', 'laqi-unit-stock-manager' ); ?></option><option value="packaging"><?php esc_html_e( 'Other packaging', 'laqi-unit-stock-manager' ); ?></option></select></td>
							<td><select class="laqi-lusm-pool-search" name="component_pool[]" aria-label="<?php echo esc_attr( $pool_label ); ?>" data-placeholder="<?php esc_attr_e( 'Search inventory pools', 'laqi-unit-stock-manager' ); ?>"></select></td>
							<td><input name="component_quantity[]" type="text" inputmode="decimal" aria-label="<?php echo esc_attr( $quantity_label ); ?>" /></td>
							<td><select name="component_unit[]" aria-label="<?php echo esc_attr( $unit_label ); ?>"><option value=""><?php esc_html_e( 'Choose unit', 'laqi-unit-stock-manager' ); ?></option>
							<?php
							foreach ( $this->units->all() as $unit ) :
								?>
								<option value="<?php echo esc_attr( $unit->key() ); ?>"><?php echo esc_html( $unit->key() . ' (' . $unit->system() . ')' ); ?></option><?php endforeach; ?></select></td>
						</tr>
					<?php endfor; ?>
					</tbody>
				</table>
				<p class="description"><?php esc_html_e( 'Complete at least two rows. Empty rows are ignored. Components are normalized independently, so a recipe can combine mass, volume, and count pools without density conversion.', 'laqi-unit-stock-manager' ); ?></p>
				<label for="laqi-lusm-recipe-native-stock"><?php esc_html_e( 'Existing WooCommerce quantity management', 'laqi-unit-stock-manager' ); ?></label>
				<select id="laqi-lusm-recipe-native-stock" name="native_stock_decision"><option value="disable"><?php esc_html_e( 'Disable native quantity management', 'laqi-unit-stock-manager' ); ?></option><option value="keep"><?php esc_html_e( 'Keep unchanged', 'laqi-unit-stock-manager' ); ?></option></select>
				<?php submit_button( __( 'Save recipe', 'laqi-unit-stock-manager' ) ); ?>
			</form>
		</section>
		<?php
	}

	/** Render result notice. @return void */
	private function notice(): void {
		$result = isset( $_GET['laqi_lusm_recipe_result'] ) ? sanitize_key( wp_unslash( $_GET['laqi_lusm_recipe_result'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'saved' === $result ) {
			wp_admin_notice(
				__( 'Recipe saved for future purchases.', 'laqi-unit-stock-manager' ),
				array(
					'type'        => 'success',
					'dismissible' => true,
				)
			);
		} elseif ( 'error' === $result ) {
			wp_admin_notice( __( 'The recipe could not be saved. Complete at least two valid component rows and check their units.', 'laqi-unit-stock-manager' ), array( 'type' => 'error' ) );
		}
	}
}
