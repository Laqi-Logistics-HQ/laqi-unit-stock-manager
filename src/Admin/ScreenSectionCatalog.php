<?php
/**
 * Unit Stock screen section registry.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Admin;

defined( 'ABSPATH' ) || exit;

use InvalidArgumentException;

/**
 * Stores self-registering admin sections without naming premium modules.
 */
final class ScreenSectionCatalog {

	/**
	 * Registered sections.
	 *
	 * @var array<string, ScreenSectionInterface>
	 */
	private $sections = array();

	/**
	 * Register a screen section.
	 *
	 * @param ScreenSectionInterface $section Section.
	 * @return void
	 * @throws InvalidArgumentException When the section ID is empty or already used.
	 */
	public function register( ScreenSectionInterface $section ): void {
		$id = sanitize_key( $section->id() );
		if ( '' === $id || isset( $this->sections[ $id ] ) ) {
			throw new InvalidArgumentException( 'Screen section IDs must be unique and non-empty.' );
		}
		$this->sections[ $id ] = $section;
	}

	/**
	 * Registered sections.
	 *
	 * @return array<string, ScreenSectionInterface>
	 */
	public function all(): array {
		return $this->sections;
	}
}
