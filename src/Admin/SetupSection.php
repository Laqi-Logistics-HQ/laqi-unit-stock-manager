<?php
/**
 * Inventory pool and product mapping setup section.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Admin;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Storage\PoolRepository;
use LaqiUnitStockManager\Storage\CustomUnitRepository;
use LaqiUnitStockManager\Storage\MappingRepository;
use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Presentation\QuantityFormatter;
use LaqiUnitStockManager\Unit\UnitRegistry;

/**
 * Renders explicit pool creation and product-consumption forms.
 */
final class SetupSection implements ScreenSectionInterface {

	/**
	 * Pool persistence.
	 *
	 * @var PoolRepository
	 */
	private $pools;

	/**
	 * Unit definitions.
	 *
	 * @var UnitRegistry
	 */
	private $units;

	/**
	 * Custom-unit persistence.
	 *
	 * @var CustomUnitRepository
	 */
	private $custom_units;

	/**
	 * Product mapping persistence.
	 *
	 * @var MappingRepository
	 */
	private $mappings;

	/**
	 * Exact quantity formatting.
	 *
	 * @var QuantityFormatter
	 */
	private $formatter;

	/**
	 * Constructor.
	 *
	 * @param PoolRepository       $pools Pool repository.
	 * @param UnitRegistry         $units Unit registry.
	 * @param CustomUnitRepository $custom_units Custom-unit persistence.
	 * @param MappingRepository    $mappings Product mapping persistence.
	 * @param QuantityFormatter    $formatter Exact quantity formatting.
	 */
	public function __construct( PoolRepository $pools, UnitRegistry $units, CustomUnitRepository $custom_units, MappingRepository $mappings, QuantityFormatter $formatter ) {
		$this->pools        = $pools;
		$this->units        = $units;
		$this->custom_units = $custom_units;
		$this->mappings     = $mappings;
		$this->formatter    = $formatter;
	}

	/** Get the section ID. @return string */
	public function id(): string {
		return 'setup';
	}

	/** Get the section title. @return string */
	public function title(): string {
		return __( 'Setup', 'laqi-unit-stock-manager' );
	}

	/** Render setup forms. @return void */
	public function render(): void {
		?>
		<div class="laqi-lusm-setup-grid">
			<section class="card">
				<h2><?php esc_html_e( 'Create inventory pool', 'laqi-unit-stock-manager' ); ?></h2>
				<p><?php esc_html_e( 'Create the authoritative bulk quantity that products and variations will consume.', 'laqi-unit-stock-manager' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="laqi_lusm_create_pool" />
					<?php wp_nonce_field( 'laqi_lusm_create_pool' ); ?>
					<?php $this->text_field( 'pool_name', __( 'Pool name', 'laqi-unit-stock-manager' ), true ); ?>
					<?php $this->text_field( 'internal_sku', __( 'Internal SKU', 'laqi-unit-stock-manager' ), false ); ?>
					<?php $this->unit_field( 'display_unit', __( 'Stock unit', 'laqi-unit-stock-manager' ) ); ?>
					<?php $this->text_field( 'opening_balance', __( 'Opening balance', 'laqi-unit-stock-manager' ), true, 'decimal' ); ?>
					<?php submit_button( __( 'Create pool', 'laqi-unit-stock-manager' ) ); ?>
				</form>
			</section>
			<section class="card">
				<h2><?php esc_html_e( 'Link a product or variation', 'laqi-unit-stock-manager' ); ?></h2>
				<p><?php esc_html_e( 'Choose exactly how much pooled stock one sold item consumes. Labels are never parsed automatically.', 'laqi-unit-stock-manager' ); ?></p>
				<?php $this->render_mapping_form(); ?>
			</section>
			<section class="card laqi-lusm-setup-wide">
				<h2><?php esc_html_e( 'Current product links', 'laqi-unit-stock-manager' ); ?></h2>
			<p><?php esc_html_e( 'Edit active consumption rules or unlink an item. Changes affect future purchases only and do not alter past order snapshots.', 'laqi-unit-stock-manager' ); ?></p>
				<?php $this->render_mappings(); ?>
			</section>
			<section class="card">
				<h2><?php esc_html_e( 'Create custom unit', 'laqi-unit-stock-manager' ); ?></h2>
				<p><?php esc_html_e( 'Define a merchant unit as an exact quantity of any existing unit, such as one sack equaling 25 kg.', 'laqi-unit-stock-manager' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="laqi_lusm_create_unit" />
					<?php wp_nonce_field( 'laqi_lusm_create_unit' ); ?>
					<?php $this->text_field( 'unit_key', __( 'Unit key', 'laqi-unit-stock-manager' ), true ); ?>
					<?php $this->text_field( 'unit_label', __( 'Label', 'laqi-unit-stock-manager' ), true ); ?>
					<?php $this->text_field( 'unit_symbol', __( 'Symbol', 'laqi-unit-stock-manager' ), true ); ?>
					<?php $this->text_field( 'reference_value', __( 'Equivalent quantity', 'laqi-unit-stock-manager' ), true, 'decimal' ); ?>
					<?php $this->unit_field( 'reference_unit', __( 'Of unit', 'laqi-unit-stock-manager' ) ); ?>
					<?php submit_button( __( 'Create custom unit', 'laqi-unit-stock-manager' ) ); ?>
				</form>
				<?php $this->render_custom_units(); ?>
			</section>
		</div>
		<?php
	}

	/** Render active product mappings. @return void */
	private function render_mappings(): void {
		$mappings = $this->mappings->active();
		$pools    = $this->pools->search( '', 500 );
		if ( array() === $mappings ) {
			echo '<p>' . esc_html__( 'No products or variations are linked yet.', 'laqi-unit-stock-manager' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped laqi-lusm-mapping-table">
			<thead><tr>
				<th scope="col"><?php esc_html_e( 'Product or variation', 'laqi-unit-stock-manager' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Consumption rule', 'laqi-unit-stock-manager' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Actions', 'laqi-unit-stock-manager' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $mappings as $mapping ) : ?>
				<?php
				$product   = wc_get_product( $mapping->variation_id() > 0 ? $mapping->variation_id() : $mapping->product_id() );
				$component = current( $mapping->components() );
				$pool      = $component ? $this->pools->find( $component->pool_id() ) : null;
				$editable  = $pool && $component ? $this->formatter->editable( new Quantity( $pool->quantity()->family(), $component->consumption() ), $pool->display_unit() ) : array(
					'value' => '',
					'unit'  => '',
				);
				?>
				<tr>
					<th scope="row">
					<?php
					/* translators: %d: unavailable WooCommerce product or variation ID. */
					echo esc_html( $product ? $product->get_formatted_name() : sprintf( __( 'Unavailable product #%d', 'laqi-unit-stock-manager' ), $mapping->variation_id() > 0 ? $mapping->variation_id() : $mapping->product_id() ) );
					?>
					</th>
					<td>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="laqi-lusm-mapping-edit">
							<input type="hidden" name="action" value="laqi_lusm_update_mapping" />
							<input type="hidden" name="mapping_id" value="<?php echo esc_attr( $mapping->id() ); ?>" />
							<input type="hidden" name="mapping_version" value="<?php echo esc_attr( $mapping->version() ); ?>" />
							<?php wp_nonce_field( 'laqi_lusm_update_mapping_' . $mapping->id() ); ?>
							<label class="screen-reader-text" for="laqi-lusm-edit-pool-<?php echo esc_attr( $mapping->id() ); ?>"><?php esc_html_e( 'Inventory pool', 'laqi-unit-stock-manager' ); ?></label>
							<select id="laqi-lusm-edit-pool-<?php echo esc_attr( $mapping->id() ); ?>" name="pool_id" required>
							<?php foreach ( $pools as $candidate_pool ) : ?>
								<option value="<?php echo esc_attr( $candidate_pool->id() ); ?>" <?php selected( $component && $component->pool_id() === $candidate_pool->id() ); ?>><?php echo esc_html( $candidate_pool->name() . ' (' . $candidate_pool->display_unit() . ')' ); ?></option>
							<?php endforeach; ?>
							</select>
							<label class="screen-reader-text" for="laqi-lusm-edit-consumption-<?php echo esc_attr( $mapping->id() ); ?>"><?php esc_html_e( 'Consumption per sold item', 'laqi-unit-stock-manager' ); ?></label>
							<input id="laqi-lusm-edit-consumption-<?php echo esc_attr( $mapping->id() ); ?>" name="consumption" type="text" inputmode="decimal" value="<?php echo esc_attr( $editable['value'] ); ?>" required size="8" />
							<label class="screen-reader-text" for="laqi-lusm-edit-unit-<?php echo esc_attr( $mapping->id() ); ?>"><?php esc_html_e( 'Consumption unit', 'laqi-unit-stock-manager' ); ?></label>
							<select id="laqi-lusm-edit-unit-<?php echo esc_attr( $mapping->id() ); ?>" name="consumption_unit" required>
							<?php foreach ( $this->units->all() as $unit ) : ?>
								<option value="<?php echo esc_attr( $unit->key() ); ?>" <?php selected( $editable['unit'] === $unit->key() ); ?>><?php echo esc_html( $unit->key() . ' (' . $unit->system() . ')' ); ?></option>
							<?php endforeach; ?>
							</select>
							<?php submit_button( __( 'Save changes', 'laqi-unit-stock-manager' ), 'secondary small', '', false ); ?>
						</form>
					</td>
					<td>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="laqi_lusm_unlink_mapping" />
							<input type="hidden" name="mapping_id" value="<?php echo esc_attr( $mapping->id() ); ?>" />
							<?php wp_nonce_field( 'laqi_lusm_unlink_mapping_' . $mapping->id() ); ?>
							<?php submit_button( __( 'Unlink', 'laqi-unit-stock-manager' ), 'secondary small', '', false ); ?>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/** Render existing custom definitions. @return void */
	private function render_custom_units(): void {
		$units = $this->custom_units->active();
		if ( array() === $units ) {
			return;
		}
		echo '<h3>' . esc_html__( 'Custom units', 'laqi-unit-stock-manager' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'Unused units can be retired. Units used by an inventory pool or another active custom unit are protected.', 'laqi-unit-stock-manager' ) . '</p>';
		echo '<ul class="laqi-lusm-custom-units">';
		foreach ( $units as $unit ) {
			/* translators: 1: unit label, 2: unit key, 3: equivalent quantity, 4: reference unit. */
			echo '<li><span>' . esc_html( sprintf( __( '%1$s (%2$s) = %3$s %4$s', 'laqi-unit-stock-manager' ), $unit['label'], $unit['unit_key'], $unit['reference_value'], $unit['reference_unit'] ) ) . '</span>';
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="laqi_lusm_retire_unit" />
				<input type="hidden" name="unit_id" value="<?php echo esc_attr( $unit['id'] ); ?>" />
				<?php wp_nonce_field( 'laqi_lusm_retire_unit_' . $unit['id'] ); ?>
				<?php submit_button( __( 'Retire', 'laqi-unit-stock-manager' ), 'secondary small', '', false ); ?>
			</form>
			<?php
			echo '</li>';
		}
		echo '</ul>';
	}

	/** Render the mapping form. @return void */
	private function render_mapping_form(): void {
		$pools = $this->pools->search( '', 500 );
		if ( array() === $pools ) {
			echo '<p class="notice notice-info inline">' . esc_html__( 'Create an inventory pool before linking products.', 'laqi-unit-stock-manager' ) . '</p>';
			return;
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="laqi_lusm_save_mapping" />
			<?php wp_nonce_field( 'laqi_lusm_save_mapping' ); ?>
			<label for="laqi-lusm-product"><?php esc_html_e( 'Product or variation', 'laqi-unit-stock-manager' ); ?></label>
			<select id="laqi-lusm-product" class="wc-product-search" name="purchasable_id" data-placeholder="<?php esc_attr_e( 'Search for a product or variation', 'laqi-unit-stock-manager' ); ?>" data-action="woocommerce_json_search_products_and_variations" data-allow_clear="true" required></select>
			<label for="laqi-lusm-pool"><?php esc_html_e( 'Inventory pool', 'laqi-unit-stock-manager' ); ?></label>
			<select id="laqi-lusm-pool" name="pool_id" required>
				<?php foreach ( $pools as $pool ) : ?>
					<option value="<?php echo esc_attr( $pool->id() ); ?>"><?php echo esc_html( $pool->name() . ' (' . $pool->display_unit() . ')' ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php $this->text_field( 'consumption', __( 'Consumption per sold item', 'laqi-unit-stock-manager' ), true, 'decimal' ); ?>
			<?php $this->unit_field( 'consumption_unit', __( 'Consumption unit', 'laqi-unit-stock-manager' ) ); ?>
			<label for="laqi-lusm-existing-stock"><?php esc_html_e( 'Existing WooCommerce stock', 'laqi-unit-stock-manager' ); ?></label>
			<select id="laqi-lusm-existing-stock" name="existing_stock_decision" required>
				<option value="disable"><?php esc_html_e( 'Disable native quantity management', 'laqi-unit-stock-manager' ); ?></option>
				<option value="transfer"><?php esc_html_e( 'Add native item count to this pool, then disable it', 'laqi-unit-stock-manager' ); ?></option>
				<option value="keep"><?php esc_html_e( 'Keep native quantity management unchanged', 'laqi-unit-stock-manager' ); ?></option>
			</select>
			<p class="description laqi-lusm-form-description"><?php esc_html_e( 'Transferring multiplies the current native item count by the consumption per item and adds that exact quantity to the pool once.', 'laqi-unit-stock-manager' ); ?></p>
			<?php submit_button( __( 'Save mapping', 'laqi-unit-stock-manager' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Render a text input.
	 *
	 * @param string $name       Field name.
	 * @param string $label      Field label.
	 * @param bool   $required   Whether required.
	 * @param string $input_mode Optional input mode.
	 * @return void
	 */
	private function text_field( string $name, string $label, bool $required, string $input_mode = 'text' ): void {
		echo '<label for="laqi-lusm-' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label>';
		echo '<input id="laqi-lusm-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" type="text" inputmode="' . esc_attr( $input_mode ) . '" ' . ( $required ? 'required' : '' ) . ' />';
	}

	/**
	 * Render a unit selector.
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @return void
	 */
	private function unit_field( string $name, string $label ): void {
		echo '<label for="laqi-lusm-' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label>';
		echo '<select id="laqi-lusm-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" required>';
		foreach ( $this->units->all() as $unit ) {
			echo '<option value="' . esc_attr( $unit->key() ) . '">' . esc_html( $unit->key() . ' (' . $unit->system() . ')' ) . '</option>';
		}
		echo '</select>';
	}
}
