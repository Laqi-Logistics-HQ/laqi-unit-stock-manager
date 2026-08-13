<?php
/**
 * Basic stock movement type.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Inventory;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable movement type for the shared registry.
 */
final class MovementType implements MovementTypeInterface {

	/**
	 * Stored key.
	 *
	 * @var string
	 */
	private $key;

	/**
	 * Translated label.
	 *
	 * @var string
	 */
	private $label;

	/**
	 * Constructor.
	 *
	 * @param string $key   Stored key.
	 * @param string $label Translated label.
	 */
	public function __construct( string $key, string $label ) {
		$this->key   = $key;
		$this->label = $label;
	}

	/** Get the stored key. @return string */
	public function key(): string {
		return $this->key;
	}

	/** Get the translated label. @return string */
	public function label(): string {
		return $this->label;
	}
}
