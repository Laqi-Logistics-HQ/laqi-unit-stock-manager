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
use LaqiUnitStockManager\Storage\CustomUnitRepository;
use LaqiUnitStockManager\Unit\UnitRegistry;
use Throwable;
use LaqiUnitStockManager\WooCommerce\ExistingStockMigrator;
use LaqiUnitStockManager\WooCommerce\PurchasableResolver;

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
	 * Custom-unit persistence.
	 *
	 * @var CustomUnitRepository
	 */
	private $custom_units;

	/**
	 * Existing stock migration.
	 *
	 * @var ExistingStockMigrator
	 */
	private $stock_migrator;

	/**
	 * Product and variation resolution.
	 *
	 * @var PurchasableResolver
	 */
	private $purchasables;

	/**
	 * Constructor.
	 *
	 * @param PoolRepository        $pools     Pool repository.
	 * @param MappingRepository     $mappings  Mapping repository.
	 * @param UnitRegistry          $units     Unit registry.
	 * @param StockMutationService  $mutations Mutation service.
	 * @param CustomUnitRepository  $custom_units Custom-unit persistence.
	 * @param ExistingStockMigrator $stock_migrator Existing stock migration.
	 * @param PurchasableResolver   $purchasables Product and variation resolution.
	 */
	public function __construct( PoolRepository $pools, MappingRepository $mappings, UnitRegistry $units, StockMutationService $mutations, CustomUnitRepository $custom_units, ExistingStockMigrator $stock_migrator, PurchasableResolver $purchasables ) {
		$this->pools          = $pools;
		$this->mappings       = $mappings;
		$this->units          = $units;
		$this->mutations      = $mutations;
		$this->custom_units   = $custom_units;
		$this->stock_migrator = $stock_migrator;
		$this->purchasables   = $purchasables;
	}

	/** Register setup endpoints. @return void */
	public function register(): void {
		add_action( 'admin_post_laqi_lusm_create_pool', array( $this, 'create_pool' ) );
		add_action( 'admin_post_laqi_lusm_save_mapping', array( $this, 'save_mapping' ) );
		add_action( 'admin_post_laqi_lusm_unlink_mapping', array( $this, 'unlink_mapping' ) );
		add_action( 'admin_post_laqi_lusm_update_mapping', array( $this, 'update_mapping' ) );
		add_action( 'admin_post_laqi_lusm_create_unit', array( $this, 'create_unit' ) );
	}

	/** Update an active mapping without repeating native-stock migration.
	 *
	 * @return void
	 * @throws \InvalidArgumentException When edit input is invalid.
	 */
	public function update_mapping(): void {
		$mapping_id = isset( $_POST['mapping_id'] ) ? absint( $_POST['mapping_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$this->authorize( 'laqi_lusm_update_mapping_' . $mapping_id );
		try {
			$mapping = $this->mappings->find_active( $mapping_id );
			if ( null === $mapping ) {
				throw new \InvalidArgumentException( 'The product mapping is not active.' );
			}
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
			$version = isset( $_POST['mapping_version'] ) ? absint( $_POST['mapping_version'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$this->mappings->save_single_pool( $mapping->product_id(), $mapping->variation_id(), $pool_id, $quantity->amount(), true, $version );
			do_action( 'laqi_lusm_mapping_changed', $mapping->product_id(), $mapping->variation_id(), $pool_id );
			$this->redirect( 'mapping_updated' );
		} catch ( Throwable $error ) {
			unset( $error );
			$this->redirect( 'setup_error' );
		}
	}

	/** Deactivate a product mapping without changing historical snapshots. @return void */
	public function unlink_mapping(): void {
		$mapping_id = isset( $_POST['mapping_id'] ) ? absint( $_POST['mapping_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$this->authorize( 'laqi_lusm_unlink_mapping_' . $mapping_id );
		try {
			$mapping = $this->mappings->deactivate( $mapping_id );
			do_action( 'laqi_lusm_mapping_changed', $mapping->product_id(), $mapping->variation_id(), 0 );
			$this->redirect( 'mapping_unlinked' );
		} catch ( Throwable $error ) {
			unset( $error );
			$this->redirect( 'setup_error' );
		}
	}

	/**
	 * Create a merchant-defined unit.
	 *
	 * @return void
	 * @throws \InvalidArgumentException When unit input is invalid.
	 */
	public function create_unit(): void {
		$this->authorize( 'laqi_lusm_create_unit' );
		try {
			$key       = isset( $_POST['unit_key'] ) ? sanitize_key( wp_unslash( $_POST['unit_key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$label     = isset( $_POST['unit_label'] ) ? sanitize_text_field( wp_unslash( $_POST['unit_label'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$symbol    = isset( $_POST['unit_symbol'] ) ? sanitize_text_field( wp_unslash( $_POST['unit_symbol'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$value     = isset( $_POST['reference_value'] ) ? sanitize_text_field( wp_unslash( $_POST['reference_value'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$reference = isset( $_POST['reference_unit'] ) ? sanitize_key( wp_unslash( $_POST['reference_unit'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( '' === $label || '' === $symbol ) {
				throw new \InvalidArgumentException( 'A custom unit requires a label and symbol.' );
			}
			$this->custom_units->create( $this->units, $key, $label, $symbol, $value, $reference );
			$this->redirect( 'unit_created' );
		} catch ( Throwable $error ) {
			unset( $error );
			$this->redirect( 'setup_error' );
		}
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
			$purchasable  = $this->purchasables->resolve( isset( $_POST['purchasable_id'] ) ? absint( $_POST['purchasable_id'] ) : 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$product_id   = $purchasable['product_id'];
			$variation_id = $purchasable['variation_id'];

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
			$decision = isset( $_POST['existing_stock_decision'] ) ? sanitize_key( wp_unslash( $_POST['existing_stock_decision'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$product  = wc_get_product( $variation_id > 0 ? $variation_id : $product_id );
			$this->stock_migrator->apply( $product, $pool_id, $quantity->amount(), $decision );
			do_action( 'laqi_lusm_mapping_changed', $product_id, $variation_id, $pool_id );
			$this->redirect( 'mapping_saved' );
		} catch ( Throwable $error ) {
			unset( $error );
			$this->redirect( 'setup_error' );
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
