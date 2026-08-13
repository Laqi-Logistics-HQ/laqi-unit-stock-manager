<?php
/**
 * Material cost and unit economics screen.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Admin;

defined( 'ABSPATH' ) || exit;

// Compact section methods implement the screen-section contract.
// phpcs:disable Generic.Commenting.DocComment.MissingShort, Squiz.Commenting.FunctionComment

use LaqiUnitStockManager\Admin\ScreenSectionInterface;
use LaqiUnitStockManager\Premium\Costing\MaterialEconomicsService;
use LaqiUnitStockManager\Storage\MappingRepository;

/** Presents read-only material economics without automating retail prices. */
final class MaterialCostsSection implements ScreenSectionInterface {
	/** @var MaterialEconomicsService */ private $economics;
	/** @var MappingRepository */ private $mappings;

	/** Constructor. */
	public function __construct( MaterialEconomicsService $economics, MappingRepository $mappings ) {
		$this->economics = $economics;
		$this->mappings  = $mappings; }
	/** Section ID. */ public function id(): string {
		return 'costs'; }
	/** Section title. */ public function title(): string {
		return __( 'Costs', 'laqi-unit-stock-manager' ); }

	/** Render linked product economics. */
	public function render(): void {
		?>
		<section class="card"><h2><?php esc_html_e( 'Current unit economics', 'laqi-unit-stock-manager' ); ?></h2><p><?php esc_html_e( 'Material costs use the weighted average from priced receipts. They are informational and never change product prices.', 'laqi-unit-stock-manager' ); ?></p><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Product or variation', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Material cost per sold item', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Current price', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Gross margin before other costs', 'laqi-unit-stock-manager' ); ?></th></tr></thead><tbody>
		<?php
		foreach ( $this->mappings->active() as $mapping ) :
			$economics = $this->economics->calculate( $mapping );
			$product   = $economics['product'];
			$material  = $economics['material_cost'];
			$price     = $economics['price'];
			?>
			<?php $product_name = $product ? $product->get_formatted_name() : sprintf( /* translators: %d: WooCommerce product ID. */ __( 'Unavailable product #%d', 'laqi-unit-stock-manager' ), $mapping->variation_id() > 0 ? $mapping->variation_id() : $mapping->product_id() ); ?>
			<tr><td><?php echo esc_html( $product_name ); ?></td><td><?php echo null === $material ? esc_html__( 'No priced receipt yet', 'laqi-unit-stock-manager' ) : wp_kses_post( wc_price( $material, array( 'currency' => $economics['currency'] ) ) ); ?></td><td><?php echo $product ? wp_kses_post( wc_price( $price ) ) : '&mdash;'; ?></td><td><?php echo null !== $economics['margin'] ? esc_html( wc_format_decimal( $economics['margin'], 1 ) . '%' ) : '&mdash;'; ?></td></tr>
		<?php endforeach; ?>
		</tbody></table></section>
		<?php
	}
}
