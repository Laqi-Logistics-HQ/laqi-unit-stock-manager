=== Laqi Unit Stock Manager for WooCommerce ===
Contributors: laqilogistics
Tags: woocommerce, inventory, stock management, variable products, units
Requires at least: 6.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
WC requires at least: 7.1
WC tested up to: 11.0

Manage one bulk stock quantity shared by simple products and variations sold in different package sizes.

== Description ==

Laqi Unit Stock Manager gives WooCommerce stores one authoritative quantity for products packaged from the same physical stock.

For example, keep 10 kg of an ingredient in one inventory pool and link 0.1 g, 0.25 g, 1 g, 2 g, 5 g, and 10 g variations to it. Every sale consumes the exact package quantity from that shared pool. The plugin calculates with normalized integers rather than floating-point values, so small decimal packages do not accumulate rounding drift.

= One stock screen =

WooCommerce > Unit Stock contains all plugin workflows as tabs under one menu item:

* Stock: search pools by pool name, internal SKU, linked product name, product or variation SKU, or variation attribute. See current balance, linked packages, per-item consumption, saleable quantity, diagnostics, and inline adjustments.
* Setup: create pools, link simple products or individual variations, edit or unlink active consumption rules, decide what to do with existing WooCommerce stock, and define custom merchant units.
* Activity: browse the complete append-only correctness ledger created by orders, refunds, restorations, migrations, edits, and manual adjustments.

= Shared package availability =

The plugin aggregates the whole cart before checking availability. Two lines that are individually valid are rejected when their combined demand exceeds the same pool. This validation works with classic Cart and Checkout and the WooCommerce Store API used by Cart and Checkout Blocks.

Order items retain the exact pool demand and mapping version used at purchase. Later mapping changes therefore cannot alter refunds or stock restoration for an existing order. Reductions, restorations, refunds, admin-created orders, quantity edits, added lines, and removed lines use idempotent stock movements.

= Units and custom quantities =

Built-in families include mass, volume, and count. Common metric, US customary, and imperial units are explicitly distinguished. You can also define exact store-specific units, such as one sack equaling 25 kg, one drum equaling 200 l, or one tray equaling 24 units.

= Privacy and external services =

The plugin stores inventory pools, product mappings, operational order references, and stock movements in your WordPress database. Manual adjustments record the acting WordPress user ID for accountability. The WordPress privacy exporter reports those attributed movements, and erasure anonymizes the user association while retaining the inventory ledger.

The plugin does not copy customer contact, address, or payment data into its own tables. It does not contact Laqi Logistics, require an account or licence, or send data to an external service.

== Installation ==

1. Install and activate WooCommerce.
2. Install and activate Laqi Unit Stock Manager for WooCommerce.
3. Go to WooCommerce > Unit Stock > Setup.
4. Create an inventory pool with its stock unit and opening balance.
5. Link a simple product or an individual variation to the pool and enter the exact quantity consumed by one sold item.
6. Choose whether to disable, transfer, or keep the product's existing WooCommerce quantity management.
7. Repeat the mapping step for every package drawing from that pool.
8. Use the Stock tab to confirm linked products, saleable quantities, and current stock.

Back up the database before transferring established WooCommerce stock. The transfer option multiplies the current native item count by the configured consumption and adds that exact amount to the pool once.

== Frequently Asked Questions ==

= Can several variations consume the same stock? =

Yes. Map each variation to the same inventory pool and specify its exact consumption per sold item. A 0.25 g variation consumes 0.25 g while a 2 g variation consumes 2 g from the same balance.

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

= What happens when I delete the plugin? =

Deleting the final installed edition removes the plugin's custom inventory tables and schema option. If another edition remains installed, shared inventory data is preserved.

== Changelog ==

= 0.1.0 =
* Initial Free release with exact shared inventory pools, variable-package mappings, cart validation, order lifecycle synchronization, custom units, stock adjustments, activity records, REST endpoints, and Free/paid artifact separation.
