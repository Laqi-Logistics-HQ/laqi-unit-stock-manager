<?php
/**
 * Versioned operations CSV mapper registry.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Exchange;

defined( 'ABSPATH' ) || exit;
// phpcs:disable Squiz.Commenting.VariableComment, Squiz.Commenting.FunctionComment
// phpcs:disable Squiz.Commenting.FunctionCommentThrowTag

use InvalidArgumentException;

/** Allows record types to extend CSV exchange without editing its engine. */
final class CsvRowMapperRegistry {
	/** Mappers. @var array<string,CsvRowMapperInterface> */ private $mappers = array();
	/** Register mapper. @throws InvalidArgumentException For duplicate/invalid types. */
	public function register( CsvRowMapperInterface $mapper ): void {
		$type = sanitize_key( $mapper->type() );
		if ( '' === $type || isset( $this->mappers[ $type ] ) ) {
			throw new InvalidArgumentException( 'CSV row mapper types must be unique.' );
		} $this->mappers[ $type ] = $mapper; }
	/** One mapper. @throws InvalidArgumentException For unknown type. */
	public function get( string $type ): CsvRowMapperInterface {
		if ( ! isset( $this->mappers[ $type ] ) ) {
			throw new InvalidArgumentException( 'Unknown CSV record type.' );
		} return $this->mappers[ $type ]; }
	/** All mappers. @return array<string,CsvRowMapperInterface> */ public function all(): array {
		return $this->mappers; }
}
