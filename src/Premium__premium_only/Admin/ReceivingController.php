<?php
/**
 * Paid receiving request controller.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Admin;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Admin\UnitStockPage;
use LaqiUnitStockManager\Premium\Receiving\ReceivingService;
use LaqiUnitStockManager\Premium\Receiving\SupplierRepository;
use LaqiUnitStockManager\Storage\PoolRepository;
use LaqiUnitStockManager\Unit\UnitRegistry;
use Throwable;

/** Validates supplier, pack, and receipt administration. */
final class ReceivingController {
	/** Suppliers. @var SupplierRepository
	 *
	 * @var SupplierRepository
	 */ private $suppliers;
	/** Pools. @var PoolRepository
	 *
	 * @var PoolRepository
	 */ private $pools;
	/** Units. @var UnitRegistry
	 *
	 * @var UnitRegistry
	 */ private $units;
	/** Receiving. @var ReceivingService
	 *
	 * @var ReceivingService
	 */ private $receiving;
	/** Constructor.
	 *
	 * @param SupplierRepository $suppliers Suppliers.
	 * @param PoolRepository     $pools Pools.
	 * @param UnitRegistry       $units Units.
	 * @param ReceivingService   $receiving Receiving.
	 */
	public function __construct( SupplierRepository $suppliers, PoolRepository $pools, UnitRegistry $units, ReceivingService $receiving ) {
		$this->suppliers = $suppliers;
		$this->pools     = $pools;
		$this->units     = $units;
		$this->receiving = $receiving; }
	/** Register endpoints. @return void */
	public function register(): void {
		add_action( 'admin_post_laqi_lusm_create_supplier', array( $this, 'create_supplier' ) );
		add_action( 'admin_post_laqi_lusm_create_supplier_pack', array( $this, 'create_pack' ) );
		add_action( 'admin_post_laqi_lusm_receive_supplier_pack', array( $this, 'receive' ) );
		add_action( 'admin_post_laqi_lusm_schedule_incoming_stock', array( $this, 'schedule_incoming' ) );
		add_action( 'admin_post_laqi_lusm_receive_incoming_stock', array( $this, 'receive_incoming' ) );
	}
	/** Create supplier. @return void */
	public function create_supplier(): void {
		$this->authorize( 'laqi_lusm_create_supplier' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified above.
		try {
			$name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
			$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
			$days  = isset( $_POST['lead_time_days'] ) ? absint( $_POST['lead_time_days'] ) : 0;
			$this->suppliers->create_supplier( $name, $email, $days );
			$this->redirect( 'supplier_created' );
		} catch ( Throwable $error ) {
			unset( $error );
			$this->redirect( 'receiving_error' ); }
	}
	/** Create pack.
	 *
	 * @return void
	 * @throws \InvalidArgumentException For an unknown pool or unit mismatch.
	 */
	public function create_pack(): void {
		$this->authorize( 'laqi_lusm_create_supplier_pack' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified above.
		try {
			$supplier_id = isset( $_POST['supplier_id'] ) ? absint( $_POST['supplier_id'] ) : 0;
			$pool_id     = isset( $_POST['pool_id'] ) ? absint( $_POST['pool_id'] ) : 0;
			$name        = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
			$value       = isset( $_POST['quantity'] ) ? sanitize_text_field( wp_unslash( $_POST['quantity'] ) ) : '';
			$pool        = $this->pools->find( $pool_id );
			if ( null === $pool ) {
				throw new \InvalidArgumentException( 'Unknown pool.' ); }
			$quantity = $this->units->normalize( $value, $pool->display_unit() );
			if ( $quantity->family() !== $pool->quantity()->family() ) {
				throw new \InvalidArgumentException( 'Wrong pack unit.' ); }
			$this->suppliers->create_pack( $supplier_id, $pool_id, $name, $quantity->amount() );
			$this->redirect( 'pack_created' );
		} catch ( Throwable $error ) {
			unset( $error );
			$this->redirect( 'receiving_error' ); }
	}
	/** Receive packs. @return void */
	public function receive(): void {
		$this->authorize( 'laqi_lusm_receive_supplier_pack' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified above.
		try {
			$pack_id   = isset( $_POST['pack_id'] ) ? absint( $_POST['pack_id'] ) : 0;
			$count     = isset( $_POST['pack_count'] ) ? absint( $_POST['pack_count'] ) : 0;
			$reference = isset( $_POST['reference'] ) ? sanitize_text_field( wp_unslash( $_POST['reference'] ) ) : '';
			$this->receiving->receive( $pack_id, $count, $reference, get_current_user_id(), 'receipt:' . get_current_user_id() . ':' . wp_generate_uuid4() );
			$this->redirect( 'stock_received' );
		} catch ( Throwable $error ) {
			unset( $error );
			$this->redirect( 'receiving_error' ); }
	}
	/** Schedule incoming stock. @return void */
	public function schedule_incoming(): void {
		$this->authorize( 'laqi_lusm_schedule_incoming_stock' );
		try {
			$pack_id   = isset( $_POST['pack_id'] ) ? absint( $_POST['pack_id'] ) : 0;
			$count     = isset( $_POST['pack_count'] ) ? absint( $_POST['pack_count'] ) : 0;
			$date      = isset( $_POST['expected_date'] ) ? sanitize_text_field( wp_unslash( $_POST['expected_date'] ) ) : '';
			$reference = isset( $_POST['reference'] ) ? sanitize_text_field( wp_unslash( $_POST['reference'] ) ) : '';
			$this->suppliers->create_incoming( $pack_id, $count, $date, $reference );
			$this->redirect( 'incoming_scheduled' );
		} catch ( Throwable $error ) {
			unset( $error );
			$this->redirect( 'receiving_error' );
		}
	}
	/** Receive scheduled incoming stock. @return void */
	public function receive_incoming(): void {
		$incoming_id = isset( $_POST['incoming_id'] ) ? absint( $_POST['incoming_id'] ) : 0;
		$this->authorize( 'laqi_lusm_receive_incoming_stock_' . $incoming_id );
		try {
			$this->receiving->receive_incoming( $incoming_id, get_current_user_id() );
			$this->redirect( 'incoming_received' );
		} catch ( Throwable $error ) {
			unset( $error );
			$this->redirect( 'receiving_error' );
		}
	}
	/** Authorize a write.
	 *
	 * @param string $nonce Nonce action.
	 * @return void
	 */
	private function authorize( string $nonce ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage receiving.', 'laqi-unit-stock-manager' ) );
		} check_admin_referer( $nonce ); }
	/** Redirect.
	 *
	 * @param string $result Result.
	 * @return void
	 */
	private function redirect( string $result ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                       => UnitStockPage::SLUG,
					'section'                    => 'receiving',
					'laqi_lusm_receiving_result' => $result,
				),
				admin_url( 'admin.php' )
			)
		);
		exit; }
}
