<?php
/**
 * Versioned operations CSV exchange service.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Exchange;

defined( 'ABSPATH' ) || exit;
// phpcs:disable Squiz.Commenting.VariableComment, Squiz.Commenting.FunctionComment
// phpcs:disable Squiz.Commenting.FunctionCommentThrowTag
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fclose, Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition

use InvalidArgumentException;
use RuntimeException;

/** Serializes registered operational records with a stable versioned schema. */
final class OperationsCsvService {
	const VERSION  = '1';
	const MAX_ROWS = 5000;
	/** Registry. @var CsvRowMapperRegistry */ private $registry;
	/** Constructor. @param CsvRowMapperRegistry $registry Registry. */ public function __construct( CsvRowMapperRegistry $registry ) {
		$this->registry = $registry; }
	/** Stable columns. @return array<int,string> */ public function headers(): array {
		return array( 'schema_version', 'record_type', 'supplier_name', 'supplier_email', 'lead_time_days', 'pool_sku', 'pool_name', 'pack_name', 'quantity', 'unit', 'pack_count', 'expected_date', 'reference', 'safety_stock' ); }
	/** All export rows. @return array<int,array<string,string>> */
	public function rows(): array {
		$rows = array();
		foreach ( $this->registry->all() as $type => $mapper ) {
			foreach ( $mapper->export_rows() as $row ) {
				$row    = array_merge(
					array_fill_keys( $this->headers(), '' ),
					$row,
					array(
						'schema_version' => self::VERSION,
						'record_type'    => $type,
					)
				);
				$rows[] = array_map( array( $this, 'spreadsheet_safe' ), $row );
			}
		} return $rows; }
	/** Import a CSV stream. @return array<string,int> */
	public function import_file( string $file ): array {
		$handle = fopen( $file, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $handle ) {
			throw new RuntimeException( 'Could not open the CSV file.' ); }
		$headers = fgetcsv( $handle, 0, ',', '"', '' );
		if ( ! is_array( $headers ) ) {
			fclose( $handle );
			throw new InvalidArgumentException( 'The CSV file has no header.' ); } // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		$headers[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $headers[0] );
		if ( $headers !== $this->headers() ) {
			fclose( $handle );
			throw new InvalidArgumentException( 'The CSV columns do not match schema version 1.' ); } // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		$result = array(
			'created' => 0,
			'skipped' => 0,
		);
		$count  = 0;
		while ( false !== ( $values = fgetcsv( $handle, 0, ',', '"', '' ) ) ) {
			if ( ++$count > self::MAX_ROWS ) {
				fclose( $handle );
				throw new InvalidArgumentException( 'The CSV file contains too many rows.' );
			} if ( count( $values ) !== count( $headers ) ) {
				fclose( $handle );
				throw new InvalidArgumentException( 'A CSV row has the wrong number of columns.' );
			} $row = array_combine( $headers, $values );
			$row   = array_map( array( $this, 'spreadsheet_restore' ), $row );
			if ( self::VERSION !== $row['schema_version'] ) {
				fclose( $handle );
				throw new InvalidArgumentException( 'Unsupported CSV schema version.' );
			} $status = $this->registry->get( sanitize_key( $row['record_type'] ) )->import_row( array_map( 'sanitize_text_field', $row ) );
			++$result[ $status ]; }
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $result;
	}
	/** Prevent spreadsheet applications from evaluating user-authored cells. */
	private function spreadsheet_safe( string $value ): string {
		return preg_match( '/^[=+\-@]/', $value ) ? "'" . $value : $value; }
	/** Restore only the escape marker emitted by spreadsheet_safe(). */
	private function spreadsheet_restore( string $value ): string {
		return preg_match( "/^'[=+\-@]/", $value ) ? substr( $value, 1 ) : $value; }
}
