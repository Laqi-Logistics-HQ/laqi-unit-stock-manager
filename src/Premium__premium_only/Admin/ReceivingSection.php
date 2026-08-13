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
	/** Constructor.
	 *
	 * @param SupplierRepository $suppliers Suppliers.
	 * @param PoolRepository     $pools Pools.
	 * @param QuantityFormatter  $formatter Formatter.
	 */
	public function __construct( SupplierRepository $suppliers, PoolRepository $pools, QuantityFormatter $formatter ) {
		$this->suppliers = $suppliers;
		$this->pools     = $pools;
		$this->formatter = $formatter; }
	/** ID. @return string */ public function id(): string {
		return 'receiving'; }
	/** Title. @return string */ public function title(): string {
		return __( 'Receiving', 'laqi-unit-stock-manager' ); }
	/** Render. @return void */
	public function render(): void {
		$suppliers = $this->suppliers->suppliers();
		$packs     = $this->suppliers->packs();
		$incoming  = $this->suppliers->incoming_deliveries();
		$this->notice(); ?>
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
			<option value="<?php echo esc_attr( (string) $pack['id'] ); ?>"><?php echo esc_html( $pack['supplier_name'] . ' — ' . $pack['pack_name'] . ' → ' . $pack['pool_name'] ); ?></option><?php endforeach; ?></select><label for="laqi-lusm-pack-count"><?php esc_html_e( 'Packages received', 'laqi-unit-stock-manager' ); ?></label><input type="number" min="1" max="1000000" id="laqi-lusm-pack-count" name="pack_count" value="1" required /><label for="laqi-lusm-receipt-reference"><?php esc_html_e( 'Delivery reference', 'laqi-unit-stock-manager' ); ?></label><input id="laqi-lusm-receipt-reference" name="reference" maxlength="191" /><?php submit_button( __( 'Receive into stock', 'laqi-unit-stock-manager' ) ); ?></form></section>
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
	<tr><td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $delivery['expected_date'] ) ); ?></td><td><?php echo esc_html( $delivery['supplier_name'] ); ?></td><td><?php echo esc_html( $delivery['pack_count'] . ' × ' . $delivery['pack_name'] ); ?></td><td><?php echo esc_html( $delivery['pool_name'] ); ?></td><td><?php echo esc_html( $this->formatter->format( new Quantity( $delivery['family'], (int) $delivery['quantity_base'] ), $delivery['display_unit'] ) ); ?></td><td><?php echo esc_html( $delivery['reference'] ); ?></td><td><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="laqi_lusm_receive_incoming_stock" /><input type="hidden" name="incoming_id" value="<?php echo esc_attr( (string) $delivery['id'] ); ?>" /><?php wp_nonce_field( 'laqi_lusm_receive_incoming_stock_' . $delivery['id'] ); ?><?php submit_button( __( 'Mark received', 'laqi-unit-stock-manager' ), 'secondary small', '', false ); ?></form></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></section>
		<section class="card"><h2><?php esc_html_e( 'Recent receipts', 'laqi-unit-stock-manager' ); ?></h2><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Received', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Supplier', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Pack', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Pool', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Quantity', 'laqi-unit-stock-manager' ); ?></th><th><?php esc_html_e( 'Reference', 'laqi-unit-stock-manager' ); ?></th></tr></thead><tbody>
		<?php
		foreach ( $this->suppliers->receipts() as $receipt ) :
			?>
			<tr><td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $receipt['created_at'] . ' +0000' ) ); ?></td><td><?php echo esc_html( $receipt['supplier_name'] ); ?></td><td><?php echo esc_html( $receipt['pack_count'] . ' × ' . $receipt['pack_name'] ); ?></td><td><?php echo esc_html( $receipt['pool_name'] ); ?></td><td><?php echo esc_html( $this->formatter->format( new Quantity( $receipt['family'], (int) $receipt['quantity_base'] ), $receipt['display_unit'] ) ); ?></td><td><?php echo esc_html( $receipt['reference'] ); ?></td></tr><?php endforeach; ?></tbody></table></section>
		<?php
	}
	/** Notice. @return void */
	private function notice(): void {
		$result   = isset( $_GET['laqi_lusm_receiving_result'] ) ? sanitize_key( wp_unslash( $_GET['laqi_lusm_receiving_result'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$messages = array(
			'supplier_created'   => __( 'Supplier added.', 'laqi-unit-stock-manager' ),
			'pack_created'       => __( 'Supplier pack defined.', 'laqi-unit-stock-manager' ),
			'stock_received'     => __( 'Supplier stock received.', 'laqi-unit-stock-manager' ),
			'incoming_scheduled' => __( 'Incoming stock scheduled.', 'laqi-unit-stock-manager' ),
			'incoming_received'  => __( 'Incoming stock moved into on-hand inventory.', 'laqi-unit-stock-manager' ),
			'receiving_error'    => __( 'The receiving operation could not be completed.', 'laqi-unit-stock-manager' ),
		);
		if ( isset( $messages[ $result ] ) ) {
			wp_admin_notice(
				$messages[ $result ],
				array(
					'type'        => 'receiving_error' === $result ? 'error' : 'success',
					'dismissible' => true,
				)
			); } }
}
