<?php
/**
 * Paid reorder settings controller.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Admin;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Admin\UnitStockPage;
use LaqiUnitStockManager\Premium\Receiving\SupplierRepository;
use LaqiUnitStockManager\Premium\Replenishment\ReorderPolicyRepository;
use LaqiUnitStockManager\Storage\PoolRepository;
use LaqiUnitStockManager\Unit\UnitRegistry;
use Throwable;

/** Saves exact pool replenishment policies. */
final class ReorderController {
	/** Policies. @var ReorderPolicyRepository
	 *
	 * @var ReorderPolicyRepository
	 */ private $policies;
	/** Pools. @var PoolRepository
	 *
	 * @var PoolRepository
	 */ private $pools;
	/** Suppliers. @var SupplierRepository
	 *
	 * @var SupplierRepository
	 */ private $suppliers;
	/** Units. @var UnitRegistry
	 *
	 * @var UnitRegistry
	 */ private $units;
	/** Constructor.
	 *
	 * @param ReorderPolicyRepository $policies Policies.
	 * @param PoolRepository          $pools Pools.
	 * @param SupplierRepository      $suppliers Suppliers.
	 * @param UnitRegistry            $units Units.
	 */
	public function __construct( ReorderPolicyRepository $policies, PoolRepository $pools, SupplierRepository $suppliers, UnitRegistry $units ) {
		$this->policies  = $policies;
		$this->pools     = $pools;
		$this->suppliers = $suppliers;
		$this->units     = $units; }
	/** Register endpoint. @return void */ public function register(): void {
		add_action( 'admin_post_laqi_lusm_save_reorder_policy', array( $this, 'handle' ) ); }
	/** Save policy.
	 *
	 * @return void
	 * @throws \InvalidArgumentException When configuration is invalid.
	 */
	public function handle(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage reorder suggestions.', 'laqi-unit-stock-manager' ) ); }
		$pool_id = isset( $_POST['pool_id'] ) ? absint( $_POST['pool_id'] ) : 0;
		check_admin_referer( 'laqi_lusm_save_reorder_policy_' . $pool_id );
		try {
			$pool    = $this->pools->find( $pool_id );
			$pack_id = isset( $_POST['pack_id'] ) ? absint( $_POST['pack_id'] ) : 0;
			$pack    = $this->suppliers->pack( $pack_id );
			$value   = isset( $_POST['safety_stock'] ) ? sanitize_text_field( wp_unslash( $_POST['safety_stock'] ) ) : '';
			if ( null === $pool || null === $pack || (int) $pack['pool_id'] !== $pool_id ) {
				throw new \InvalidArgumentException( 'Invalid reorder policy.' ); }
			$quantity = $this->units->normalize( $value, $pool->display_unit() );
			$this->policies->save( $pool_id, $pack_id, $quantity->amount() );
			$this->redirect( $pool_id, 'reorder_saved' );
		} catch ( Throwable $error ) {
			unset( $error );
			$this->redirect( $pool_id, 'reorder_error' ); }
	}
	/** Redirect.
	 *
	 * @param int    $pool_id Pool ID.
	 * @param string $result Result.
	 * @return void
	 */
	private function redirect( int $pool_id, string $result ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                     => UnitStockPage::SLUG,
					'section'                  => 'reorder',
					'pool_id'                  => $pool_id,
					'laqi_lusm_reorder_result' => $result,
				),
				admin_url( 'admin.php' )
			)
		);
		exit; }
}
