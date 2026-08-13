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
	 * Operational SKU.
	 *
	 * @var string
	 */
	private $internal_sku;

	/**
	 * Persisted optimistic-lock version.
	 *
	 * @var int
	 */
	private $version;

	/**
	 * Constructor.
	 *
	 * @param int      $id               Pool ID.
	 * @param string   $name             Pool name.
	 * @param Quantity $quantity         Exact normalized balance.
	 * @param string   $display_unit     Preferred display unit.
	 * @param bool     $allow_backorders Whether negative balances are allowed.
	 * @param string   $internal_sku     Optional operational SKU.
	 * @param int      $version          Persisted optimistic-lock version.
	 */
	public function __construct( int $id, string $name, Quantity $quantity, string $display_unit, bool $allow_backorders, string $internal_sku = '', int $version = 1 ) {
		$this->id               = $id;
		$this->name             = $name;
		$this->quantity         = $quantity;
		$this->display_unit     = $display_unit;
		$this->allow_backorders = $allow_backorders;
		$this->internal_sku     = $internal_sku;
		$this->version          = $version;
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

	/** Operational SKU. @return string */
	public function internal_sku(): string {
		return $this->internal_sku;
	}

	/** Persisted optimistic-lock version. @return int */
	public function version(): int {
		return $this->version;
	}
}
