# Contributing

This repository is the free edition of **Laqi Unit Stock Manager for
WooCommerce**, the one published in the WordPress.org plugin directory. The Pro
add-on is a separate product in its own repository, so no paid code lives here.

Everything in this repository is GPL-2.0-or-later. By opening a pull request you
agree that your contribution ships under that licence.

## Where to take it

| You have | Take it to |
|----------|-----------|
| A suspected security issue | [SECURITY.md](SECURITY.md), privately. Never a public issue |
| A question about using the plugin | The [WordPress.org support forum](https://wordpress.org/support/plugin/laqi-unit-stock-manager/) |
| A bug you can reproduce | A GitHub issue here |
| A patch | A pull request here, after reading the section below |
| A translation into your language | [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/laqi-unit-stock-manager), not a pull request |

Support questions belong in the forum rather than here, so that the answer is
public and the next merchant with the same question can find it.

## Before you write code

Open an issue first for anything larger than a small fix, and wait for a reply.
It is not a formality. A patch can be perfectly good and still not be mergeable
here:

- The plugin targets **PHP 7.4**. Merchants on old hosting are the reason it
  exists, so modern syntax is not a style preference we can trade away.
- Some requests belong in the Pro add-on rather than in this repository, and that
  is a decision about the product, not about the code.
- Anything published in the free edition stays free. We do not move a shipped
  capability behind the paywall later, so what lands here is a permanent
  commitment.
- Automated checks **do not run on pull requests**, deliberately, to keep the
  Actions budget for releases. Review is manual, and larger changes cost more of
  it.

## Local setup

Clone the repository into any WordPress install's `wp-content/plugins/` and it
runs. [docs/development.md](docs/development.md) has the full setup, the file
layout, and the extension contracts. The short version:

```bash
composer install     # PSR-4 autoload and dev tooling
composer lint        # phpcs, WordPress standards and PHP 7.4 compatibility
composer lint:fix    # phpcbf
composer test        # PHPUnit, needs the WordPress test suite installed

npm install
npm run lint:js      # eslint
npm run lint:css     # stylelint
```

Run the relevant ones before opening a pull request. Since nothing runs them for
you on a pull request, an unlinted patch simply sits there.

## Coding standards

- WordPress Coding Standards, enforced by this plugin's own `phpcs.xml.dist`.
- PHP 7.4 only. No union types, constructor promotion, `match`, enums, or the
  nullsafe operator.
- Prefix every plugin-owned global with `laqi_lusm_`, and use `laqi-lusm-` for
  handles, slugs, HTML IDs, CSS classes, and custom properties. Do not shorten it
  to the bare initials, even on the plugin's own admin screen.
- Escape on output, sanitize on input, and check a nonce and a capability on
  every write.
- Use WooCommerce CRUD and data stores rather than direct post meta.
- Any `admin_notices` callback must guard on `get_current_screen()` and render
  dismissible markup. An unguarded notice appears on every wp-admin page and the
  directory flags it, however legitimate the message is.
- Every Action Scheduler hook you add must also be cancelled in
  `Plugin::on_deactivate()` and cleaned up in `uninstall.php`.
- No new runtime Composer dependency. The published archive ships without the
  Composer runtime and uses the fallback PSR-4 loader.

## Translations

There are two separate things here, and they go to different places.

### Translating the plugin into your language

Use the plugin's GlotPress project:
<https://translate.wordpress.org/projects/wp-plugins/laqi-unit-stock-manager>

Please do not send `.po` or `.mo` files in a pull request. For a plugin hosted in
the directory, WordPress installs translations from that project into
`wp-content/languages/plugins/`, and those take precedence over anything bundled
in the plugin, so a locale file merged here would never be the one users load. It
would only drift.

The same project also carries the plugin's readme, so the directory listing can
be translated as well as the interface.

The files under `languages/` are for builds distributed outside the directory,
where GlotPress does not reach, and the maintainer keeps those current.

### Keeping strings translatable in code

This part is very much wanted in pull requests.

- Wrap every user-facing string: `__()`, `esc_html__()`, `esc_attr__()` in PHP.
- The text domain is the literal string `'laqi-unit-stock-manager'`. Never a
  variable or a constant. The extraction tooling reads the source without running
  it, so a computed domain produces a string nobody can translate.
- Do not build a sentence by concatenation. Use `printf()` with placeholders, and
  numbered placeholders such as `%1$s` and `%2$s` whenever there is more than one,
  because other languages reorder them.
- Put a translator comment directly above any string with a placeholder:

  ```php
  /* translators: %s: inventory pool name. */
  printf( esc_html__( 'Pool %s has no linked products.', 'laqi-unit-stock-manager' ), esc_html( $pool_name ) );
  ```

- Use `_n()` for plurals rather than an `if` on the count.
- Interface strings used by `assets/js/admin.js` are translated in PHP and handed
  to it in the `i18n` array of its `wp_localize_script()` call in
  `src/Assets.php`. Follow that pattern for a new one. Reaching for
  `wp.i18n.__()` in JavaScript instead means the enqueued handle also needs
  `wp_set_script_translations()` and a compiled `.json` for every locale, so
  raise that in the issue first rather than mixing both approaches in one file.
- Regenerate the template when you add or change a string, keeping the excludes,
  or local build output shows up as drift:

  ```bash
  wp i18n make-pot . languages/laqi-unit-stock-manager.pot \
    --domain=laqi-unit-stock-manager --exclude=build,node_modules,vendor
  ```

[languages/README.md](languages/README.md) has the full recipe, including the
compile steps.

## Pull requests

- Branch off `main`, one topic per pull request.
- Say what changes for a merchant using the plugin, and how you tested it.
- Include a before and after screenshot for any admin interface change.
- Do not bump the version, edit `Stable tag`, or add changelog entries. Releases
  are cut by the maintainer, and a version bump in a patch only creates a
  conflict.
