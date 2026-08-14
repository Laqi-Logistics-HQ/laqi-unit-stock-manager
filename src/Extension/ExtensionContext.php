<?php
/**
 * Public add-on service context.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Extension;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Admin\ScreenSectionCatalog;
use LaqiUnitStockManager\Availability\AvailabilityService;
use LaqiUnitStockManager\Consumption\CalculatorRegistry;
use LaqiUnitStockManager\Container;
use LaqiUnitStockManager\Diagnostics\MappingDiagnostics;
use LaqiUnitStockManager\Inventory\MovementRegistry;
use LaqiUnitStockManager\Inventory\StockAdjustmentService;
use LaqiUnitStockManager\Inventory\StockMutationService;
use LaqiUnitStockManager\Presentation\MovementPresenter;
use LaqiUnitStockManager\Presentation\PoolPresenter;
use LaqiUnitStockManager\Presentation\QuantityFormatter;
use LaqiUnitStockManager\Storage\MappingRepository;
use LaqiUnitStockManager\Storage\MovementRepository;
use LaqiUnitStockManager\Storage\PoolRepository;
use LaqiUnitStockManager\Unit\UnitRegistry;

/** Wraps internal composition behind the supported extension contract. */
final class ExtensionContext implements ExtensionContextInterface {

	/**
	 * Internal service container.
	 *
	 * @var Container
	 */
	private $container;

	/**
	 * Constructor.
	 *
	 * @param Container $container Internal service container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/** Public API version. @return string */
	public function api_version(): string {
		return LAQI_LUSM_API_VERSION;
	}

	/** Extensible exact-unit registry. @return UnitRegistry */
	public function units(): UnitRegistry {
		return $this->container->unit_registry();
	}

	/** Extensible movement-type registry. @return MovementRegistry */
	public function movements(): MovementRegistry {
		return $this->container->movement_registry();
	}

	/** Consumption calculator registry. @return CalculatorRegistry */
	public function calculators(): CalculatorRegistry {
		return $this->container->calculator_registry();
	}

	/** Unit Stock admin sections. @return ScreenSectionCatalog */
	public function admin_sections(): ScreenSectionCatalog {
		return $this->container->screen_section_catalog();
	}

	/** Inventory-pool persistence. @return PoolRepository */
	public function pools(): PoolRepository {
		return $this->container->pool_repository();
	}

	/** Namespaced inventory-pool policy persistence. @return PoolPolicyStore */
	public function pool_policies(): PoolPolicyStore {
		return $this->container->pool_policy_store();
	}

	/** Product-mapping persistence. @return MappingRepository */
	public function mappings(): MappingRepository {
		return $this->container->mapping_repository();
	}

	/** Read-only product-mapping diagnostics. @return MappingDiagnostics */
	public function mapping_diagnostics(): MappingDiagnostics {
		return $this->container->mapping_diagnostics();
	}

	/** Movement-ledger reads. @return MovementRepository */
	public function movement_history(): MovementRepository {
		return $this->container->movement_repository();
	}

	/** Authoritative stock mutation service. @return StockMutationService */
	public function stock_mutations(): StockMutationService {
		return $this->container->stock_mutation_service();
	}

	/** Validated stock adjustment service. @return StockAdjustmentService */
	public function stock_adjustments(): StockAdjustmentService {
		return $this->container->stock_adjustment_service();
	}

	/** Combined pool availability. @return AvailabilityService */
	public function availability(): AvailabilityService {
		return $this->container->availability_service();
	}

	/** Exact quantity formatter. @return QuantityFormatter */
	public function quantities(): QuantityFormatter {
		return $this->container->quantity_formatter();
	}

	/** Inventory-pool presenter. @return PoolPresenter */
	public function pool_presenter(): PoolPresenter {
		return $this->container->pool_presenter();
	}

	/** Movement-ledger presenter. @return MovementPresenter */
	public function movement_presenter(): MovementPresenter {
		return $this->container->movement_presenter();
	}
}
