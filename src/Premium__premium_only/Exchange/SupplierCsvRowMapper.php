<?php
/**
 * Supplier CSV rows.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Exchange;

defined( 'ABSPATH' ) || exit;
// phpcs:disable Squiz.Commenting.VariableComment, Squiz.Commenting.FunctionComment
use LaqiUnitStockManager\Premium\Receiving\SupplierRepository;

/** Exchanges suppliers idempotently by exact name. */
final class SupplierCsvRowMapper implements CsvRowMapperInterface {
	/** Suppliers. @var SupplierRepository */ private $suppliers;
	/** Constructor. @param SupplierRepository $suppliers Suppliers. */ public function __construct( SupplierRepository $suppliers ) {
		$this->suppliers = $suppliers; }
	/** Type. @return string */ public function type(): string {
		return 'supplier'; }
	/** Rows. @return array<int,array<string,string>> */ public function export_rows(): array {
		$rows = array();
		foreach ( $this->suppliers->suppliers() as $supplier ) {
			$rows[] = array(
				'supplier_name'  => (string) $supplier['name'],
				'supplier_email' => (string) $supplier['email'],
				'lead_time_days' => (string) $supplier['lead_time_days'],
			);
		} return $rows; }
	/** Import. @param array<string,string> $row Row. @return string */ public function import_row( array $row ): string {
		if ( null !== $this->suppliers->supplier_by_name( $row['supplier_name'] ) ) {
			return 'skipped';
		} $this->suppliers->create_supplier( $row['supplier_name'], $row['supplier_email'], absint( $row['lead_time_days'] ) );
		return 'created'; }
}
