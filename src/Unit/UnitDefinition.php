<?php
/**
 * Unit definition value object.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Unit;

defined( 'ABSPATH' ) || exit;

/**
 * Describes a unit as an integer multiple of its family's base unit.
 */
final class UnitDefinition {

	/**
	 * Unit key.
	 *
	 * @var string
	 */
	private $key;

	/**
	 * Measurement family.
	 *
	 * @var string
	 */
	private $family;

	/**
	 * Number of canonical base units.
	 *
	 * @var int
	 */
	private $base_factor;

	/**
	 * Unit system.
	 *
	 * @var string
	 */
	private $system;

	/**
	 * Merchant-facing unit label.
	 *
	 * @var string
	 */
	private $label;

	/**
	 * Merchant-facing unit symbol.
	 *
	 * @var string
	 */
	private $symbol;

	/**
	 * Constructor.
	 *
	 * @param string $key         Unit key.
	 * @param string $family      Measurement family.
	 * @param int    $base_factor Number of base units.
	 * @param string $system      Unit system or custom.
	 * @param string $label       Merchant-facing label.
	 * @param string $symbol      Merchant-facing symbol.
	 */
	public function __construct( string $key, string $family, int $base_factor, string $system = 'custom', string $label = '', string $symbol = '' ) {
		$this->key         = $key;
		$this->family      = $family;
		$this->base_factor = $base_factor;
		$this->system      = $system;
		$this->label       = '' !== $label ? $label : $key;
		$this->symbol      = '' !== $symbol ? $symbol : $key;
	}

	/**
	 * Unit key.
	 *
	 * @return string
	 */
	public function key(): string {
		return $this->key;
	}

	/**
	 * Measurement family.
	 *
	 * @return string
	 */
	public function family(): string {
		return $this->family;
	}

	/**
	 * Number of canonical base units.
	 *
	 * @return int
	 */
	public function base_factor(): int {
		return $this->base_factor;
	}

	/**
	 * Unit system.
	 *
	 * @return string
	 */
	public function system(): string {
		return $this->system;
	}

	/** Merchant-facing label. @return string */
	public function label(): string {
		return $this->label;
	}

	/** Merchant-facing symbol. @return string */
	public function symbol(): string {
		return $this->symbol;
	}
}
