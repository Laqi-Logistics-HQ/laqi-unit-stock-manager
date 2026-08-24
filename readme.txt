=== Laqi Unit Stock Manager for WooCommerce ===
Contributors: laqilogistics
Tags: woocommerce, inventory, stock management, variable products, units
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
WC requires at least: 7.1
WC tested up to: 11.0

Manage one bulk stock quantity shared by simple products and variations sold in different package sizes.

== Description ==

Laqi Unit Stock Manager gives WooCommerce stores one authoritative quantity for products packaged from the same physical stock.

For example, keep 10 kg of an ingredient in one inventory pool and link 250 mg, 0.2 g, 1 g, 50 g, 500 g, and 2 kg packages to it. Enter each package in whichever unit and precision suit it, whole or decimal and 250 mg or 0.25 g alike, and the mapping converts it into the pool's unit for you. Every sale then draws that exact quantity from the shared pool, calculated with normalized integers rather than floating-point values, so fractional packages sharing a balance with kilogram ones never accumulate rounding drift.

**Shared inventory without package-level guesswork**

* Create inventory pools measured by mass, volume, length, or count.
* Link any number of simple products and individual variations to the same pool.
* Give every linked item its exact per-sale consumption quantity.
* See current stock, sellable quantity, linked packages, and mapping diagnostics together.
* Search by pool, internal SKU, product, variation, or product SKU.
* Filter the native Products list by linked, unlinked, incomplete, warning, or recipe-backed status.

**Exact units**

* Use clearly separated metric, US customary, and imperial units.
* Convert compatible units while configuring a mapping, such as grams consumed from a kilogram pool.
* Define exact store-specific units, such as one sack equal to 25 kg or one tray equal to 24 units.
* Retire unused custom units without invalidating historical stock records.

**Safe WooCommerce stock handling**

* Aggregate the complete cart before checking a shared pool, so individually valid lines cannot oversell it together.
* Validate classic checkout and the Store API used by Cart and Checkout Blocks.
* Reduce pooled stock once when WooCommerce reduces an order's stock.
* Restore the exact original quantity for cancellations, refunds, and restocks.
* Reconcile admin-created orders, quantity edits, added lines, and removed lines.
* Preserve the mapping version and pool demand used at purchase, even when the product is reconfigured later.
* Synchronize WooCommerce stock status from the limiting shared pool.

**One operational workspace**

Products > Unit Stock keeps cross-product inventory work under one menu item. Product-specific configuration stays with the product: simple products use their Unit Stock Product data panel, while each variation has Unit Stock controls inside its native variation panel.

* Stock shows current balances, linked packages, sellable quantities, diagnostics, and inline adjustments.
* Setup creates pools and custom units and provides a searchable directory of active product links.
* Activity provides a paginated append-only ledger for orders, refunds, restorations, migrations, edits, and manual adjustments.
* The WooCommerce order screen explains each pooled-stock movement and the mapping and quantity that produced it.

**Laqi Unit Stock Manager Pro**

The separately installed Pro add-on adds suppliers and purchasing packs, receiving, incoming deliveries, traceable batches, FEFO and explicit dispatch priority, holds and quarantine, recalls, losses, alerts, forecasting, reorder planning, purchase-order drafts and supplier transmission, reports, scenario planning, recipes and material costs, mobile stocktaking, integrations, and a searchable exportable ledger. Free remains the authoritative inventory engine and is fully functional without an account or licence.

Compare editions at [laqi-logistics.com](https://laqi-logistics.com/plugins/laqi-unit-stock-manager/).

= Privacy and external services =

The plugin stores inventory pools, product mappings, operational order references, and stock movements in your WordPress database. Manual adjustments record the acting WordPress user ID for accountability. The WordPress privacy exporter reports those attributed movements, and erasure anonymizes the user association while retaining the inventory ledger.

The plugin does not copy customer contact, address, or payment data into its own tables. It does not contact Laqi Logistics, require an account or licence, or send data to an external service.

== Installation ==

1. Install and activate WooCommerce.
2. Install and activate Laqi Unit Stock Manager for WooCommerce.
3. Go to Products > Unit Stock > Setup, or open the Unit Stock panel while editing a product.
4. Create an inventory pool with its stock unit and opening balance.
5. Link a simple product or an individual variation to the pool and enter the exact quantity consumed by one sold item.
6. Choose whether to disable, transfer, or keep the product's existing WooCommerce quantity management.
7. Repeat the mapping step for every package drawing from that pool.
8. Use the Stock tab to confirm linked products, sellable quantities, and current stock.

Back up the database before transferring established WooCommerce stock. The transfer option multiplies the current native item count by the configured consumption and adds that exact amount to the pool once.

== Screenshots ==

1. Review physical pools, linked packages, exact consumption rules, and sellable quantities in the Stock workspace.
2. Check Unit Stock mapping coverage and filter products from the native WooCommerce Products list.
3. Configure a variation's inventory pool and exact consumption inside its native variation panel.
4. Edit an inventory pool's name, internal SKU, and display unit without rebuilding its stock history.
5. Define exact merchant units and convert between compatible mass, volume, length, and count units.
6. Review the append-only Activity ledger for stock reductions, restorations, migrations, and manual adjustments.

== Frequently Asked Questions ==

= Can several variations consume the same stock? =

Yes. Map each variation to the same inventory pool and specify its exact consumption per sold item, in whichever unit of that pool's measurement family suits the package. A 0.25 g variation consumes 0.25 g while a 2 kg variation consumes 2 kg from the same balance.

= Can I use my own units? =

Yes. The Setup tab can define an exact custom unit from an existing unit. Custom units are available to pool and mapping forms after creation. Unused custom units can be retired, while units used by pools or other active custom definitions are protected.

= What happens to existing WooCommerce stock? =

Each mapping requires an explicit choice: disable native quantity management, transfer the native item count into the pool and then disable it, or leave native quantity management unchanged.

= Do mapping changes affect old orders? =

No. Each order item stores the exact mapping version and normalized demand used for that purchase. Refunds and restorations use that snapshot.

= Are manually created and edited orders supported? =

Yes. The plugin snapshots admin-created items before reduction and reconciles quantity changes, added lines, and deleted lines after reduction. An unsafe edit after a partial restock is blocked rather than guessing.

= Does this work with HPOS and Cart and Checkout Blocks? =

Yes. The plugin declares High-Performance Order Storage and Cart and Checkout Blocks compatibility, uses WooCommerce CRUD for orders, and validates Store API carts.

= Does the plugin allow backorders? =

The storage model supports pool-level backorder policy. The current setup screen creates pools with backorders disabled, so pooled stock cannot become negative.

= Does the free edition require an account or licence? =

No. The WordPress.org edition contains no licensing SDK, requires no account or licence, and does not contact Laqi Logistics.

= What is the difference between Free and Pro? =

Free is the complete shared-stock engine: pools, exact units, product and variation mappings, checkout validation, order reductions and restorations, stock adjustments, diagnostics, and activity history. Pro is a separate add-on for receiving, suppliers, batches, supply controls, forecasting, purchasing, alerts, reporting, recipes, costing, mobile counts, and external operations.

= What happens when I delete the plugin? =

Deleting the final installed edition removes the plugin's custom inventory tables and schema option. If another edition remains installed, shared inventory data is preserved.

== Source Code ==

This plugin ships human-readable PHP, JavaScript, and CSS without minified or generated runtime assets. No separate compilation step is required to inspect or reproduce the distributed code.

== Changelog ==

= 1.2.1 =
* Changed the Activity ledger's two free-text boxes into one search box, since the Search box already covered everything the Reason box did.
* Changed the search filter to start its own row, so every filter bar reads the same way.
* Fixed search filters cutting their placeholder text off mid-word; a search filter now fills the space its row has left over.

= 1.2.0 =
* Added an Actor column to the Activity ledger, so you can see who made each change, and a search box matching pool, movement, source, reason, or actor.
* Changed the Source column to stop showing the acting user's name. Source now answers what caused a movement and Actor answers who performed it.
* Added extension API 1.2, letting an add-on render actions beside the Activity filter bar.

= 1.1.0 =
* Added filters to the Activity ledger: inventory pool, movement, source, actor, date range, and recorded reason. Page links keep the filters, and the empty state names the ones hiding your rows.
* Added shared dataset components so Free and registered add-on tabs paginate and filter growing tables the same way.
* Added extension API 1.1: pool policies can be counted and paged by namespace, and movement reads accept categorical filters.
* Changed movement reads to run through one filtered query rather than three near-identical pairs.
* Fixed filtering the Activity ledger losing the product it was scoped to and silently widening to every pool.
* Added an extension API check for whether one pool owns a policy namespace.
* Changed how pools owning an extension policy are found, from decoding every stored policy in PHP to a keyed index. Existing policies are indexed automatically on upgrade.

= 1.0.3 =
* Declared compatibility with WordPress 7.1.
* Broadened the shared-pool example so it spans milligram to kilogram packages and decimal quantities, rather than whole grams alone.

= 1.0.2 =
* Fixed the "WooCommerce is required" and "two editions are active" notices rendering on every wp-admin page. They now appear only on the Plugins screen, where you can act on them, and are dismissible.

= 1.0.1 =
* Improved the plugin description shown on the Plugins screen.

= 1.0.0 =
* Initial WordPress.org release of the complete Free shared-stock engine.
* Added exact inventory pools shared by simple products and individual variations, with mass, volume, length, count, and custom merchant units.
* Added combined-cart availability checks, immutable order-item mapping snapshots, idempotent stock reductions and restorations, and safe reconciliation of admin order edits.
* Added product and variation mapping controls in their native WooCommerce editors, mapping coverage on the Products list, stock diagnostics, and synchronized stock status.
* Added searchable pool management, inline stock corrections, existing-stock migration choices, custom-unit conversion and retirement, and paginated product-link and activity views.
* Added order-level stock audit details, privacy export and erasure support, uninstall cleanup, versioned REST endpoints, HPOS compatibility, and Cart and Checkout Blocks support.
