<?php
/**
 * Inventory pool and product mapping setup section.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Admin;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Storage\PoolRepository;
use LaqiUnitStockManager\Unit\UnitRegistry;
use WC_Product;

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
	 * Constructor.
	 *
	 * @param PoolRepository $pools Pool repository.
	 * @param UnitRegistry   $units Unit registry.
	 */
	public function __construct( PoolRepository $pools, UnitRegistry $units ) {
		$this->pools = $pools;
		$this->units = $units;
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
		</div>
		<?php
	}

	/** Render the mapping form. @return void */
	private function render_mapping_form(): void {
		$pools    = $this->pools->search( '', 500 );
		$products = $this->products();
		if ( array() === $pools ) {
			echo '<p class="notice notice-info inline">' . esc_html__( 'Create an inventory pool before linking products.', 'laqi-unit-stock-manager' ) . '</p>';
			return;
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="laqi_lusm_save_mapping" />
			<?php wp_nonce_field( 'laqi_lusm_save_mapping' ); ?>
			<label for="laqi-lusm-product"><?php esc_html_e( 'Product or variation', 'laqi-unit-stock-manager' ); ?></label>
			<select id="laqi-lusm-product" name="purchasable" required>
				<option value=""><?php esc_html_e( 'Select a product', 'laqi-unit-stock-manager' ); ?></option>
				<?php foreach ( $products as $product ) : ?>
					<option value="<?php echo esc_attr( $this->product_value( $product ) ); ?>"><?php echo esc_html( $product->get_formatted_name() ); ?></option>
				<?php endforeach; ?>
			</select>
			<label for="laqi-lusm-pool"><?php esc_html_e( 'Inventory pool', 'laqi-unit-stock-manager' ); ?></label>
			<select id="laqi-lusm-pool" name="pool_id" required>
				<?php foreach ( $pools as $pool ) : ?>
					<option value="<?php echo esc_attr( $pool->id() ); ?>"><?php echo esc_html( $pool->name() . ' (' . $pool->display_unit() . ')' ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php $this->text_field( 'consumption', __( 'Consumption per sold item', 'laqi-unit-stock-manager' ), true, 'decimal' ); ?>
			<?php $this->unit_field( 'consumption_unit', __( 'Consumption unit', 'laqi-unit-stock-manager' ) ); ?>
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

	/** Get selectable products. @return WC_Product[] */
	private function products(): array {
		$ids      = get_posts(
			array(
				'post_type'   => array( 'product', 'product_variation' ),
				'post_status' => 'publish',
				'numberposts' => 50,
				'orderby'     => 'title',
				'order'       => 'ASC',
				'fields'      => 'ids',
			)
		);
		$products = array_filter( array_map( 'wc_get_product', $ids ) );

		return array_values(
			array_filter(
				$products,
				static function ( WC_Product $product ): bool {
					return $product->is_type( 'simple' ) || $product->is_type( 'variation' );
				}
			)
		);
	}

	/**
	 * Encode a purchasable ID pair.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	private function product_value( WC_Product $product ): string {
		return $product->is_type( 'variation' ) ? $product->get_parent_id() . ':' . $product->get_id() : $product->get_id() . ':0';
	}
}
