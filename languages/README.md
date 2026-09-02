# Translations

This directory holds the plugin's translation files. The plugin's text domain
is its slug (`laqi-unit-stock-manager`) and `Domain Path` points here.

| File | What it is | Tracked? |
|------|-----------|----------|
| `laqi-unit-stock-manager.pot` | Free/shared translatable strings (generated) | yes |
| `laqi-unit-stock-manager-<locale>.po` | A translator's working file for one locale | yes |
| `laqi-unit-stock-manager-<locale>.mo` | Compiled PHP translations (loaded at runtime) | yes |
| `laqi-unit-stock-manager-<locale>-<handle>.json` | Compiled JS translations for script `<handle>` | yes |

## Regenerate (run from the running stack)

These use WP-CLI, which lives in the `php-fpm` container. Run from the plugin
directory so paths stay relative:

```bash
cd /path/to/wordpress_localhost
WORK="docker.exe compose exec -u www-data -w /var/www/html/wp-content/plugins/laqi-unit-stock-manager php-fpm"

# 1. Extract every translatable string (PHP + JS) into the .pot template.
$WORK wp i18n make-pot . languages/laqi-unit-stock-manager.pot --domain=laqi-unit-stock-manager --exclude=build,node_modules,vendor

# 2. After translating .po files, compile them:
$WORK wp i18n make-mo   languages                 # .po -> .mo  (PHP strings)
$WORK wp i18n make-json languages --no-purge      # .po -> .json (JS strings)
```

The plugin's own JavaScript carries no translatable strings today.
`assets/js/admin.js` receives its interface text already translated, in the
`i18n` array of the `wp_localize_script()` call in `src/Assets.php`, so those
strings are extracted from the PHP like any other.

The `make-json` step above therefore produces nothing yet. It matters the moment
any script calls `wp.i18n.__()` directly: that script's registered handle then
also needs `wp_set_script_translations( $handle, 'laqi-unit-stock-manager',
LAQI_LUSM_PATH . 'languages' )` after it is enqueued, or the compiled `.json`
never loads.
