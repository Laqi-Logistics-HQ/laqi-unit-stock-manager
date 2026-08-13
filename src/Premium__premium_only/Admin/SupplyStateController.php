<?php
/**
 * Supply-state requests.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Admin;

defined( 'ABSPATH' ) || exit;
// phpcs:disable Generic.Commenting.DocComment.MissingShort, Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag
// phpcs:disable WordPress.Security.NonceVerification.Missing -- Each endpoint verifies its dynamic nonce before acting.
use LaqiUnitStockManager\Admin\UnitStockPage;
use LaqiUnitStockManager\Premium\Supply\StockHoldService;
use LaqiUnitStockManager\Premium\Supply\SafetyStockPolicyRepository;
use LaqiUnitStockManager\Storage\PoolRepository;
use LaqiUnitStockManager\Unit\UnitRegistry;
use Throwable;
/** Secures quarantined/damaged stock actions. */
final class SupplyStateController {
	/** @var StockHoldService */ private $holds;
	/** @var PoolRepository */ private $pools;
	/** @var UnitRegistry */ private $units;
	/** @var SafetyStockPolicyRepository */ private $safety_stock;
	/** Constructor. */ public function __construct( StockHoldService $holds, PoolRepository $pools, UnitRegistry $units, SafetyStockPolicyRepository $safety_stock ) {
		$this->holds        = $holds;
		$this->pools        = $pools;
		$this->units        = $units;
		$this->safety_stock = $safety_stock;}
	/** Register endpoints. */ public function register(): void {
		add_action( 'admin_post_laqi_lusm_place_stock_hold', array( $this, 'place' ) );
		add_action( 'admin_post_laqi_lusm_release_stock_hold', array( $this, 'release' ) );
		add_action( 'admin_post_laqi_lusm_write_off_stock_hold', array( $this, 'write_off' ) );
		add_action( 'admin_post_laqi_lusm_save_safety_stock', array( $this, 'save_safety_stock' ) );}
	/** Save protected stock. */ public function save_safety_stock(): void {
		$pool_id = isset( $_POST['pool_id'] ) ? absint( $_POST['pool_id'] ) : 0;
		$this->authorize( 'laqi_lusm_save_safety_stock_' . $pool_id );
		try {
			$pool = $this->pools->find( $pool_id );
			if ( null === $pool ) {
				throw new \InvalidArgumentException( 'Unknown pool.' );
			}$value   = isset( $_POST['safety_stock'] ) ? sanitize_text_field( wp_unslash( $_POST['safety_stock'] ) ) : '';
			$quantity = $this->units->normalize( $value, $pool->display_unit() );
			$this->safety_stock->save( $pool_id, $quantity->amount() );
			$this->redirect( $pool_id, 'safety_stock_saved' );
		} catch ( Throwable $error ) {
			unset( $error );
			$this->redirect( $pool_id, 'supply_error' );}}
	/** Place hold. */ public function place(): void {
		$pool_id = isset( $_POST['pool_id'] ) ? absint( $_POST['pool_id'] ) : 0;
		$this->authorize( 'laqi_lusm_place_stock_hold_' . $pool_id );
		try {
			$pool = $this->pools->find( $pool_id );
			if ( null === $pool ) {
				throw new \InvalidArgumentException( 'Unknown pool.' );
			}$value   = isset( $_POST['quantity'] ) ? sanitize_text_field( wp_unslash( $_POST['quantity'] ) ) : '';
			$state    = isset( $_POST['state'] ) ? sanitize_key( wp_unslash( $_POST['state'] ) ) : '';
			$reason   = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';
			$quantity = $this->units->normalize( $value, $pool->display_unit() );
			$this->holds->place( $pool_id, $state, $quantity->amount(), $reason, get_current_user_id() );
			$this->redirect( $pool_id, 'hold_placed' );
		} catch ( Throwable $error ) {
			unset( $error );
			$this->redirect( $pool_id, 'supply_error' );}}
	/** Release. */ public function release(): void {
		$this->finish( 'release' );}
	/** Write off. */ public function write_off(): void {
		$this->finish( 'write_off' );}
	/** Complete action. */ private function finish( string $action ): void {
		$hold_id = isset( $_POST['hold_id'] ) ? absint( $_POST['hold_id'] ) : 0;
		$pool_id = isset( $_POST['pool_id'] ) ? absint( $_POST['pool_id'] ) : 0;
		$this->authorize( 'laqi_lusm_' . $action . '_stock_hold_' . $hold_id );
		try {
			if ( 'release' === $action ) {
				$this->holds->release( $hold_id );
			} else {
				$this->holds->write_off( $hold_id, get_current_user_id() );
			}$this->redirect( $pool_id, 'hold_' . $action . 'd' );
		} catch ( Throwable $error ) {
			unset( $error );
			$this->redirect( $pool_id, 'supply_error' );}}
	/** Authorize. */ private function authorize( string $nonce ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage supply states.', 'laqi-unit-stock-manager' ) );
		}check_admin_referer( $nonce );}
	/** Redirect. */ private function redirect( int $pool_id, string $result ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                    => UnitStockPage::SLUG,
					'section'                 => 'reservations',
					'pool_id'                 => $pool_id,
					'laqi_lusm_supply_result' => $result,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;}
}
