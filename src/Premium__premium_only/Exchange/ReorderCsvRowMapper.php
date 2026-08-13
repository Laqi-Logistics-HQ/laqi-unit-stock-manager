<?php
/**
 * Reorder policy CSV rows.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Exchange;

defined( 'ABSPATH' ) || exit;
// phpcs:disable Squiz.Commenting.VariableComment, Squiz.Commenting.FunctionComment
// phpcs:disable Squiz.Commenting.FunctionCommentThrowTag
use InvalidArgumentException;
use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Premium\Receiving\SupplierRepository;
use LaqiUnitStockManager\Premium\Replenishment\ReorderPolicyRepository;
use LaqiUnitStockManager\Presentation\QuantityFormatter;
use LaqiUnitStockManager\Storage\PoolRepository;
use LaqiUnitStockManager\Unit\UnitRegistry;

/** Exchanges reorder policies without replacing other pool policies. */
final class ReorderCsvRowMapper implements CsvRowMapperInterface {
	/** Pools. @var PoolRepository */ private $pools;
	/** Suppliers. @var SupplierRepository */ private $suppliers;
	/** Policies. @var ReorderPolicyRepository */ private $policies;
	/** Formatter. @var QuantityFormatter */ private $formatter;
	/** Units. @var UnitRegistry */ private $units;
	/** Constructor. */ public function __construct( PoolRepository $pools, SupplierRepository $suppliers, ReorderPolicyRepository $policies, QuantityFormatter $formatter, UnitRegistry $units ) {
		$this->pools     = $pools;
		$this->suppliers = $suppliers;
		$this->policies  = $policies;
		$this->formatter = $formatter;
		$this->units     = $units; }
	/** Type. @return string */ public function type(): string {
		return 'reorder_policy'; }
	/** Rows. @return array<int,array<string,string>> */ public function export_rows(): array {
		$rows = array();
		foreach ( $this->policies->configured_ids() as $pool_id ) {
			$pool   = $this->pools->find( $pool_id );
			$policy = $this->policies->find( $pool_id );
			$pack   = null === $policy ? null : $this->suppliers->pack( $policy['preferred_pack_id'] );
			if ( null !== $pool && null !== $policy && null !== $pack ) {
				$rows[] = array(
					'supplier_name' => (string) $pack['supplier_name'],
					'pool_sku'      => $pool->internal_sku(),
					'pool_name'     => $pool->name(),
					'pack_name'     => (string) $pack['name'],
					'unit'          => $pool->display_unit(),
					'safety_stock'  => $this->formatter->decimal( new Quantity( $pool->quantity()->family(), $policy['safety_stock_base'] ), $pool->display_unit() ),
				);
			}
		} return $rows; }
	/** Import. @param array<string,string> $row Row. @return string */ public function import_row( array $row ): string {
		$supplier = $this->suppliers->supplier_by_name( $row['supplier_name'] );
		$pool     = $this->pools->find_by_identity( $row['pool_sku'], $row['pool_name'] );
		if ( null === $supplier || null === $pool || $row['unit'] !== $pool->display_unit() ) {
			throw new InvalidArgumentException( 'A reorder policy references an unknown supplier, pool, or unit.' );
		} $pack = $this->suppliers->pack_by_identity( (int) $supplier['id'], $pool->id(), $row['pack_name'] );
		if ( null === $pack ) {
			throw new InvalidArgumentException( 'A reorder policy references an unknown supplier pack.' );
		} $quantity = $this->units->normalize( $row['safety_stock'], $row['unit'] );
		$existing   = $this->policies->find( $pool->id() );
		if ( null !== $existing && $existing['preferred_pack_id'] === (int) $pack['id'] && $existing['safety_stock_base'] === $quantity->amount() ) {
			return 'skipped';
		} $this->policies->save( $pool->id(), (int) $pack['id'], $quantity->amount() );
		return 'created'; }
}
