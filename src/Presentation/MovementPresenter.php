<?php
/**
 * Normalized movement presenter.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Presentation;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Inventory\MovementRegistry;

/**
 * Produces a stable ledger shape for admin, REST, CLI, and exports.
 */
final class MovementPresenter {

	/**
	 * Quantity formatter.
	 *
	 * @var QuantityFormatter
	 */
	private $formatter;

	/**
	 * Movement types.
	 *
	 * @var MovementRegistry
	 */
	private $types;

	/** Constructor.
	 *
	 * @param QuantityFormatter $formatter Quantity formatter.
	 * @param MovementRegistry  $types     Movement types.
	 */
	public function __construct( QuantityFormatter $formatter, MovementRegistry $types ) {
		$this->formatter = $formatter;
		$this->types     = $types;
	}

	/** Present one repository row.
	 *
	 * @param array<string, mixed> $row Repository row.
	 * @return array<string, mixed> */
	public function present( array $row ): array {
		return array(
			'id'              => (int) $row['id'],
			'pool_id'         => (int) $row['pool_id'],
			'pool_name'       => (string) $row['pool_name'],
			'type'            => (string) $row['type'],
			'type_label'      => $this->types->label( (string) $row['type'] ),
			'delta_base'      => (int) $row['delta_base'],
			'delta_display'   => $this->format_quantity( $row, (int) $row['delta_base'] ),
			'balance_base'    => (int) $row['balance_base'],
			'balance_display' => $this->format_quantity( $row, (int) $row['balance_base'] ),
			'source_type'     => (string) $row['source_type'],
			'source_id'       => (int) $row['source_id'],
			'actor_id'        => (int) $row['actor_id'],
			'actor_name'      => isset( $row['actor_name'] ) ? (string) $row['actor_name'] : '',
			'reason'          => (string) $row['reason'],
			'created_at'      => (string) $row['created_at'],
		);
	}

	/** Format a movement quantity even when its pool was removed.
	 *
	 * @param array<string, mixed> $row    Repository row.
	 * @param int                  $amount Base amount.
	 * @return string */
	private function format_quantity( array $row, int $amount ): string {
		$family = (string) $row['family'];
		$unit   = (string) $row['display_unit'];
		if ( '' === $family || '' === $unit ) {
			return (string) $amount;
		}
		return $this->formatter->format( new Quantity( $family, $amount ), $unit );
	}
}
