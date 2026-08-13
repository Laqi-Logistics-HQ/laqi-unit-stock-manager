<?php
/**
 * Pooled product stock-status synchronization.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\WooCommerce;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Availability\AvailabilityService;
use LaqiUnitStockManager\Storage\MappingRepository;
use WC_Product_Variable;

/**
 * Mirrors pooled availability into WooCommerce's catalog stock status.
 */
final class StockStatusSynchronizer {

	/** Mapping persistence.
	 *
	 * @var MappingRepository */
	private $mappings;

	/** Availability calculations.
	 *
	 * @var AvailabilityService */
	private $availability;

	/**
	 * Constructor.
	 *
	 * @param MappingRepository   $mappings     Mapping persistence.
	 * @param AvailabilityService $availability Availability calculations.
	 */
	public function __construct( MappingRepository $mappings, AvailabilityService $availability ) {
		$this->mappings     = $mappings;
		$this->availability = $availability;
	}

	/** Register stock events. @return void */
	public function register(): void {
		add_action( 'laqi_lusm_stock_mutated', array( $this, 'sync_pools' ), 10, 1 );
		add_action( 'laqi_lusm_mapping_changed', array( $this, 'sync_mapping' ), 10, 2 );
	}

	/**
	 * Synchronize all purchasables linked to changed pools.
	 *
	 * @param int[] $pool_ids Pool IDs.
	 * @return void
	 */
	public function sync_pools( array $pool_ids ): void {
		$parents = array();
		foreach ( array_unique( array_map( 'absint', $pool_ids ) ) as $pool_id ) {
			foreach ( $this->mappings->find_for_pool( $pool_id ) as $mapping ) {
				$this->sync_mapping( $mapping->product_id(), $mapping->variation_id() );
				if ( $mapping->variation_id() > 0 ) {
					$parents[] = $mapping->product_id();
				}
			}
		}
		foreach ( array_unique( $parents ) as $parent_id ) {
			WC_Product_Variable::sync_stock_status( $parent_id );
		}
	}

	/**
	 * Synchronize one simple product or variation.
	 *
	 * @param int $product_id   Simple or parent product ID.
	 * @param int $variation_id Variation ID or zero.
	 * @return void
	 */
	public function sync_mapping( int $product_id, int $variation_id = 0 ): void {
		$product = wc_get_product( $variation_id > 0 ? $variation_id : $product_id );
		if ( ! $product ) {
			return;
		}
		$saleable = $this->availability->saleable_quantity( $product_id, $variation_id );
		$product->set_stock_status( null === $saleable || $saleable > 0 ? 'instock' : 'outofstock' );
		$product->save();
	}
}
