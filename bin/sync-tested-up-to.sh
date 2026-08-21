#!/usr/bin/env bash
# Keep readme.txt's "Tested up to" header in step with the current WordPress
# release.
#
# Usage:
#   bin/sync-tested-up-to.sh            # report, as key=value lines
#   bin/sync-tested-up-to.sh --apply    # rewrite readme.txt to the current release
#
# Reporting mode prints GitHub-Actions-style output lines:
#
#   current=7.0
#   latest=7.1
#   needs-bump=1
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
case "${1:-}" in
  --apply) APPLY=1 ;;
  "") ;;
  *) echo "usage: bin/sync-tested-up-to.sh [--apply]" >&2; exit 1 ;;
esac

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

README="readme.txt"
[ -f "$README" ] || { echo "no $README in $ROOT" >&2; exit 1; }

# A missing or unparsable answer must fail loudly. Reporting "no bump needed"
# because the network was down is how this quietly stops working.
RESPONSE="$(curl -sf --max-time 30 https://api.wordpress.org/core/version-check/1.7/ || true)"
[ -n "$RESPONSE" ] || { echo "could not reach api.wordpress.org" >&2; exit 1; }

LATEST="$(RESPONSE="$RESPONSE" python3 - <<'PY'
import json, os, re, sys

try:
    offers = json.loads(os.environ["RESPONSE"]).get("offers") or []
except json.JSONDecodeError:
    sys.exit("version-check API did not return JSON")

if not offers:
    sys.exit("version-check API returned no offers")

# Same reading as plugin-check Version_Utils::get_wordpress_stable_version().
version = str(offers[0].get("current") or "").split("-")[0]
match = re.match(r"^\d+\.\d", version)
if not match:
    sys.exit("could not read a version from the first offer: " + repr(version))

print(match.group(0))
PY
)"

CURRENT_RAW="$(grep -ioP '^Tested up to:\s*\K\S+' "$README" | head -n1 || true)"
[ -n "$CURRENT_RAW" ] || { echo "no 'Tested up to:' header in $README" >&2; exit 1; }

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
  printf 'current=%s\nlatest=%s\nneeds-bump=%s\n' "$CURRENT" "$LATEST" "$NEEDS_BUMP"
  exit 0
fi

if [ "$NEEDS_BUMP" != "1" ]; then
  echo "readme.txt already declares $CURRENT; nothing to apply"
  exit 0
fi

# Replace only the value, so a readme that pads its headers keeps its alignment.
LATEST="$LATEST" README="$README" python3 - <<'PY'
import io, os, re

path = os.environ["README"]
latest = os.environ["LATEST"]
text = io.open(path, encoding="utf-8").read()

updated, count = re.subn(
    r"(?im)^(Tested up to:[ \t]*)\S+",
    lambda m: m.group(1) + latest,
    text,
    count=1,
)
if count != 1:
    raise SystemExit("could not rewrite the Tested up to header")

io.open(path, "w", encoding="utf-8", newline="").write(updated)
PY

echo "readme.txt now declares Tested up to: $LATEST (was $CURRENT)"
