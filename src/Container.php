<?php
/**
 * Shared service composition root.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Inventory\StockMutationService;
use LaqiUnitStockManager\Inventory\MovementRegistry;
use LaqiUnitStockManager\Inventory\MovementType;
use LaqiUnitStockManager\Storage\CustomUnitRepository;
use LaqiUnitStockManager\Storage\MappingRepository;
use LaqiUnitStockManager\Storage\PoolRepository;
use LaqiUnitStockManager\Storage\MovementRepository;
use LaqiUnitStockManager\Consumption\CalculatorRegistry;
use LaqiUnitStockManager\Availability\AvailabilityService;
use LaqiUnitStockManager\Admin\ScreenSectionCatalog;
use LaqiUnitStockManager\Presentation\PoolPresenter;
use LaqiUnitStockManager\Presentation\QuantityFormatter;
use LaqiUnitStockManager\Presentation\MovementPresenter;
use LaqiUnitStockManager\Unit\UnitRegistry;

/**
 * Builds and memoizes shared Free services for Free and Pro modules.
 */
final class Container {

	/**
	 * Admin section extensions.
	 *
	 * @var ScreenSectionCatalog|null
	 */
	private $screen_sections;

	/**
	 * Runtime unit registry.
	 *
	 * @var UnitRegistry|null
	 */
	private $unit_registry;

	/**
	 * Movement type extensions.
	 *
	 * @var MovementRegistry|null
	 */
	private $movement_registry;

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

	/**
	 * Inventory pool persistence.
	 *
	 * @return PoolRepository
	 */
	public function pool_repository(): PoolRepository {
		global $wpdb;

		return new PoolRepository( $wpdb );
	}

	/**
	 * Product mapping persistence.
	 *
	 * @return MappingRepository
	 */
	public function mapping_repository(): MappingRepository {
		global $wpdb;

		return new MappingRepository( $wpdb );
	}

	/** Movement ledger reads. @return MovementRepository */
	public function movement_repository(): MovementRepository {
		global $wpdb;
		return new MovementRepository( $wpdb );
	}

	/** Extensible movement types. @return MovementRegistry */
	public function movement_registry(): MovementRegistry {
		if ( null === $this->movement_registry ) {
			$this->movement_registry = new MovementRegistry();
			$this->movement_registry->register( new MovementType( 'opening', __( 'Opening balance', 'laqi-unit-stock-manager' ) ) );
			$this->movement_registry->register( new MovementType( 'order_reduction', __( 'Order reduction', 'laqi-unit-stock-manager' ) ) );
			$this->movement_registry->register( new MovementType( 'order_restore', __( 'Order restoration', 'laqi-unit-stock-manager' ) ) );
			$this->movement_registry->register( new MovementType( 'refund_restore', __( 'Refund restoration', 'laqi-unit-stock-manager' ) ) );
			$this->movement_registry->register( new MovementType( 'manual_set', __( 'Manual stock count', 'laqi-unit-stock-manager' ) ) );
			$this->movement_registry->register( new MovementType( 'manual_add', __( 'Manual addition', 'laqi-unit-stock-manager' ) ) );
			$this->movement_registry->register( new MovementType( 'manual_subtract', __( 'Manual subtraction', 'laqi-unit-stock-manager' ) ) );
			$this->movement_registry->register( new MovementType( 'migration_import', __( 'Existing stock migration', 'laqi-unit-stock-manager' ) ) );
		}
		return $this->movement_registry;
	}

	/** Shared movement presenter. @return MovementPresenter */
	public function movement_presenter(): MovementPresenter {
		return new MovementPresenter( $this->quantity_formatter(), $this->movement_registry() );
	}

	/**
	 * Extensible consumption calculator registry.
	 *
	 * @return CalculatorRegistry
	 */
	public function calculator_registry(): CalculatorRegistry {
		return new CalculatorRegistry();
	}

	/**
	 * Combined shared-pool availability service.
	 *
	 * @return AvailabilityService
	 */
	public function availability_service(): AvailabilityService {
		return new AvailabilityService(
			$this->mapping_repository(),
			$this->pool_repository(),
			$this->calculator_registry()
		);
	}

	/**
	 * Shared normalized inventory-pool presenter.
	 *
	 * @return PoolPresenter
	 */
	public function pool_presenter(): PoolPresenter {
		return new PoolPresenter( $this->quantity_formatter() );
	}

	/** Exact quantity display formatter. @return QuantityFormatter */
	public function quantity_formatter(): QuantityFormatter {
		return new QuantityFormatter( $this->unit_registry() );
	}

	/**
	 * Extensible Unit Stock screen section catalog.
	 *
	 * @return ScreenSectionCatalog
	 */
	public function screen_section_catalog(): ScreenSectionCatalog {
		if ( null === $this->screen_sections ) {
			$this->screen_sections = new ScreenSectionCatalog();
		}
		return $this->screen_sections;
	}
}
