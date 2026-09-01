<?php
/**
 * Deterministic inventory records for the browser-quality suite.
 *
 * @package LaqiUnitStockManager
 */

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Inventory\StockMutationService;
use LaqiUnitStockManager\Storage\PoolRepository;

global $wpdb;

$pool = ( new PoolRepository( $wpdb ) )->create(
	'Admin quality flour',
	new Quantity( 'mass', 1000000000000 ),
	'ng',
	'kg',
	false,
	'QA-FLOUR'
);

$mutations = new StockMutationService( $wpdb );
for ( $movement = 1; $movement <= 26; $movement++ ) {
	$mutations->apply(
		$pool->id(),
		1,
		'manual_add',
		'admin-quality:' . $movement,
		array( 'reason' => 'Admin quality fixture' )
	);
}
