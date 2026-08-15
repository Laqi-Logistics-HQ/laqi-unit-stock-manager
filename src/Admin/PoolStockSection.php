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
use LaqiUnitStockManager\Unit\UnitRegistry;

/**
 * Renders searchable pool balances and inline adjustment controls.
 */
final class PoolStockSection implements ScreenSectionInterface {

	/** Pools displayed per page. */
	const PER_PAGE = 25;

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
	 * Shared admin pagination.
	 *
	 * @var PaginationRenderer
	 */
	private $pagination;

	/**
	 * Unit definitions.
	 *
	 * @var UnitRegistry
	 */
	private $units;

	/**
	 * Constructor.
	 *
	 * @param PoolRepository      $pools     Pool repository.
	 * @param PoolPresenter       $presenter    Shared pool presenter.
	 * @param MappingRepository   $mappings     Mapping persistence.
	 * @param AvailabilityService $availability Availability calculations.
	 * @param QuantityFormatter   $formatter    Exact quantity formatting.
	 * @param MappingDiagnostics  $diagnostics  Configuration diagnostics.
	 * @param PaginationRenderer  $pagination   Shared admin pagination.
	 * @param UnitRegistry        $units        Unit definitions.
	 */
	public function __construct( PoolRepository $pools, PoolPresenter $presenter, MappingRepository $mappings, AvailabilityService $availability, QuantityFormatter $formatter, MappingDiagnostics $diagnostics, PaginationRenderer $pagination, UnitRegistry $units ) {
		$this->pools        = $pools;
		$this->presenter    = $presenter;
		$this->mappings     = $mappings;
		$this->availability = $availability;
		$this->formatter    = $formatter;
		$this->diagnostics  = $diagnostics;
		$this->pagination   = $pagination;
		$this->units        = $units;
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
		$search      = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$total       = $this->pools->count_search( $search );
		$total_pages = max( 1, intdiv( $total + self::PER_PAGE - 1, self::PER_PAGE ) );
		$page        = isset( $_GET['stock_page'] ) ? absint( $_GET['stock_page'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page        = max( 1, min( $total_pages, $page ) );
		$offset      = ( $page - 1 ) * self::PER_PAGE;
		$rows        = array_map( array( $this->presenter, 'present' ), $this->pools->search( $search, self::PER_PAGE, $offset ) );
		?>
		<form method="get" class="laqi-lusm-search">
			<input type="hidden" name="page" value="laqi-unit-stock-manager" />
			<input type="hidden" name="section" value="stock" />
			<label class="screen-reader-text" for="laqi-lusm-search"><?php esc_html_e( 'Search inventory pools', 'laqi-unit-stock-manager' ); ?></label>
			<input id="laqi-lusm-search" type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search pools, products, SKUs, or attributes', 'laqi-unit-stock-manager' ); ?>" />
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
					<th scope="row" data-label="<?php esc_attr_e( 'Inventory pool', 'laqi-unit-stock-manager' ); ?>"><?php $this->render_details_form( $row ); ?></th>
					<td data-label="<?php esc_attr_e( 'On hand', 'laqi-unit-stock-manager' ); ?>"><strong><?php echo esc_html( $row['quantity_display'] ); ?></strong></td>
					<td data-label="<?php esc_attr_e( 'Linked products', 'laqi-unit-stock-manager' ); ?>"><?php $this->render_links( (int) $row['id'] ); ?></td>
					<td data-label="<?php esc_attr_e( 'Adjustment', 'laqi-unit-stock-manager' ); ?>"><?php $this->render_adjustment_form( $row ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		if ( $total > 0 ) {
			/* translators: 1: first visible pool number, 2: last visible pool number, 3: total matching pools. */
			$summary = sprintf( __( 'Showing %1$d-%2$d of %3$d inventory pools.', 'laqi-unit-stock-manager' ), $offset + 1, $offset + count( $rows ), $total );
			$this->pagination->render(
				$summary,
				__( 'Inventory pool pages', 'laqi-unit-stock-manager' ),
				'stock_page',
				array(
					'page'    => UnitStockPage::SLUG,
					'section' => 'stock',
					's'       => $search,
				),
				$page,
				$total_pages
			);
		}
		?>
		<?php
	}

	/** Render editable operational pool details.
	 *
	 * @param array<string, mixed> $row Pool row.
	 * @return void
	 */
	private function render_details_form( array $row ): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="laqi-lusm-pool-details">
			<input type="hidden" name="action" value="laqi_lusm_update_pool" />
			<input type="hidden" name="pool_id" value="<?php echo esc_attr( $row['id'] ); ?>" />
			<input type="hidden" name="pool_version" value="<?php echo esc_attr( $row['version'] ); ?>" />
			<?php wp_nonce_field( 'laqi_lusm_update_pool_' . $row['id'] ); ?>
			<label class="screen-reader-text" for="laqi-lusm-pool-name-<?php echo esc_attr( $row['id'] ); ?>"><?php esc_html_e( 'Pool name', 'laqi-unit-stock-manager' ); ?></label>
			<input id="laqi-lusm-pool-name-<?php echo esc_attr( $row['id'] ); ?>" name="pool_name" value="<?php echo esc_attr( $row['name'] ); ?>" required />
			<label class="screen-reader-text" for="laqi-lusm-pool-sku-<?php echo esc_attr( $row['id'] ); ?>"><?php esc_html_e( 'Internal SKU', 'laqi-unit-stock-manager' ); ?></label>
			<input id="laqi-lusm-pool-sku-<?php echo esc_attr( $row['id'] ); ?>" name="internal_sku" value="<?php echo esc_attr( $row['internal_sku'] ); ?>" placeholder="<?php esc_attr_e( 'Internal SKU', 'laqi-unit-stock-manager' ); ?>" />
			<label class="screen-reader-text" for="laqi-lusm-pool-unit-<?php echo esc_attr( $row['id'] ); ?>"><?php esc_html_e( 'Display unit', 'laqi-unit-stock-manager' ); ?></label>
			<select id="laqi-lusm-pool-unit-<?php echo esc_attr( $row['id'] ); ?>" name="display_unit">
			<?php foreach ( $this->units->all() as $unit ) : ?>
				<?php if ( $unit->family() === $row['family'] ) : ?>
					<option value="<?php echo esc_attr( $unit->key() ); ?>" <?php selected( $unit->key(), $row['display_unit'] ); ?>><?php echo esc_html( $unit->label() . ' (' . $unit->symbol() . ')' ); ?></option>
				<?php endif; ?>
			<?php endforeach; ?>
			</select>
			<?php submit_button( __( 'Save details', 'laqi-unit-stock-manager' ), 'secondary small', '', false ); ?>
		</form>
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
			echo '<li><strong>' . esc_html( wp_strip_all_tags( $product->get_formatted_name() ) ) . '</strong><br />';
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
		$reason_templates = apply_filters( 'laqi_lusm_adjustment_reason_templates', array(), 'manual' );
		$list_id          = 'laqi-lusm-reasons-' . $row['id'];
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
			<input id="laqi-lusm-reason-<?php echo esc_attr( $row['id'] ); ?>" name="reason" type="text" placeholder="<?php esc_attr_e( 'Reason (optional)', 'laqi-unit-stock-manager' ); ?>" <?php echo ! empty( $reason_templates ) ? 'list="' . esc_attr( $list_id ) . '"' : ''; ?> />
			<?php
			if ( ! empty( $reason_templates ) ) :
				?>
				<datalist id="<?php echo esc_attr( $list_id ); ?>">
				<?php
				foreach ( $reason_templates as $template ) :
					?>
				<option value="<?php echo esc_attr( $template ); ?>"></option><?php endforeach; ?></datalist><?php endif; ?>
			<?php submit_button( __( 'Apply', 'laqi-unit-stock-manager' ), 'secondary small', '', false ); ?>
		</form>
		<?php
	}
}
