<?php
/**
 * Supplier pack CSV rows.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Exchange;

defined( 'ABSPATH' ) || exit;
// phpcs:disable Squiz.Commenting.VariableComment, Squiz.Commenting.FunctionComment
// phpcs:disable Squiz.Commenting.FunctionCommentThrowTag
use InvalidArgumentException;
use LaqiUnitStockManager\Premium\Receiving\SupplierRepository;
use LaqiUnitStockManager\Presentation\QuantityFormatter;
use LaqiUnitStockManager\Storage\PoolRepository;
use LaqiUnitStockManager\Unit\UnitRegistry;

/** Exchanges exact supplier packs through merchant-facing identities. */
final class PackCsvRowMapper implements CsvRowMapperInterface {
	/** Suppliers. @var SupplierRepository */ private $suppliers;
	/** Pools. @var PoolRepository */ private $pools;
	/** Formatter. @var QuantityFormatter */ private $formatter;
	/** Units. @var UnitRegistry */ private $units;
	/** Constructor. */ public function __construct( SupplierRepository $suppliers, PoolRepository $pools, QuantityFormatter $formatter, UnitRegistry $units ) {
		$this->suppliers = $suppliers;
		$this->pools     = $pools;
		$this->formatter = $formatter;
		$this->units     = $units; }
	/** Type. @return string */ public function type(): string {
		return 'supplier_pack'; }
	/** Rows. @return array<int,array<string,string>> */ public function export_rows(): array {
		$rows = array();
		foreach ( $this->suppliers->packs() as $pack ) {
			$pool = $this->pools->find( (int) $pack['pool_id'] );
			if ( null !== $pool ) {
				$rows[] = array(
					'supplier_name' => (string) $pack['supplier_name'],
					'pool_sku'      => $pool->internal_sku(),
					'pool_name'     => $pool->name(),
					'pack_name'     => (string) $pack['pack_name'],
					'quantity'      => $this->formatter->decimal( new \LaqiUnitStockManager\Domain\Quantity( $pool->quantity()->family(), (int) $pack['quantity_base'] ), $pool->display_unit() ),
					'unit'          => $pool->display_unit(),
				);
			}
		} return $rows; }
	/** Import. @param array<string,string> $row Row. @return string */ public function import_row( array $row ): string {
		$supplier = $this->suppliers->supplier_by_name( $row['supplier_name'] );
		$pool     = $this->pools->find_by_identity( $row['pool_sku'], $row['pool_name'] );
		if ( null === $supplier || null === $pool || $row['unit'] !== $pool->display_unit() ) {
			throw new InvalidArgumentException( 'A supplier pack references an unknown supplier, pool, or unit.' );
		} if ( null !== $this->suppliers->pack_by_identity( (int) $supplier['id'], $pool->id(), $row['pack_name'] ) ) {
			return 'skipped';
		} $quantity = $this->units->normalize( $row['quantity'], $row['unit'] );
		$this->suppliers->create_pack( (int) $supplier['id'], $pool->id(), $row['pack_name'], $quantity->amount() );
		return 'created'; }
}
