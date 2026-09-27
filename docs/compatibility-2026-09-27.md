# Compatibility verification: 27 September 2026

Verified the working tree containing the new Get PRO link for Laqi Unit Stock Manager.
This pass verifies the changes prepared for release 1.2.6.

| PHP runtime | WordPress | WooCommerce | Result |
|---|---|---|---|
| 7.4.33 | 7.1.2 | 11.1.2 | 128 integration tests / 479 assertions; passed |
| 8.5.11 | 7.1.2 | 11.1.2 | 128 integration tests / 479 assertions; passed |

PHPUnit 9.6.35 ran with the WordPress 7.1.2 test library and separate test
databases for each PHP runtime. `WP_PLUGIN_DIR` matched the `/plugins`
bootstrap paths so the uninstall guards did not mistake the browser site's
symlinks for another installed edition. Tests retained their normal conversion of PHP
errors, warnings, notices and deprecations to exceptions. No plugin code changes
were required to pass on these versions.

The current PHPCompatibility sniffs (develop commit `4b1ac83`) also passed for
PHP 7.4 through 8.5, with warning severity 1, including the plugin's PHP tests.
Dependencies, node_modules, dist and test-results were excluded, matching the
release workflow. This static scan supplements the two runtime test results;
it does not claim runtime testing on every intermediate PHP version.

Authenticated Chromium smoke checks passed on both PHP runtimes: the Installed
Plugins row showed the correct Get PRO product URL, and the plugin's main admin
screen loaded with its expected heading and no uncaught JavaScript errors or
visible PHP errors. Order Status Workflows' React app was also checked for its
rendered ready state. External HTTP was blocked on this isolated browser site;
checkout and third-party services were outside this pass.

The pass used a separate Docker Compose project, `laqi-compat-20260927`, with
copied plugin working trees and its own MariaDB 12.3.2 database. The normal demo
site and its plugin activation state were preserved. Local harness and logs:
`github/compatibility-pass-20260927/` in the development workspace.

The WordPress `Tested up to: 7.1` and WooCommerce `WC tested up to: 11.1`
series declarations already cover these patch releases. The changelogs record
the exact versions verified here; the PHP minimum remains 7.4.

The release quality matrix now pins its current WordPress/WooCommerce pairing
to 7.1.2/11.1.2 and includes PHP 8.5 alongside the existing PHP 7.4 and 8.3 rows.
No GitHub Actions workflows were enabled or dispatched for this pass.

Release preparation also verified all three WordPress.org packages and their
release manifests. The Pro presence check uses WordPress `is_plugin_active()`;
all six active/inactive link checks passed against the local WordPress runtime
without changing plugin activation state.
