<?php
/** Versioned operations CSV exchange tests. @package LaqiUnitStockManager */

use LaqiUnitStockManager\Premium\Exchange\CsvRowMapperInterface;
use LaqiUnitStockManager\Premium\Exchange\CsvRowMapperRegistry;
use LaqiUnitStockManager\Premium\Exchange\OperationsCsvService;

/** Predictable mapper used to exercise the reusable exchange contract. */
final class Laqi_Lusm_Test_Csv_Mapper implements CsvRowMapperInterface {
	/** Imported rows. @var array<int,array<string,string>> */ public $imported = array();
	/** Row type. */ public function type(): string { return 'supplier'; }
	/** Export one formula-like merchant value. @return array<int,array<string,string>> */
	public function export_rows(): array { return array( array( 'supplier_name' => '=SUM(1,1)', 'lead_time_days' => '7' ) ); }
	/** Record imports and report idempotent repeats. @param array<string,string> $row Row. */
	public function import_row( array $row ): string { $status = empty( $this->imported ) ? 'created' : 'skipped'; $this->imported[] = $row; return $status; }
}

/** Protects the public schema and mapper extension point. */
class Test_Operations_Csv_Exchange extends WP_UnitTestCase {
	/** Export is schema ordered and spreadsheet safe without changing round trips. */
	public function test_versioned_rows_round_trip_idempotently(): void {
		$mapper = new Laqi_Lusm_Test_Csv_Mapper();
		$service = $this->service( $mapper );
		$rows = $service->rows();
		$this->assertSame( OperationsCsvService::VERSION, $rows[0]['schema_version'] );
		$this->assertSame( "'=SUM(1,1)", $rows[0]['supplier_name'] );

		$file = $this->csv_file( $service->headers(), $rows );
		$this->assertSame( array( 'created' => 1, 'skipped' => 0 ), $service->import_file( $file ) );
		$this->assertSame( '=SUM(1,1)', $mapper->imported[0]['supplier_name'] );
		$this->assertSame( array( 'created' => 0, 'skipped' => 1 ), $service->import_file( $file ) );
		unlink( $file );
	}

	/** Unknown schema versions are rejected before mapper dispatch. */
	public function test_unknown_schema_version_is_rejected(): void {
		$mapper = new Laqi_Lusm_Test_Csv_Mapper();
		$service = $this->service( $mapper );
		$row = $service->rows()[0];
		$row['schema_version'] = '99';
		$file = $this->csv_file( $service->headers(), array( $row ) );
		$this->expectException( InvalidArgumentException::class );
		try { $service->import_file( $file ); } finally { unlink( $file ); }
	}

	/** Duplicate row types cannot silently replace a registered importer. */
	public function test_registry_rejects_duplicate_types(): void {
		$mapper = new Laqi_Lusm_Test_Csv_Mapper();
		$registry = new CsvRowMapperRegistry();
		$registry->register( $mapper );
		$this->expectException( InvalidArgumentException::class );
		$registry->register( $mapper );
	}

	/** Build the production service around a test mapper. */
	private function service( Laqi_Lusm_Test_Csv_Mapper $mapper ): OperationsCsvService { $registry = new CsvRowMapperRegistry(); $registry->register( $mapper ); return new OperationsCsvService( $registry ); }

	/** Write a portable CSV fixture. @param array<int,string> $headers Headers. @param array<int,array<string,string>> $rows Rows. */
	private function csv_file( array $headers, array $rows ): string { $file = wp_tempnam( 'laqi-lusm-csv' ); $handle = fopen( $file, 'w' ); fputcsv( $handle, $headers, ',', '"', '' ); foreach ( $rows as $row ) { fputcsv( $handle, array_values( $row ), ',', '"', '' ); } fclose( $handle ); return $file; }
}
