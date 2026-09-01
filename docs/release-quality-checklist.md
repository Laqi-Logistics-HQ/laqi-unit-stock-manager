# Release accessibility and performance checklist

## 1.0.0 pre-release review, refreshed 2026-08-17

The 2026-08-14 automated sign-off below is historical evidence, not the final
release gate. Product-editor mappings, order stock audits, length units, the
filtered unit picker, and the conversion calculator merged after that run.
Run the complete current quality workflow and repeat the manual checks before
publishing 1.0.0.

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
WordPress/PHP/WooCommerce 10.9.4/11.0.0 PHPUnit lanes are green. This completed
the repository sign-off for the 2026-08-14 candidate. It does not cover later
changes now included in the 1.0.0 candidate.

### Release handoff

Release documentation and implementation are prepared. Current automated and
manual sign-off, publication, and directory submission remain deliberate owner
actions:

1. Complete and record the manual accessibility and performance checks below.
2. Set the repository variable `RELEASE_BUILDS_ENABLED=true` only when 1.0.0 is
   approved for publication. No release variables or secrets were configured at
   sign-off.
3. Publish GitHub tag/release `1.0.0`, then wait for the release workflow and its
   reused quality workflow to pass.
4. Download the workflow-built `-wordpressorg.zip` and verify its checksum.
5. Repeat the canonical WordPress.org submission checklist and both official
   Plugin Check passes against the workflow-built archive immediately before
   uploading it. The local exact-slug 1.0.0 archive passed both checks without
   errors or warnings on 2026-08-17.
6. Review the banner, icon, and six numbered files in `wordpress-org-assets/`
   against the screenshot captions before the first SVN publication.

Publishing the GitHub release, enabling deployment variables, and submitting an
archive are intentionally outside this repository sign-off; each changes an
external release channel and requires explicit owner approval.

CI provides a baseline, not a complete accessibility or performance
certification. Before publication, record evidence for:

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
