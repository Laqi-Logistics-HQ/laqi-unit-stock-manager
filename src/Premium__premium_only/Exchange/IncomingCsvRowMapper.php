<?php
/**
 * Incoming delivery CSV rows.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Exchange;

defined( 'ABSPATH' ) || exit;
// phpcs:disable Squiz.Commenting.VariableComment, Squiz.Commenting.FunctionComment
// phpcs:disable Squiz.Commenting.FunctionCommentThrowTag
use InvalidArgumentException;
use LaqiUnitStockManager\Premium\Receiving\SupplierRepository;
use LaqiUnitStockManager\Storage\PoolRepository;

/** Exchanges pending incoming deliveries after suppliers and packs. */
final class IncomingCsvRowMapper implements CsvRowMapperInterface {
	/** Suppliers. @var SupplierRepository */ private $suppliers;
	/** Pools. @var PoolRepository */ private $pools;
	/** Constructor. */ public function __construct( SupplierRepository $suppliers, PoolRepository $pools ) {
		$this->suppliers = $suppliers;
		$this->pools     = $pools; }
	/** Type. @return string */ public function type(): string {
		return 'incoming'; }
	/** Rows. @return array<int,array<string,string>> */ public function export_rows(): array {
		$rows = array();
		foreach ( $this->suppliers->incoming_deliveries() as $delivery ) {
			$pool = $this->pools->find( (int) $delivery['pool_id'] );
			if ( null !== $pool ) {
				$rows[] = array(
					'supplier_name' => (string) $delivery['supplier_name'],
					'pool_sku'      => $pool->internal_sku(),
					'pool_name'     => $pool->name(),
					'pack_name'     => (string) $delivery['pack_name'],
					'pack_count'    => (string) $delivery['pack_count'],
					'expected_date' => (string) $delivery['expected_date'],
					'reference'     => (string) $delivery['reference'],
				);
			}
		} return $rows; }
	/** Import. @param array<string,string> $row Row. @return string */ public function import_row( array $row ): string {
		if ( $this->suppliers->has_incoming_reference( $row['reference'] ) ) {
			return 'skipped';
		} $supplier = $this->suppliers->supplier_by_name( $row['supplier_name'] );
		$pool       = $this->pools->find_by_identity( $row['pool_sku'], $row['pool_name'] );
		if ( null === $supplier || null === $pool ) {
			throw new InvalidArgumentException( 'An incoming delivery references an unknown supplier or pool.' );
		} $pack = $this->suppliers->pack_by_identity( (int) $supplier['id'], $pool->id(), $row['pack_name'] );
		if ( null === $pack ) {
			throw new InvalidArgumentException( 'An incoming delivery references an unknown supplier pack.' );
		} $this->suppliers->create_incoming( (int) $pack['id'], absint( $row['pack_count'] ), $row['expected_date'], $row['reference'] );
		return 'created'; }
}
