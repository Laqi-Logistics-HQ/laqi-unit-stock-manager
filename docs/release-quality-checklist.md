# Release accessibility and performance checklist

## 0.1.0 submission review — 2026-08-14

The release package passed its final merge-gated quality run. Review was
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

The final hardening change fixed both failures, tests WooCommerce 10.9.4 and
11.0.0 across the four PHPUnit lanes, declares WooCommerce 11.0 compatibility,
and updates Node-based actions away from the deprecated Node 20 runtime.

Merge-triggered quality run
[`31798352233`](https://github.com/Laqi-Logistics-HQ/laqi-unit-stock-manager/actions/runs/31798352233)
passed on commit `2b3b23a`: official Plugin Check, browser/axe coverage, PHP
7.4–8.5 compatibility, coding standards, all channel archives, and the four
WordPress/PHP/WooCommerce 10.9.4/11.0.0 PHPUnit lanes are green. This completes
the repository release sign-off for 0.1.0.

### Release handoff

Repository implementation and automated sign-off are complete. Publishing and
marketplace submission remain deliberate owner actions:

1. Complete and record the manual accessibility and performance checks below.
2. Set the repository variable `RELEASE_BUILDS_ENABLED=true` only when 0.1.0 is
   approved for publication. No release variables or secrets were configured at
   sign-off.
3. Publish GitHub tag/release `0.1.0`, then wait for the release workflow and its
   reused quality workflow to pass.
4. Download the workflow-built archives and verify their checksums. Use only the
   `-wordpressorg.zip` package for a WordPress.org submission and only the
   `-woocommerce.zip` package for Woo Marketplace submission.
5. Complete the canonical WordPress.org or Woo Marketplace submission checklist
   against that exact archive immediately before uploading it. Woo's vendor-side
   QIT results are an additional blocking gate.
6. Do not upload or distribute the `-freemius.zip` package until a Freemius
   product and `freemius/wordpress-sdk` dependency are configured and its deploy
   credentials and gate have been reviewed.

Publishing the GitHub release, enabling deployment variables, and submitting an
archive are intentionally outside this repository sign-off; each changes an
external release channel and requires explicit owner approval.

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
