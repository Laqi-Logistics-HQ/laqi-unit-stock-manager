<?php
/**
 * Pool-aware product scanning API.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Integrations;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Availability\AvailabilityService;
use LaqiUnitStockManager\Consumption\CalculatorRegistry;
use LaqiUnitStockManager\Presentation\PoolPresenter;
use LaqiUnitStockManager\Rest\InventoryController;
use LaqiUnitStockManager\Storage\MappingRepository;
use LaqiUnitStockManager\Storage\PoolRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/** Resolves a WooCommerce SKU or barcode to exact pooled-stock demand. */
final class MobileStockLookupController {
	/** Product mappings.
	 *
	 * @var MappingRepository */ private $mappings;
	/** Inventory pools.
	 *
	 * @var PoolRepository */ private $pools;
	/** Pool presenter.
	 *
	 * @var PoolPresenter */ private $presenter;
	/** Availability service.
	 *
	 * @var AvailabilityService */ private $availability;
	/** Consumption calculators.
	 *
	 * @var CalculatorRegistry */ private $calculators;

	/**
	 * Constructor.
	 *
	 * @param MappingRepository   $mappings     Mappings.
	 * @param PoolRepository      $pools        Pools.
	 * @param PoolPresenter       $presenter    Pool presenter.
	 * @param AvailabilityService $availability Availability service.
	 * @param CalculatorRegistry  $calculators  Calculators.
	 */
	public function __construct( MappingRepository $mappings, PoolRepository $pools, PoolPresenter $presenter, AvailabilityService $availability, CalculatorRegistry $calculators ) {
		$this->mappings     = $mappings;
		$this->pools        = $pools;
		$this->presenter    = $presenter;
		$this->availability = $availability;
		$this->calculators  = $calculators;
	}

	/** Register route wiring. @return void */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/** Register the scanner lookup route. @return void */
	public function register_routes(): void {
		register_rest_route(
			InventoryController::NAMESPACE,
			'/scan',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'scan' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'code' => array(
						'type'              => 'string',
						'required'          => true,
						'minLength'         => 1,
						'maxLength'         => 100,
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
	 * Resolve one SKU or global unique ID.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function scan( WP_REST_Request $request ) {
		$code       = (string) $request['code'];
		$product_id = (int) wc_get_product_id_by_sku( $code );
		if ( $product_id < 1 && function_exists( 'wc_get_product_id_by_global_unique_id' ) ) {
			$product_id = (int) wc_get_product_id_by_global_unique_id( $code );
		}
		$product = $product_id > 0 ? wc_get_product( $product_id ) : false;
		if ( ! $product ) {
			return new WP_Error( 'laqi_lusm_scan_not_found', __( 'No product matches this scan code.', 'laqi-unit-stock-manager' ), array( 'status' => 404 ) );
		}
		$variation_id = $product->is_type( 'variation' ) ? $product->get_id() : 0;
		$parent_id    = $variation_id > 0 ? $product->get_parent_id() : $product->get_id();
		$mapping      = $this->mappings->find_for_product( $parent_id, $variation_id );
		if ( null === $mapping ) {
			return new WP_Error( 'laqi_lusm_scan_unmapped', __( 'The scanned product is not mapped to pooled stock.', 'laqi-unit-stock-manager' ), array( 'status' => 404 ) );
		}
		$demand = $this->calculators->get( $mapping->calculator_type() )->calculate( $mapping, 1 );
		$pools  = array();
		foreach ( $demand as $pool_id => $quantity ) {
			$pool = $this->pools->find( (int) $pool_id );
			if ( null !== $pool ) {
				$row                       = $this->presenter->present( $pool );
				$row['demand_per_product'] = (int) $quantity;
				$row['available_base']     = (int) apply_filters( 'laqi_lusm_pool_available_quantity', $pool->quantity()->amount(), $pool->id() );
				$pools[]                   = $row;
			}
		}
		return new WP_REST_Response(
			array(
				'version'           => 1,
				'code'              => $code,
				'product'           => array(
					'id'        => $product->get_id(),
					'parent_id' => $parent_id,
					'name'      => $product->get_name(),
					'sku'       => $product->get_sku(),
				),
				'saleable_quantity' => $this->availability->saleable_quantity( $parent_id, $variation_id ),
				'pools'             => $pools,
			)
		);
	}
}
