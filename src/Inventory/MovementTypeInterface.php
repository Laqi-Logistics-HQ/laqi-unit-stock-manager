<?php
/**
 * Extensible stock movement type contract.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Inventory;

defined( 'ABSPATH' ) || exit;

/**
 * Gives Free and Pro movement types stable keys and translated labels.
 */
interface MovementTypeInterface {

	/** Get the stored movement key. @return string */
	public function key(): string;

	/** Get the translated movement label. @return string */
	public function label(): string;
}
