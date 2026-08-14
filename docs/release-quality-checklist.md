# Release accessibility and performance checklist

## 0.1.0 submission review — 2026-08-14

The release package is ready for its final merge-gated quality run. Review was
performed against the current official WordPress.org plugin guidelines and Woo
Marketplace submission requirements. The source and generated channel boundaries,
licensing, dependency declaration, update ownership, readme metadata, HPOS and
Cart/Checkout Blocks declarations, privacy handling, uninstall behavior, and
single-purpose WooCommerce admin placement are consistent with those requirements.

Hosted run `31796518257` established the pre-fix baseline on WordPress 6.9.5 and
7.0.2 with PHP 7.4 and 8.3:

- official WordPress Plugin Check passed against the generated Free package;
- all three channel-archive checks passed;
- coding standards, syntax, and all four PHPUnit lanes passed;
- the modern PHPCompatibility scan found only missing explicit CSV escape
  arguments; and
- browser login was blocked because Pro labels triggered just-in-time translation
  loading before `init`.

The final hardening change fixes both failures, tests WooCommerce 10.9.4 and
11.0.0 across the four PHPUnit lanes, declares WooCommerce 11.0 compatibility,
and updates Node-based actions away from the deprecated Node 20 runtime. The
merge-triggered `quality` run is the acceptance gate: do not submit either archive
unless every job passes on the merge commit.

Woo's submission-only QIT API, E2E, activation, security, PHPCompatibility,
malware, and validation checks still run in the vendor submission flow. Passing
this repository gate does not replace those checks; a QIT failure blocks the
Marketplace upload until addressed.

CI provides a baseline, not a complete accessibility or performance
certification. Before each Marketplace upload, record evidence for:

- automated authenticated browser rendering and axe results;
- keyboard-only operation, logical focus order, visible focus, modal focus
  trapping, and focus restoration;
- a manual screen-reader pass through every critical flow;
- 200% and 400% zoom without hidden controls, clipped content, or loss of
  functionality;
- contrast and non-color status cues;
- reduced-motion behavior;
- page-load time, database-query count, peak memory, background-work cost, and
  plugin asset sizes on a representative store; and
- no plugin assets, queries, cron work, or other side effects after deactivation.

Document the WordPress, WooCommerce, PHP, browser, assistive-technology, HPOS,
theme, and extension versions used. Treat regressions from the prior recorded
baseline as release blockers until explained.
