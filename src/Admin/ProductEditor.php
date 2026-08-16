<?php
/**
 * Unit Stock controls in the WooCommerce product editor.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Admin;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Domain\ProductMapping;
use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Presentation\QuantityFormatter;
use LaqiUnitStockManager\Storage\MappingRepository;
use LaqiUnitStockManager\Storage\PoolRepository;
use LaqiUnitStockManager\Unit\UnitRegistry;
use LaqiUnitStockManager\WooCommerce\ExistingStockMigrator;
use Throwable;

/** Places product-specific mapping controls alongside WooCommerce product data. */
final class ProductEditor {

	/**
	 * Pool persistence.
	 *
	 * @var PoolRepository
	 */
	private $pools;

	/**
	 * Mapping persistence.
	 *
	 * @var MappingRepository
	 */
	private $mappings;

	/**
	 * Unit definitions.
	 *
	 * @var UnitRegistry
	 */
	private $units;

	/**
	 * Quantity formatter.
	 *
	 * @var QuantityFormatter
	 */
	private $formatter;

	/**
	 * Native stock migration.
	 *
	 * @var ExistingStockMigrator
	 */
	private $stock_migrator;

	/**
	 * Construct the product editor integration.
	 *
	 * @param PoolRepository        $pools Pool persistence.
	 * @param MappingRepository     $mappings Mapping persistence.
	 * @param UnitRegistry          $units Unit definitions.
	 * @param QuantityFormatter     $formatter Quantity formatter.
	 * @param ExistingStockMigrator $stock_migrator Native stock migration.
	 */
	public function __construct( PoolRepository $pools, MappingRepository $mappings, UnitRegistry $units, QuantityFormatter $formatter, ExistingStockMigrator $stock_migrator ) {
		$this->pools          = $pools;
		$this->mappings       = $mappings;
		$this->units          = $units;
		$this->formatter      = $formatter;
		$this->stock_migrator = $stock_migrator;
	}

	/** Register product-data hooks. @return void */
	public function register(): void {
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_panel' ) );
		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'render_variation_fields' ), 20, 3 );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product' ) );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation' ), 20, 2 );
	}

	/**
	 * Add the product-data tab.
	 *
	 * @param array<string, mixed> $tabs Product-data tabs.
	 * @return array<string, mixed>
	 */
	public function add_tab( array $tabs ): array {
		$tabs['laqi_lusm'] = array(
			'label'    => __( 'Unit Stock', 'laqi-unit-stock-manager' ),
			'target'   => 'laqi_lusm_product_data',
			'class'    => array( 'show_if_simple', 'show_if_variable' ),
			'priority' => 65,
		);
		return $tabs;
	}

	/** Render product or variation mapping forms. @return void */
	public function render_panel(): void {
		global $post;
		$product = $post ? wc_get_product( $post->ID ) : false;
		?>
		<div id="laqi_lusm_product_data" class="panel woocommerce_options_panel hidden">
			<p class="laqi-lusm-product-editor-intro"><?php esc_html_e( 'Connect this product to the exact pooled quantity consumed by each sale. The Unit Stock workspace remains available for bulk setup and inventory operations.', 'laqi-unit-stock-manager' ); ?></p>
			<?php
			if ( ! $product ) {
				echo '<p class="laqi-lusm-product-editor-intro">' . esc_html__( 'Save the product before configuring Unit Stock.', 'laqi-unit-stock-manager' ) . '</p>';
			} elseif ( $product->is_type( 'variable' ) ) {
				$children = $product->get_children();
				if ( array() === $children ) {
					echo '<p class="laqi-lusm-product-editor-intro">' . esc_html__( 'Create and save at least one variation before configuring Unit Stock.', 'laqi-unit-stock-manager' ) . '</p>';
				} else {
					$configured = 0;
					foreach ( $children as $variation_id ) {
						if ( $this->mappings->find_for_product( $product->get_id(), $variation_id ) ) {
							++$configured;
						}
					}
					$this->render_variable_summary( $configured, count( $children ) );
				}
			} else {
				$this->render_mapping( $product->get_id(), 0, $product->get_name() );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render Unit Stock fields inside one native variation panel.
	 *
	 * @param int      $loop Variation loop index.
	 * @param array    $variation_data Variation metadata.
	 * @param \WP_Post $variation Variation post.
	 * @return void
	 */
	public function render_variation_fields( int $loop, array $variation_data, \WP_Post $variation ): void {
		unset( $loop, $variation_data );
		$product_id = (int) $variation->post_parent;
		if ( $product_id > 0 ) {
			$this->render_mapping( $product_id, (int) $variation->ID, __( 'Unit Stock', 'laqi-unit-stock-manager' ), true );
		}
	}

	/**
	 * Render the compact variable-product summary.
	 *
	 * @param int $configured Number of configured variations.
	 * @param int $total Total saved variations.
	 * @return void
	 */
	private function render_variable_summary( int $configured, int $total ): void {
		?>
		<section class="laqi-lusm-product-mapping laqi-lusm-variable-summary">
			<h4><?php esc_html_e( 'Variation mappings', 'laqi-unit-stock-manager' ); ?></h4>
			<p>
			<?php
			printf(
				/* translators: 1: configured variation count, 2: total variation count. */
				esc_html__( '%1$d of %2$d variations are configured for Unit Stock.', 'laqi-unit-stock-manager' ),
				(int) $configured,
				(int) $total
			);
			?>
			</p>
			<button type="button" class="button" data-laqi-lusm-open-variations><?php esc_html_e( 'Open variations', 'laqi-unit-stock-manager' ); ?></button>
			<p class="description"><?php esc_html_e( 'Expand a variation to manage its pool, consumption quantity, unit, or recipe link.', 'laqi-unit-stock-manager' ); ?></p>
		</section>
		<?php
	}

	/**
	 * Render one purchasable mapping.
	 *
	 * @param int    $product_id Product ID.
	 * @param int    $variation_id Variation ID or zero.
	 * @param string $label Purchasable label.
	 * @param bool   $variation_context Whether this renders inside a variation.
	 * @return void
	 */
	private function render_mapping( int $product_id, int $variation_id, string $label, bool $variation_context = false ): void {
		$purchasable_id = $variation_id > 0 ? $variation_id : $product_id;
		$mapping        = $this->mappings->find_for_product( $product_id, $variation_id );
		if ( $mapping && 'single_pool' !== $mapping->calculator_type() ) {
			?>
			<section class="laqi-lusm-product-mapping <?php echo $variation_context ? 'laqi-lusm-variation-mapping' : ''; ?>">
				<h4><?php echo esc_html( $label ); ?></h4>
				<p><?php esc_html_e( 'This product uses a multi-component recipe. Edit it in the Unit Stock workspace.', 'laqi-unit-stock-manager' ); ?></p>
				<a class="button" href="<?php echo esc_url( $this->workspace_url() ); ?>"><?php esc_html_e( 'Open product links', 'laqi-unit-stock-manager' ); ?></a>
			</section>
			<?php
			return;
		}

		$component = $mapping ? current( $mapping->components() ) : false;
		$pool      = $component ? $this->pools->find( $component->pool_id() ) : null;
		$editable  = $pool && $component ? $this->formatter->editable( new Quantity( $pool->quantity()->family(), $component->consumption() ), $pool->display_unit() ) : array(
			'value' => '',
			'unit'  => '',
		);
		?>
		<section class="laqi-lusm-product-mapping <?php echo $variation_context ? 'laqi-lusm-variation-mapping' : ''; ?>">
			<h4><?php echo esc_html( $label ); ?></h4>
			<div class="laqi-lusm-product-mapping-grid">
				<?php if ( $mapping ) : ?>
					<input type="hidden" name="laqi_lusm_mapping[<?php echo esc_attr( $purchasable_id ); ?>][mapping_id]" value="<?php echo esc_attr( $mapping->id() ); ?>" />
					<input type="hidden" name="laqi_lusm_mapping[<?php echo esc_attr( $purchasable_id ); ?>][mapping_version]" value="<?php echo esc_attr( $mapping->version() ); ?>" />
				<?php endif; ?>
				<label><span><?php esc_html_e( 'Inventory pool', 'laqi-unit-stock-manager' ); ?></span><select name="laqi_lusm_mapping[<?php echo esc_attr( $purchasable_id ); ?>][pool_id]"><?php $this->render_pool_options( $pool ? $pool->id() : 0 ); ?></select></label>
				<label><span><?php esc_html_e( 'Consumption per sale', 'laqi-unit-stock-manager' ); ?></span><input name="laqi_lusm_mapping[<?php echo esc_attr( $purchasable_id ); ?>][consumption]" type="text" inputmode="decimal" value="<?php echo esc_attr( $editable['value'] ); ?>" /></label>
				<label><span><?php esc_html_e( 'Unit', 'laqi-unit-stock-manager' ); ?></span><select name="laqi_lusm_mapping[<?php echo esc_attr( $purchasable_id ); ?>][consumption_unit]"><?php $this->render_unit_options( $editable['unit'] ); ?></select></label>
				<?php if ( ! $mapping ) : ?>
					<label><span><?php esc_html_e( 'Existing WooCommerce stock', 'laqi-unit-stock-manager' ); ?></span><select name="laqi_lusm_mapping[<?php echo esc_attr( $purchasable_id ); ?>][existing_stock_decision]"><option value="disable"><?php esc_html_e( 'Disable native stock management', 'laqi-unit-stock-manager' ); ?></option><option value="transfer"><?php esc_html_e( 'Transfer it into the pool', 'laqi-unit-stock-manager' ); ?></option><option value="keep"><?php esc_html_e( 'Keep it unchanged', 'laqi-unit-stock-manager' ); ?></option></select></label>
				<?php else : ?>
					<label class="laqi-lusm-product-unlink"><input type="checkbox" name="laqi_lusm_mapping[<?php echo esc_attr( $purchasable_id ); ?>][unlink]" value="1" /> <span><?php esc_html_e( 'Unlink from Unit Stock', 'laqi-unit-stock-manager' ); ?></span></label>
				<?php endif; ?>
			</div>
			<p class="description">
				<?php
				if ( $variation_context ) {
					esc_html_e( 'Unit Stock changes use this variation panel’s Save changes button.', 'laqi-unit-stock-manager' );
				} else {
					esc_html_e( 'Unit Stock changes are saved when you update the product.', 'laqi-unit-stock-manager' );
				}
				?>
			</p>
		</section>
		<?php
	}

	/** Save a simple product's native mapping fields.
	 *
	 * @param int $product_id Product being saved.
	 * @return void */
	public function save_product( int $product_id ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! isset( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' ) ) {
			return;
		}
		$product = wc_get_product( $product_id );
		if ( $product && ! $product->is_type( 'variable' ) ) {
			$this->save_submitted_mapping( $product_id, $product_id );
		}
	}

	/**
	 * Save one variation's native mapping fields.
	 *
	 * WooCommerce authorizes its variation-save request before firing this hook.
	 *
	 * @param int $variation_id Variation being saved.
	 * @param int $loop Variation loop index.
	 * @return void
	 */
	public function save_variation( int $variation_id, int $loop ): void {
		unset( $loop );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$product_id = (int) wp_get_post_parent_id( $variation_id );
		if ( $product_id > 0 ) {
			$this->save_submitted_mapping( $product_id, $variation_id );
		}
	}

	/**
	 * Save one matching item from the submitted mapping collection.
	 *
	 * @param int $product_id Parent/simple product ID.
	 * @param int $purchasable_id Product or variation ID.
	 * @return void
	 */
	private function save_submitted_mapping( int $product_id, int $purchasable_id ): void {
		// WooCommerce verifies its product or variation nonce before these save hooks run.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$submitted = isset( $_POST['laqi_lusm_mapping'] ) && is_array( $_POST['laqi_lusm_mapping'] ) ? map_deep( wp_unslash( $_POST['laqi_lusm_mapping'] ), 'sanitize_text_field' ) : array();
		$fields    = isset( $submitted[ $purchasable_id ] ) && is_array( $submitted[ $purchasable_id ] ) ? $submitted[ $purchasable_id ] : null;
		if ( null === $fields ) {
			return;
		}
		try {
			$this->save_mapping_fields( $product_id, $purchasable_id, $fields );
		} catch ( Throwable $error ) {
			\WC_Admin_Meta_Boxes::add_error( $error->getMessage() );
		}
	}

	/**
	 * Validate and persist one submitted mapping.
	 *
	 * @param int                  $product_id Parent/simple product ID.
	 * @param int                  $purchasable_id Product or variation ID.
	 * @param array<string, mixed> $fields Submitted fields.
	 * @return void
	 * @throws \InvalidArgumentException When submitted mapping fields are invalid.
	 */
	private function save_mapping_fields( int $product_id, int $purchasable_id, array $fields ): void {
		$variation_id = $purchasable_id === $product_id ? 0 : $purchasable_id;
		$mapping      = $this->mappings->find_for_product( $product_id, $variation_id );
		if ( $mapping && ! empty( $fields['unlink'] ) ) {
			$this->mappings->deactivate( $mapping->id() );
			do_action( 'laqi_lusm_mapping_changed', $product_id, $variation_id, 0 );
			return;
		}
		$pool_id = isset( $fields['pool_id'] ) ? absint( $fields['pool_id'] ) : 0;
		if ( 0 === $pool_id && ! $mapping ) {
			return;
		}
		$pool = $this->pools->find( $pool_id );
		if ( null === $pool ) {
			throw new \InvalidArgumentException( esc_html__( 'Unit Stock requires a valid inventory pool.', 'laqi-unit-stock-manager' ) );
		}
		$unit        = isset( $fields['consumption_unit'] ) ? sanitize_key( $fields['consumption_unit'] ) : '';
		$consumption = isset( $fields['consumption'] ) ? sanitize_text_field( $fields['consumption'] ) : '';
		$quantity    = $this->units->normalize( $consumption, $unit );
		if ( $quantity->family() !== $pool->quantity()->family() || $quantity->amount() < 1 ) {
			throw new \InvalidArgumentException( esc_html__( 'Unit Stock consumption must be positive and use the pool measurement family.', 'laqi-unit-stock-manager' ) );
		}
		$version = $mapping && isset( $fields['mapping_version'] ) ? absint( $fields['mapping_version'] ) : null;
		$this->mappings->save_single_pool( $product_id, $variation_id, $pool_id, $quantity->amount(), (bool) $mapping, $version );
		if ( ! $mapping ) {
			$decision = isset( $fields['existing_stock_decision'] ) ? sanitize_key( $fields['existing_stock_decision'] ) : 'disable';
			$this->stock_migrator->apply( wc_get_product( $purchasable_id ), $pool_id, $quantity->amount(), $decision );
		}
		do_action( 'laqi_lusm_mapping_changed', $product_id, $variation_id, $pool_id );
	}

	/**
	 * Render pool options.
	 *
	 * @param int $selected Selected pool ID.
	 * @return void
	 */
	private function render_pool_options( int $selected ): void {
		echo '<option value="">' . esc_html__( 'Select an inventory pool', 'laqi-unit-stock-manager' ) . '</option>';
		foreach ( $this->pools->search( '', 500 ) as $pool ) {
			echo '<option value="' . esc_attr( $pool->id() ) . '" ' . selected( $selected, $pool->id(), false ) . '>' . esc_html( $pool->name() . ' (' . $pool->display_unit() . ')' ) . '</option>';
		}
	}

	/**
	 * Render unit options.
	 *
	 * @param string $selected Selected key.
	 * @return void
	 */
	private function render_unit_options( string $selected ): void {
		foreach ( $this->units->all() as $unit ) {
			echo '<option value="' . esc_attr( $unit->key() ) . '" ' . selected( $selected, $unit->key(), false ) . '>' . esc_html( $unit->label() . ' (' . $unit->symbol() . ')' ) . '</option>';
		}
	}

	/** Product links workspace URL. @return string */
	private function workspace_url(): string {
		return add_query_arg(
			array(
				'post_type'  => 'product',
				'page'       => UnitStockPage::SLUG,
				'section'    => 'setup',
				'setup_view' => 'products',
			),
			admin_url( 'edit.php' )
		);
	}
}
