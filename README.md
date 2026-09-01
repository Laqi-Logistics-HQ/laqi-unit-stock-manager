# Laqi Unit Stock Manager for WooCommerce

**One physical stock balance, shared by every package size you sell it in.**

[Install from WordPress.org](https://wordpress.org/plugins/laqi-unit-stock-manager/) ·
[Documentation](https://laqi-logistics.com/documentation/laqi-unit-stock-manager/) ·
[Pro add-on](https://laqi-logistics.com/plugins/laqi-unit-stock-manager/)

Keep 10 kg of an ingredient in one inventory pool, and link the 250 mg, 1 g, 50 g,
500 g and 2 kg packages to it. Enter each package in whatever unit suits it, whole
or decimal, and every sale draws that exact quantity from the shared pool.

Quantities are held as **whole numbers, not floats**, so milligram packages sharing a
balance with kilogram ones never accumulate rounding drift. That is the part most
inventory plugins get wrong, and it is why this one exists.

![Inventory pools](wordpress-org-assets/screenshot-1.png)

## This is the free plugin, and it is not a demo

It is the complete, authoritative inventory engine:

- **Inventory pools** measured by mass, volume, length or count, with any number of
  simple products and individual variations linked to each
- **Exact units.** Metric, US customary and imperial, kept separate. Convert while
  configuring a mapping; define store-specific units like a 25 kg sack or a 24-unit
  tray, and retire them without invalidating history
- **Safe WooCommerce stock handling.** The whole cart is aggregated before a pool is
  checked, so individually valid lines cannot oversell it together. Classic checkout
  and the Store API used by Cart & Checkout Blocks are both validated.
- **Correct reversals.** Cancellations, refunds and restocks return the exact original
  quantity, and admin order edits are reconciled. The mapping version and pool demand
  used at purchase are preserved even if the product is reconfigured later.
- **No licence, no account, no SDK.** This package contains no licensing code and
  contacts nobody.

There are no caps here to upgrade past. WordPress.org
[Guideline 5](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
forbids shipping functionality that is present but locked behind payment, so anything
paid has to be genuinely separate code, which is what the add-on below is.

## Laqi Unit Stock Manager Pro

This plugin keeps the numbers right. **Pro is the operations built around them**, a
separate plugin installed alongside it. Both stay active, and this plugin remains the
authoritative ledger; Pro never overrides it.

| | What it adds |
|---|---|
| **Receiving & traceable batches** | Suppliers and purchasing packs, scheduled incoming deliveries, supplier lots, expiry dates and material cost. FEFO allocation or explicit dispatch priority. Hold, quarantine, count, write off, transfer and recall, with an auditable event history, plus a review of affected orders and customers before a recall is confirmed. |
| **Planning & purchasing** | Demand forecasting with days remaining, projected stock-out date and confidence. Safety stock, supplier lead time, incoming stock, and whole-pack reorder suggestions saved as immutable purchase-order drafts with separate approval. |
| **Alerts** | Low-stock email and signed-webhook alerts with reminders, escalation, quiet hours and delivery history. Expiry warnings for dated stock. |
| **Recipes & costs** | Multi-component recipes from ingredients, containers, closures and labels. Weighted material cost and unit economics from priced receipts. Typed physical losses for spillage, processing loss, evaporation, damage, samples and expiry. |
| **Daily controls** | Reusable adjustment reasons, capability-based approval policies for sensitive changes, an anomaly review that never touches the free ledger, and a searchable movement ledger with spreadsheet-safe CSV export. |
| **Mobile & integrations** | Mobile stocktaking with SKU/GTIN/UPC/EAN lookup and camera scanning where the browser supports it. Versioned CSV exchange, authenticated atomic stock movements for ERP and WMS, and read-only stock and forecast fields for rule engines. |
| **Compatibility** | Product Bundles, Composite Products and subscription renewals, without double-counting container or copied order lines. |

**[See the full comparison and pricing →](https://laqi-logistics.com/plugins/laqi-unit-stock-manager/)**

Removing Pro later is a one-plugin deactivation. Pools, mappings, movements and stock
levels all stay, because this plugin owns them.


## Why

A shop selling the same physical stock in several package sizes has no single
number to trust. WooCommerce counts items, not contents, so 250 mg sachets and
2 kg tubs of one ingredient become separate quantities that drift apart the
moment either sells. Laqi Unit Stock Manager gives that stock one authoritative
balance and lets every package draw its exact share from it.

## Full feature set

- **Inventory pools** measured by mass, volume, length or count, with any number
  of simple products and individual variations linked to the same pool, each with
  its own exact per-sale consumption.
- **Exact units.** Metric, US customary and imperial, kept separate. Convert
  while configuring a mapping, such as grams consumed from a kilogram pool.
  Define store-specific units like a 25 kg sack or a 24-unit tray, and retire
  unused ones without invalidating historical records.
- **Whole-number arithmetic.** Quantities are held as integers rather than
  floating-point values, so milligram packages sharing a balance with kilogram
  ones never accumulate rounding drift. This is the part most inventory plugins
  get wrong.
- **Cart-aware checkout validation.** The whole cart is aggregated before a pool
  is checked, so individually valid lines cannot oversell it together. Classic
  checkout and the Store API behind Cart and Checkout Blocks are both validated.
- **Correct reversals.** Cancellations, refunds and restocks return the exact
  original quantity. Admin-created orders, quantity edits, added lines and
  removed lines are all reconciled, and an unsafe edit after a partial restock is
  blocked rather than guessed at.
- **Immutable purchase snapshots.** Each order item stores the mapping version
  and normalised demand used at the time, so reconfiguring a product later never
  rewrites what an old order consumed.
- **One workspace.** Stock balances, sellable quantities, linked packages,
  diagnostics and inline adjustments in one place, with search by pool, internal
  SKU, product, variation or product SKU.
- **Append-only activity ledger** covering orders, refunds, restorations,
  migrations, edits and manual adjustments, paginated, with the WooCommerce order
  screen explaining each pooled movement and the mapping that produced it.
- **Products list integration.** Filter the native list by linked, unlinked,
  incomplete, warning or recipe-backed status, without per-row queries.
- **No backorders.** Every pool is created with backorders disabled, so pooled
  stock cannot fall below zero.

## Requirements

| | |
|---|---|
| WordPress | 6.8 or newer |
| WooCommerce | 7.1 or newer |
| PHP | 7.4 or newer |

Tested up to WordPress 7.1 and WooCommerce 11.0. Compatible with High-Performance
Order Storage and Cart & Checkout Blocks.

## Installation

Install from [WordPress.org](https://wordpress.org/plugins/laqi-unit-stock-manager/),
which is the easiest route and keeps the plugin updated from your dashboard. For
a manual install, download the zip attached to the
[latest release](https://github.com/Laqi-Logistics-HQ/laqi-unit-stock-manager/releases/latest)
and upload it under **Plugins → Add New → Upload Plugin**.

Dropping this repository into `wp-content/plugins/` also works: this plugin ships
plain PHP, JavaScript and CSS with no build step.

1. Create an inventory pool under **Products → Unit Stock → Setup**, with its stock
   unit and opening balance.
2. Link a simple product or an individual variation to the pool, and enter the exact
   quantity one sold item consumes.
3. Choose whether to disable, transfer, or keep that product's existing WooCommerce
   quantity management.
4. Repeat for every package drawing from the same pool.
5. Confirm linked products, sellable quantities and current stock on the **Stock** tab.

**Back up the database before transferring established WooCommerce stock.** The
transfer option multiplies the current native item count by the configured
consumption and adds that amount to the pool once.

## Usage

### Create a pool

**Products → Unit Stock → Setup** creates an inventory pool: its measurement
family, its stock unit and its opening balance. Custom units are defined here
too, as an exact multiple of an existing unit.

### Link products to it

A simple product is linked from its **Unit Stock** panel in the Product data
box. A variable product's mappings live inside each native variation panel, with
the product-level panel showing a configured-count summary. Enter the exact
quantity one sold item consumes, in whichever unit of that pool's family suits
the package.

Each mapping requires an explicit decision about the product's existing
WooCommerce stock: disable native quantity management, transfer the current item
count into the pool and then disable it, or leave it untouched. Back up the
database before choosing transfer, since it adds to the pool once and cannot
guess a second time.

### Watch stock move

**Stock** shows current balances, sellable quantities per package, linked
products and mapping diagnostics, and takes inline adjustments with a reason.
**Activity** is the append-only ledger of every movement, and the WooCommerce
order screen explains each pooled movement against the order that caused it.

## Privacy

`src/Privacy.php` registers the WordPress exporter, eraser, and suggested
privacy-policy content. Attributed movements export with the acting user and an
erasure request anonymizes that association while retaining the stock ledger.

The plugin contacts no external service. It has no licensing SDK and sends nothing
anywhere.

## Internationalization

The text domain is the slug `laqi-unit-stock-manager`; `Domain Path` is `/languages`.
Wrap **every** user-facing string in that domain, PHP (`__()`, `esc_html__()`) and
JS (`wp.i18n.__()`) alike. Regenerate translation files per
[`languages/README.md`](languages/README.md).

## Contributing

Local setup, the file layout, the extension contracts and the release process are
in [docs/development.md](docs/development.md).

## License

GPL-2.0-or-later.
