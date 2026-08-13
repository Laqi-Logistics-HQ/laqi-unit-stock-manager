<?php
/**
 * Manual stock adjustment request controller.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Admin;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Inventory\StockMutationService;
use LaqiUnitStockManager\Storage\PoolRepository;
use LaqiUnitStockManager\Unit\UnitRegistry;
use Throwable;

/**
 * Validates admin requests and routes every balance change through mutations.
 */
final class StockAdjustmentController {

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

	/**
	 * Authoritative mutation path.
	 *
	 * @var StockMutationService
	 */
	private $mutations;

	/**
	 * Constructor.
	 *
	 * @param PoolRepository       $pools     Pool repository.
	 * @param UnitRegistry         $units     Unit registry.
	 * @param StockMutationService $mutations Mutation service.
	 */
	public function __construct( PoolRepository $pools, UnitRegistry $units, StockMutationService $mutations ) {
		$this->pools     = $pools;
		$this->units     = $units;
		$this->mutations = $mutations;
	}

	/** Register the adjustment endpoint. @return void */
	public function register(): void {
		add_action( 'admin_post_laqi_lusm_adjust_stock', array( $this, 'handle' ) );
	}

	/**
	 * Validate and apply an adjustment request.
	 *
	 * @return void
	 * @throws \InvalidArgumentException When submitted stock data is invalid.
	 */
	public function handle(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to adjust unit stock.', 'laqi-unit-stock-manager' ) );
		}

		$pool_id = isset( $_POST['pool_id'] ) ? absint( $_POST['pool_id'] ) : 0;
		check_admin_referer( 'laqi_lusm_adjust_stock_' . $pool_id );

		try {
			$pool = $this->pools->find( $pool_id );
			if ( null === $pool ) {
				throw new \InvalidArgumentException( 'Unknown inventory pool.' );
			}

			$mode     = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
			$unit     = isset( $_POST['unit'] ) ? sanitize_key( wp_unslash( $_POST['unit'] ) ) : '';
			$raw      = isset( $_POST['quantity'] ) ? sanitize_text_field( wp_unslash( $_POST['quantity'] ) ) : '';
			$reason   = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';
			$quantity = $this->units->normalize( $raw, $unit );

			if ( $quantity->family() !== $pool->quantity()->family() || $unit !== $pool->display_unit() ) {
				throw new \InvalidArgumentException( 'The adjustment unit does not match the inventory pool.' );
			}

			$key     = 'admin:' . get_current_user_id() . ':' . wp_generate_uuid4();
			$context = array(
				'source_type' => 'manual',
				'actor_id'    => get_current_user_id(),
				'reason'      => $reason,
			);
			if ( 'set' === $mode ) {
				$this->mutations->set_balance( $pool_id, $quantity->amount(), 'manual_set', $key, $context );
			} elseif ( 'add' === $mode || 'subtract' === $mode ) {
				$direction = 'add' === $mode ? 1 : -1;
				$this->mutations->apply( $pool_id, $direction * $quantity->amount(), 'manual_' . $mode, $key, $context );
			} else {
				throw new \InvalidArgumentException( 'Unknown stock adjustment type.' );
			}

			$this->redirect( 'updated' );
		} catch ( Throwable $error ) {
			unset( $error );
			$this->redirect( 'error' );
		}
	}

	/**
	 * Return to the stock screen with a result notice.
	 *
	 * @param string $result Result code.
	 * @return void
	 */
	private function redirect( string $result ): void {
		$url = add_query_arg(
			array(
				'page'             => UnitStockPage::SLUG,
				'laqi_lusm_result' => $result,
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}
}
