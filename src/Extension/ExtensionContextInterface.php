<?php
/**
 * Stable service surface exposed to add-on plugins.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Extension;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Admin\ScreenSectionCatalog;
use LaqiUnitStockManager\Availability\AvailabilityService;
use LaqiUnitStockManager\Consumption\CalculatorRegistry;
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

/**
 * Versioned services that extensions may depend on.
 *
 * The internal dependency container is deliberately not part of this contract.
 */
interface ExtensionContextInterface {

	/** Public API version implemented by this context. @return string */
	public function api_version(): string;

	/** Extensible exact-unit registry. @return UnitRegistry */
	public function units(): UnitRegistry;

	/** Extensible movement-type registry. @return MovementRegistry */
	public function movements(): MovementRegistry;

	/** Consumption calculator registry. @return CalculatorRegistry */
	public function calculators(): CalculatorRegistry;

	/** Unit Stock admin sections. @return ScreenSectionCatalog */
	public function admin_sections(): ScreenSectionCatalog;

	/** Inventory-pool persistence. @return PoolRepository */
	public function pools(): PoolRepository;

	/** Product-mapping persistence. @return MappingRepository */
	public function mappings(): MappingRepository;

	/** Movement-ledger reads. @return MovementRepository */
	public function movement_history(): MovementRepository;

	/** Authoritative stock mutation service. @return StockMutationService */
	public function stock_mutations(): StockMutationService;

	/** Validated stock adjustment service. @return StockAdjustmentService */
	public function stock_adjustments(): StockAdjustmentService;

	/** Combined pool availability. @return AvailabilityService */
	public function availability(): AvailabilityService;

	/** Exact quantity formatter. @return QuantityFormatter */
	public function quantities(): QuantityFormatter;

	/** Inventory-pool presenter. @return PoolPresenter */
	public function pool_presenter(): PoolPresenter;

	/** Movement-ledger presenter. @return MovementPresenter */
	public function movement_presenter(): MovementPresenter;
}
