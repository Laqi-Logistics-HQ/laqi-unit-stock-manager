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

	/** Active mappings displayed per page. */
	const PER_PAGE = 25;

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
	 * Shared admin pagination.
	 *
	 * @var PaginationRenderer
	 */
	private $pagination;

	/**
	 * Constructor.
	 *
	 * @param PoolRepository       $pools Pool repository.
	 * @param UnitRegistry         $units Unit registry.
	 * @param CustomUnitRepository $custom_units Custom-unit persistence.
	 * @param MappingRepository    $mappings Product mapping persistence.
	 * @param QuantityFormatter    $formatter Exact quantity formatting.
	 * @param PaginationRenderer   $pagination Shared admin pagination.
	 */
	public function __construct( PoolRepository $pools, UnitRegistry $units, CustomUnitRepository $custom_units, MappingRepository $mappings, QuantityFormatter $formatter, PaginationRenderer $pagination ) {
		$this->pools        = $pools;
		$this->units        = $units;
		$this->custom_units = $custom_units;
		$this->mappings     = $mappings;
		$this->formatter    = $formatter;
		$this->pagination   = $pagination;
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
		$views  = array(
			'pools'    => __( 'Inventory pools', 'laqi-unit-stock-manager' ),
			'products' => __( 'Product links', 'laqi-unit-stock-manager' ),
			'units'    => __( 'Custom units', 'laqi-unit-stock-manager' ),
		);
		$active = isset( $_GET['setup_view'] ) ? sanitize_key( wp_unslash( $_GET['setup_view'] ) ) : 'pools'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $views[ $active ] ) ) {
			$active = 'pools';
		}
		?>
		<nav class="nav-tab-wrapper laqi-lusm-subtabs" aria-label="<?php esc_attr_e( 'Setup workspace', 'laqi-unit-stock-manager' ); ?>">
			<?php
			foreach ( $views as $id => $label ) :
				$url = add_query_arg(
					array(
						'page'       => UnitStockPage::SLUG,
						'section'    => 'setup',
						'setup_view' => $id,
					),
					admin_url( 'admin.php' )
				);
				?>
				<a class="nav-tab <?php echo $id === $active ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( $url ); ?>" <?php echo $id === $active ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>
		<div class="laqi-lusm-setup-grid">
			<?php if ( 'pools' === $active ) : ?>
			<section class="card">
				<h2><?php esc_html_e( 'Create inventory pool', 'laqi-unit-stock-manager' ); ?></h2>
				<p><?php esc_html_e( 'Create the authoritative bulk quantity that products and variations will consume.', 'laqi-unit-stock-manager' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="laqi_lusm_create_pool" />
					<input type="hidden" name="setup_view" value="pools" />
					<?php wp_nonce_field( 'laqi_lusm_create_pool' ); ?>
					<?php $this->text_field( 'pool_name', __( 'Pool name', 'laqi-unit-stock-manager' ), true ); ?>
					<?php $this->text_field( 'internal_sku', __( 'Internal SKU', 'laqi-unit-stock-manager' ), false ); ?>
					<?php $this->unit_field( 'display_unit', __( 'Stock unit', 'laqi-unit-stock-manager' ) ); ?>
					<?php $this->text_field( 'opening_balance', __( 'Opening balance', 'laqi-unit-stock-manager' ), true, 'decimal' ); ?>
					<?php submit_button( __( 'Create pool', 'laqi-unit-stock-manager' ) ); ?>
				</form>
			</section>
			<?php elseif ( 'products' === $active ) : ?>
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
			<?php else : ?>
			<section class="card laqi-lusm-custom-unit-create">
				<h2><?php esc_html_e( 'Create custom unit', 'laqi-unit-stock-manager' ); ?></h2>
				<p><?php esc_html_e( 'Define a merchant unit as an exact quantity of any existing unit, such as one sack equaling 25 kg.', 'laqi-unit-stock-manager' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="laqi_lusm_create_unit" />
					<input type="hidden" name="setup_view" value="units" />
					<?php wp_nonce_field( 'laqi_lusm_create_unit' ); ?>
					<?php $this->text_field( 'unit_key', __( 'Unit key', 'laqi-unit-stock-manager' ), true ); ?>
					<?php $this->text_field( 'unit_label', __( 'Label', 'laqi-unit-stock-manager' ), true ); ?>
					<?php $this->text_field( 'unit_symbol', __( 'Symbol', 'laqi-unit-stock-manager' ), true ); ?>
					<?php $this->text_field( 'reference_value', __( 'Equivalent quantity', 'laqi-unit-stock-manager' ), true, 'decimal' ); ?>
					<?php $this->unit_field( 'reference_unit', __( 'Of unit', 'laqi-unit-stock-manager' ) ); ?>
					<?php submit_button( __( 'Create custom unit', 'laqi-unit-stock-manager' ) ); ?>
				</form>
			</section>
			<section class="card laqi-lusm-custom-unit-directory">
				<h2><?php esc_html_e( 'Custom units', 'laqi-unit-stock-manager' ); ?></h2>
				<p><?php esc_html_e( 'Review merchant-defined units and retire definitions that are no longer needed.', 'laqi-unit-stock-manager' ); ?></p>
				<?php $this->render_custom_units(); ?>
			</section>
			<?php endif; ?>
		</div>
		<?php
	}

	/** Render active product mappings. @return void */
	private function render_mappings(): void {
		$total       = $this->mappings->count_active();
		$total_pages = max( 1, intdiv( $total + self::PER_PAGE - 1, self::PER_PAGE ) );
		$page        = isset( $_GET['mapping_page'] ) ? absint( $_GET['mapping_page'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page        = max( 1, min( $total_pages, $page ) );
		$offset      = ( $page - 1 ) * self::PER_PAGE;
		$mappings    = $this->mappings->active( self::PER_PAGE, $offset );
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
					echo esc_html( $product ? wp_strip_all_tags( $product->get_formatted_name() ) : sprintf( __( 'Unavailable product #%d', 'laqi-unit-stock-manager' ), $mapping->variation_id() > 0 ? $mapping->variation_id() : $mapping->product_id() ) );
					?>
					</th>
					<td>
						<?php if ( 'single_pool' !== $mapping->calculator_type() ) : ?>
							<?php
							/* translators: 1: mapping calculator type, 2: component count. */
							echo esc_html( sprintf( __( '%1$s mapping with %2$d components. Use its dedicated section to edit it.', 'laqi-unit-stock-manager' ), $mapping->calculator_type(), count( $mapping->components() ) ) );
							?>
						<?php else : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="laqi-lusm-mapping-edit">
							<input type="hidden" name="action" value="laqi_lusm_update_mapping" />
							<input type="hidden" name="setup_view" value="products" />
							<input type="hidden" name="mapping_id" value="<?php echo esc_attr( $mapping->id() ); ?>" />
							<input type="hidden" name="mapping_version" value="<?php echo esc_attr( $mapping->version() ); ?>" />
							<?php wp_nonce_field( 'laqi_lusm_update_mapping_' . $mapping->id() ); ?>
							<label class="screen-reader-text" for="laqi-lusm-edit-pool-<?php echo esc_attr( $mapping->id() ); ?>"><?php esc_html_e( 'Inventory pool', 'laqi-unit-stock-manager' ); ?></label>
							<select id="laqi-lusm-edit-pool-<?php echo esc_attr( $mapping->id() ); ?>" class="laqi-lusm-pool-search" name="pool_id" data-placeholder="<?php esc_attr_e( 'Search inventory pools', 'laqi-unit-stock-manager' ); ?>" required>
							<?php if ( $pool ) : ?>
								<option value="<?php echo esc_attr( $pool->id() ); ?>" selected><?php echo esc_html( $pool->name() . ' (' . $pool->display_unit() . ')' ); ?></option>
							<?php endif; ?>
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
						<?php endif; ?>
					</td>
					<td>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="laqi_lusm_unlink_mapping" />
							<input type="hidden" name="setup_view" value="products" />
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
		/* translators: 1: first visible mapping number, 2: last visible mapping number, 3: total active mappings. */
		$summary = sprintf( __( 'Showing %1$d-%2$d of %3$d product links.', 'laqi-unit-stock-manager' ), $offset + 1, $offset + count( $mappings ), $total );
		$this->pagination->render(
			$summary,
			__( 'Product link pages', 'laqi-unit-stock-manager' ),
			'mapping_page',
			array(
				'page'       => UnitStockPage::SLUG,
				'section'    => 'setup',
				'setup_view' => 'products',
			),
			$page,
			$total_pages
		);
		?>
		<?php
	}

	/** Render existing custom definitions. @return void */
	private function render_custom_units(): void {
		$units = $this->custom_units->active();
		if ( array() === $units ) {
			echo '<p>' . esc_html__( 'No custom units have been created yet.', 'laqi-unit-stock-manager' ) . '</p>';
			return;
		}
		echo '<p class="description">' . esc_html__( 'Unused units can be retired. Units used by an inventory pool or another active custom unit are protected.', 'laqi-unit-stock-manager' ) . '</p>';
		echo '<table class="widefat striped laqi-lusm-custom-units"><thead><tr><th scope="col">' . esc_html__( 'Unit', 'laqi-unit-stock-manager' ) . '</th><th scope="col">' . esc_html__( 'Equivalent quantity', 'laqi-unit-stock-manager' ) . '</th><th scope="col">' . esc_html__( 'Actions', 'laqi-unit-stock-manager' ) . '</th></tr></thead><tbody>';
		foreach ( $units as $unit ) {
			echo '<tr><th scope="row">' . esc_html( $unit['label'] . ' (' . $unit['unit_key'] . ')' ) . '</th><td data-label="' . esc_attr__( 'Equivalent quantity', 'laqi-unit-stock-manager' ) . '">' . esc_html( $unit['reference_value'] . ' ' . $unit['reference_unit'] ) . '</td><td data-label="' . esc_attr__( 'Actions', 'laqi-unit-stock-manager' ) . '">';
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="laqi_lusm_retire_unit" />
				<input type="hidden" name="setup_view" value="units" />
				<input type="hidden" name="unit_id" value="<?php echo esc_attr( $unit['id'] ); ?>" />
				<?php wp_nonce_field( 'laqi_lusm_retire_unit_' . $unit['id'] ); ?>
				<?php submit_button( __( 'Retire', 'laqi-unit-stock-manager' ), 'secondary small', '', false ); ?>
			</form>
			<?php
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/** Render the mapping form. @return void */
	private function render_mapping_form(): void {
		if ( 0 === $this->pools->count_search() ) {
			echo '<p class="notice notice-info inline">' . esc_html__( 'Create an inventory pool before linking products.', 'laqi-unit-stock-manager' ) . '</p>';
			return;
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="laqi_lusm_save_mapping" />
			<input type="hidden" name="setup_view" value="products" />
			<?php wp_nonce_field( 'laqi_lusm_save_mapping' ); ?>
			<label for="laqi-lusm-product"><?php esc_html_e( 'Product or variation', 'laqi-unit-stock-manager' ); ?></label>
			<select id="laqi-lusm-product" class="wc-product-search" name="purchasable_id" data-placeholder="<?php esc_attr_e( 'Search for a product or variation', 'laqi-unit-stock-manager' ); ?>" data-action="woocommerce_json_search_products_and_variations" data-allow_clear="true" required></select>
			<label for="laqi-lusm-pool"><?php esc_html_e( 'Inventory pool', 'laqi-unit-stock-manager' ); ?></label>
		<select id="laqi-lusm-pool" class="laqi-lusm-pool-search" name="pool_id" data-placeholder="<?php esc_attr_e( 'Search inventory pools', 'laqi-unit-stock-manager' ); ?>" required></select>
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
			echo '<option value="' . esc_attr( $unit->key() ) . '">' . esc_html( $unit->label() . ' (' . $unit->symbol() . ') — ' . $this->system_label( $unit->system() ) ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Human-readable unit system.
	 *
	 * @param string $system Stored system key.
	 * @return string
	 */
	private function system_label( string $system ): string {
		$labels = array(
			'metric'       => __( 'Metric', 'laqi-unit-stock-manager' ),
			'imperial'     => __( 'Imperial', 'laqi-unit-stock-manager' ),
			'us_customary' => __( 'US customary', 'laqi-unit-stock-manager' ),
			'count'        => __( 'Count', 'laqi-unit-stock-manager' ),
			'custom'       => __( 'Custom', 'laqi-unit-stock-manager' ),
		);

		return isset( $labels[ $system ] ) ? $labels[ $system ] : $system;
	}
}
