<?php
/**
 * Basic stock movement activity tab.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Admin;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Presentation\MovementPresenter;
use LaqiUnitStockManager\Storage\MovementRepository;

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
	 * Shared admin pagination.
	 *
	 * @var PaginationRenderer
	 */
	private $pagination;

	/** Constructor.
	 *
	 * @param MovementRepository $movements Movement reads.
	 * @param MovementPresenter  $presenter Movement presenter.
	 * @param PaginationRenderer $pagination Shared admin pagination.
	 */
	public function __construct( MovementRepository $movements, MovementPresenter $presenter, PaginationRenderer $pagination ) {
		$this->movements  = $movements;
		$this->presenter  = $presenter;
		$this->pagination = $pagination;
	}

	/** Get the section ID. @return string */
	public function id(): string {
		return 'activity';
	}

	/** Get the section title. @return string */
	public function title(): string {
		return __( 'Activity', 'laqi-unit-stock-manager' );
	}

	/** Render recent movements. @return void */
	public function render(): void {
		$pool_ids      = $this->requested_pool_ids();
		$total         = array() === $pool_ids ? $this->movements->count() : $this->movements->count_for_pools( $pool_ids );
		$total_pages   = max( 1, intdiv( $total + self::PER_PAGE - 1, self::PER_PAGE ) );
		$page          = isset( $_GET['activity_page'] ) ? absint( $_GET['activity_page'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page          = max( 1, min( $total_pages, $page ) );
		$offset        = ( $page - 1 ) * self::PER_PAGE;
		$movement_rows = array() === $pool_ids ? $this->movements->recent( self::PER_PAGE, $offset ) : $this->movements->recent_for_pools( $pool_ids, self::PER_PAGE, $offset );
		$rows          = array_map( array( $this->presenter, 'present' ), $movement_rows );
		?>
		<p><?php esc_html_e( 'The latest pooled-stock changes are shown below. These records are append-only.', 'laqi-unit-stock-manager' ); ?></p>
		<?php if ( array() !== $pool_ids ) : ?>
			<p class="laqi-lusm-context-filter"><strong><?php esc_html_e( 'Filtered to the pools used by the selected product.', 'laqi-unit-stock-manager' ); ?></strong> <a href="<?php echo esc_url( $this->activity_url() ); ?>"><?php esc_html_e( 'Show all activity', 'laqi-unit-stock-manager' ); ?></a></p>
		<?php endif; ?>
		<div class="laqi-lusm-table-scroll">
		<table class="widefat striped laqi-lusm-activity-table">
			<thead><tr>
				<th scope="col"><?php esc_html_e( 'Date', 'laqi-unit-stock-manager' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Inventory pool', 'laqi-unit-stock-manager' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Movement', 'laqi-unit-stock-manager' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Change', 'laqi-unit-stock-manager' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Balance', 'laqi-unit-stock-manager' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Source', 'laqi-unit-stock-manager' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Reason', 'laqi-unit-stock-manager' ); ?></th>
			</tr></thead>
			<tbody>
			<?php if ( array() === $rows ) : ?>
				<tr><td colspan="7"><?php esc_html_e( 'No stock movements recorded yet.', 'laqi-unit-stock-manager' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $rows as $row ) : ?>
				<tr>
					<td data-label="<?php esc_attr_e( 'Date', 'laqi-unit-stock-manager' ); ?>"><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $row['created_at'] . ' UTC' ) ) ); ?></td>
					<th scope="row" data-label="<?php esc_attr_e( 'Inventory pool', 'laqi-unit-stock-manager' ); ?>"><?php echo esc_html( $row['pool_name'] ); ?></th>
					<td data-label="<?php esc_attr_e( 'Movement', 'laqi-unit-stock-manager' ); ?>"><?php echo esc_html( $row['type_label'] ); ?></td>
					<td data-label="<?php esc_attr_e( 'Change', 'laqi-unit-stock-manager' ); ?>"><?php echo esc_html( $row['delta_display'] ); ?></td>
					<td data-label="<?php esc_attr_e( 'Balance', 'laqi-unit-stock-manager' ); ?>"><?php echo esc_html( $row['balance_display'] ); ?></td>
					<td data-label="<?php esc_attr_e( 'Source', 'laqi-unit-stock-manager' ); ?>"><?php echo esc_html( $this->source_label( $row ) ); ?></td>
					<td data-label="<?php esc_attr_e( 'Reason', 'laqi-unit-stock-manager' ); ?>"><?php echo esc_html( $row['reason'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		</div>
		<?php
		if ( $total > 0 ) {
			/* translators: 1: first visible movement number, 2: last visible movement number, 3: total movements. */
			$summary = sprintf( __( 'Showing %1$d-%2$d of %3$d stock movements.', 'laqi-unit-stock-manager' ), $offset + 1, $offset + count( $rows ), $total );
			$this->pagination->render(
				$summary,
				__( 'Stock movement pages', 'laqi-unit-stock-manager' ),
				'activity_page',
				array(
					'page'     => UnitStockPage::SLUG,
					'section'  => 'activity',
					'pool_ids' => implode( ',', $pool_ids ),
				),
				$page,
				$total_pages
			);
		}
		?>
		<?php
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
		if ( $row['actor_id'] > 0 ) {
			$user = get_userdata( $row['actor_id'] );
			if ( $user ) {
				return $user->display_name;
			}
		}
		if ( '' !== $row['source_type'] ) {
			return $row['source_id'] > 0 ? $row['source_type'] . ' #' . $row['source_id'] : $row['source_type'];
		}
		return __( 'System', 'laqi-unit-stock-manager' );
	}
}
