# Laqi Unit Stock Manager for WooCommerce

Laqi Unit Stock Manager for WooCommerce for WordPress / WooCommerce. Targets **PHP 7.4+**.

## Development

This plugin lives in the `Laqi-Logistics-HQ/plugins` checkout, which is bind-mounted
into the `wordpress_localhost` stack at `wp-content/plugins/laqi-unit-stock-manager` — so
it's served by the running site and editable in VS Code with Xdebug.

```bash
cd /path/to/wordpress_localhost

# Work inside this plugin (deps + lint with THIS plugin's phpcs.xml.dist).
WORK="docker.exe compose exec -u www-data -w /var/www/html/wp-content/plugins/laqi-unit-stock-manager php-fpm"
$WORK composer install     # PSR-4 autoload + dev tools (optional)
$WORK phpcs                # lint to WordPress standards + PHP 7.4 compat
$WORK phpcbf               # auto-fix

# Tests (run `make test-install` once first)
make test p=laqi-unit-stock-manager

# Build assets only if you adopt a wp-scripts build (blocks/React/SCSS).
make npm p=laqi-unit-stock-manager c="install"
make npm p=laqi-unit-stock-manager c="run build"
```

## Structure

```
laqi-unit-stock-manager.php   # bootstrap: headers, constants, autoload, lifecycle, init
uninstall.php         # data cleanup on delete (plugin NOT loaded here)
src/Plugin.php        # main class (PSR-4 namespace LaqiUnitStockManager); WC guard, HPOS, i18n
src/Privacy.php       # exporter, eraser, and privacy-policy integration points
src/Assets.php        # scopes Unit Stock admin styles
assets/css/           # admin.css (Unit Stock screen only, version-busted)
languages/            # .pot / .po / .mo / .json — see languages/README.md
bin/build.sh          # build distributable zip per channel (woocommerce|freemius|wordpressorg)
bin/install-wp-tests.sh # provision pinned WordPress test suites for CI
.github/workflows/    # quality.yml + gated three-channel release.yml
tests/                # PHPUnit (WordPress test suite)
phpcs.xml.dist        # WordPress standards + PHP 7.4 compatibility
composer.json         # PSR-4 autoload + dev tooling
```

## Releasing

This plugin is its own git repo. To ship a version, publish a **GitHub Release**;
`.github/workflows/release.yml` then builds and attaches the distributable zips:

- `laqi-unit-stock-manager-<version>-woocommerce.zip` — the active channel (WooCommerce.com
  handles updates/licensing; the Freemius SDK is stripped).
- `laqi-unit-stock-manager-<version>-freemius.zip` — ready for when the Freemius SDK is added.
- `laqi-unit-stock-manager-<version>-wordpressorg.zip` — complete Free edition with
  every `__premium_only` file physically removed.

The workflow is **OFF until** you set the repo variable
`RELEASE_BUILDS_ENABLED=true` (`gh variable set RELEASE_BUILDS_ENABLED --body true`).
Every pull request still runs `quality.yml`: coding standards, PHP syntax,
PHPUnit across the supported PHP/WordPress matrix, and all three channel archive
checks. It also starts a real WordPress/WooCommerce environment and runs an
authenticated Playwright + axe admin-quality gate. Customize the plugin URL and
root selectors in `tests/e2e/admin-quality.spec.js` when scaffolding a plugin.
Complete the manual screen-reader, keyboard, zoom, contrast, and performance
checks in `docs/release-quality-checklist.md` before a Marketplace upload.
Releases call that same workflow and cannot package until it passes.
Build locally with:

```bash
composer install --no-dev --optimize-autoloader
# Only when package-lock.json is committed:
npm ci
npm run build
bash bin/build.sh --channel woocommerce
bash bin/build.sh --channel wordpressorg
```

The WooCommerce build excludes the complete Composer runtime and uses the
fallback PSR-4 loader. If a lockfile marks a real asset build, packaging fails
unless its generated runtime exists.

## Assets

Styles live in `assets/css` and are enqueued by `src/Assets.php` only on the
Unit Stock admin screen, with a unique handle and the plugin version for cache
busting. The Setup tab uses WooCommerce's enhanced selector for scalable AJAX
product, variation, and inventory-pool search. Active links are reviewed and
soft-unlinked in the same tab; historical mapping components remain available
for immutable order restoration. The plugin has no frontend UI and registers no
one screen-scoped plugin script; it registers no storefront assets.

Mapping edits update the active pool and exact consumption rule without repeating
the one-time native-stock migration decision. They use optimistic mapping versions,
so a stale form cannot silently overwrite a newer administrator change.
Current product links use the shared pagination renderer in 25-row pages, so the
Setup tab can manage every active mapping without a fixed listing ceiling.

Custom units use soft retirement. The repository prevents retirement while a pool
uses the key as its base/display unit or another active custom unit references it;
retired records remain stored so a historical key is never redefined accidentally.

The Stock tab searches pool names and internal SKUs together with linked product
names, variation SKUs, and attributes. Results use repository-backed counts and
25-row pages, so the admin screen does not silently stop at a fixed catalog size.
Pool names, internal SKUs, and compatible display units can be corrected inline
without changing the normalized balance; optimistic versions protect concurrent edits.
The Activity tab uses the same shared pagination renderer to expose the complete
append-only ledger in 50-row pages. The versioned movements REST endpoint accepts
`page` and `limit` and returns matching pagination metadata alongside its items.
All plugin-owned globals use `laqi_lusm_`; registered handles, slugs,
HTML IDs, CSS classes, and CSS custom properties use the equivalent hyphenated
form combining `laqi` with the plugin initials. Do not shorten UI selectors just
because an element appears only on the plugin's own admin screen. Add a file,
then enqueue it through an equally narrow screen or feature guard.

For a real build pipeline (Gutenberg blocks, React, JSX, SCSS), use
`@wordpress/scripts` (`package.json`): source in `assets/src`, output to
`build/`, and enqueue using the generated `build/*.asset.php` (deps + version).

## Privacy and background work

`src/Privacy.php` registers the WordPress exporter, eraser, and suggested
privacy-policy content. Attributed movements export with the acting user and an
erasure request anonymizes that association while retaining the stock ledger.

Premium supply-state modules may reduce on-hand stock to an available-to-sell
quantity through `laqi_lusm_pool_available_quantity`. The filter receives the
current normalized on-hand integer and pool ID; implementations must return a
normalized integer and must not mutate stock. Order reservations, non-saleable
holds, and merchant-defined safety stock compose through this contract, while
the reusable projection service reports current and post-incoming availability.
The Free availability and mutation services remain edition-neutral.

Paid receiving creates one idempotent batch record per supplier-receipt movement,
including the supplier lot, optional expiry, receipt cost snapshot, received and
remaining normalized quantities. The batch repository exposes dated stock in
earliest-expiry-first order with undated receipts last; allocation and recall
modules build on that contract without changing the original pool ledger.
The shared mutation engine exposes `laqi_lusm_stock_movement_applying` from
inside its existing database transaction. Paid FEFO allocation journals each
batch or legacy-unbatched portion there, so a failed extension rolls back the
pool, batch balances, and movement together. Positive order movements restore
the exact consumed lots before a later stock cycle can allocate them again.
Expired active lots remain physically on hand but are excluded from storefront,
reservation, hold, and order-allocation capacity; non-order corrections may
still reduce them so later quarantine and write-off workflows are not blocked.
The Receiving tab also provides exact batch stocktakes, permanent write-offs,
and reversible quarantine/release controls. Quarantined quantities remain in
physical on-hand stock but are excluded from every availability path. Each
status transition records its quantity snapshot, actor, optional reason, and
timestamp in an append-only batch-event journal; quantity-changing operations
continue to use the authoritative stock movement and allocation journals.
The recall review derives affected orders from the immutable allocation journal,
requires explicit merchant confirmation and a reason, quarantines the remaining
lot as recalled, and never contacts customers automatically.

Every Action Scheduler hook added by the plugin must also be added to the list in
`Plugin::on_deactivate()`. Deactivation must cancel all plugin-owned scheduled
work; uninstall must repeat that cleanup and remove plugin-owned data.

## Internationalization

The text domain is the slug `laqi-unit-stock-manager`; `Domain Path` is `/languages`.
Wrap **every** user-facing string — PHP (`__()`, `esc_html__()`) and JS
(`wp.i18n.__()`) — in that domain. Regenerate translation files per
[`languages/README.md`](languages/README.md).
