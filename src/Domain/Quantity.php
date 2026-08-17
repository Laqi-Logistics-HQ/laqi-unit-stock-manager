<?php
/**
 * Exact normalized stock quantity.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Domain;

defined( 'ABSPATH' ) || exit;

use InvalidArgumentException;

/**
 * Immutable quantity stored in a measurement family's canonical base unit.
 */
final class Quantity {

	/**
	 * Measurement family.
	 *
	 * @var string
	 */
	private $family;

	/**
	 * Normalized amount.
	 *
	 * @var int
	 */
	private $amount;

	/**
	 * Constructor.
	 *
	 * @param string $family Measurement family.
	 * @param int    $amount Normalized amount.
	 * @throws InvalidArgumentException When the family is unknown.
	 */
	public function __construct( string $family, int $amount ) {
		if ( ! in_array( $family, array( 'mass', 'volume', 'length', 'area', 'count' ), true ) ) {
			throw new InvalidArgumentException( 'Unknown quantity family.' );
		}

		$this->family = $family;
		$this->amount = $amount;
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
	 * Normalized integer amount.
	 *
	 * @return int
	 */
	public function amount(): int {
		return $this->amount;
	}

	/**
	 * Add another quantity from the same family.
	 *
	 * @param Quantity $other Quantity to add.
	 * @return Quantity
	 */
	public function plus( Quantity $other ): Quantity {
		$this->assert_same_family( $other );

		return new self( $this->family, $this->amount + $other->amount );
	}

	/**
	 * Whether this quantity covers another quantity.
	 *
	 * @param Quantity $required Required quantity.
	 * @return bool
	 */
	public function covers( Quantity $required ): bool {
		$this->assert_same_family( $required );

		return $this->amount >= $required->amount;
	}

	/**
	 * Require matching measurement families.
	 *
	 * @param Quantity $other Other quantity.
	 * @return void
	 * @throws InvalidArgumentException When the families differ.
	 */
	private function assert_same_family( Quantity $other ): void {
		if ( $this->family !== $other->family ) {
			throw new InvalidArgumentException( 'Cannot combine different quantity families.' );
		}
	}
}
