<?php
/**
 * Completed stock movement result.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Inventory;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable result returned by the stock mutation service.
 */
final class MovementResult {

	/**
	 * Movement ID.
	 *
	 * @var int
	 */
	private $movement_id;

	/**
	 * Resulting normalized balance.
	 *
	 * @var int
	 */
	private $balance;

	/**
	 * Whether an existing movement was reused.
	 *
	 * @var bool
	 */
	private $duplicate;

	/**
	 * Constructor.
	 *
	 * @param int  $movement_id Movement ID.
	 * @param int  $balance     Resulting balance.
	 * @param bool $duplicate   Whether an existing idempotent result was reused.
	 */
	public function __construct( int $movement_id, int $balance, bool $duplicate = false ) {
		$this->movement_id = $movement_id;
		$this->balance     = $balance;
		$this->duplicate   = $duplicate;
	}

	/**
	 * Movement ID.
	 *
	 * @return int
	 */
	public function movement_id(): int {
		return $this->movement_id;
	}

	/**
	 * Resulting normalized balance.
	 *
	 * @return int
	 */
	public function balance(): int {
		return $this->balance;
	}

	/**
	 * Whether an existing movement was reused.
	 *
	 * @return bool
	 */
	public function is_duplicate(): bool {
		return $this->duplicate;
	}
}
