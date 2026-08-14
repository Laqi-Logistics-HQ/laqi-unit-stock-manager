<?php
/**
 * Stock anomaly operations screen.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Admin;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Admin\ScreenSectionInterface;
use LaqiUnitStockManager\Premium\Anomalies\StockAnomalyDetector;

/** Renders read-only findings from the anomaly detector. */
final class StockAnomaliesSection implements ScreenSectionInterface {
	/**
	 * Anomaly detector.
	 *
	 * @var StockAnomalyDetector
	 */
	private $detector;

	/**
	 * Constructor.
	 *
	 * @param StockAnomalyDetector $detector Anomaly detector.
	 */
	public function __construct( StockAnomalyDetector $detector ) {
		$this->detector = $detector;
	}

	/**
	 * Section ID.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'anomalies';
	}

	/**
	 * Section title.
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Anomalies', 'laqi-unit-stock-manager' );
	}

	/**
	 * Render anomaly findings.
	 *
	 * @return void
	 */
	public function render(): void {
		$anomalies = $this->detector->detect();
		?>
		<p><?php esc_html_e( 'Review suspicious recent movements and mapping conflicts. Findings are read-only and never change stock automatically.', 'laqi-unit-stock-manager' ); ?></p>
		<div class="laqi-lusm-table-scroll"><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Severity', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Finding', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Inventory pool', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Source', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Date', 'laqi-unit-stock-manager' ); ?></th></tr></thead><tbody>
		<?php foreach ( $anomalies as $anomaly ) : ?>
			<tr><td><?php echo esc_html( ucfirst( $anomaly['severity'] ) ); ?></td><td><strong><?php echo esc_html( $anomaly['title'] ); ?></strong><br /><?php echo esc_html( $anomaly['detail'] ); ?></td><td><?php echo esc_html( $anomaly['pool_name'] ); ?></td><td><?php echo esc_html( $anomaly['source'] ); ?></td><td><?php echo esc_html( '' !== $anomaly['created_at'] ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $anomaly['created_at'] . ' UTC' ) ) : '—' ); ?></td></tr>
		<?php endforeach; ?>
		<?php
		if ( empty( $anomalies ) ) :
			?>
			<tr><td colspan="5"><?php esc_html_e( 'No stock anomalies detected in the inspected activity.', 'laqi-unit-stock-manager' ); ?></td></tr><?php endif; ?>
		</tbody></table></div>
		<?php
	}
}
