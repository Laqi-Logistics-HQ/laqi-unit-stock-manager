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
use LaqiUnitStockManager\Unit\UnitRegistry;
use Throwable;
/** Secures batch state, write-off, and stocktake requests. */
final class BatchOperationsController {
	/** @var BatchOperationsService */ private $operations;
	/** @var BatchRepository */ private $batches;
	/** @var UnitRegistry */ private $units;
	/** Constructor. */ public function __construct( BatchOperationsService $operations, BatchRepository $batches, UnitRegistry $units ) {
		$this->operations = $operations;
		$this->batches    = $batches;
		$this->units      = $units; }
	/** Register. */ public function register(): void {
		add_action( 'admin_post_laqi_lusm_batch_quarantine', array( $this, 'quarantine' ) );
		add_action( 'admin_post_laqi_lusm_batch_release', array( $this, 'release' ) );
		add_action( 'admin_post_laqi_lusm_batch_write_off', array( $this, 'write_off' ) );
		add_action( 'admin_post_laqi_lusm_batch_stocktake', array( $this, 'stocktake' ) );}
	/** Quarantine. */ public function quarantine(): void {
		$this->finish( 'quarantine' );}
	/** Release. */ public function release(): void {
		$this->finish( 'release' );}
	/** Write off. */ public function write_off(): void {
		$this->finish( 'write_off' );}
	/** Stocktake. */ public function stocktake(): void {
		$this->finish( 'stocktake' );}
	/** Execute. */ private function finish( string $operation ): void {
		$id = isset( $_POST['batch_id'] ) ? absint( $_POST['batch_id'] ) : 0;
		$this->authorize( 'laqi_lusm_batch_' . $operation . '_' . $id );
		try {
			$actor_id = get_current_user_id();
			if ( 'stocktake' === $operation ) {
				$batch = $this->batches->find( $id );
				if ( null === $batch ) {
					throw new \InvalidArgumentException( 'Unknown batch.' );
				}$value = isset( $_POST['quantity'] ) ? sanitize_text_field( wp_unslash( $_POST['quantity'] ) ) : '';
				$target = $this->units->normalize( $value, $batch['display_unit'] )->amount();
				$this->operations->stocktake( $id, $target, $actor_id );
			} elseif ( 'write_off' === $operation ) {
				$this->operations->write_off( $id, $actor_id );
			} else {
				$reason = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';
				$this->operations->{$operation}( $id, $actor_id, $reason );
			}$results = array(
				'quarantine' => 'batch_quarantined',
				'release'    => 'batch_released',
				'write_off'  => 'batch_written_off',
				'stocktake'  => 'batch_stocktake_saved',
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
