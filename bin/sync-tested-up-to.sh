#!/usr/bin/env bash
# Keep the "Tested up to" headers in step with the current WordPress and
# WooCommerce releases.
#
# Usage:
#   bin/sync-tested-up-to.sh                            # WordPress, report
#   bin/sync-tested-up-to.sh --apply                    # WordPress, rewrite
#   bin/sync-tested-up-to.sh --component woocommerce    # WooCommerce, report
#   bin/sync-tested-up-to.sh --component woocommerce --apply
#
# Reporting mode prints GitHub-Actions-style output lines:
#
#   current=7.0
#   latest=7.1
#   latest-full=7.1
#   needs-bump=1
#
# WHY WOOCOMMERCE IS HERE TOO
#
# It was not, and the header drifted: the readme declared "WC tested up to:
# 10.9" while WooCommerce had reached 11.0.1. That header was not a lie - the
# phpunit matrix pins WooCommerce 10.9.0, so 10.9 was exactly what had been
# tested - but nothing ever moved the pin, so the tested version and the claim
# aged together and silently. WooCommerce shows merchants "has not been tested
# with your version" on anything newer, which on a new listing is the first
# thing a shopper of plugins sees.
#
# So the pin and the two headers move together, in one commit, and only after
# the suite has run against that WooCommerce. Three files carry it:
#
#   readme.txt                      WC tested up to:
#   <slug>.php                      WC tested up to:
#   .github/workflows/quality.yml   the pinned woocommerce.<version>.zip
#
# The headers take major.minor, which is what WooCommerce compares; the pin
# takes the full version, because that is what the download URL needs.
#
# WHY THIS EXISTS, AND WHY IT DOES NOT JUST REWRITE THE HEADER AT PACKAGE TIME
#
# Plugin Check errors on ANY lag: Plugin_Readme_Check compares the readme value
# against the current release with version_compare( ..., "<" ), so the morning
# WordPress ships a major, every release build starts failing on a header. That
# is a genuine nuisance - it fails at release time, which is the worst moment.
#
# The tempting fix is to stamp the current version into the zip during the
# build. Do not. "Tested up to" is a claim that the plugin was RUN against that
# version. Stamping whatever shipped this morning asserts something no test ever
# checked, hides a real incompatibility behind a green header, and leaves the
# archive saying something git does not - which matters here, because the
# WordPress.org SVN flow publishes trunk/readme.txt as the file users read.
#
# So this script only reports and rewrites. The workflow that calls it runs the
# test suite against the new WordPress FIRST and opens a pull request only when
# that passes, so the claim stays backed by a test run a human can look at.
#
# The version is resolved exactly the way Plugin Check resolves it - the first
# offer from the version-check API, suffix stripped, truncated to major.minor
# (see plugin-check includes/Traits/Version_Utils.php). Matching it matters: any
# other reading of "current" would let this script report agreement while the
# check that gates the release still fails.
set -euo pipefail

APPLY=0
COMPONENT="wordpress"

while [ $# -gt 0 ]; do
  case "$1" in
    --apply) APPLY=1; shift ;;
    --component) COMPONENT="${2:-}"; shift 2 ;;
    *) echo "usage: bin/sync-tested-up-to.sh [--component wordpress|woocommerce] [--apply]" >&2; exit 1 ;;
  esac
done

case "$COMPONENT" in
  wordpress|woocommerce) ;;
  *) echo "unknown component: $COMPONENT" >&2; exit 1 ;;
esac

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

README="readme.txt"
[ -f "$README" ] || { echo "no $README in $ROOT" >&2; exit 1; }

# A missing or unparsable answer must fail loudly. Reporting "no bump needed"
# because the network was down is how this quietly stops working.
if [ "$COMPONENT" = "woocommerce" ]; then
  API="https://api.wordpress.org/plugins/info/1.0/woocommerce.json"
  HEADER="WC tested up to"
else
  API="https://api.wordpress.org/core/version-check/1.7/"
  HEADER="Tested up to"
fi

# To a file rather than a variable: WooCommerce's plugin-info payload carries
# its whole changelog and runs to megabytes, which overflows the environment
# and fails with "Argument list too long" rather than anything self-explanatory.
RESPONSE_FILE="$(mktemp)"
trap 'rm -f "$RESPONSE_FILE"' EXIT

curl -sf --max-time 30 "$API" -o "$RESPONSE_FILE" || true
[ -s "$RESPONSE_FILE" ] || { echo "could not reach $API" >&2; exit 1; }

# Two values come back: the full version, which the WooCommerce pin needs for
# its download URL, and major.minor, which is what both headers carry and what
# Plugin Check and WooCommerce each compare against.
RESOLVED="$(RESPONSE_FILE="$RESPONSE_FILE" COMPONENT="$COMPONENT" python3 - <<'PY'
import io, json, os, re, sys

component = os.environ["COMPONENT"]

try:
    with io.open(os.environ["RESPONSE_FILE"], encoding="utf-8") as handle:
        payload = json.load(handle)
except ValueError:
    sys.exit("the version API did not return JSON")

if component == "woocommerce":
    full = str(payload.get("version") or "")
    if not full:
        sys.exit("the plugins API returned no version for woocommerce")
else:
    offers = payload.get("offers") or []
    if not offers:
        sys.exit("version-check API returned no offers")
    # Same reading as plugin-check Version_Utils::get_wordpress_stable_version().
    full = str(offers[0].get("current") or "").split("-")[0]

match = re.match(r"^\d+\.\d+", full)
if not match:
    sys.exit("could not read a version from: " + repr(full))

print(match.group(0))
print(full)
PY
)"

LATEST="$(printf '%s\n' "$RESOLVED" | sed -n 1p)"
LATEST_FULL="$(printf '%s\n' "$RESOLVED" | sed -n 2p)"

CURRENT_RAW="$(grep -ioP "^${HEADER}:\s*\K\S+" "$README" | head -n1 || true)"
[ -n "$CURRENT_RAW" ] || { echo "no '${HEADER}:' header in $README" >&2; exit 1; }

# Truncate the declared value the same way, so 7.0.2 and 7.0 compare alike.
CURRENT="$(CURRENT_RAW="$CURRENT_RAW" python3 - <<'PY'
import os, re, sys

match = re.match(r"^\d+\.\d", os.environ["CURRENT_RAW"])
if not match:
    sys.exit("could not read a version from the Tested up to header")

print(match.group(0))
PY
)"

NEEDS_BUMP="$(CURRENT="$CURRENT" LATEST="$LATEST" python3 - <<'PY'
import os

def key(value):
    return [int(part) for part in value.split(".")]

print("1" if key(os.environ["CURRENT"]) < key(os.environ["LATEST"]) else "0")
PY
)"

if [ "$APPLY" -eq 0 ]; then
  printf 'current=%s\nlatest=%s\nlatest-full=%s\nneeds-bump=%s\n' \
    "$CURRENT" "$LATEST" "$LATEST_FULL" "$NEEDS_BUMP"
  exit 0
fi

if [ "$NEEDS_BUMP" != "1" ]; then
  echo "readme.txt already declares $CURRENT; nothing to apply"
  exit 0
fi

# Replace only the value, so a file that pads its headers keeps its alignment.
# For WooCommerce this is three files rather than one: the claim lives in the
# readme and the plugin header, and the version it is a claim ABOUT is the pin
# in the phpunit matrix. Moving any of them alone is how the last drift began.
LATEST="$LATEST" LATEST_FULL="$LATEST_FULL" README="$README" \
HEADER="$HEADER" COMPONENT="$COMPONENT" python3 - <<'PY'
import io, os, re

latest = os.environ["LATEST"]
latest_full = os.environ["LATEST_FULL"]
header = os.environ["HEADER"]
component = os.environ["COMPONENT"]

def rewrite(path, pattern, replacement, required=True):
    text = io.open(path, encoding="utf-8").read()
    updated, count = re.subn(pattern, replacement, text, count=1)
    if count != 1:
        if not required:
            return False
        raise SystemExit("could not rewrite %s in %s" % (header, path))
    io.open(path, "w", encoding="utf-8", newline="").write(updated)
    return True

# The readme carries the header on its own line.
rewrite(
    os.environ["README"],
    r"(?im)^(" + re.escape(header) + r":[ \t]*)\S+",
    lambda m: m.group(1) + latest,
)

if component == "woocommerce":
    # The plugin header, where the same claim sits behind a docblock asterisk.
    plugin_file = next(
        name for name in sorted(os.listdir("."))
        if name.endswith(".php") and "Plugin Name:" in io.open(name, encoding="utf-8").read(4000)
    )
    rewrite(
        plugin_file,
        r"(?im)^([ \t]*\*[ \t]*" + re.escape(header) + r":[ \t]*)\S+",
        lambda m: m.group(1) + latest,
    )

    # And the version the suite actually runs against. A header raised without
    # this would be the claim moving while the test behind it stayed put.
    #
    # Three shapes are in use across these repos and all of them are legitimate:
    #
    #   woocommerce.10.9.0.zip              a literal download URL
    #   woocommerce: "11.0.0"               a phpunit matrix leg
    #   WOOCOMMERCE_VERSION: "10.9.4"       an env value the URL interpolates
    #
    # Only the HIGHEST version found is moved. A matrix that deliberately keeps
    # an older WooCommerce leg for regression cover must keep it; raising every
    # version it mentions would quietly drop that coverage while looking like a
    # version bump.
    workflow = ".github/workflows/quality.yml"
    text = io.open(workflow, encoding="utf-8").read()

    pins = [
        (r"woocommerce\.(\d+\.\d+\.\d+)\.zip", "woocommerce.%s.zip"),
        (r"woocommerce:\s*\"(\d+\.\d+\.\d+)\"", 'woocommerce: "%s"'),
        (r"WOOCOMMERCE_VERSION:\s*\"(\d+\.\d+\.\d+)\"", 'WOOCOMMERCE_VERSION: "%s"'),
    ]

    def as_key(value):
        return [int(part) for part in value.split(".")]

    moved = False
    for pattern, template in pins:
        found = re.findall(pattern, text)
        if not found:
            continue
        highest = max(found, key=as_key)
        # EVERY occurrence of the highest version, not the first. A matrix
        # usually pairs each PHP version with the same WooCommerce, so moving
        # one leg leaves the others behind and the run silently stops testing
        # one combination it claims to cover.
        text = re.sub(
            pattern.replace(r"(\d+\.\d+\.\d+)", re.escape(highest)),
            (template % latest_full).replace("\\", "\\\\"),
            text,
        )
        moved = True

    if not moved:
        raise SystemExit(
            "no WooCommerce version pin found in %s - the header would be a claim "
            "with no test behind it" % workflow
        )

    io.open(workflow, "w", encoding="utf-8", newline="").write(text)
PY

if [ "$COMPONENT" = "woocommerce" ]; then
  echo "readme.txt and the plugin header now declare $HEADER: $LATEST (was $CURRENT); quality.yml pins woocommerce.$LATEST_FULL.zip"
else
  echo "readme.txt now declares $HEADER: $LATEST (was $CURRENT)"
fi
