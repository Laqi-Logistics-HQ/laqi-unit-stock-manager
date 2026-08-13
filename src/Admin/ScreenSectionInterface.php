<?php
/**
 * Extensible Unit Stock screen section contract.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Lets Free and physically separate Pro modules contribute screen sections.
 */
interface ScreenSectionInterface {

	/** Get the stable section ID. @return string */
	public function id(): string;

	/** Get the translated section title. @return string */
	public function title(): string;

	/** Render the section contents. @return void */
	public function render(): void;
}
