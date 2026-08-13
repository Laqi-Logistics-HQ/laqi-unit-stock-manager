<?php
/**
 * Paid searchable movement ledger section.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Admin;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Admin\PaginationRenderer;
use LaqiUnitStockManager\Admin\ScreenSectionInterface;
use LaqiUnitStockManager\Admin\UnitStockPage;
use LaqiUnitStockManager\Presentation\MovementPresenter;
use LaqiUnitStockManager\Storage\MovementRepository;

/** Adds operational ledger search without changing Free correctness records. */
final class MovementLedgerSection implements ScreenSectionInterface {
	const PER_PAGE = 50;
	/**
	 * Movement reads.
	 *
	 * @var MovementRepository
	 */
	private $movements;
	/**
	 * Movement presenter.
	 *
	 * @var MovementPresenter
	 */
	private $presenter;
	/**
	 * Shared pagination.
	 *
	 * @var PaginationRenderer
	 */
	private $pagination;

	/** Constructor.
	 *
	 * @param MovementRepository $movements Movements.
	 * @param MovementPresenter  $presenter Presenter.
	 * @param PaginationRenderer $pagination Pagination.
	 */
	public function __construct( MovementRepository $movements, MovementPresenter $presenter, PaginationRenderer $pagination ) {
		$this->movements  = $movements;
		$this->presenter  = $presenter;
		$this->pagination = $pagination;
	}
	/** Section ID. @return string */
	public function id(): string {
		return 'ledger';
	}
	/** Section title. @return string */
	public function title(): string {
		return __( 'Ledger', 'laqi-unit-stock-manager' );
	}
	/** Render searchable ledger. @return void */
	public function render(): void {
		$term        = isset( $_GET['ledger_search'] ) ? sanitize_text_field( wp_unslash( $_GET['ledger_search'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page        = isset( $_GET['ledger_page'] ) ? max( 1, absint( $_GET['ledger_page'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$total       = $this->movements->count_search( $term );
		$total_pages = max( 1, intdiv( $total + self::PER_PAGE - 1, self::PER_PAGE ) );
		$page        = min( $page, $total_pages );
		$offset      = ( $page - 1 ) * self::PER_PAGE;
		$rows        = array_map( array( $this->presenter, 'present' ), $this->movements->search( $term, self::PER_PAGE, $offset ) );
		?>
		<form method="get" class="laqi-lusm-search"><input type="hidden" name="page" value="<?php echo esc_attr( UnitStockPage::SLUG ); ?>" /><input type="hidden" name="section" value="ledger" /><label class="screen-reader-text" for="laqi-lusm-ledger-search"><?php esc_html_e( 'Search stock movements', 'laqi-unit-stock-manager' ); ?></label><input id="laqi-lusm-ledger-search" name="ledger_search" value="<?php echo esc_attr( $term ); ?>" placeholder="<?php esc_attr_e( 'Search pools, movement types, sources, or reasons', 'laqi-unit-stock-manager' ); ?>" /><?php submit_button( __( 'Search', 'laqi-unit-stock-manager' ), 'secondary', '', false ); ?></form>
		<table class="widefat striped laqi-lusm-activity-table"><thead><tr><th><?php esc_html_e( 'Date', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Inventory pool', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Movement', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Change', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Balance', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Reason', 'laqi-unit-stock-manager' ); ?></th></tr></thead><tbody>
		<?php
		foreach ( $rows as $row ) :
			?>
			<tr><td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $row['created_at'] . ' UTC' ) ) ); ?></td><td><?php echo esc_html( $row['pool_name'] ); ?></td><td><?php echo esc_html( $row['type_label'] ); ?></td><td><?php echo esc_html( $row['delta_display'] ); ?></td><td><?php echo esc_html( $row['balance_display'] ); ?></td><td><?php echo esc_html( $row['reason'] ); ?></td></tr><?php endforeach; ?>
		<?php if ( empty( $rows ) ) : ?>
			<tr><td colspan="6"><?php esc_html_e( 'No matching movements found.', 'laqi-unit-stock-manager' ); ?></td></tr>
		<?php endif; ?>
		</tbody></table>
		<?php
		if ( $total > 0 ) {
			/* translators: 1: first row, 2: last row, 3: total matching movements. */
			$summary = sprintf( __( 'Showing %1$d-%2$d of %3$d matching movements.', 'laqi-unit-stock-manager' ), $offset + 1, $offset + count( $rows ), $total );
			$this->pagination->render(
				$summary,
				__( 'Ledger pages', 'laqi-unit-stock-manager' ),
				'ledger_page',
				array(
					'page'          => UnitStockPage::SLUG,
					'section'       => 'ledger',
					'ledger_search' => $term,
				),
				$page,
				$total_pages
			);
		}
		?>
		<?php
	}
}
