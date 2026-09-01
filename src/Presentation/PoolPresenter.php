<?php
/**
 * Normalized inventory-pool presenter.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Presentation;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Domain\Pool;

/**
 * Produces the stable row shape shared by admin, REST, CLI, and exports.
 */
final class PoolPresenter {

	/**
	 * Exact display formatter.
	 *
	 * @var QuantityFormatter
	 */
	private $formatter;

	/**
	 * Constructor.
	 *
	 * @param QuantityFormatter $formatter Exact formatter.
	 */
	public function __construct( QuantityFormatter $formatter ) {
		$this->formatter = $formatter;
	}

	/**
	 * Present a pool.
	 *
	 * @param Pool $pool Inventory pool.
	 * @return array<string, mixed>
	 */
	public function present( Pool $pool ): array {
		return array(
			'id'               => $pool->id(),
			'name'             => $pool->name(),
			'family'           => $pool->quantity()->family(),
			'display_unit'     => $pool->display_unit(),
			'quantity_base'    => $pool->quantity()->amount(),
			'quantity_display' => $this->formatter->format( $pool->quantity(), $pool->display_unit() ),
			'allow_backorders' => $pool->allows_backorders(),
			'internal_sku'     => $pool->internal_sku(),
			'version'          => $pool->version(),
		);
	}
}
