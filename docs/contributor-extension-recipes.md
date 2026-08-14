# Contributor extension recipes

These recipes describe the supported composition seams in Laqi Unit Stock
Manager. Extensions should register behavior after the shared container exists;
they must not write pool balances, ledger rows, or plugin tables directly.

## Ground rules

- Target PHP 7.4 and follow the plugin's WordPress coding standards.
- Give every stored type a stable, lowercase `sanitize_key()`-compatible key.
- Keep authoritative quantities as normalized integers. Convert decimal strings
  through `UnitRegistry`; never use floating point for stock.
- Send every balance change through `StockMutationService` or the higher-level
  `StockAdjustmentService`, with a stable idempotency key and audit context.
- Put new paid implementations in the separately distributed Premium add-on.
  The in-tree `__premium_only` boundary exists only until extraction is complete.
- Add focused PHPUnit coverage and verify all three channel archives whenever a
  recipe changes packaged behavior.

The public composition points are:

```php
add_action( 'laqi_lusm_register_calculators', $callback );
add_action( 'laqi_lusm_extensions_ready', $callback );
```

Use `laqi_lusm_register_calculators` only for calculators, because the container
builds that registry lazily. `laqi_lusm_extensions_ready` receives an
`ExtensionContextInterface`, which is the supported, versioned add-on API. Check
`LAQI_LUSM_API_VERSION` before booting an add-on. The concrete `Container`,
`laqi_lusm_booted`, and `laqi_lusm_premium_ready` are transitional internals for
the current in-tree paid edition and must not be used by new extensions.

## Add a unit

For a site-managed unit, prefer the existing custom-unit UI/repository. It stores
an exact multiple of a registered reference unit and reloads it at bootstrap.
For a code-owned unit, register a `UnitDefinition` on the container registry:

```php
add_action(
	'laqi_lusm_extensions_ready',
	static function ( \LaqiUnitStockManager\Extension\ExtensionContextInterface $context ): void {
		// One metric tonne is exactly 1,000 kg in the mass base representation.
		$context->units()->register(
			new \LaqiUnitStockManager\Unit\UnitDefinition(
				'metric_tonne',
				'mass',
				1000000000000000,
				'metric'
			)
		);
	}
);
```

The factor is the number of canonical family base units represented by one new
unit. Do not introduce a new family through registration alone: family-specific
storage, UI, formatting, and compatibility rules require an architectural change.
Test exact normalization, the smallest accepted fraction, overflow rejection,
formatting, and archive presence.

## Add a movement type

Movement types supply presentation labels; they do not mutate stock themselves:

```php
add_action(
	'laqi_lusm_extensions_ready',
	static function ( \LaqiUnitStockManager\Extension\ExtensionContextInterface $context ): void {
		$context->movements()->register(
			new \LaqiUnitStockManager\Inventory\MovementType(
				'quality_sample',
				__( 'Quality-control sample', 'your-text-domain' )
			)
		);
	}
);
```

Apply it through the adjustment or mutation service. The key must remain stable
after release because immutable ledger rows store it. Tests should prove the
translated label, signed delta, source, actor, reason, and retry behavior.

## Add a consumption calculator

Implement `ConsumptionCalculatorInterface`. `calculate()` receives an immutable
mapping and sold item count and returns `pool ID => positive normalized demand`.
It must not query mutable product metadata or change inventory.

```php
final class ExampleCalculator implements
	\LaqiUnitStockManager\Consumption\ConsumptionCalculatorInterface {
	public function type(): string {
		return 'example';
	}

	public function calculate(
		\LaqiUnitStockManager\Domain\ProductMapping $mapping,
		int $quantity
	): array {
		$demand = array();
		foreach ( $mapping->components() as $component ) {
			$demand[ $component->pool_id() ] =
				$component->consumption() * $quantity;
		}
		return $demand;
	}
}

add_action(
	'laqi_lusm_register_calculators',
	static function ( $registry ): void {
		$registry->register( new ExampleCalculator() );
	}
);
```

Validate positive quantities and integer overflow before multiplication in a
production calculator. Persist components through `MappingRepository`, snapshot
the calculated demand onto order items, and test combined availability, atomic
multi-pool failure, reduction, restoration, edits, and mapping changes after sale.

## Add an admin section

Implement `ScreenSectionInterface` with a unique ID, translated title, and an
escaped renderer. Register it after boot:

```php
add_action(
	'laqi_lusm_extensions_ready',
	static function ( \LaqiUnitStockManager\Extension\ExtensionContextInterface $context ): void {
		$context->admin_sections()->register( new ExampleSection() );
	}
);
```

Give mutations a separate authenticated controller. Check
`manage_woocommerce`, verify a nonce, sanitize input, call an application
service, and redirect with a result code. Enqueue assets only on
`woocommerce_page_laqi-unit-stock-manager`; a paid section should own a paid-only
asset entry point. Test permissions, nonce failure, escaping, empty state, and
service delegation.

## Add a notification channel

Paid notification channels implement `AlertChannelInterface`: a stable `key()`,
an `enabled()` policy check, and `deliver()` returning `success` plus a safe
diagnostic message. Register the instance on the module-owned
`AlertChannelRegistry` before constructing its evaluator.

Channels receive normalized event and policy arrays. They must not mutate stock,
mark delivery successful before the remote side accepts it, or place secrets in
ledger/history messages. Test disabled configuration, success, timeout/failure,
escaping or signing, and evaluator deduplication.

## Add a CSV row mapper

Paid operations exchange types implement `CsvRowMapperInterface` and register on
`CsvRowMapperRegistry`. `type()` is the stable versioned record key;
`export_rows()` emits string-valued rows; `import_row()` validates one normalized
row and returns `created` or `skipped`.

Mappers should resolve repositories and application services injected at
construction. They must not issue ad hoc SQL or mutate balances directly. Keep
spreadsheet-formula protection at the export boundary and test headers, version
rejection, exact unit conversion, partial invalid input, and round trips.

## Add an order or ecosystem adapter

There is deliberately no generic order-adapter registry yet. WooCommerce
extensions expose different lifecycle contracts, so adapters are small classes
that register their documented hooks from `laqi_lusm_extensions_ready`. Reuse
the public extension context and the normal `OrderStockLifecycle`; do not call
the mutation service a second time for
an event core WooCommerce already emits.

An adapter test matrix should cover creation and update, classic and Store API
origins where applicable, idempotent repeated hooks, immutable snapshots,
reservation conversion/release, reduction/restoration, and absence of duplicate
container-line consumption.

## Add an allocation strategy

Allocation is currently implemented by focused batch collaborators rather than a
public strategy registry. Extend the existing transaction hooks only when the new
strategy can preserve the pool mutation transaction, deterministic lock order,
lot-level journals, and exact restoration. Do not invent or document a registry
that the composition root does not provide. A new generally selectable strategy
requires a separate design review and a stable interface before third-party use.

## Verification checklist

Run from the local WordPress development repository and plugin directory as
documented in `README.md`:

1. PHPUnit, including focused failure and idempotency tests.
2. PHPCS and PHP 7.4 syntax/compatibility checks.
3. JavaScript/CSS lint when assets change.
4. WooCommerce, Freemius, and WordPress.org builds.
5. Paid-class presence in both paid archives and absence from WordPress.org.
6. Activation/deactivation/uninstall checks when hooks, jobs, options, or tables
   are introduced.
