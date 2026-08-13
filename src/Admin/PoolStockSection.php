<?php
/**
 * Free inventory-pool stock section.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Admin;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Presentation\PoolPresenter;
use LaqiUnitStockManager\Storage\PoolRepository;
use LaqiUnitStockManager\Storage\MappingRepository;
use LaqiUnitStockManager\Availability\AvailabilityService;
use LaqiUnitStockManager\Diagnostics\MappingDiagnostics;
use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Presentation\QuantityFormatter;

/**
 * Renders searchable pool balances and inline adjustment controls.
 */
final class PoolStockSection implements ScreenSectionInterface {

	/**
	 * Pool persistence.
	 *
	 * @var PoolRepository
	 */
	private $pools;

	/**
	 * Shared row presenter.
	 *
	 * @var PoolPresenter
	 */
	private $presenter;

	/** Mapping persistence.
	 *
	 * @var MappingRepository */
	private $mappings;

	/** Availability calculations.
	 *
	 * @var AvailabilityService */
	private $availability;

	/** Exact quantity formatting.
	 *
	 * @var QuantityFormatter */
	private $formatter;

	/** Configuration diagnostics.
	 *
	 * @var MappingDiagnostics */
	private $diagnostics;

	/**
	 * Constructor.
	 *
	 * @param PoolRepository      $pools     Pool repository.
	 * @param PoolPresenter       $presenter    Shared pool presenter.
	 * @param MappingRepository   $mappings     Mapping persistence.
	 * @param AvailabilityService $availability Availability calculations.
	 * @param QuantityFormatter   $formatter    Exact quantity formatting.
	 * @param MappingDiagnostics  $diagnostics  Configuration diagnostics.
	 */
	public function __construct( PoolRepository $pools, PoolPresenter $presenter, MappingRepository $mappings, AvailabilityService $availability, QuantityFormatter $formatter, MappingDiagnostics $diagnostics ) {
		$this->pools        = $pools;
		$this->presenter    = $presenter;
		$this->mappings     = $mappings;
		$this->availability = $availability;
		$this->formatter    = $formatter;
		$this->diagnostics  = $diagnostics;
	}

	/** Get the section ID. @return string */
	public function id(): string {
		return 'stock';
	}

	/** Get the section title. @return string */
	public function title(): string {
		return __( 'Stock', 'laqi-unit-stock-manager' );
	}

	/** Render the stock table. @return void */
	public function render(): void {
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rows   = array_map( array( $this->presenter, 'present' ), $this->pools->search( $search ) );
		?>
		<form method="get" class="laqi-lusm-search">
			<input type="hidden" name="page" value="laqi-unit-stock-manager" />
			<label class="screen-reader-text" for="laqi-lusm-search"><?php esc_html_e( 'Search inventory pools', 'laqi-unit-stock-manager' ); ?></label>
			<input id="laqi-lusm-search" type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search pools or SKU', 'laqi-unit-stock-manager' ); ?>" />
			<?php submit_button( __( 'Search', 'laqi-unit-stock-manager' ), 'secondary', '', false ); ?>
		</form>
		<table class="widefat striped laqi-lusm-stock-table">
			<thead><tr>
				<th scope="col"><?php esc_html_e( 'Inventory pool', 'laqi-unit-stock-manager' ); ?></th>
				<th scope="col"><?php esc_html_e( 'On hand', 'laqi-unit-stock-manager' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Linked products', 'laqi-unit-stock-manager' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Adjustment', 'laqi-unit-stock-manager' ); ?></th>
			</tr></thead>
			<tbody>
			<?php if ( array() === $rows ) : ?>
				<tr><td colspan="4"><?php esc_html_e( 'No inventory pools found.', 'laqi-unit-stock-manager' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $rows as $row ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $row['name'] ); ?></th>
					<td><strong><?php echo esc_html( $row['quantity_display'] ); ?></strong></td>
					<td><?php $this->render_links( (int) $row['id'] ); ?></td>
					<td><?php $this->render_adjustment_form( $row ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/** Render linked products and variation availability.
	 *
	 * @param int $pool_id Pool ID.
	 * @return void */
	private function render_links( int $pool_id ): void {
		$mappings = $this->mappings->find_for_pool( $pool_id );
		$pool     = $this->pools->find( $pool_id );
		if ( array() === $mappings ) {
			echo '<span class="notice-warning">' . esc_html__( 'No products linked', 'laqi-unit-stock-manager' ) . '</span>';
			return;
		}
		echo '<ul class="laqi-lusm-linked-products">';
		foreach ( $mappings as $mapping ) {
			$product = wc_get_product( $mapping->variation_id() > 0 ? $mapping->variation_id() : $mapping->product_id() );
			if ( ! $product ) {
				continue;
			}
			$saleable  = $this->availability->saleable_quantity( $mapping->product_id(), $mapping->variation_id() );
			$component = current(
				array_filter(
					$mapping->components(),
					static function ( $candidate ) use ( $pool_id ): bool {
						return $candidate->pool_id() === $pool_id;
					}
				)
			);
			echo '<li><strong>' . esc_html( $product->get_formatted_name() ) . '</strong><br />';
			if ( $component && $pool ) {
				/* translators: %s: exact pool quantity consumed by one sold item. */
				echo esc_html( sprintf( __( 'Uses %s per item.', 'laqi-unit-stock-manager' ), $this->formatter->format( new Quantity( $pool->quantity()->family(), $component->consumption() ), $pool->display_unit() ) ) ) . ' ';
			}
			/* translators: %s: number of saleable items or Unlimited. */
			echo esc_html( sprintf( __( 'Saleable: %s', 'laqi-unit-stock-manager' ), null === $saleable ? __( 'Unlimited', 'laqi-unit-stock-manager' ) : number_format_i18n( $saleable ) ) );
			foreach ( $this->diagnostics->inspect( $mapping ) as $warning ) {
				echo '<span class="laqi-lusm-warning">' . esc_html( $warning ) . '</span>';
			}
			echo '</li>';
		}
		echo '</ul>';
	}

	/**
	 * Render one adjustment form.
	 *
	 * @param array<string, mixed> $row Presented pool row.
	 * @return void
	 */
	private function render_adjustment_form( array $row ): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="laqi-lusm-adjustment">
			<input type="hidden" name="action" value="laqi_lusm_adjust_stock" />
			<input type="hidden" name="pool_id" value="<?php echo esc_attr( $row['id'] ); ?>" />
			<input type="hidden" name="unit" value="<?php echo esc_attr( $row['display_unit'] ); ?>" />
			<?php wp_nonce_field( 'laqi_lusm_adjust_stock_' . $row['id'] ); ?>
			<label class="screen-reader-text" for="laqi-lusm-mode-<?php echo esc_attr( $row['id'] ); ?>"><?php esc_html_e( 'Adjustment type', 'laqi-unit-stock-manager' ); ?></label>
			<select id="laqi-lusm-mode-<?php echo esc_attr( $row['id'] ); ?>" name="mode">
				<option value="set"><?php esc_html_e( 'Set to', 'laqi-unit-stock-manager' ); ?></option>
				<option value="add"><?php esc_html_e( 'Add', 'laqi-unit-stock-manager' ); ?></option>
				<option value="subtract"><?php esc_html_e( 'Subtract', 'laqi-unit-stock-manager' ); ?></option>
			</select>
			<label class="screen-reader-text" for="laqi-lusm-quantity-<?php echo esc_attr( $row['id'] ); ?>"><?php esc_html_e( 'Quantity', 'laqi-unit-stock-manager' ); ?></label>
			<input id="laqi-lusm-quantity-<?php echo esc_attr( $row['id'] ); ?>" name="quantity" type="text" inputmode="decimal" required size="10" />
			<span><?php echo esc_html( $row['display_unit'] ); ?></span>
			<label class="screen-reader-text" for="laqi-lusm-reason-<?php echo esc_attr( $row['id'] ); ?>"><?php esc_html_e( 'Reason', 'laqi-unit-stock-manager' ); ?></label>
			<input id="laqi-lusm-reason-<?php echo esc_attr( $row['id'] ); ?>" name="reason" type="text" placeholder="<?php esc_attr_e( 'Reason (optional)', 'laqi-unit-stock-manager' ); ?>" />
			<?php submit_button( __( 'Apply', 'laqi-unit-stock-manager' ), 'secondary small', '', false ); ?>
		</form>
		<?php
	}
}
