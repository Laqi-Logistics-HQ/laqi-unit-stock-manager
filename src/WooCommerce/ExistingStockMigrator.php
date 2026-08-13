<?php
/**
 * Existing WooCommerce stock migration decisions.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\WooCommerce;

defined( 'ABSPATH' ) || exit;

use InvalidArgumentException;
use LaqiUnitStockManager\Inventory\StockMutationService;
use WC_Product;
use WC_Product_Variable;

/**
 * Applies explicit native-stock decisions after a pooled mapping is saved.
 */
final class ExistingStockMigrator {

	const KEEP     = 'keep';
	const DISABLE  = 'disable';
	const TRANSFER = 'transfer';

	/**
	 * Authoritative mutation service.
	 *
	 * @var StockMutationService
	 */
	private $mutations;

	/**
	 * Constructor.
	 *
	 * @param StockMutationService $mutations Stock mutations.
	 */
	public function __construct( StockMutationService $mutations ) {
		$this->mutations = $mutations;
	}

	/**
	 * Apply a merchant's existing-stock decision.
	 *
	 * @param WC_Product $product     Mapped simple product or variation.
	 * @param int        $pool_id     Destination pool ID.
	 * @param int        $consumption Normalized pool consumption per item.
	 * @param string     $decision    One of the class decision constants.
	 * @return void
	 * @throws InvalidArgumentException When the decision or transfer cannot be represented safely.
	 */
	public function apply( WC_Product $product, int $pool_id, int $consumption, string $decision ): void {
		if ( self::KEEP === $decision ) {
			return;
		}
		if ( ! in_array( $decision, array( self::DISABLE, self::TRANSFER ), true ) ) {
			throw new InvalidArgumentException( 'Unknown existing-stock decision.' );
		}

		if ( self::TRANSFER === $decision ) {
			$native_quantity = (int) $product->get_stock_quantity();
			if ( $native_quantity < 0 || ( $native_quantity > 0 && $consumption > intdiv( PHP_INT_MAX, $native_quantity ) ) ) {
				throw new InvalidArgumentException( 'The existing WooCommerce stock cannot be transferred safely.' );
			}
			if ( $native_quantity > 0 ) {
				$product_key = $product->is_type( 'variation' ) ? $product->get_parent_id() . ':' . $product->get_id() : $product->get_id() . ':0';
				$this->mutations->apply(
					$pool_id,
					$native_quantity * $consumption,
					'migration_import',
					'migration-native:' . $product_key . ':pool:' . $pool_id . ':quantity:' . $native_quantity . ':consumption:' . $consumption,
					array(
						'source_type' => 'product_migration',
						'source_id'   => $product->get_id(),
						'actor_id'    => get_current_user_id(),
					)
				);
			}
		}

		$product->set_manage_stock( false );
		$product->save();
		if ( $product->is_type( 'variation' ) ) {
			WC_Product_Variable::sync_stock_status( $product->get_parent_id() );
		}
	}
}
