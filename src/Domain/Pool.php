<?php
/**
 * Inventory pool entity.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable view of one authoritative physical stock balance.
 */
final class Pool {

	/**
	 * Pool ID.
	 *
	 * @var int
	 */
	private $id;

	/**
	 * Pool name.
	 *
	 * @var string
	 */
	private $name;

	/**
	 * Exact normalized balance.
	 *
	 * @var Quantity
	 */
	private $quantity;

	/**
	 * Preferred display unit.
	 *
	 * @var string
	 */
	private $display_unit;

	/**
	 * Whether negative stock is allowed.
	 *
	 * @var bool
	 */
	private $allow_backorders;

	/**
	 * Constructor.
	 *
	 * @param int      $id               Pool ID.
	 * @param string   $name             Pool name.
	 * @param Quantity $quantity         Exact normalized balance.
	 * @param string   $display_unit     Preferred display unit.
	 * @param bool     $allow_backorders Whether negative balances are allowed.
	 */
	public function __construct( int $id, string $name, Quantity $quantity, string $display_unit, bool $allow_backorders ) {
		$this->id               = $id;
		$this->name             = $name;
		$this->quantity         = $quantity;
		$this->display_unit     = $display_unit;
		$this->allow_backorders = $allow_backorders;
	}

	/**
	 * Pool ID.
	 *
	 * @return int
	 */
	public function id(): int {
		return $this->id;
	}

	/**
	 * Pool name.
	 *
	 * @return string
	 */
	public function name(): string {
		return $this->name;
	}

	/**
	 * Exact normalized balance.
	 *
	 * @return Quantity
	 */
	public function quantity(): Quantity {
		return $this->quantity;
	}

	/**
	 * Preferred display unit.
	 *
	 * @return string
	 */
	public function display_unit(): string {
		return $this->display_unit;
	}

	/**
	 * Whether negative stock is allowed.
	 *
	 * @return bool
	 */
	public function allows_backorders(): bool {
		return $this->allow_backorders;
	}
}
