<?php
/**
 * Batch operation requests.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Admin;

defined( 'ABSPATH' ) || exit;
// phpcs:disable Generic.Commenting.DocComment.MissingShort, Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag, WordPress.Security.NonceVerification.Missing
use LaqiUnitStockManager\Admin\UnitStockPage;
use LaqiUnitStockManager\Premium\Batches\BatchOperationsService;
use LaqiUnitStockManager\Premium\Batches\BatchRepository;
use LaqiUnitStockManager\Premium\Batches\BatchTransferService;
use LaqiUnitStockManager\Unit\UnitRegistry;
use Throwable;
/** Secures batch state, write-off, and stocktake requests. */
final class BatchOperationsController {
	/** @var BatchOperationsService */ private $operations;
	/** @var BatchRepository */ private $batches;
	/** @var UnitRegistry */ private $units;
	/** @var BatchTransferService */ private $transfers;
	/** Constructor. */ public function __construct( BatchOperationsService $operations, BatchRepository $batches, UnitRegistry $units, BatchTransferService $transfers ) {
		$this->operations = $operations;
		$this->batches    = $batches;
		$this->units      = $units;
		$this->transfers  = $transfers; }
	/** Register. */ public function register(): void {
		add_action( 'admin_post_laqi_lusm_batch_quarantine', array( $this, 'quarantine' ) );
		add_action( 'admin_post_laqi_lusm_batch_release', array( $this, 'release' ) );
		add_action( 'admin_post_laqi_lusm_batch_write_off', array( $this, 'write_off' ) );
		add_action( 'admin_post_laqi_lusm_batch_expiry_write_off', array( $this, 'expiry_write_off' ) );
		add_action( 'admin_post_laqi_lusm_batch_stocktake', array( $this, 'stocktake' ) );
		add_action( 'admin_post_laqi_lusm_batch_recall', array( $this, 'recall' ) );
		add_action( 'admin_post_laqi_lusm_batch_transfer', array( $this, 'transfer' ) );}
	/** Transfer. */ public function transfer(): void {
		$this->finish( 'transfer' );}
	/** Confirm recall. */ public function recall(): void {
		$this->finish( 'recall' );}
	/** Quarantine. */ public function quarantine(): void {
		$this->finish( 'quarantine' );}
	/** Release. */ public function release(): void {
		$this->finish( 'release' );}
	/** Write off. */ public function write_off(): void {
		$this->finish( 'write_off' );}
	/** Write off expired stock. */ public function expiry_write_off(): void {
		$this->finish( 'expiry_write_off' );}
	/** Stocktake. */ public function stocktake(): void {
		$this->finish( 'stocktake' );}
	/** Execute. */ private function finish( string $operation ): void {
		$id = isset( $_POST['batch_id'] ) ? absint( $_POST['batch_id'] ) : 0;
		$this->authorize( 'laqi_lusm_batch_' . $operation . '_' . $id );
		try {
			$actor_id = get_current_user_id();
			if ( 'transfer' === $operation ) {
				$batch = $this->batches->find( $id );
				if ( null === $batch ) {
					throw new \InvalidArgumentException( 'Unknown batch.' );
				}
				$value       = isset( $_POST['quantity'] ) ? sanitize_text_field( wp_unslash( $_POST['quantity'] ) ) : '';
				$quantity    = $this->units->normalize( $value, $batch['display_unit'] )->amount();
				$destination = isset( $_POST['destination_pool_id'] ) ? absint( $_POST['destination_pool_id'] ) : 0;
				$reason      = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';
				$event_key   = isset( $_POST['transfer_key'] ) ? sanitize_text_field( wp_unslash( $_POST['transfer_key'] ) ) : '';
				$this->transfers->transfer( $id, $destination, $quantity, $actor_id, $reason, $event_key );
			} elseif ( 'stocktake' === $operation ) {
				$batch = $this->batches->find( $id );
				if ( null === $batch ) {
					throw new \InvalidArgumentException( 'Unknown batch.' );
				}$value = isset( $_POST['quantity'] ) ? sanitize_text_field( wp_unslash( $_POST['quantity'] ) ) : '';
				$target = $this->units->normalize( $value, $batch['display_unit'] )->amount();
				$this->operations->stocktake( $id, $target, $actor_id );
			} elseif ( 'expiry_write_off' === $operation ) {
				$this->operations->write_off( $id, $actor_id, 'loss_expiry', 'Expired batch waste' );
			} elseif ( 'write_off' === $operation ) {
				$this->operations->write_off( $id, $actor_id );
			} else {
				$reason = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';
				$this->operations->{$operation}( $id, $actor_id, $reason );
			}$results = array(
				'quarantine'       => 'batch_quarantined',
				'release'          => 'batch_released',
				'recall'           => 'batch_recalled',
				'transfer'         => 'batch_transferred',
				'write_off'        => 'batch_written_off',
				'expiry_write_off' => 'batch_expiry_written_off',
				'stocktake'        => 'batch_stocktake_saved',
			);
			$this->redirect( $results[ $operation ] );
		} catch ( Throwable $error ) {
			unset( $error );
			$this->redirect( 'batch_error' );}}
	/** Authorize. */ private function authorize( string $nonce ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage batches.', 'laqi-unit-stock-manager' ) );
		}check_admin_referer( $nonce );}
	/** Redirect. */ private function redirect( string $result ): void {
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
		exit;}
}
