<?php
/**
 * Mobile stock-count REST endpoint.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Integrations;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Inventory\StockAdjustmentService;
use LaqiUnitStockManager\Presentation\PoolPresenter;
use LaqiUnitStockManager\Rest\InventoryController;
use LaqiUnitStockManager\Storage\PoolRepository;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/** Records exact mobile counts through the shared stock-adjustment service. */
final class MobileStocktakeController {
	/**
	 * Shared adjustments.
	 *
	 * @var StockAdjustmentService
	 */
	private $adjustments;
	/**
	 * Pool reads.
	 *
	 * @var PoolRepository
	 */
	private $pools;
	/**
	 * Pool presenter.
	 *
	 * @var PoolPresenter
	 */
	private $presenter;

	/**
	 * Constructor.
	 *
	 * @param StockAdjustmentService $adjustments Shared adjustments.
	 * @param PoolRepository         $pools       Pool reads.
	 * @param PoolPresenter          $presenter   Pool presenter.
	 */
	public function __construct( StockAdjustmentService $adjustments, PoolRepository $pools, PoolPresenter $presenter ) {
		$this->adjustments = $adjustments;
		$this->pools       = $pools;
		$this->presenter   = $presenter;
	}

	/** Register route wiring. @return void */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/** Register the stocktake route. @return void */
	public function register_routes(): void {
		register_rest_route(
			InventoryController::NAMESPACE,
			'/pools/(?P<id>[\d]+)/stocktake',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'stocktake' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'id'              => array(
						'type'     => 'integer',
						'minimum'  => 1,
						'required' => true,
					),
					'quantity'        => array(
						'type'     => 'string',
						'required' => true,
					),
					'unit'            => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
					'reason'          => array(
						'type'              => 'string',
						'required'          => true,
						'minLength'         => 1,
						'maxLength'         => 255,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'idempotency_key' => array(
						'type'              => 'string',
						'required'          => true,
						'minLength'         => 8,
						'maxLength'         => 120,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/** Check inventory-management permission. @return bool */
	public function can_manage(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Record an exact count.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function stocktake( WP_REST_Request $request ) {
		try {
			$pool_id = (int) $request['id'];
			$result  = $this->adjustments->stocktake( $pool_id, (string) $request['quantity'], (string) $request['unit'], (string) $request['reason'], get_current_user_id(), 'mobile-stocktake:' . (string) $request['idempotency_key'] );
			$pool    = $this->pools->find( $pool_id );
			return new WP_REST_Response(
				array(
					'version'     => 1,
					'duplicate'   => $result->is_duplicate(),
					'movement_id' => $result->movement_id(),
					'pool'        => null === $pool ? null : $this->presenter->present( $pool ),
				)
			);
		} catch ( Throwable $error ) {
			return new WP_Error( 'laqi_lusm_invalid_stocktake', $error->getMessage(), array( 'status' => 400 ) );
		}
	}
}
