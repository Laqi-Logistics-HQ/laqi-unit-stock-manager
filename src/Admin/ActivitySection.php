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

	/** Constructor.
	 *
	 * @param MovementRepository $movements Movement reads.
	 * @param MovementPresenter  $presenter Movement presenter.
	 */
	public function __construct( MovementRepository $movements, MovementPresenter $presenter ) {
		$this->movements = $movements;
		$this->presenter = $presenter;
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
		$rows = array_map( array( $this->presenter, 'present' ), $this->movements->recent() );
		?>
		<p><?php esc_html_e( 'The latest pooled-stock changes are shown below. These records are append-only.', 'laqi-unit-stock-manager' ); ?></p>
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
					<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $row['created_at'] . ' UTC' ) ) ); ?></td>
					<th scope="row"><?php echo esc_html( $row['pool_name'] ); ?></th>
					<td><?php echo esc_html( $row['type_label'] ); ?></td>
					<td><?php echo esc_html( $row['delta_display'] ); ?></td>
					<td><?php echo esc_html( $row['balance_display'] ); ?></td>
					<td><?php echo esc_html( $this->source_label( $row ) ); ?></td>
					<td><?php echo esc_html( $row['reason'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
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
