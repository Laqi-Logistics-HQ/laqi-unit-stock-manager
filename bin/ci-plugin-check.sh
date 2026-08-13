#!/usr/bin/env bash
# Run the official WordPress Plugin Check against a built package, in CI.
#
# Usage: bin/ci-plugin-check.sh <slug> <build-dir>
#
# Replaces wordpress/plugin-check-action, which cannot be told which WordPress
# to run against. Its `wp-version` input is honoured only for the literal
# 'trunk' and maps every other value, including a real version number, to
# `null`:
#
#   WP_VERSION: ${{ inputs.wp-version == 'trunk' && '"WordPress/WordPress#master"' || 'null' }}
#
# v1.1.6 through v1.1.9 and main all do this, so there is no release to upgrade
# to. `null` leaves wp-env to resolve "latest" itself, which it does as a GIT
# REF, and api.wordpress.org offers a release hours before the
# WordPress/WordPress mirror tags it. During that window every run fails with
# "couldn't find remote ref <version>", which has broken real releases here.
#
# The zip, unlike the tag, is published with the release. So core is pinned here
# to the newest released version as a zip URL, which is both current and immune
# to the tag race. Everything else mirrors what the action did: map the built
# package in, install the plugin's declared dependencies, then check.
set -euo pipefail

SLUG="${1:-}"
BUILD_DIR="${2:-}"

if [ -z "$SLUG" ] || [ -z "$BUILD_DIR" ]; then
  echo "usage: bin/ci-plugin-check.sh <slug> <build-dir>" >&2
  exit 1
fi

[ -d "$BUILD_DIR" ] || { echo "no such build dir: $BUILD_DIR" >&2; exit 1; }
[ -f "$BUILD_DIR/$SLUG.php" ] || { echo "no $SLUG.php in $BUILD_DIR" >&2; exit 1; }

PLUGIN_DIR="$(cd "$BUILD_DIR" && pwd)"

# ---------------------------------------------------------------------------
# Resolve the WordPress to test against.
# ---------------------------------------------------------------------------
WP_VERSION="$(
  curl -sf --max-time 30 https://api.wordpress.org/core/version-check/1.7/ \
    | python3 -c 'import json,sys; print(json.load(sys.stdin)["offers"][0]["version"])' 2>/dev/null || true
)"

if [ -z "$WP_VERSION" ]; then
  echo "::error::could not resolve the current WordPress version from api.wordpress.org" >&2
  exit 1
fi

WP_ZIP="https://wordpress.org/wordpress-${WP_VERSION}.zip"

# Fail here rather than inside wp-env, where the error is a git stack trace.
if ! curl -sfI --max-time 30 "$WP_ZIP" >/dev/null; then
  echo "::error::WordPress $WP_VERSION is offered by the API but $WP_ZIP is not downloadable" >&2
  exit 1
fi

echo "Plugin Check will run against WordPress $WP_VERSION"

# ---------------------------------------------------------------------------
# Environment.
# ---------------------------------------------------------------------------
# Written at the working directory, not inside the package, so nothing is added
# to the tree being checked. testsEnvironment is off because Plugin Check only
# needs the development environment, and starting both doubles the time.
SLUG="$SLUG" PLUGIN_DIR="$PLUGIN_DIR" WP_ZIP="$WP_ZIP" python3 - > .wp-env.json <<'PY'
import json, os

print(json.dumps({
    "core": os.environ["WP_ZIP"],
    "phpVersion": "7.4",
    "port": 8880,
    "testsPort": 8881,
    "testsEnvironment": False,
    "plugins": ["https://downloads.wordpress.org/plugin/plugin-check.zip"],
    "mappings": {
        "wp-content/plugins/" + os.environ["SLUG"]: os.environ["PLUGIN_DIR"],
    },
}, indent=2))
PY

cat .wp-env.json

npm -g --no-fund i @wordpress/env >/dev/null
wp-env start --update

# `Requires Plugins` dependencies have to be present or the plugin cannot boot
# and the runtime checks exercise nothing. WooCommerce is the one that matters.
#
# Read the header off the built file rather than asking WP-CLI for it. Going
# through `wp-env run` wraps the answer in wp-env's own status lines, and the
# value came back empty, so nothing was installed, activation failed with
# "requires 1 plugin ... woocommerce", and the checks still reported success.
DEPENDENCIES="$(
  grep -oiP '^\s*\*\s*Requires Plugins:\s*\K.*' "$PLUGIN_DIR/$SLUG.php" \
    | head -n1 | tr -d '\r' | tr ',' ' ' || true
)"

if [ -n "${DEPENDENCIES// /}" ]; then
  echo "Installing declared dependencies: $DEPENDENCIES"
  # shellcheck disable=SC2086 -- deliberately word-split into separate slugs.
  wp-env run cli wp plugin install --activate $DEPENDENCIES
else
  echo "Plugin declares no dependencies"
fi

wp-env run cli wp plugin activate "$SLUG"

# Verify rather than assume. `wp plugin activate` prints a warning and still
# exits 0 when a dependency is missing, so without this the whole run reports
# "No errors found" for a plugin that never loaded.
ACTIVE="$(wp-env run cli wp plugin list --status=active --field=name 2>/dev/null | tr -d '\r' || true)"

if ! grep -qx "$SLUG" <<<"$ACTIVE"; then
  echo "::error::$SLUG is not active, so the runtime checks would not exercise it" >&2
  echo "active plugins were:" >&2
  echo "$ACTIVE" >&2
  exit 1
fi

echo "$SLUG is active"

# ---------------------------------------------------------------------------
# The checks.
# ---------------------------------------------------------------------------
# Two passes, matching docs/wordpress-org-submission-checklist.md: the mandatory
# directory category, then everything. --require loads plugin-check's cli.php,
# without which WP-CLI silently runs the static checks only.
STATUS=0

run_check() {
  local label="$1"
  shift

  echo "::group::wp plugin check ($label)"
  local out
  out="$(wp-env run cli wp plugin check "$SLUG" --format=json \
    --require=./wp-content/plugins/plugin-check/cli.php "$@" 2>/dev/null || true)"
  echo "$out"
  echo "::endgroup::"

  if ! LABEL="$label" python3 -c '
import json, os, re, sys

label = os.environ["LABEL"]
raw = sys.stdin.read().strip()

# wp-env prefixes WP-CLI output with its own status lines; keep the JSON array.
#
# Unrecognised output means the check did not run: wp-env failed, the plugin
# would not activate, WP-CLI errored. Treat that as a failure. Reporting
# "0 errors" because nothing came back is how a broken gate passes silently,
# which is the exact way the plugin-check-action hid a skipped check.
#
# A clean run is the exception: WP-CLI prints its success sentinel INSTEAD of an
# empty array, even under --format=json. That is a real result, so match it
# explicitly rather than loosening the rule above and letting anything through.
match = re.search(r"\[.*\]", raw, re.S)

if match:
    try:
        items = json.loads(match.group(0))
    except json.JSONDecodeError as exc:
        print("::error::{}: could not parse Plugin Check output: {}".format(label, exc))
        print(raw[:2000])
        sys.exit(1)
elif re.search(r"Success: Checks complete\.", raw):
    items = []
else:
    print("::error::{}: Plugin Check returned no parsable result, so it did not run".format(label))
    print(raw[:2000] if raw else "(no output at all)")
    sys.exit(1)

errors = [i for i in items if str(i.get("type", "")).upper() == "ERROR"]
warnings = [i for i in items if str(i.get("type", "")).upper() == "WARNING"]

print("{}: {} error(s), {} warning(s)".format(label, len(errors), len(warnings)))

for item in errors:
    print("::error file={},line={}::[{}] {}".format(
        item.get("file", ""), item.get("line", 0),
        item.get("code", "unknown"), item.get("message", "")))

sys.exit(1 if errors else 0)
' <<<"$out"; then
    STATUS=1
  fi
}

run_check "plugin_repo (mandatory)" --categories=plugin_repo
run_check "all checks" --include-experimental

wp-env stop >/dev/null 2>&1 || true

if [ "$STATUS" -ne 0 ]; then
  echo "::error::Plugin Check reported errors against the built package" >&2
  exit 1
fi

echo "Plugin Check clean against WordPress $WP_VERSION"
