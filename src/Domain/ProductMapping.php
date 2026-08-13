<?php
/**
 * Product-to-pool mapping entity.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable mapping for one simple product or variation.
 */
final class ProductMapping {

	/**
	 * Mapping ID.
	 *
	 * @var int
	 */
	private $id;

	/**
	 * Parent or simple product ID.
	 *
	 * @var int
	 */
	private $product_id;

	/**
	 * Variation ID or zero.
	 *
	 * @var int
	 */
	private $variation_id;

	/**
	 * Registered calculator type.
	 *
	 * @var string
	 */
	private $calculator_type;

	/**
	 * Persisted mapping version.
	 *
	 * @var int
	 */
	private $version;

	/**
	 * Consumption components.
	 *
	 * @var MappingComponent[]
	 */
	private $components;

	/**
	 * Constructor.
	 *
	 * @param int                $id              Mapping ID.
	 * @param int                $product_id      Parent/simple product ID.
	 * @param int                $variation_id    Variation ID or zero.
	 * @param string             $calculator_type Registered calculator type.
	 * @param MappingComponent[] $components      Consumption components.
	 * @param int                $version         Persisted mapping version.
	 */
	public function __construct( int $id, int $product_id, int $variation_id, string $calculator_type, array $components, int $version = 1 ) {
		$this->id              = $id;
		$this->product_id      = $product_id;
		$this->variation_id    = $variation_id;
		$this->calculator_type = $calculator_type;
		$this->components      = $components;
		$this->version         = $version;
	}

	/**
	 * Mapping ID.
	 *
	 * @return int
	 */
	public function id(): int {
		return $this->id;
	}

	/**
	 * Parent or simple product ID.
	 *
	 * @return int
	 */
	public function product_id(): int {
		return $this->product_id;
	}

	/**
	 * Variation ID or zero.
	 *
	 * @return int
	 */
	public function variation_id(): int {
		return $this->variation_id;
	}

	/**
	 * Registered calculator type.
	 *
	 * @return string
	 */
	public function calculator_type(): string {
		return $this->calculator_type;
	}

	/**
	 * Persisted mapping version.
	 *
	 * @return int
	 */
	public function version(): int {
		return $this->version;
	}

	/**
	 * Consumption components.
	 *
	 * @return MappingComponent[]
	 */
	public function components(): array {
		return $this->components;
	}
}
