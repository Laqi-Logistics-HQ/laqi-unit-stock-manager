<?php
/**
 * Inventory-pool detail edits.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Admin;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Storage\PoolRepository;
use LaqiUnitStockManager\Unit\UnitRegistry;
use Throwable;

/** Updates pool metadata without touching the authoritative balance. */
final class PoolDetailsController {

	/**
	 * Pool persistence.
	 *
	 * @var PoolRepository
	 */
	private $pools;

	/**
	 * Unit definitions.
	 *
	 * @var UnitRegistry
	 */
	private $units;

	/** Constructor.
	 *
	 * @param PoolRepository $pools Pools.
	 * @param UnitRegistry   $units Units.
	 */
	public function __construct( PoolRepository $pools, UnitRegistry $units ) {
		$this->pools = $pools;
		$this->units = $units;
	}

	/** Register endpoint. @return void */
	public function register(): void {
		add_action( 'admin_post_laqi_lusm_update_pool', array( $this, 'update' ) );
	}

	/** Apply a pool detail edit.
	 *
	 * @return void
	 * @throws \InvalidArgumentException When submitted details are invalid.
	 */
	public function update(): void {
		$pool_id = isset( $_POST['pool_id'] ) ? absint( $_POST['pool_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage unit stock.', 'laqi-unit-stock-manager' ) );
		}
		check_admin_referer( 'laqi_lusm_update_pool_' . $pool_id );
		try {
			$pool = $this->pools->find( $pool_id );
			if ( null === $pool ) {
				throw new \InvalidArgumentException( 'The inventory pool does not exist.' );
			}
			$name    = isset( $_POST['pool_name'] ) ? sanitize_text_field( wp_unslash( $_POST['pool_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$sku     = isset( $_POST['internal_sku'] ) ? sanitize_text_field( wp_unslash( $_POST['internal_sku'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$unit    = isset( $_POST['display_unit'] ) ? sanitize_key( wp_unslash( $_POST['display_unit'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$version = isset( $_POST['pool_version'] ) ? absint( $_POST['pool_version'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( $this->units->get( $unit )->family() !== $pool->quantity()->family() ) {
				throw new \InvalidArgumentException( 'The display unit is incompatible with the pool.' );
			}
			$this->pools->update_details( $pool_id, $name, $sku, $unit, $version );
			$result = 'pool_updated';
		} catch ( Throwable $error ) {
			unset( $error );
			$result = 'pool_update_error';
		}
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => UnitStockPage::SLUG,
					'section'          => 'stock',
					'laqi_lusm_result' => $result,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
