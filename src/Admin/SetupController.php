<?php
/**
 * Pool and product mapping setup requests.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Admin;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Inventory\StockMutationService;
use LaqiUnitStockManager\Storage\MappingRepository;
use LaqiUnitStockManager\Storage\PoolRepository;
use LaqiUnitStockManager\Unit\UnitRegistry;
use Throwable;
use WC_Product_Variation;

/**
 * Applies explicit setup decisions through shared domain services.
 */
final class SetupController {

	/** Pool persistence.
	 *
	 * @var PoolRepository */
	private $pools;

	/** Mapping persistence.
	 *
	 * @var MappingRepository */
	private $mappings;

	/** Unit definitions.
	 *
	 * @var UnitRegistry */
	private $units;

	/** Mutation service.
	 *
	 * @var StockMutationService */
	private $mutations;

	/**
	 * Constructor.
	 *
	 * @param PoolRepository       $pools     Pool repository.
	 * @param MappingRepository    $mappings  Mapping repository.
	 * @param UnitRegistry         $units     Unit registry.
	 * @param StockMutationService $mutations Mutation service.
	 */
	public function __construct( PoolRepository $pools, MappingRepository $mappings, UnitRegistry $units, StockMutationService $mutations ) {
		$this->pools     = $pools;
		$this->mappings  = $mappings;
		$this->units     = $units;
		$this->mutations = $mutations;
	}

	/** Register setup endpoints. @return void */
	public function register(): void {
		add_action( 'admin_post_laqi_lusm_create_pool', array( $this, 'create_pool' ) );
		add_action( 'admin_post_laqi_lusm_save_mapping', array( $this, 'save_mapping' ) );
	}

	/** Create an inventory pool.
	 *
	 * @return void
	 * @throws \InvalidArgumentException When setup input is invalid. */
	public function create_pool(): void {
		$this->authorize( 'laqi_lusm_create_pool' );
		try {
			$name    = isset( $_POST['pool_name'] ) ? sanitize_text_field( wp_unslash( $_POST['pool_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$sku     = isset( $_POST['internal_sku'] ) ? sanitize_text_field( wp_unslash( $_POST['internal_sku'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$unit    = isset( $_POST['display_unit'] ) ? sanitize_key( wp_unslash( $_POST['display_unit'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$opening = isset( $_POST['opening_balance'] ) ? sanitize_text_field( wp_unslash( $_POST['opening_balance'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( '' === $name ) {
				throw new \InvalidArgumentException( 'A pool name is required.' );
			}

			$definition = $this->units->get( $unit );
			$quantity   = $this->units->normalize( $opening, $unit );
			$pool       = $this->pools->create_empty( $name, $definition->family(), $this->base_unit( $definition->family() ), $unit, false, $sku );
			$this->mutations->set_balance(
				$pool->id(),
				$quantity->amount(),
				'opening',
				'pool:' . $pool->id() . ':opening',
				array(
					'source_type' => 'setup',
					'actor_id'    => get_current_user_id(),
				)
			);
			$this->redirect( 'pool_created' );
		} catch ( Throwable $error ) {
			unset( $error );
			$this->redirect( 'setup_error' );
		}
	}

	/** Save a product-to-pool mapping.
	 *
	 * @return void
	 * @throws \InvalidArgumentException When mapping input is invalid. */
	public function save_mapping(): void {
		$this->authorize( 'laqi_lusm_save_mapping' );
		try {
			$purchasable = isset( $_POST['purchasable'] ) ? sanitize_text_field( wp_unslash( $_POST['purchasable'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( ! preg_match( '/^([1-9][0-9]*):(0|[1-9][0-9]*)$/', $purchasable, $matches ) ) {
				throw new \InvalidArgumentException( 'A valid product or variation is required.' );
			}
			$product_id   = (int) $matches[1];
			$variation_id = (int) $matches[2];
			$this->validate_product( $product_id, $variation_id );

			$pool_id = isset( $_POST['pool_id'] ) ? absint( $_POST['pool_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$pool    = $this->pools->find( $pool_id );
			if ( null === $pool ) {
				throw new \InvalidArgumentException( 'A valid inventory pool is required.' );
			}
			$unit        = isset( $_POST['consumption_unit'] ) ? sanitize_key( wp_unslash( $_POST['consumption_unit'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$consumption = isset( $_POST['consumption'] ) ? sanitize_text_field( wp_unslash( $_POST['consumption'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$quantity    = $this->units->normalize( $consumption, $unit );
			if ( $quantity->family() !== $pool->quantity()->family() || $quantity->amount() < 1 ) {
				throw new \InvalidArgumentException( 'Consumption must use the pool measurement family and be greater than zero.' );
			}

			$this->mappings->save_single_pool( $product_id, $variation_id, $pool_id, $quantity->amount() );
			do_action( 'laqi_lusm_mapping_changed', $product_id, $variation_id, $pool_id );
			$this->redirect( 'mapping_saved' );
		} catch ( Throwable $error ) {
			unset( $error );
			$this->redirect( 'setup_error' );
		}
	}

	/**
	 * Validate an exact purchasable object.
	 *
	 * @param int $product_id   Simple or parent product ID.
	 * @param int $variation_id Variation ID or zero.
	 * @return void
	 * @throws \InvalidArgumentException When the purchasable object is unsupported.
	 */
	private function validate_product( int $product_id, int $variation_id ): void {
		$product = wc_get_product( $variation_id > 0 ? $variation_id : $product_id );
		if ( ! $product || ( $variation_id > 0 && ( ! $product instanceof WC_Product_Variation || $product->get_parent_id() !== $product_id ) ) || ( 0 === $variation_id && ! $product->is_type( 'simple' ) ) ) {
			throw new \InvalidArgumentException( 'Only simple products and valid variations can be linked.' );
		}
	}

	/** Authorize a setup request.
	 *
	 * @param string $nonce_action Nonce action.
	 * @return void */
	private function authorize( string $nonce_action ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to configure unit stock.', 'laqi-unit-stock-manager' ) );
		}
		check_admin_referer( $nonce_action );
	}

	/** Resolve the canonical base unit.
	 *
	 * @param string $family Measurement family.
	 * @return string */
	private function base_unit( string $family ): string {
		$units = array(
			'mass'   => 'ng',
			'volume' => 'sixteenth_nanolitre',
			'count'  => 'unit',
		);
		return isset( $units[ $family ] ) ? $units[ $family ] : $family;
	}

	/** Redirect to setup.
	 *
	 * @param string $result Result code.
	 * @return void */
	private function redirect( string $result ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => UnitStockPage::SLUG,
					'section'          => 'setup',
					'laqi_lusm_result' => $result,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
