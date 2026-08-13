<?php
/**
 * Versioned operations CSV row contract.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Exchange;

defined( 'ABSPATH' ) || exit;
// phpcs:disable Squiz.Commenting.FunctionComment

/** One independently registered CSV record type. */
interface CsvRowMapperInterface {
	/** Stable record type. @return string */ public function type(): string;
	/** Export rows. @return array<int,array<string,string>> */ public function export_rows(): array;
	/** Import one normalized row. @param array<string,string> $row Row. @return string created or skipped */ public function import_row( array $row ): string;
}
