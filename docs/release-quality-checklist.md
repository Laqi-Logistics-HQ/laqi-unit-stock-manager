# Release accessibility and performance checklist

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
