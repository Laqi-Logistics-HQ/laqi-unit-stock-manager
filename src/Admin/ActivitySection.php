<?php
/**
 * Basic stock movement activity tab.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Admin;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Inventory\MovementRegistry;
use LaqiUnitStockManager\Presentation\MovementPresenter;
use LaqiUnitStockManager\Storage\MovementRepository;
use LaqiUnitStockManager\Storage\PoolRepository;

/**
 * Shows the recent immutable correctness ledger in Free.
 */
final class ActivitySection implements ScreenSectionInterface {

	/** Movements displayed per page. */
	const PER_PAGE = 25;

	/**
	 * Movement reads.
	 *
	 * @var MovementRepository
	 */
	private $movements;

	/**
	 * Shared movement presenter.
	 *
	 * @var MovementPresenter
	 */
	private $presenter;

	/**
	 * Shared dataset chrome.
	 *
	 * @var DatasetRenderer
	 */
	private $tables;

	/**
	 * Movement type labels.
	 *
	 * @var MovementRegistry
	 */
	private $types;

	/**
	 * Pool lookups, for naming a chosen pool filter.
	 *
	 * @var PoolRepository
	 */
	private $pools;

	/** Constructor.
	 *
	 * @param MovementRepository $movements Movement reads.
	 * @param MovementPresenter  $presenter Movement presenter.
	 * @param DatasetRenderer    $tables    Shared dataset chrome.
	 * @param MovementRegistry   $types     Movement type labels.
	 * @param PoolRepository     $pools     Pool lookups.
	 */
	public function __construct( MovementRepository $movements, MovementPresenter $presenter, DatasetRenderer $tables, MovementRegistry $types, PoolRepository $pools ) {
		$this->movements = $movements;
		$this->presenter = $presenter;
		$this->tables    = $tables;
		$this->types     = $types;
		$this->pools     = $pools;
	}

	/** Get the section ID. @return string */
	public function id(): string {
		return 'activity';
	}

	/** Get the section title. @return string */
	public function title(): string {
		return __( 'Activity', 'laqi-unit-stock-manager' );
	}

	/** Render the filtered movement ledger. @return void */
	public function render(): void {
		$pool_ids = $this->requested_pool_ids();
		$filters  = DatasetFilters::read( $this->filter_spec() );
		$this->describe_pool( $filters );
		$query = $filters->to_query();
		if ( array() !== $pool_ids ) {
			$query['pool_ids'] = $pool_ids;
		}
		$page = DatasetPage::from_query( 'activity_page', $this->movements->count( $query ), self::PER_PAGE );
		$rows = array_map( array( $this->presenter, 'present' ), $this->movements->page( $query, $page->per_page(), $page->offset() ) );
		$view = new DatasetView(
			'activity',
			$filters,
			$page,
			array(
				'post_type' => 'product',
				'page'      => UnitStockPage::SLUG,
				'section'   => 'activity',
				'pool_ids'  => implode( ',', $pool_ids ),
			)
		);
		?>
		<p><?php esc_html_e( 'The latest pooled-stock changes are shown below. These records are append-only.', 'laqi-unit-stock-manager' ); ?></p>
		<?php if ( array() !== $pool_ids ) : ?>
			<p class="laqi-lusm-context-filter"><strong><?php esc_html_e( 'Filtered to the pools used by the selected product.', 'laqi-unit-stock-manager' ); ?></strong> <a href="<?php echo esc_url( $this->activity_url() ); ?>"><?php esc_html_e( 'Show all activity', 'laqi-unit-stock-manager' ); ?></a></p>
		<?php endif; ?>
		<?php $this->tables->filters( $view, __( 'Filter stock movements', 'laqi-unit-stock-manager' ) ); ?>
		<?php
		/**
		 * Render actions beside the Activity filter bar.
		 *
		 * Extensions echo their own controls - an export button, for example.
		 * The active filters and the paging state are passed so an action can
		 * operate on exactly the rows the merchant is looking at rather than
		 * the whole ledger.
		 *
		 * Output is the listener's responsibility to escape.
		 *
		 * @since Extension API 1.2
		 *
		 * @param DatasetFilters $filters  Active filters.
		 * @param array<int>     $pool_ids Pools the screen is scoped to, empty for all.
		 * @param int            $total    Movements matching the filters.
		 */
		do_action( 'laqi_lusm_activity_actions', $filters, $pool_ids, $page->total() );
		?>
		<?php if ( array() === $rows ) : ?>
			<?php $this->tables->empty_state( $view, __( 'No stock movements recorded yet.', 'laqi-unit-stock-manager' ) ); ?>
		<?php else : ?>
		<div class="laqi-lusm-table-scroll">
		<table class="widefat striped laqi-lusm-activity-table">
			<thead><tr>
				<th scope="col"><?php esc_html_e( 'Date', 'laqi-unit-stock-manager' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Inventory pool', 'laqi-unit-stock-manager' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Movement', 'laqi-unit-stock-manager' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Change', 'laqi-unit-stock-manager' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Balance', 'laqi-unit-stock-manager' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Source', 'laqi-unit-stock-manager' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Actor', 'laqi-unit-stock-manager' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Reason', 'laqi-unit-stock-manager' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $rows as $row ) : ?>
				<tr>
					<td data-label="<?php esc_attr_e( 'Date', 'laqi-unit-stock-manager' ); ?>"><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $row['created_at'] . ' UTC' ) ) ); ?></td>
					<th scope="row" data-label="<?php esc_attr_e( 'Inventory pool', 'laqi-unit-stock-manager' ); ?>"><?php echo esc_html( $row['pool_name'] ); ?></th>
					<td data-label="<?php esc_attr_e( 'Movement', 'laqi-unit-stock-manager' ); ?>"><?php echo esc_html( $row['type_label'] ); ?></td>
					<td data-label="<?php esc_attr_e( 'Change', 'laqi-unit-stock-manager' ); ?>"><?php echo esc_html( $row['delta_display'] ); ?></td>
					<td data-label="<?php esc_attr_e( 'Balance', 'laqi-unit-stock-manager' ); ?>"><?php echo esc_html( $row['balance_display'] ); ?></td>
					<td data-label="<?php esc_attr_e( 'Source', 'laqi-unit-stock-manager' ); ?>"><?php echo esc_html( $this->source_label( $row ) ); ?></td>
					<td data-label="<?php esc_attr_e( 'Actor', 'laqi-unit-stock-manager' ); ?>"><?php echo esc_html( $this->actor_label( $row ) ); ?></td>
					<td data-label="<?php esc_attr_e( 'Reason', 'laqi-unit-stock-manager' ); ?>"><?php echo esc_html( $row['reason'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		</div>
			<?php
			/* translators: 1: first visible movement number, 2: last visible movement number, 3: total movements. */
			$summary = $page->summary( __( 'Showing %1$d-%2$d of %3$d stock movements.', 'laqi-unit-stock-manager' ), count( $rows ) );
			$this->tables->pagination( $view, $summary, __( 'Stock movement pages', 'laqi-unit-stock-manager' ) );
			?>
		<?php endif; ?>
		<?php
	}

	/**
	 * Declare the ledger filters.
	 *
	 * Quantities are deliberately absent: pools can use different unit
	 * families, so comparing a change or balance across pools would compare
	 * unrelated measurements.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function filter_spec(): array {
		$types = array( '' => __( 'All movements', 'laqi-unit-stock-manager' ) );
		foreach ( $this->movements->used_types() as $type ) {
			$types[ (string) $type ] = $this->types->label( (string) $type );
		}
		$sources = array( '' => __( 'All sources', 'laqi-unit-stock-manager' ) );
		foreach ( $this->movements->used_sources() as $source ) {
			$sources[ (string) $source ] = (string) $source;
		}
		$actors = array(
			''  => __( 'Anyone', 'laqi-unit-stock-manager' ),
			'0' => __( 'System', 'laqi-unit-stock-manager' ),
		);
		foreach ( $this->movements->used_actors() as $actor ) {
			$actors[ (string) (int) $actor['id'] ] = '' !== (string) $actor['name'] ? (string) $actor['name'] : sprintf( /* translators: %d: user ID. */ __( 'User #%d', 'laqi-unit-stock-manager' ), (int) $actor['id'] );
		}

		return array(
			'activity_pool'   => array(
				'control' => 'pool',
				'filter'  => 'pool_id',
				'label'   => __( 'Inventory pool', 'laqi-unit-stock-manager' ),
			),
			'activity_type'   => array(
				'control' => 'select',
				'filter'  => 'type',
				'label'   => __( 'Movement', 'laqi-unit-stock-manager' ),
				'choices' => $types,
			),
			'activity_source' => array(
				'control' => 'select',
				'filter'  => 'source_type',
				'label'   => __( 'Source', 'laqi-unit-stock-manager' ),
				'choices' => $sources,
			),
			'activity_actor'  => array(
				'control' => 'select',
				'filter'  => 'actor',
				'label'   => __( 'Actor', 'laqi-unit-stock-manager' ),
				'choices' => $actors,
			),
			'activity_from'   => array(
				'control' => 'date',
				'filter'  => 'from',
				'label'   => __( 'From', 'laqi-unit-stock-manager' ),
			),
			'activity_to'     => array(
				'control' => 'date',
				'filter'  => 'to',
				'label'   => __( 'To', 'laqi-unit-stock-manager' ),
			),
			'activity_reason' => array(
				'control'     => 'search',
				'filter'      => 'reason',
				'label'       => __( 'Reason', 'laqi-unit-stock-manager' ),
				'placeholder' => __( 'Text recorded with the change', 'laqi-unit-stock-manager' ),
			),
			'activity_search' => array(
				'control'     => 'search',
				'filter'      => 'search',
				'label'       => __( 'Search', 'laqi-unit-stock-manager' ),
				'placeholder' => __( 'Pool, movement, source, reason, or actor', 'laqi-unit-stock-manager' ),
			),
		);
	}

	/**
	 * Name the chosen pool so filter summaries can show it.
	 *
	 * @param DatasetFilters $filters Active filters.
	 * @return void
	 */
	private function describe_pool( DatasetFilters $filters ): void {
		$pool_id = (int) $filters->value( 'activity_pool' );
		if ( $pool_id < 1 ) {
			return;
		}
		$pool = $this->pools->find( $pool_id );
		$filters->describe( 'activity_pool', null === $pool ? sprintf( /* translators: %d: inventory pool ID. */ __( 'Pool #%d', 'laqi-unit-stock-manager' ), $pool_id ) : $pool->name() );
	}

	/** Read validated contextual pool IDs from the URL. @return int[] */
	private function requested_pool_ids(): array {
		$value = isset( $_GET['pool_ids'] ) ? sanitize_text_field( wp_unslash( $_GET['pool_ids'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return array_values( array_unique( array_filter( array_map( 'absint', explode( ',', $value ) ) ) ) );
	}

	/** Build the unfiltered Activity URL. @return string */
	private function activity_url(): string {
		return add_query_arg(
			array(
				'post_type' => 'product',
				'page'      => UnitStockPage::SLUG,
				'section'   => 'activity',
			),
			admin_url( 'edit.php' )
		);
	}

	/** Get a readable movement source.
	 *
	 * @param array<string, mixed> $row Presented row.
	 * @return string */
	private function source_label( array $row ): string {
		if ( '' !== $row['source_type'] ) {
			return $row['source_id'] > 0 ? $row['source_type'] . ' #' . $row['source_id'] : $row['source_type'];
		}
		return __( 'System', 'laqi-unit-stock-manager' );
	}

	/**
	 * Human-readable actor.
	 *
	 * Split out of source_label(), which used to return the acting user's name
	 * when there was one. That conflated two different questions - what caused
	 * the movement, and who performed it - and made a manual adjustment read as
	 * though a person were its source. With an Actor column present it would
	 * also have printed the same name in two adjacent cells.
	 *
	 * The name is already on the row: MovementPresenter sets actor_name, and the
	 * filter bar has always listed actors by name.
	 *
	 * @param array<string, mixed> $row Presented movement.
	 * @return string
	 */
	private function actor_label( array $row ): string {
		return '' !== $row['actor_name'] ? $row['actor_name'] : __( 'System or deleted user', 'laqi-unit-stock-manager' );
	}
}
