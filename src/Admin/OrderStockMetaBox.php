<?php
/**
 * Order-level pooled-stock audit panel.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Admin;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Presentation\MovementPresenter;
use LaqiUnitStockManager\Storage\MovementRepository;
use LaqiUnitStockManager\WooCommerce\OrderItemSnapshotter;
use LaqiUnitStockManager\WooCommerce\OrderStockLifecycle;
use WC_Order;
use WP_Post;

/** Shows the exact stock effects beside a WooCommerce order. */
final class OrderStockMetaBox {

	/**
	 * Movement reads.
	 *
	 * @var MovementRepository
	 */
	private $movements;

	/**
	 * Movement presentation.
	 *
	 * @var MovementPresenter
	 */
	private $presenter;

	/**
	 * Constructor.
	 *
	 * @param MovementRepository $movements Movement reads.
	 * @param MovementPresenter  $presenter Movement presentation.
	 */
	public function __construct( MovementRepository $movements, MovementPresenter $presenter ) {
		$this->movements = $movements;
		$this->presenter = $presenter;
	}

	/** Register classic-order and HPOS hooks. */
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'hide_internal_item_meta' ) );
	}

	/** Register the side panel on both supported order screens. */
	public function add_meta_boxes(): void {
		$screens = array( 'shop_order' );
		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$screens[] = wc_get_page_screen_id( 'shop-order' );
		}

		foreach ( array_unique( $screens ) as $screen ) {
			add_meta_box(
				'laqi-lusm-order-stock',
				__( 'Unit Stock', 'laqi-unit-stock-manager' ),
				array( $this, 'render' ),
				$screen,
				'side',
				'default'
			);
		}
	}

	/**
	 * Hide implementation metadata from the order-item display.
	 *
	 * @param string[] $keys Hidden metadata keys.
	 * @return string[]
	 */
	public function hide_internal_item_meta( array $keys ): array {
		$keys[] = OrderItemSnapshotter::META_KEY;
		$keys[] = OrderStockLifecycle::RESTOCKED_QUANTITY_META;
		return array_values( array_unique( $keys ) );
	}

	/**
	 * Render the order's immutable stock movements.
	 *
	 * @param WC_Order|WP_Post $order_object Order screen object.
	 * @return void
	 */
	public function render( $order_object ): void {
		$order = $order_object instanceof WC_Order ? $order_object : ( $order_object instanceof WP_Post ? wc_get_order( $order_object->ID ) : false );
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$movement_rows = $this->movements->for_source( 'order', $order->get_id() );
		$rows          = array_map( array( $this->presenter, 'present' ), $movement_rows );
		$state         = (string) $order->get_meta( OrderStockLifecycle::STATE_META, true );
		?>
		<p>
			<strong><?php esc_html_e( 'Pooled stock state:', 'laqi-unit-stock-manager' ); ?></strong>
			<?php echo esc_html( $this->state_label( $state ) ); ?>
		</p>
		<?php if ( array() === $rows ) : ?>
			<p><?php esc_html_e( 'No pooled-stock movements have been recorded for this order.', 'laqi-unit-stock-manager' ); ?></p>
		<?php else : ?>
			<ul class="laqi-lusm-order-stock-movements">
			<?php foreach ( $rows as $row ) : ?>
				<li>
					<strong><?php echo esc_html( $row['type_label'] ); ?></strong><br>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: inventory pool name, 2: signed stock change, 3: resulting pool balance. */
							__( '%1$s: %2$s, balance %3$s', 'laqi-unit-stock-manager' ),
							$row['pool_name'],
							$row['delta_display'],
							$row['balance_display']
						)
					);
					?>
					<br>
					<small><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $row['created_at'] . ' UTC' ) ) ); ?></small>
				</li>
			<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php
		/**
		 * Renders extension-owned order stock details after the core movement list.
		 *
		 * @param WC_Order                        $order         Order.
		 * @param array<int,array<string,mixed>> $movement_rows Raw movement rows.
		 */
		do_action( 'laqi_lusm_order_stock_audit', $order, $movement_rows );
	}

	/**
	 * Get a readable lifecycle state.
	 *
	 * @param string $state Stored lifecycle state.
	 * @return string
	 */
	private function state_label( string $state ): string {
		if ( 'reduced' === $state ) {
			return __( 'Reduced', 'laqi-unit-stock-manager' );
		}
		if ( 'restored' === $state ) {
			return __( 'Restored', 'laqi-unit-stock-manager' );
		}
		return __( 'Not reduced', 'laqi-unit-stock-manager' );
	}
}
