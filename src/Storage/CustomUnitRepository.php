<?php
/**
 * Merchant-defined unit persistence.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Storage;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Unit\UnitDefinition;
use LaqiUnitStockManager\Unit\UnitRegistry;
use RuntimeException;
use wpdb;

/**
 * Stores and loads custom units without changing the conversion engine.
 */
final class CustomUnitRepository {

	/**
	 * WordPress database connection.
	 *
	 * @var wpdb
	 */
	private $db;

	/**
	 * Constructor.
	 *
	 * @param wpdb $db WordPress database connection.
	 */
	public function __construct( wpdb $db ) {
		$this->db = $db;
	}

	/**
	 * Create and register a merchant-defined unit.
	 *
	 * @param UnitRegistry $registry         Unit registry.
	 * @param string       $key              Stable custom key.
	 * @param string       $label            Merchant-facing label.
	 * @param string       $symbol           Merchant-facing symbol.
	 * @param string       $reference_value  Exact reference quantity.
	 * @param string       $reference_unit   Existing unit key.
	 * @return UnitDefinition
	 * @throws RuntimeException When persistence fails.
	 */
	public function create( UnitRegistry $registry, string $key, string $label, string $symbol, string $reference_value, string $reference_unit ): UnitDefinition {
		$definition = $registry->register_custom( $key, $reference_value, $reference_unit, $label, $symbol );
		$now        = current_time( 'mysql', true );
		$inserted   = $this->db->insert(
			Schema::table( 'units' ),
			array(
				'unit_key'        => $definition->key(),
				'label'           => $label,
				'symbol'          => $symbol,
				'family'          => $definition->family(),
				'base_factor'     => $definition->base_factor(),
				'reference_value' => $reference_value,
				'reference_unit'  => $reference_unit,
				'active'          => 1,
				'created_at'      => $now,
				'updated_at'      => $now,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			throw new RuntimeException( 'Could not save the custom stock unit.' );
		}

		return $definition;
	}

	/**
	 * Register every active custom unit into a runtime registry.
	 *
	 * @param UnitRegistry $registry Unit registry.
	 * @return void
	 */
	public function register_all( UnitRegistry $registry ): void {
		$rows = $this->db->get_results(
			'SELECT unit_key, label, symbol, family, base_factor FROM ' . Schema::table( 'units' ) . ' WHERE active = 1 ORDER BY id ASC', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		foreach ( $rows as $row ) {
			$registry->register(
				new UnitDefinition(
					(string) $row['unit_key'],
					(string) $row['family'],
					(int) $row['base_factor'],
					'custom',
					(string) $row['label'],
					(string) $row['symbol']
				)
			);
		}
	}

	/**
	 * List active merchant-defined unit metadata.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function active(): array {
		$rows = $this->db->get_results(
			'SELECT id, unit_key, label, symbol, family, reference_value, reference_unit FROM ' . Schema::table( 'units' ) . ' WHERE active = 1 ORDER BY label ASC, id ASC', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Deactivate an unused custom unit while retaining its stable key.
	 *
	 * @param int $unit_id Custom-unit ID.
	 * @return void
	 * @throws \InvalidArgumentException When the unit is inactive or in use.
	 * @throws RuntimeException When persistence fails.
	 */
	public function deactivate( int $unit_id ): void {
		$row = $this->db->get_row(
			$this->db->prepare( 'SELECT unit_key FROM ' . Schema::table( 'units' ) . ' WHERE id = %d AND active = 1', $unit_id ),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			throw new \InvalidArgumentException( 'The custom stock unit is not active.' );
		}

		$key         = (string) $row['unit_key'];
		$pool_uses   = (int) $this->db->get_var(
			$this->db->prepare( 'SELECT COUNT(*) FROM ' . Schema::table( 'pools' ) . ' WHERE base_unit = %s OR display_unit = %s', $key, $key )
		);
		$custom_uses = (int) $this->db->get_var(
			$this->db->prepare( 'SELECT COUNT(*) FROM ' . Schema::table( 'units' ) . ' WHERE reference_unit = %s AND active = 1 AND id <> %d', $key, $unit_id )
		);
		if ( $pool_uses > 0 || $custom_uses > 0 ) {
			throw new \InvalidArgumentException( 'A custom stock unit cannot be retired while it is in use.' );
		}

		$updated = $this->db->update(
			Schema::table( 'units' ),
			array(
				'active'     => 0,
				'updated_at' => current_time( 'mysql', true ),
			),
			array(
				'id'     => $unit_id,
				'active' => 1,
			),
			array( '%d', '%s' ),
			array( '%d', '%d' )
		);
		if ( 1 !== $updated ) {
			throw new RuntimeException( 'Could not retire the custom stock unit.' );
		}
	}
}
