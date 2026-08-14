<?php
/**
 * Mobile stocktaking admin section.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Admin;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Admin\ScreenSectionInterface;

/** Renders a touch-friendly scan and count workflow. */
final class MobileStocktakeSection implements ScreenSectionInterface {
	/** Section ID. @return string */
	public function id(): string {
		return 'mobile-count';
	}

	/** Section title. @return string */
	public function title(): string {
		return __( 'Mobile count', 'laqi-unit-stock-manager' );
	}

	/** Render the progressively enhanced workflow. @return void */
	public function render(): void {
		$reason_templates = apply_filters( 'laqi_lusm_adjustment_reason_templates', array(), 'mobile_stocktake' );
		?>
		<section id="laqi-lusm-mobile-stocktake" class="card laqi-lusm-mobile-stocktake">
			<h2><?php esc_html_e( 'Scan and count stock', 'laqi-unit-stock-manager' ); ?></h2>
			<p><?php esc_html_e( 'Scan a WooCommerce barcode or enter a SKU, choose the affected pool, then submit the physical quantity counted.', 'laqi-unit-stock-manager' ); ?></p>
			<form class="laqi-lusm-mobile-scan-form">
				<label for="laqi-lusm-mobile-code"><?php esc_html_e( 'Barcode, GTIN, or SKU', 'laqi-unit-stock-manager' ); ?></label>
				<div class="laqi-lusm-mobile-actions"><input id="laqi-lusm-mobile-code" name="code" autocomplete="off" autocapitalize="off" required /><button type="submit" class="button button-primary"><?php esc_html_e( 'Find stock', 'laqi-unit-stock-manager' ); ?></button><button type="button" class="button laqi-lusm-camera-button" hidden><?php esc_html_e( 'Use camera', 'laqi-unit-stock-manager' ); ?></button></div>
			</form>
			<video class="laqi-lusm-camera-preview" playsinline muted hidden></video>
			<p class="laqi-lusm-mobile-status" role="status" aria-live="polite"></p>
			<form class="laqi-lusm-mobile-count-form" hidden>
				<h3 class="laqi-lusm-mobile-product"></h3>
				<label for="laqi-lusm-mobile-pool"><?php esc_html_e( 'Inventory pool', 'laqi-unit-stock-manager' ); ?></label><select id="laqi-lusm-mobile-pool" required></select>
				<p class="laqi-lusm-mobile-balance"></p>
				<label for="laqi-lusm-mobile-quantity"><?php esc_html_e( 'Physical quantity counted', 'laqi-unit-stock-manager' ); ?></label><div class="laqi-lusm-mobile-quantity"><input id="laqi-lusm-mobile-quantity" inputmode="decimal" required /><span class="laqi-lusm-mobile-unit"></span></div>
				<label for="laqi-lusm-mobile-reason"><?php esc_html_e( 'Count reason or reference', 'laqi-unit-stock-manager' ); ?></label><input id="laqi-lusm-mobile-reason" maxlength="255" required <?php echo ! empty( $reason_templates ) ? 'list="laqi-lusm-mobile-reasons"' : ''; ?> />
				<?php
				if ( ! empty( $reason_templates ) ) :
					?>
					<datalist id="laqi-lusm-mobile-reasons">
					<?php
					foreach ( $reason_templates as $template ) :
						?>
					<option value="<?php echo esc_attr( $template ); ?>"></option><?php endforeach; ?></datalist><?php endif; ?>
				<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Save physical count', 'laqi-unit-stock-manager' ); ?></button>
			</form>
		</section>
		<?php
	}
}
