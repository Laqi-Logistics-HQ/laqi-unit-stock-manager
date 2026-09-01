<?php
/**
 * Mapping component entity.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * One pool quantity consumed by a product mapping.
 */
final class MappingComponent {

	/**
	 * Pool ID.
	 *
	 * @var int
	 */
	private $pool_id;

	/**
	 * Normalized consumption per sold item.
	 *
	 * @var int
	 */
	private $consumption;

	/**
	 * Component role.
	 *
	 * @var string
	 */
	private $role;

	/**
	 * Constructor.
	 *
	 * @param int    $pool_id    Pool ID.
	 * @param int    $consumption Normalized quantity consumed per sold item.
	 * @param string $role       Component role.
	 */
	public function __construct( int $pool_id, int $consumption, string $role = 'contents' ) {
		$this->pool_id     = $pool_id;
		$this->consumption = $consumption;
		$this->role        = $role;
	}

	/**
	 * Pool ID.
	 *
	 * @return int
	 */
	public function pool_id(): int {
		return $this->pool_id;
	}

	/**
	 * Normalized consumption per sold item.
	 *
	 * @return int
	 */
	public function consumption(): int {
		return $this->consumption;
	}

	/**
	 * Component role.
	 *
	 * @return string
	 */
	public function role(): string {
		return $this->role;
	}
}
