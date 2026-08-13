<?php
/**
 * WooCommerce pooled-stock cart validation.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\WooCommerce;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Availability\AvailabilityResult;
use LaqiUnitStockManager\Availability\AvailabilityService;
use WC_Cart;
use WP_Error;

/**
 * Validates all cart lines together against their shared pools.
 */
final class CartValidator {

	/**
	 * Combined availability service.
	 *
	 * @var AvailabilityService
	 */
	private $availability;

	/**
	 * Constructor.
	 *
	 * @param AvailabilityService $availability Availability service.
	 */
	public function __construct( AvailabilityService $availability ) {
		$this->availability = $availability;
	}

	/**
	 * Register classic and Store API validation hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'woocommerce_check_cart_items', array( $this, 'validate_classic_cart' ), 20 );
		add_action( 'woocommerce_store_api_cart_errors', array( $this, 'validate_store_api_cart' ), 20, 2 );
	}

	/**
	 * Validate the classic cart and add one actionable notice.
	 *
	 * @return void
	 */
	public function validate_classic_cart(): void {
		if ( ! function_exists( 'WC' ) || ! WC()->cart instanceof WC_Cart ) {
			return;
		}

		$result = $this->check_cart( WC()->cart );
		if ( ! $result->is_available() ) {
			wc_add_notice( $this->message(), 'error' );
		}
	}

	/**
	 * Validate Cart and Checkout Blocks through the Store API error collection.
	 *
	 * @param WP_Error $errors Store API errors.
	 * @param WC_Cart  $cart   Cart being validated.
	 * @return void
	 */
	public function validate_store_api_cart( WP_Error $errors, WC_Cart $cart ): void {
		if ( ! $this->check_cart( $cart )->is_available() ) {
			$errors->add( 'laqi_lusm_insufficient_pool_stock', $this->message() );
		}
	}

	/**
	 * Check one WooCommerce cart.
	 *
	 * @param WC_Cart $cart Cart instance.
	 * @return AvailabilityResult
	 */
	public function check_cart( WC_Cart $cart ): AvailabilityResult {
		$lines = array();
		foreach ( $cart->get_cart() as $item ) {
			$lines[] = array(
				'product_id'   => isset( $item['product_id'] ) ? (int) $item['product_id'] : 0,
				'variation_id' => isset( $item['variation_id'] ) ? (int) $item['variation_id'] : 0,
				'quantity'     => isset( $item['quantity'] ) ? (int) $item['quantity'] : 0,
			);
		}

		return $this->availability->check( $lines );
	}

	/**
	 * Customer-facing validation message.
	 *
	 * @return string
	 */
	private function message(): string {
		return __( 'The selected package quantities are no longer available from the shared stock. Please reduce the quantities in your cart.', 'laqi-unit-stock-manager' );
	}
}
