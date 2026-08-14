<?php
/**
 * External inventory movement REST controller.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Integrations;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Rest\InventoryController;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/** Provides an authenticated ERP/WMS movement intake endpoint. */
final class ExternalMovementController {
	/** Movement import service.
	 *
	 * @var ExternalMovementService */
	private $service;

	/**
	 * Constructor.
	 *
	 * @param ExternalMovementService $service Import service.
	 */
	public function __construct( ExternalMovementService $service ) {
		$this->service = $service;
	}

	/** Register route wiring. @return void */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/** Register the external movement endpoint. @return void */
	public function register_routes(): void {
		register_rest_route(
			InventoryController::NAMESPACE,
			'/external-movements',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
	}

	/** Check inventory-management permission. @return bool */
	public function can_manage(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Import one atomic external event.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create( WP_REST_Request $request ) {
		try {
			$movements = $request->get_param( 'movements' );
			$results   = $this->service->import(
				sanitize_key( (string) $request->get_param( 'integration' ) ),
				sanitize_text_field( (string) $request->get_param( 'event_id' ) ),
				is_array( $movements ) ? $movements : array(),
				get_current_user_id()
			);
			return new WP_REST_Response(
				array(
					'version' => 1,
					'items'   => array_map(
						static function ( $result ): array {
							return array(
								'movement_id'  => $result->movement_id(),
								'balance_base' => $result->balance(),
								'duplicate'    => $result->is_duplicate(),
							);
						},
						$results
					),
				)
			);
		} catch ( Throwable $error ) {
			return new WP_Error( 'laqi_lusm_invalid_external_movement', $error->getMessage(), array( 'status' => 400 ) );
		}
	}
}
