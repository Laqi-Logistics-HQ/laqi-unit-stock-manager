<?php
/**
 * Inventory-pool AJAX search.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Admin;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Storage\PoolRepository;

/** Supplies paginated pool choices to the Setup tab. */
final class PoolSearchController {

	/**
	 * Pool persistence.
	 *
	 * @var PoolRepository
	 */
	private $pools;

	/** Constructor.
	 *
	 * @param PoolRepository $pools Pool persistence.
	 */
	public function __construct( PoolRepository $pools ) {
		$this->pools = $pools;
	}

	/** Register the authenticated AJAX endpoint. @return void */
	public function register(): void {
		add_action( 'wp_ajax_laqi_lusm_search_pools', array( $this, 'search' ) );
	}

	/** Return SelectWoo-compatible pool results. @return void */
	public function search(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to manage unit stock.', 'laqi-unit-stock-manager' ) ), 403 );
		}
		check_ajax_referer( 'laqi_lusm_search_pools', 'security' );

		$term  = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page  = isset( $_GET['page'] ) ? max( 1, absint( $_GET['page'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$limit = 20;
		$total = $this->pools->count_search( $term );
		$items = array();
		foreach ( $this->pools->search( $term, $limit, ( $page - 1 ) * $limit ) as $pool ) {
			$items[] = array(
				'id'   => $pool->id(),
				'text' => $pool->name() . ' (' . $pool->display_unit() . ')',
			);
		}
		wp_send_json_success(
			array(
				'results'    => $items,
				'pagination' => array( 'more' => $page * $limit < $total ),
			)
		);
	}
}
