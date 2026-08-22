#!/usr/bin/env bash
#
# Emit this plugin's release manifest as JSON, for publishing to R2.
#
#   bin/build-release-manifest.sh > manifest.json
#
# Everything here is read from the plugin's own files, because every field was
# previously typed a second time into the website and every one of them had
# drifted: the site advertised a version two releases behind, offered a free
# download from the release before that, and claimed "tested up to 7.0.2" when
# the plugin only ever declared 7.0.
#
# The website reads this instead of keeping its own copy, so there is one place
# to change and no way for the two to disagree.
set -euo pipefail

cd "$(dirname "$0")/.."

# Derive the slug from the plugin file, not from the directory name. A checkout
# does not have to be named after the plugin — a scaffold, a CI workspace or a
# reviewer's clone often is not — and reading `basename $PWD` silently looks for
# a file that is not there.
MAIN="$(grep -lE '^\s*\*\s*Plugin Name:' ./*.php 2>/dev/null | head -n1)"
[ -n "$MAIN" ] || { echo "no plugin file with a Plugin Name header here" >&2; exit 1; }
MAIN="${MAIN#./}"
SLUG="${MAIN%.php}"
README="readme.txt"

# readme.txt is the source for the WordPress.org-facing fields; the plugin
# header carries the version WordPress itself installs.
header() {
	grep -oP "^\s*(\*\s*)?$1:\s*\K.*" "$README" 2>/dev/null | head -n1 | tr -d '\r' | xargs || true
}

VERSION="$(grep -oP '^\s*\*\s*Version:\s*\K\S+' "$MAIN" | head -n1)"
STABLE="$(header 'Stable tag')"

# A mismatch here means a release would install a different version from the one
# the directory advertises. Refuse rather than publish the disagreement.
if [ -n "$STABLE" ] && [ "$STABLE" != "$VERSION" ]; then
	echo "plugin header says $VERSION but readme Stable tag says $STABLE" >&2
	exit 1
fi

# Declared on before_woocommerce_init; grep is enough to know whether we declare
# them at all, which is what the compatibility box on the site reports.
has() { grep -rq "$1" . --include='*.php' --exclude-dir=vendor --exclude-dir=node_modules && echo true || echo false; }

python3 - "$SLUG" "$VERSION" \
	"$(header 'Requires at least')" "$(header 'Tested up to')" \
	"$(header 'Requires PHP')" \
	"$(header 'WC requires at least')" "$(header 'WC tested up to')" \
	"$(has custom_order_tables)" "$(has cart_checkout_blocks)" <<'PY'
import json, os, re, sys, datetime

slug, version, wp_min, wp_tested, php_min, wc_min, wc_tested, hpos, blocks = sys.argv[1:10]

# The full release history, parsed from the changelog.txt that ships in the
# package. It goes in the manifest so the website can render a release history
# without a checkout of this (private) repository — the same reason version and
# compatibility are here rather than typed into the site.
#
# The whole history every time, not a recent slice: this file is rewritten on
# every release, so a complete list keeps the reader stateless and stops the
# site having to merge what it fetched with what it already had.
TYPES = "Add|Fix|Change|Tweak|Dev|Enhancement|Security|Remove|Update"


def changelog(path="changelog.txt"):
    if not os.path.isfile(path):
        return []
    releases, release, entry = [], None, None
    with open(path, encoding="utf-8") as handle:
        for raw in handle:
            line = raw.strip()
            if not line or line.startswith("***"):
                continue
            header = re.match(r"^(\d{4}-\d{2}-\d{2})\s+-\s+version\s+(\S+)$", line, re.I)
            if header:
                release = {"version": header.group(2), "date": header.group(1), "changes": []}
                releases.append(release)
                entry = None
                continue
            if release is None:
                continue
            bullet = re.match(r"^\*\s+(?:(" + TYPES + r")\s+-\s+)?(.+)$", line, re.I)
            if bullet:
                kind = bullet.group(1).capitalize() if bullet.group(1) else "Change"
                entry = {"type": kind, "text": bullet.group(2).strip()}
                release["changes"].append(entry)
                continue
            # A wrapped bullet continues the entry above it, so its tail is not
            # silently dropped.
            if entry is not None:
                entry["text"] += " " + line
    return [r for r in releases if r["changes"]]


print(json.dumps({
    "slug": slug,
    "version": version,
    "updated": datetime.datetime.now(datetime.timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"),
    "compatibility": {
        "wpMin": wp_min,
        "wpTested": wp_tested,
        "wcMin": wc_min,
        "wcTested": wc_tested,
        "phpMin": php_min,
        "hpos": hpos == "true",
        "checkoutBlocks": blocks == "true",
    },
    "changelog": changelog(),
}, indent=2))
PY
