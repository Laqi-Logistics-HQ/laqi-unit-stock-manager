<?php
/**
 * Paid recipe setup requests.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Admin;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Admin\UnitStockPage;
use LaqiUnitStockManager\Domain\MappingComponent;
use LaqiUnitStockManager\Storage\MappingRepository;
use LaqiUnitStockManager\Storage\PoolRepository;
use LaqiUnitStockManager\Unit\UnitRegistry;
use LaqiUnitStockManager\WooCommerce\PurchasableResolver;
use Throwable;

/** Validates and persists multi-pool recipes. */
final class RecipeController {
	/** Product mappings.
	 *
	 * @var MappingRepository
	 */
	private $mappings;
	/** Inventory pools.
	 *
	 * @var PoolRepository
	 */
	private $pools;
	/** Unit definitions.
	 *
	 * @var UnitRegistry
	 */
	private $units;
	/** Product resolution.
	 *
	 * @var PurchasableResolver
	 */
	private $purchasables;

	/**
	 * Constructor.
	 *
	 * @param MappingRepository   $mappings     Mappings.
	 * @param PoolRepository      $pools        Pools.
	 * @param UnitRegistry        $units        Units.
	 * @param PurchasableResolver $purchasables Purchasables.
	 */
	public function __construct( MappingRepository $mappings, PoolRepository $pools, UnitRegistry $units, PurchasableResolver $purchasables ) {
		$this->mappings     = $mappings;
		$this->pools        = $pools;
		$this->units        = $units;
		$this->purchasables = $purchasables;
	}

	/** Register endpoint. @return void */
	public function register(): void {
		add_action( 'admin_post_laqi_lusm_save_recipe', array( $this, 'save' ) );
	}

	/** Save an explicit recipe.
	 *
	 * @return void
	 * @throws \InvalidArgumentException When fewer than two valid components are submitted.
	 */
	public function save(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to configure product recipes.', 'laqi-unit-stock-manager' ) );
		}
		check_admin_referer( 'laqi_lusm_save_recipe' );
		try {
			$purchasable = $this->purchasables->resolve( isset( $_POST['purchasable_id'] ) ? absint( $_POST['purchasable_id'] ) : 0 );
			$components  = $this->components();
			if ( count( $components ) < 2 ) {
				throw new \InvalidArgumentException( 'A recipe requires at least two components.' );
			}
			$this->mappings->save_components( $purchasable['product_id'], $purchasable['variation_id'], 'recipe', $components );
			$product  = wc_get_product( $purchasable['variation_id'] > 0 ? $purchasable['variation_id'] : $purchasable['product_id'] );
			$decision = isset( $_POST['native_stock_decision'] ) ? sanitize_key( wp_unslash( $_POST['native_stock_decision'] ) ) : 'disable';
			if ( 'disable' === $decision && $product ) {
				$product->set_manage_stock( false );
				$product->save();
			}
			do_action( 'laqi_lusm_mapping_changed', $purchasable['product_id'], $purchasable['variation_id'], 0 );
			$this->redirect( 'saved' );
		} catch ( Throwable $error ) {
			unset( $error );
			$this->redirect( 'error' );
		}
	}

	/** Build validated normalized components.
	 *
	 * @return MappingComponent[]
	 * @throws \InvalidArgumentException When a component is incomplete or incompatible with its pool.
	 */
	private function components(): array {
		$pools      = isset( $_POST['component_pool'] ) && is_array( $_POST['component_pool'] ) ? array_map( 'absint', wp_unslash( $_POST['component_pool'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$roles      = isset( $_POST['component_role'] ) && is_array( $_POST['component_role'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['component_role'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$quantities = isset( $_POST['component_quantity'] ) && is_array( $_POST['component_quantity'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['component_quantity'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$units      = isset( $_POST['component_unit'] ) && is_array( $_POST['component_unit'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['component_unit'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$allowed    = array( 'contents', 'container', 'closure', 'label', 'packaging' );
		$components = array();
		foreach ( $pools as $index => $raw_pool_id ) {
			$pool_id = absint( $raw_pool_id );
			$value   = isset( $quantities[ $index ] ) ? sanitize_text_field( $quantities[ $index ] ) : '';
			$unit    = isset( $units[ $index ] ) ? sanitize_key( $units[ $index ] ) : '';
			if ( 0 === $pool_id && '' === $value && '' === $unit ) {
				continue;
			}
			$pool = $this->pools->find( $pool_id );
			$role = isset( $roles[ $index ] ) ? sanitize_key( $roles[ $index ] ) : '';
			if ( null === $pool || ! in_array( $role, $allowed, true ) ) {
				throw new \InvalidArgumentException( 'A recipe component is invalid.' );
			}
			$quantity = $this->units->normalize( $value, $unit );
			if ( $quantity->family() !== $pool->quantity()->family() || $quantity->amount() < 1 ) {
				throw new \InvalidArgumentException( 'A recipe component must use its pool measurement family.' );
			}
			$components[] = new MappingComponent( $pool_id, $quantity->amount(), $role );
		}
		return $components;
	}

	/** Redirect to recipes.
	 *
	 * @param string $result Result.
	 * @return void
	 */
	private function redirect( string $result ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                    => UnitStockPage::SLUG,
					'section'                 => 'recipes',
					'laqi_lusm_recipe_result' => $result,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
