<?php
/**
 * Stock movement type registry.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Inventory;

defined( 'ABSPATH' ) || exit;

use InvalidArgumentException;

/**
 * Resolves Free and independently registered Pro movement labels.
 */
final class MovementRegistry {

	/**
	 * Registered types.
	 *
	 * @var array<string, MovementTypeInterface>
	 */
	private $types = array();

	/** Register a movement type.
	 *
	 * @param MovementTypeInterface $type Movement type.
	 * @return void
	 * @throws InvalidArgumentException When the key is empty or already registered. */
	public function register( MovementTypeInterface $type ): void {
		$key = sanitize_key( $type->key() );
		if ( '' === $key || isset( $this->types[ $key ] ) ) {
			throw new InvalidArgumentException( 'Movement type keys must be unique and non-empty.' );
		}
		$this->types[ $key ] = $type;
	}

	/** Get a translated label with a readable fallback.
	 *
	 * @param string $key Stored key.
	 * @return string */
	public function label( string $key ): string {
		return isset( $this->types[ $key ] ) ? $this->types[ $key ]->label() : ucwords( str_replace( '_', ' ', $key ) );
	}
}
