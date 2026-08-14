<?php
/** Premium multi-component recipe tests. @package LaqiUnitStockManager */

use LaqiUnitStockManager\Availability\AvailabilityService;
use LaqiUnitStockManager\Container;
use LaqiUnitStockManager\Domain\MappingComponent;
use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Inventory\InsufficientStockException;
use LaqiUnitStockManager\Storage\Schema;

/** Verifies limiting-component availability and atomic recipe movements. */
class Test_Multi_Component_Recipes extends WP_UnitTestCase {
	/** @var Container */ private $container;
	/** @var int[] */ private $pool_ids = array();
	/** @var int */ private $product_id;
	/** @var string */ private $event_namespace;

	/** Create ingredient, jar, lid, and label pools plus one recipe. */
	public function set_up(): void {
		parent::set_up();
		$this->container       = new Container();
		$this->event_namespace = wp_generate_uuid4();
		$this->product_id      = self::factory()->post->create( array( 'post_type' => 'product' ) );
		$pools                 = $this->container->pool_repository();
		$ingredient            = $pools->create( 'Recipe ingredient ' . $this->event_namespace, new Quantity( 'mass', 1000 ), 'ng', 'g' );
		$jar                   = $pools->create( 'Recipe jars ' . $this->event_namespace, new Quantity( 'count', 10 ), 'each', 'each' );
		$lid                   = $pools->create( 'Recipe lids ' . $this->event_namespace, new Quantity( 'count', 8 ), 'each', 'each' );
		$label                 = $pools->create( 'Recipe labels ' . $this->event_namespace, new Quantity( 'count', 20 ), 'each', 'each' );
		$this->pool_ids        = array( $ingredient->id(), $jar->id(), $lid->id(), $label->id() );
		$this->container->mapping_repository()->save_components(
			$this->product_id,
			0,
			'recipe',
			array(
				new MappingComponent( $ingredient->id(), 250, 'contents' ),
				new MappingComponent( $jar->id(), 1, 'container' ),
				new MappingComponent( $lid->id(), 1, 'closure' ),
				new MappingComponent( $label->id(), 1, 'label' ),
			)
		);
	}

	/** Remove durable rows committed by repository transactions. */
	public function tear_down(): void {
		global $wpdb;
		$mapping_ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . Schema::table( 'mappings' ) . ' WHERE product_id = %d', $this->product_id ) );
		foreach ( $mapping_ids as $mapping_id ) {
			$wpdb->delete( Schema::table( 'mapping_components' ), array( 'mapping_id' => $mapping_id ), array( '%d' ) );
		}
		$wpdb->delete( Schema::table( 'mappings' ), array( 'product_id' => $this->product_id ), array( '%d' ) );
		foreach ( $this->pool_ids as $pool_id ) {
			$wpdb->delete( Schema::table( 'movements' ), array( 'pool_id' => $pool_id ), array( '%d' ) );
			$wpdb->delete( Schema::table( 'pools' ), array( 'id' => $pool_id ), array( '%d' ) );
		}
		parent::tear_down();
	}

	/** The smallest component quotient controls the sellable product count. */
	public function test_saleable_quantity_uses_limiting_component(): void {
		$this->assertSame( 4, $this->availability()->saleable_quantity( $this->product_id ) );
	}

	/** Backorderable ingredients do not hide a finite packaging limit. */
	public function test_saleable_quantity_skips_only_backorderable_components(): void {
		global $wpdb;
		$wpdb->update( Schema::table( 'pools' ), array( 'allow_backorders' => 1 ), array( 'id' => $this->pool_ids[0] ), array( '%d' ), array( '%d' ) );
		$this->assertSame( 8, $this->availability()->saleable_quantity( $this->product_id ) );
	}

	/** Every component demand is snapshottable as one normalized pool map. */
	public function test_calculator_returns_exact_recipe_demand(): void {
		$mapping = $this->container->mapping_repository()->find_for_product( $this->product_id );
		$demand  = $this->container->calculator_registry()->get( 'recipe' )->calculate( $mapping, 3 );
		$this->assertSame( array( 750, 3, 3, 3 ), array_values( $demand ) );
	}

	/** A shortage in one component rolls every earlier pool mutation back. */
	public function test_recipe_decrement_is_atomic_across_all_pools(): void {
		$demand   = $this->container->calculator_registry()->get( 'recipe' )->calculate( $this->container->mapping_repository()->find_for_product( $this->product_id ), 5 );
		$commands = array();
		foreach ( $demand as $pool_id => $amount ) {
			$commands[] = array( 'pool_id' => $pool_id, 'delta' => -$amount, 'type' => 'order_reduction', 'idempotency_key' => 'recipe:' . $this->event_namespace . ':' . $pool_id );
		}
		try {
			$this->container->stock_mutation_service()->apply_batch( $commands );
			$this->fail( 'Expected the ingredient shortage to reject the recipe.' );
		} catch ( InsufficientStockException $error ) {
			$this->assertStringContainsString( 'enough available stock', $error->getMessage() );
		}
		$this->assertSame( array( 1000, 10, 8, 20 ), $this->balances() );
	}

	/** Exact decrement and restoration affect all pools and remain idempotent. */
	public function test_recipe_decrement_and_restore_exact_snapshot(): void {
		$demand = $this->container->calculator_registry()->get( 'recipe' )->calculate( $this->container->mapping_repository()->find_for_product( $this->product_id ), 2 );
		$this->apply_demand( $demand, -1, 'reduce' );
		$this->assertSame( array( 500, 8, 6, 18 ), $this->balances() );
		$this->apply_demand( $demand, 1, 'restore' );
		$this->apply_demand( $demand, 1, 'restore' );
		$this->assertSame( array( 1000, 10, 8, 20 ), $this->balances() );
	}

	/** Shared services receive the one paid-extended calculator registry. */
	public function test_container_memoizes_paid_calculator_registry(): void {
		$this->assertSame( $this->container->calculator_registry(), $this->container->calculator_registry() );
		$this->assertSame( 'recipe', $this->container->calculator_registry()->get( 'recipe' )->type() );
	}

	/** @return AvailabilityService */
	private function availability(): AvailabilityService {
		return $this->container->availability_service();
	}

	/** @return int[] */
	private function balances(): array {
		return array_map(
			function ( int $pool_id ): int {
				return $this->container->pool_repository()->find( $pool_id )->quantity()->amount();
			},
			$this->pool_ids
		);
	}

	/** @param array<int,int> $demand Demand. @param int $direction Direction. @param string $event Event. */
	private function apply_demand( array $demand, int $direction, string $event ): void {
		$commands = array();
		foreach ( $demand as $pool_id => $amount ) {
			$commands[] = array( 'pool_id' => $pool_id, 'delta' => $direction * $amount, 'type' => $direction < 0 ? 'order_reduction' : 'order_restore', 'idempotency_key' => 'recipe:' . $this->event_namespace . ':' . $event . ':' . $pool_id );
		}
		$this->container->stock_mutation_service()->apply_batch( $commands );
	}
}
