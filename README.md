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
src/Assets.php        # registers + enqueues CSS/JS, wires JS translations
assets/css/           # admin.css, frontend.css (enqueued, version-busted)
assets/js/            # admin.js, frontend.js (use wp.i18n.__ for translatable strings)
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

## Assets (CSS / JS)

Source files live in `assets/css` and `assets/js` and are enqueued by
`src/Assets.php` with unique handles and the plugin version for cache busting.
All plugin-owned globals use `laqi_lusm_`; registered handles, slugs,
HTML IDs, CSS classes, and CSS custom properties use the equivalent hyphenated
form combining `laqi` with the plugin initials. Do not shorten UI selectors just
because an element appears only on the plugin's own admin screen. Add a file, then enqueue
it there. Guard admin enqueues to this plugin's own screens (there's a
`$hook_suffix` check stub).

For a real build pipeline (Gutenberg blocks, React, JSX, SCSS), use
`@wordpress/scripts` (`package.json`): source in `assets/src`, output to
`build/`, and enqueue using the generated `build/*.asset.php` (deps + version).

## Privacy and background work

`src/Privacy.php` registers the WordPress exporter, eraser, and privacy-policy
integration points. Its initial callbacks report no data. Replace them before
release whenever the plugin stores personal data, and document any retention or
third-party sharing accurately.

Every Action Scheduler hook added by the plugin must also be added to the list in
`Plugin::on_deactivate()`. Deactivation must cancel all plugin-owned scheduled
work; uninstall must repeat that cleanup and remove plugin-owned data.

## Internationalization

The text domain is the slug `laqi-unit-stock-manager`; `Domain Path` is `/languages`.
Wrap **every** user-facing string — PHP (`__()`, `esc_html__()`) and JS
(`wp.i18n.__()`) — in that domain. Regenerate translation files per
[`languages/README.md`](languages/README.md).
