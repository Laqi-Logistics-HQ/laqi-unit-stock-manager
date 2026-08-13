<?php
/**
 * Linked-product material economics.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Costing;

defined( 'ABSPATH' ) || exit;

// Compact service methods remain explicit through types and names.
// phpcs:disable Generic.Commenting.DocComment.MissingShort, Squiz.Commenting.FunctionComment

use LaqiUnitStockManager\Domain\ProductMapping;

/** Calculates read-only economics independently of their presentation. */
final class MaterialEconomicsService {
	/** @var MaterialCostRepository */ private $costs;

	/** @param MaterialCostRepository $costs Cost repository. */
	public function __construct( MaterialCostRepository $costs ) {
		$this->costs = $costs; }

	/** Calculate one mapped sale unit.
	 *
	 * @param ProductMapping $mapping Mapping.
	 * @return array<string,mixed>
	 */
	public function calculate( ProductMapping $mapping ): array {
		$product   = wc_get_product( $mapping->variation_id() > 0 ? $mapping->variation_id() : $mapping->product_id() );
		$component = $mapping->components()[0] ?? null;
		$pool_cost = null === $component ? null : $this->costs->pool_cost( $component->pool_id() );
		$minor     = null === $component ? null : $this->costs->consumption_cost_minor( $component->pool_id(), $component->consumption() );
		$material  = null === $minor ? null : $minor / ( 10 ** wc_get_price_decimals() );
		$price     = $product ? (float) $product->get_price() : 0.0;
		return array(
			'product'       => $product,
			'material_cost' => $material,
			'currency'      => null === $pool_cost ? '' : $pool_cost['currency'],
			'price'         => $price,
			'margin'        => null !== $material && $price > 0 ? ( ( $price - $material ) / $price ) * 100 : null,
		);
	}
}
