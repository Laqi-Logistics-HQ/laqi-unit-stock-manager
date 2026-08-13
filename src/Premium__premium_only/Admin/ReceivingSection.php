<?php
/**
 * Paid receiving screen.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Admin;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Admin\ScreenSectionInterface;
use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Premium\Batches\BatchAllocationRepository;
use LaqiUnitStockManager\Premium\Batches\BatchRepository;
use LaqiUnitStockManager\Premium\Receiving\SupplierRepository;
use LaqiUnitStockManager\Presentation\QuantityFormatter;
use LaqiUnitStockManager\Storage\PoolRepository;

/** Supplier setup and pack receiving in one tab. */
final class ReceivingSection implements ScreenSectionInterface {
	/** Suppliers. @var SupplierRepository
	 *
	 * @var SupplierRepository
	 */ private $suppliers;
	/** Pools. @var PoolRepository
	 *
	 * @var PoolRepository
	 */ private $pools;
	/** Formatter. @var QuantityFormatter
	 *
	 * @var QuantityFormatter
	 */ private $formatter;
	/** Batches.
	 *
	 * @var BatchRepository
	 */ private $batches;
	/** Allocation traceability.
	 *
	 * @var BatchAllocationRepository
	 */ private $allocations;
	/** Constructor.
	 *
	 * @param SupplierRepository        $suppliers Suppliers.
	 * @param PoolRepository            $pools Pools.
	 * @param QuantityFormatter         $formatter Formatter.
	 * @param BatchRepository           $batches Batches.
	 * @param BatchAllocationRepository $allocations Allocation traceability.
	 */
	public function __construct( SupplierRepository $suppliers, PoolRepository $pools, QuantityFormatter $formatter, BatchRepository $batches, BatchAllocationRepository $allocations ) {
		$this->suppliers   = $suppliers;
		$this->pools       = $pools;
		$this->formatter   = $formatter;
		$this->batches     = $batches;
		$this->allocations = $allocations; }
	/** ID. @return string */ public function id(): string {
		return 'receiving'; }
	/** Title. @return string */ public function title(): string {
		return __( 'Receiving', 'laqi-unit-stock-manager' ); }
	/** Render. @return void */
	public function render(): void {
		$suppliers = $this->suppliers->suppliers();
		$packs     = $this->suppliers->packs();
		$incoming  = $this->suppliers->incoming_deliveries();
		$batches   = $this->batches->batches();
		$statuses  = array(
			'active'      => __( 'Active', 'laqi-unit-stock-manager' ),
			'quarantined' => __( 'Quarantined', 'laqi-unit-stock-manager' ),
			'depleted'    => __( 'Depleted', 'laqi-unit-stock-manager' ),
			'recalled'    => __( 'Recalled', 'laqi-unit-stock-manager' ),
		);
		$this->notice();
		$this->render_recall(); ?>
		<div class="laqi-lusm-setup-grid"><section class="card"><h2><?php esc_html_e( 'Add supplier', 'laqi-unit-stock-manager' ); ?></h2><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="laqi_lusm_create_supplier" /><?php wp_nonce_field( 'laqi_lusm_create_supplier' ); ?><label for="laqi-lusm-supplier-name"><?php esc_html_e( 'Supplier name', 'laqi-unit-stock-manager' ); ?></label><input id="laqi-lusm-supplier-name" name="name" maxlength="191" required /><label for="laqi-lusm-supplier-email"><?php esc_html_e( 'Email', 'laqi-unit-stock-manager' ); ?></label><input type="email" id="laqi-lusm-supplier-email" name="email" /><label for="laqi-lusm-lead-time"><?php esc_html_e( 'Lead time (days)', 'laqi-unit-stock-manager' ); ?></label><input type="number" min="0" max="365" id="laqi-lusm-lead-time" name="lead_time_days" value="0" /><?php submit_button( __( 'Add supplier', 'laqi-unit-stock-manager' ) ); ?></form></section>
		<section class="card"><h2><?php esc_html_e( 'Define supplier pack', 'laqi-unit-stock-manager' ); ?></h2><p><?php esc_html_e( 'Examples include a sack, drum, case, or pallet. The quantity is stored exactly for the selected pool.', 'laqi-unit-stock-manager' ); ?></p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="laqi_lusm_create_supplier_pack" /><?php wp_nonce_field( 'laqi_lusm_create_supplier_pack' ); ?><label for="laqi-lusm-pack-supplier"><?php esc_html_e( 'Supplier', 'laqi-unit-stock-manager' ); ?></label><select id="laqi-lusm-pack-supplier" name="supplier_id" required>
		<?php
		foreach ( $suppliers as $supplier ) :
			?>
			<option value="<?php echo esc_attr( (string) $supplier['id'] ); ?>"><?php echo esc_html( $supplier['name'] ); ?></option><?php endforeach; ?></select><label for="laqi-lusm-pack-pool"><?php esc_html_e( 'Inventory pool', 'laqi-unit-stock-manager' ); ?></label><select id="laqi-lusm-pack-pool" name="pool_id" class="laqi-lusm-pool-search" required></select><label for="laqi-lusm-pack-name"><?php esc_html_e( 'Pack name', 'laqi-unit-stock-manager' ); ?></label><input id="laqi-lusm-pack-name" name="name" placeholder="<?php esc_attr_e( '25 kg sack', 'laqi-unit-stock-manager' ); ?>" required /><label for="laqi-lusm-pack-quantity"><?php esc_html_e( 'Quantity in the pool display unit', 'laqi-unit-stock-manager' ); ?></label><input id="laqi-lusm-pack-quantity" name="quantity" inputmode="decimal" required /><?php submit_button( __( 'Define pack', 'laqi-unit-stock-manager' ) ); ?></form></section>
		<section class="card"><h2><?php esc_html_e( 'Receive stock now', 'laqi-unit-stock-manager' ); ?></h2><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="laqi_lusm_receive_supplier_pack" /><?php wp_nonce_field( 'laqi_lusm_receive_supplier_pack' ); ?><label for="laqi-lusm-receive-pack"><?php esc_html_e( 'Supplier pack', 'laqi-unit-stock-manager' ); ?></label><select id="laqi-lusm-receive-pack" name="pack_id" required>
		<?php
		foreach ( $packs as $pack ) :
			?>
			<option value="<?php echo esc_attr( (string) $pack['id'] ); ?>"><?php echo esc_html( $pack['supplier_name'] . ' — ' . $pack['pack_name'] . ' → ' . $pack['pool_name'] ); ?></option><?php endforeach; ?></select><label for="laqi-lusm-pack-count"><?php esc_html_e( 'Packages received', 'laqi-unit-stock-manager' ); ?></label><input type="number" min="1" max="1000000" id="laqi-lusm-pack-count" name="pack_count" value="1" required /><label for="laqi-lusm-supplier-lot"><?php esc_html_e( 'Supplier lot (optional)', 'laqi-unit-stock-manager' ); ?></label><input id="laqi-lusm-supplier-lot" name="supplier_lot" maxlength="191" /><label for="laqi-lusm-expiry-date"><?php esc_html_e( 'Expiry date (optional)', 'laqi-unit-stock-manager' ); ?></label><input type="date" id="laqi-lusm-expiry-date" name="expiry_date" /><label for="laqi-lusm-total-cost"><?php esc_html_e( 'Total material cost (optional)', 'laqi-unit-stock-manager' ); ?></label><input type="number" id="laqi-lusm-total-cost" name="total_cost" inputmode="decimal" min="0" step="any" /><p class="description"><?php echo esc_html( get_woocommerce_currency() . ' — ' . __( 'used for weighted-average material economics only; retail prices are never changed.', 'laqi-unit-stock-manager' ) ); ?></p><label for="laqi-lusm-receipt-reference"><?php esc_html_e( 'Delivery reference', 'laqi-unit-stock-manager' ); ?></label><input id="laqi-lusm-receipt-reference" name="reference" maxlength="191" /><?php submit_button( __( 'Receive into stock', 'laqi-unit-stock-manager' ) ); ?></form></section>
		<section class="card"><h2><?php esc_html_e( 'Schedule incoming stock', 'laqi-unit-stock-manager' ); ?></h2><p><?php esc_html_e( 'Incoming quantities remain separate from on-hand stock until you confirm their arrival.', 'laqi-unit-stock-manager' ); ?></p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="laqi_lusm_schedule_incoming_stock" /><?php wp_nonce_field( 'laqi_lusm_schedule_incoming_stock' ); ?><label for="laqi-lusm-incoming-pack"><?php esc_html_e( 'Supplier pack', 'laqi-unit-stock-manager' ); ?></label><select id="laqi-lusm-incoming-pack" name="pack_id" required>
		<?php
		foreach ( $packs as $pack ) :
			?>
			<option value="<?php echo esc_attr( (string) $pack['id'] ); ?>"><?php echo esc_html( $pack['supplier_name'] . ' — ' . $pack['pack_name'] . ' → ' . $pack['pool_name'] ); ?></option><?php endforeach; ?></select><label for="laqi-lusm-incoming-count"><?php esc_html_e( 'Packages expected', 'laqi-unit-stock-manager' ); ?></label><input type="number" min="1" max="1000000" id="laqi-lusm-incoming-count" name="pack_count" value="1" required /><label for="laqi-lusm-expected-date"><?php esc_html_e( 'Expected arrival', 'laqi-unit-stock-manager' ); ?></label><input type="date" id="laqi-lusm-expected-date" name="expected_date" value="<?php echo esc_attr( wp_date( 'Y-m-d', time() + DAY_IN_SECONDS ) ); ?>" required /><label for="laqi-lusm-incoming-reference"><?php esc_html_e( 'Order or delivery reference', 'laqi-unit-stock-manager' ); ?></label><input id="laqi-lusm-incoming-reference" name="reference" maxlength="191" /><?php submit_button( __( 'Schedule incoming stock', 'laqi-unit-stock-manager' ) ); ?></form></section></div>
		<section class="card"><h2><?php esc_html_e( 'Incoming deliveries', 'laqi-unit-stock-manager' ); ?></h2>
		<?php
		if ( array() === $incoming ) :
			?>
			<p><?php esc_html_e( 'No incoming stock is currently scheduled.', 'laqi-unit-stock-manager' ); ?></p>
			<?php
else :
	?>
			<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Expected', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Supplier', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Pack', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Pool', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Incoming quantity', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Reference', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Action', 'laqi-unit-stock-manager' ); ?></th></tr></thead><tbody>
			<?php
			foreach ( $incoming as $delivery ) :
				?>
	<tr><td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $delivery['expected_date'] ) ); ?></td><td><?php echo esc_html( $delivery['supplier_name'] ); ?></td><td><?php echo esc_html( $delivery['pack_count'] . ' × ' . $delivery['pack_name'] ); ?></td><td><?php echo esc_html( $delivery['pool_name'] ); ?></td><td><?php echo esc_html( $this->formatter->format( new Quantity( $delivery['family'], (int) $delivery['quantity_base'] ), $delivery['display_unit'] ) ); ?></td><td><?php echo esc_html( $delivery['reference'] ); ?></td><td><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="laqi_lusm_receive_incoming_stock" /><input type="hidden" name="incoming_id" value="<?php echo esc_attr( (string) $delivery['id'] ); ?>" /><?php wp_nonce_field( 'laqi_lusm_receive_incoming_stock_' . $delivery['id'] ); ?><label for="laqi-lusm-incoming-lot-<?php echo esc_attr( (string) $delivery['id'] ); ?>"><?php esc_html_e( 'Supplier lot', 'laqi-unit-stock-manager' ); ?></label><input id="laqi-lusm-incoming-lot-<?php echo esc_attr( (string) $delivery['id'] ); ?>" name="supplier_lot" maxlength="191" /><label for="laqi-lusm-incoming-expiry-<?php echo esc_attr( (string) $delivery['id'] ); ?>"><?php esc_html_e( 'Expiry date', 'laqi-unit-stock-manager' ); ?></label><input type="date" id="laqi-lusm-incoming-expiry-<?php echo esc_attr( (string) $delivery['id'] ); ?>" name="expiry_date" /><?php submit_button( __( 'Mark received', 'laqi-unit-stock-manager' ), 'secondary small', '', false ); ?></form></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></section>
		<section class="card"><h2><?php esc_html_e( 'Received batches and lots', 'laqi-unit-stock-manager' ); ?></h2><p><?php esc_html_e( 'Each receipt creates a traceable batch. Empty supplier-lot and expiry fields remain valid and can be completed by later batch workflows.', 'laqi-unit-stock-manager' ); ?></p><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Received', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Pool', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Supplier', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Supplier lot', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Expiry', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Received quantity', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Available in batch', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Recall', 'laqi-unit-stock-manager' ); ?></th></tr></thead><tbody>
		<?php foreach ( $batches as $batch ) : ?>
		<tr><td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $batch['received_at'] . ' +0000' ) ); ?></td><td><?php echo esc_html( $batch['pool_name'] ); ?></td><td><?php echo esc_html( $batch['supplier_name'] ); ?></td><td><?php echo esc_html( '' === $batch['supplier_lot'] ? __( 'Not supplied', 'laqi-unit-stock-manager' ) : $batch['supplier_lot'] ); ?></td><td><?php echo esc_html( empty( $batch['expiry_date'] ) ? __( 'No expiry', 'laqi-unit-stock-manager' ) : mysql2date( get_option( 'date_format' ), $batch['expiry_date'] ) ); ?></td><td><?php echo esc_html( $this->formatter->format( new Quantity( $batch['family'], (int) $batch['quantity_received_base'] ), $batch['display_unit'] ) ); ?></td><td><?php echo esc_html( $this->formatter->format( new Quantity( $batch['family'], (int) $batch['quantity_available_base'] ), $batch['display_unit'] ) . ' — ' . ( $statuses[ $batch['status'] ] ?? $batch['status'] ) ); ?>
			<?php
			if ( in_array( $batch['status'], array( 'active', 'quarantined' ), true ) && (int) $batch['quantity_available_base'] > 0 ) :
				?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="laqi_lusm_batch_<?php echo esc_attr( 'active' === $batch['status'] ? 'quarantine' : 'release' ); ?>"/><input type="hidden" name="batch_id" value="<?php echo esc_attr( (string) $batch['id'] ); ?>"/><?php wp_nonce_field( 'laqi_lusm_batch_' . ( 'active' === $batch['status'] ? 'quarantine' : 'release' ) . '_' . $batch['id'] ); ?><label for="laqi-lusm-batch-reason-<?php echo esc_attr( (string) $batch['id'] ); ?>"><?php esc_html_e( 'Reason (optional)', 'laqi-unit-stock-manager' ); ?></label><input id="laqi-lusm-batch-reason-<?php echo esc_attr( (string) $batch['id'] ); ?>" name="reason" maxlength="191"/><?php submit_button( 'active' === $batch['status'] ? __( 'Quarantine batch', 'laqi-unit-stock-manager' ) : __( 'Release batch', 'laqi-unit-stock-manager' ), 'secondary small', '', false ); ?></form><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="laqi_lusm_batch_stocktake"/><input type="hidden" name="batch_id" value="<?php echo esc_attr( (string) $batch['id'] ); ?>"/><?php wp_nonce_field( 'laqi_lusm_batch_stocktake_' . $batch['id'] ); ?><label for="laqi-lusm-batch-count-<?php echo esc_attr( (string) $batch['id'] ); ?>"><?php esc_html_e( 'Counted quantity', 'laqi-unit-stock-manager' ); ?></label><input id="laqi-lusm-batch-count-<?php echo esc_attr( (string) $batch['id'] ); ?>" name="quantity" inputmode="decimal" required/><?php submit_button( __( 'Save batch count', 'laqi-unit-stock-manager' ), 'secondary small', '', false ); ?></form><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="laqi_lusm_batch_write_off"/><input type="hidden" name="batch_id" value="<?php echo esc_attr( (string) $batch['id'] ); ?>"/><?php wp_nonce_field( 'laqi_lusm_batch_write_off_' . $batch['id'] ); ?><?php submit_button( __( 'Write off batch', 'laqi-unit-stock-manager' ), 'secondary small', '', false ); ?></form>
				<?php
			elseif ( 'recalled' === $batch['status'] && (int) $batch['quantity_available_base'] > 0 ) :
				?>
											<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="laqi_lusm_batch_write_off"/><input type="hidden" name="batch_id" value="<?php echo esc_attr( (string) $batch['id'] ); ?>"/><?php wp_nonce_field( 'laqi_lusm_batch_write_off_' . $batch['id'] ); ?><?php submit_button( __( 'Write off recalled stock', 'laqi-unit-stock-manager' ), 'secondary small', '', false ); ?></form><?php endif; ?></td><td>
			<?php
			if ( 'recalled' !== $batch['status'] ) :
				?>
				<a class="button button-small" href="
				<?php
				echo esc_url(
					add_query_arg(
						array(
							'page'         => 'laqi-unit-stock-manager',
							'section'      => 'receiving',
							'recall_batch' => (int) $batch['id'],
						),
						admin_url( 'admin.php' )
					)
				);
				?>
				"><?php esc_html_e( 'Review recall', 'laqi-unit-stock-manager' ); ?></a>
				<?php
else :
				esc_html_e( 'Recall confirmed', 'laqi-unit-stock-manager' );
endif;
?>
</td></tr>
		<?php endforeach; ?>
		</tbody></table></section>
		<section class="card"><h2><?php esc_html_e( 'CSV exchange', 'laqi-unit-stock-manager' ); ?></h2><p><?php esc_html_e( 'Export or import the versioned supplier, pack, incoming-delivery, and reorder-policy schema.', 'laqi-unit-stock-manager' ); ?></p><form method="get" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="laqi_lusm_export_operations" /><?php wp_nonce_field( 'laqi_lusm_export_operations' ); ?><?php submit_button( __( 'Export operations CSV', 'laqi-unit-stock-manager' ), 'secondary', '', false ); ?></form><form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="laqi_lusm_import_operations" /><?php wp_nonce_field( 'laqi_lusm_import_operations' ); ?><label for="laqi-lusm-operations-csv"><?php esc_html_e( 'Operations CSV', 'laqi-unit-stock-manager' ); ?></label><input type="file" id="laqi-lusm-operations-csv" name="operations_csv" accept=".csv,text/csv" required /><?php submit_button( __( 'Import operations CSV', 'laqi-unit-stock-manager' ), 'secondary' ); ?></form></section>
		<section class="card"><h2><?php esc_html_e( 'Recent receipts', 'laqi-unit-stock-manager' ); ?></h2><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Received', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Supplier', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Pack', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Pool', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Quantity', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Reference', 'laqi-unit-stock-manager' ); ?></th></tr></thead><tbody>
		<?php
		foreach ( $this->suppliers->receipts() as $receipt ) :
			?>
			<tr><td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $receipt['created_at'] . ' +0000' ) ); ?></td><td><?php echo esc_html( $receipt['supplier_name'] ); ?></td><td><?php echo esc_html( $receipt['pack_count'] . ' × ' . $receipt['pack_name'] ); ?></td><td><?php echo esc_html( $receipt['pool_name'] ); ?></td><td><?php echo esc_html( $this->formatter->format( new Quantity( $receipt['family'], (int) $receipt['quantity_base'] ), $receipt['display_unit'] ) ); ?></td><td><?php echo esc_html( $receipt['reference'] ); ?></td></tr><?php endforeach; ?></tbody></table></section>
		<?php
	}
	/** Render a read-only affected-order preview before recall confirmation. */
	private function render_recall(): void {
		$batch_id = isset( $_GET['recall_batch'] ) ? absint( $_GET['recall_batch'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $batch_id < 1 ) {
			return;
		}
		$batch = $this->batches->find( $batch_id );
		if ( null === $batch || 'recalled' === $batch['status'] ) {
			return;
		}
		$orders = $this->allocations->affected_orders( $batch_id );
		?>
		<section class="card"><h2><?php esc_html_e( 'Review batch recall', 'laqi-unit-stock-manager' ); ?></h2><p><?php echo esc_html( sprintf( /* translators: %s: supplier lot or batch number. */ __( 'Review the affected orders for batch %s before confirming.', 'laqi-unit-stock-manager' ), '' !== $batch['supplier_lot'] ? $batch['supplier_lot'] : '#' . $batch_id ) ); ?></p><p><?php esc_html_e( 'Confirming a recall makes all remaining batch stock unavailable. Customers are never contacted automatically.', 'laqi-unit-stock-manager' ); ?></p>
		<?php
		if ( array() === $orders ) :
			?>
			<p><?php esc_html_e( 'No orders currently contain outstanding quantity from this batch.', 'laqi-unit-stock-manager' ); ?></p><?php else : ?>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Order', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Quantity from batch', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Last allocated', 'laqi-unit-stock-manager' ); ?></th></tr></thead><tbody>
					<?php
					foreach ( $orders as $order ) :
						$wc_order = wc_get_order( (int) $order['order_id'] );
						$edit_url = $wc_order ? $wc_order->get_edit_order_url() : '';
						?>
			<tr><td>
						<?php
						if ( '' !== $edit_url ) :
							?>
				<a href="<?php echo esc_url( $edit_url ); ?>">#<?php echo esc_html( (string) $order['order_id'] ); ?></a>
							<?php
else :
	?>
				#<?php echo esc_html( (string) $order['order_id'] ); ?><?php endif; ?></td><td><?php echo esc_html( $this->formatter->format( new Quantity( $batch['family'], (int) $order['quantity_base'] ), $batch['display_unit'] ) ); ?></td><td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $order['last_allocated_at'] . ' +0000' ) ); ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="laqi_lusm_batch_recall"/><input type="hidden" name="batch_id" value="<?php echo esc_attr( (string) $batch_id ); ?>"/><?php wp_nonce_field( 'laqi_lusm_batch_recall_' . $batch_id ); ?><label for="laqi-lusm-recall-reason"><?php esc_html_e( 'Recall reason', 'laqi-unit-stock-manager' ); ?></label><input id="laqi-lusm-recall-reason" name="reason" maxlength="191" required/><?php submit_button( __( 'Confirm batch recall', 'laqi-unit-stock-manager' ), 'primary', '', false ); ?></form></section>
		<?php
	}
	/** Notice. @return void */
	private function notice(): void {
		$result   = isset( $_GET['laqi_lusm_receiving_result'] ) ? sanitize_key( wp_unslash( $_GET['laqi_lusm_receiving_result'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$messages = array(
			'supplier_created'      => __( 'Supplier added.', 'laqi-unit-stock-manager' ),
			'pack_created'          => __( 'Supplier pack defined.', 'laqi-unit-stock-manager' ),
			'stock_received'        => __( 'Supplier stock received.', 'laqi-unit-stock-manager' ),
			'incoming_scheduled'    => __( 'Incoming stock scheduled.', 'laqi-unit-stock-manager' ),
			'incoming_received'     => __( 'Incoming stock moved into on-hand inventory.', 'laqi-unit-stock-manager' ),
			'batch_quarantined'     => __( 'Batch quarantined.', 'laqi-unit-stock-manager' ),
			'batch_released'        => __( 'Batch released.', 'laqi-unit-stock-manager' ),
			'batch_written_off'     => __( 'Batch written off.', 'laqi-unit-stock-manager' ),
			'batch_stocktake_saved' => __( 'Batch count saved.', 'laqi-unit-stock-manager' ),
			'batch_recalled'        => __( 'Batch recall confirmed. Customers were not contacted.', 'laqi-unit-stock-manager' ),
			'batch_error'           => __( 'The batch operation could not be completed.', 'laqi-unit-stock-manager' ),
			'csv_imported'          => sprintf( /* translators: 1: created rows, 2: skipped rows. */ __( 'CSV imported: %1$d created, %2$d already present.', 'laqi-unit-stock-manager' ), isset( $_GET['created'] ) ? absint( $_GET['created'] ) : 0, isset( $_GET['skipped'] ) ? absint( $_GET['skipped'] ) : 0 ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'csv_error'             => __( 'The operations CSV could not be imported.', 'laqi-unit-stock-manager' ),
			'receiving_error'       => __( 'The receiving operation could not be completed.', 'laqi-unit-stock-manager' ),
		);
		if ( isset( $messages[ $result ] ) ) {
			wp_admin_notice(
				$messages[ $result ],
				array(
					'type'        => in_array( $result, array( 'receiving_error', 'csv_error' ), true ) ? 'error' : 'success',
					'dismissible' => true,
				)
			); } }
}
