<?php
/**
 * Versioned inventory REST API.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Rest;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Inventory\StockAdjustmentService;
use LaqiUnitStockManager\Presentation\MovementPresenter;
use LaqiUnitStockManager\Presentation\PoolPresenter;
use LaqiUnitStockManager\Storage\MovementRepository;
use LaqiUnitStockManager\Storage\PoolRepository;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Exposes shared presenters and adjustment rules to authorized clients.
 */
final class InventoryController {

	const NAMESPACE = 'laqi-lusm/v1';

	/** Pool reads.
	 *
	 * @var PoolRepository */
	private $pools;

	/** Pool presenter.
	 *
	 * @var PoolPresenter */
	private $pool_presenter;

	/** Movement reads.
	 *
	 * @var MovementRepository */
	private $movements;

	/** Movement presenter.
	 *
	 * @var MovementPresenter */
	private $movement_presenter;

	/** Shared adjustments.
	 *
	 * @var StockAdjustmentService */
	private $adjustments;

	/**
	 * Constructor.
	 *
	 * @param PoolRepository         $pools              Pool reads.
	 * @param PoolPresenter          $pool_presenter     Pool presenter.
	 * @param MovementRepository     $movements          Movement reads.
	 * @param MovementPresenter      $movement_presenter Movement presenter.
	 * @param StockAdjustmentService $adjustments       Shared adjustments.
	 */
	public function __construct( PoolRepository $pools, PoolPresenter $pool_presenter, MovementRepository $movements, MovementPresenter $movement_presenter, StockAdjustmentService $adjustments ) {
		$this->pools              = $pools;
		$this->pool_presenter     = $pool_presenter;
		$this->movements          = $movements;
		$this->movement_presenter = $movement_presenter;
		$this->adjustments        = $adjustments;
	}

	/** Register version-one routes. @return void */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/** Register version-one routes. @return void */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/pools',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_pools' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'search' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'default'           => '',
					),
					'limit'  => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 500,
						'default' => 100,
					),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/pools/(?P<id>[\d]+)/adjustments',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'adjust_pool' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'id'              => array(
						'type'     => 'integer',
						'minimum'  => 1,
						'required' => true,
					),
					'mode'            => array(
						'type'     => 'string',
						'enum'     => array( 'set', 'add', 'subtract' ),
						'required' => true,
					),
					'quantity'        => array(
						'type'     => 'string',
						'required' => true,
					),
					'unit'            => array(
						'type'     => 'string',
						'required' => true,
					),
					'reason'          => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'default'           => '',
					),
					'idempotency_key' => array(
						'type'      => 'string',
						'minLength' => 8,
						'maxLength' => 120,
						'required'  => true,
					),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/movements',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_movements' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'limit' => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 100,
						'default' => 50,
					),
					'page'  => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 1000000,
						'default' => 1,
					),
				),
			)
		);
	}

	/** Check inventory-management permission. @return bool */
	public function can_manage(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/** List presented pools.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response */
	public function get_pools( WP_REST_Request $request ): WP_REST_Response {
		$rows = array_map( array( $this->pool_presenter, 'present' ), $this->pools->search( (string) $request['search'], (int) $request['limit'] ) );
		return new WP_REST_Response(
			array(
				'version' => 1,
				'items'   => $rows,
			)
		);
	}

	/** Apply one exact pool adjustment.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error */
	public function adjust_pool( WP_REST_Request $request ) {
		try {
			$result = $this->adjustments->adjust( (int) $request['id'], (string) $request['mode'], (string) $request['quantity'], sanitize_key( (string) $request['unit'] ), (string) $request['reason'], get_current_user_id(), 'rest:' . sanitize_text_field( (string) $request['idempotency_key'] ) );
			$pool   = $this->pools->find( (int) $request['id'] );
			return new WP_REST_Response(
				array(
					'version'     => 1,
					'duplicate'   => $result->is_duplicate(),
					'movement_id' => $result->movement_id(),
					'pool'        => null === $pool ? null : $this->pool_presenter->present( $pool ),
				)
			);
		} catch ( Throwable $error ) {
			return new WP_Error( 'laqi_lusm_invalid_adjustment', $error->getMessage(), array( 'status' => 400 ) );
		}
	}

	/** List presented movements.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response */
	public function get_movements( WP_REST_Request $request ): WP_REST_Response {
		$limit = (int) $request['limit'];
		$page  = (int) $request['page'];
		$total = $this->movements->count();
		$rows  = array_map( array( $this->movement_presenter, 'present' ), $this->movements->recent( $limit, ( $page - 1 ) * $limit ) );
		return new WP_REST_Response(
			array(
				'version'    => 1,
				'items'      => $rows,
				'pagination' => array(
					'page'        => $page,
					'per_page'    => $limit,
					'total_items' => $total,
					'total_pages' => max( 1, intdiv( $total + $limit - 1, $limit ) ),
				),
			)
		);
	}
}
