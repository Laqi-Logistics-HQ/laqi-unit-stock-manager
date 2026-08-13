<?php
/**
 * Reserved and available-to-sell supply screen.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Admin;

defined( 'ABSPATH' ) || exit;

// Compact section methods implement the screen-section contract.
// phpcs:disable Generic.Commenting.DocComment.MissingShort, Squiz.Commenting.FunctionComment

use LaqiUnitStockManager\Admin\ScreenSectionInterface;
use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Premium\Reservations\ReservationRepository;
use LaqiUnitStockManager\Presentation\QuantityFormatter;

/** Presents reservation supply states without creating another menu. */
final class ReservationsSection implements ScreenSectionInterface {
	/** @var ReservationRepository */ private $reservations;
	/** @var QuantityFormatter */ private $formatter;
	/** Constructor. */ public function __construct( ReservationRepository $reservations, QuantityFormatter $formatter ) {
		$this->reservations = $reservations;
		$this->formatter    = $formatter; }
	/** ID. */ public function id(): string {
		return 'reservations'; }
	/** Title. */ public function title(): string {
		return __( 'Reservations', 'laqi-unit-stock-manager' ); }
	/** Render. */ public function render(): void {
		$rows = $this->reservations->active_summary(); ?>
		<section class="card"><h2><?php esc_html_e( 'Reserved and available-to-sell stock', 'laqi-unit-stock-manager' ); ?></h2><p><?php esc_html_e( 'Checkout reservations temporarily withhold pooled stock. Successful stock reduction converts them; cancellation, failure, or expiry releases them.', 'laqi-unit-stock-manager' ); ?></p>
		<?php
		if ( array() === $rows ) :
			?>
			<p><?php esc_html_e( 'No active order reservations.', 'laqi-unit-stock-manager' ); ?></p>
			<?php
else :
	?>
			<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Pool', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'On hand', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Reserved', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Available to sell', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Orders', 'laqi-unit-stock-manager' ); ?></th></tr></thead><tbody>
			<?php
			foreach ( $rows as $row ) :
				$on_hand  = (int) $row['quantity_base'];
				$reserved = (int) $row['reserved_base'];
				?>
	<tr><td><?php echo esc_html( $row['name'] ); ?></td><td><?php echo esc_html( $this->formatter->format( new Quantity( $row['family'], $on_hand ), $row['display_unit'] ) ); ?></td><td><?php echo esc_html( $this->formatter->format( new Quantity( $row['family'], $reserved ), $row['display_unit'] ) ); ?></td><td><?php echo esc_html( $this->formatter->format( new Quantity( $row['family'], max( 0, $on_hand - $reserved ) ), $row['display_unit'] ) ); ?></td><td><?php echo esc_html( (string) $row['reservation_count'] ); ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></section>
		<?php
	}
}
