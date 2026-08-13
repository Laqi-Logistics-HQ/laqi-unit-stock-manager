<?php
/**
 * Shared service composition root.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Inventory\StockMutationService;
use LaqiUnitStockManager\Storage\CustomUnitRepository;
use LaqiUnitStockManager\Unit\UnitRegistry;

/**
 * Builds and memoizes shared Free services for Free and Pro modules.
 */
final class Container {

	/**
	 * Runtime unit registry.
	 *
	 * @var UnitRegistry|null
	 */
	private $unit_registry;

	/**
	 * Runtime unit registry, including merchant units.
	 *
	 * @return UnitRegistry
	 */
	public function unit_registry(): UnitRegistry {
		if ( null === $this->unit_registry ) {
			global $wpdb;

			$this->unit_registry = new UnitRegistry();
			( new CustomUnitRepository( $wpdb ) )->register_all( $this->unit_registry );
		}

		return $this->unit_registry;
	}

	/**
	 * Custom-unit persistence.
	 *
	 * @return CustomUnitRepository
	 */
	public function custom_unit_repository(): CustomUnitRepository {
		global $wpdb;

		return new CustomUnitRepository( $wpdb );
	}

	/**
	 * Authoritative stock mutation service.
	 *
	 * @return StockMutationService
	 */
	public function stock_mutation_service(): StockMutationService {
		global $wpdb;

		return new StockMutationService( $wpdb );
	}
}
