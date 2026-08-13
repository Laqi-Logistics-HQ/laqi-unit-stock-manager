#!/usr/bin/env bash
# Build a distributable zip of Laqi Unit Stock Manager for WooCommerce for one sales channel.
#
# Usage: bin/build.sh --channel <woocommerce|freemius|wordpressorg> [--version X.Y.Z]
#
# Run AFTER `composer install --no-dev --optimize-autoloader`. If package-lock.json
# is committed, also run `npm ci && npm run build`. Output:
# dist/<slug>-<version>-<channel>.zip with one top-level <slug>/ directory.
#
# Channels differ by which licensing SDK ships and which paid files survive: the
# WooCommerce.com build must NOT bundle the SDK (Woo's marketplace handles
# updates/licensing) but keeps paid features; the Freemius build keeps both; the
# WordPress.org build keeps neither. The SDK is embedded via Composer when
# Freemius is configured.
set -euo pipefail

SLUG="laqi-unit-stock-manager"
CHANNEL=""
VERSION=""

while [ $# -gt 0 ]; do
  case "$1" in
    --channel) CHANNEL="$2"; shift 2 ;;
    --version) VERSION="$2"; shift 2 ;;
    *) echo "unknown arg: $1" >&2; exit 1 ;;
  esac
done

case "$CHANNEL" in
  woocommerce|freemius|wordpressorg) ;;
  *) echo "usage: bin/build.sh --channel <woocommerce|freemius|wordpressorg> [--version X.Y.Z]" >&2; exit 1 ;;
esac

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

# Fall back to the Version: header in the main plugin file.
if [ -z "$VERSION" ]; then
  VERSION="$(grep -ioP '^\s*\*\s*Version:\s*\K[0-9][^\s]*' "$SLUG.php" | head -n1 || true)"
fi
[ -n "$VERSION" ] || { echo "could not determine version (pass --version)" >&2; exit 1; }

# A committed lockfile marks a real wp-scripts build. Never package a React/block
# plugin without the generated runtime its PHP code expects.
if [ -f package-lock.json ]; then
  for REQUIRED_ASSET in build/index.js build/index.asset.php; do
    if [ ! -f "$REQUIRED_ASSET" ]; then
      echo "missing required built asset: $REQUIRED_ASSET (run: npm ci && npm run build)" >&2
      exit 1
    fi
  done
fi

STAGE="$(mktemp -d)"
DEST="$STAGE/$SLUG"
mkdir -p "$DEST" dist

# The WooCommerce build uses the fallback PSR-4 loader. Exclude the complete
# Composer runtime because its generated files may require a stripped SDK.
RSYNC_CHANNEL_ARGS=()
if [ "$CHANNEL" = "woocommerce" ]; then
  RSYNC_CHANNEL_ARGS+=(--exclude 'vendor')
fi

# Copy the plugin, excluding dev-only and build tooling. The Freemius channel
# includes vendor/ after Composer removes development dependencies.
rsync -a --no-owner --no-group --delete \
  "${RSYNC_CHANNEL_ARGS[@]}" \
  --exclude '.git' \
  --exclude '.github' \
  --exclude 'node_modules' \
  --exclude 'dist' \
  --exclude '*.zip' \
  --exclude '.*' \
  --exclude 'bin' \
  --exclude 'tests' \
  --exclude 'test-results' \
  --exclude 'docs' \
  --exclude 'assets/src' \
  --exclude '.wp-env.json' \
  --exclude 'playwright.config.js' \
  --exclude '.editorconfig' \
  --exclude '.gitignore' \
  --exclude '.phpunit.result.cache' \
  --exclude '.phpcs-cache' \
  --exclude 'phpcs.xml.dist' \
  --exclude 'phpunit.xml.dist' \
  --exclude 'composer.json' \
  --exclude 'composer.lock' \
  --exclude 'package.json' \
  --exclude 'package-lock.json' \
  ./ "$DEST/"

# Reject package pollution even if a future tool writes to a new path that was
# not anticipated by the rsync exclusions above.
FORBIDDEN_ARTIFACT="$(find "$DEST" -type f \( -name '*.zip' -o -name '.*' \) -print -quit)"
if [ -n "$FORBIDDEN_ARTIFACT" ] || [ -d "$DEST/test-results" ]; then
  echo "refusing to package development artifact: ${FORBIDDEN_ARTIFACT:-$DEST/test-results}" >&2
  rm -rf "$STAGE"
  exit 1
fi

# Channel divergence: the WooCommerce.com build must not ship Composer's runtime
# tree. Freemius registers start.php as an autoload file, so removing only its
# package directory leaves vendor/autoload.php requiring a missing file. The
# plugin's fallback loader handles src/, and the scaffold has no other runtime
# package. This removal also protects builds after Freemius is configured.
if [ "$CHANNEL" != "freemius" ]; then
  rm -rf "$DEST/vendor" "$DEST/freemius"
fi
# Strip the licensing-SDK bootstrap from every channel but `freemius`.
#
# Removing vendor/ is not enough. The bootstrap and the paid-file manifest live
# in the main plugin file, so without this they ship as dead code that still
# names a licensing platform, carries its product id and public key, and — worst
# — lists every paid file the package deliberately excludes. WordPress.org reads
# that inventory as functionality "restricted or locked, only to be made
# available by payment" (Guideline 5), and WooCommerce.com owns licensing on its
# own channel, so a rival SDK has no business in that archive either. This cost
# a WordPress.org review round on order-status-automation.
#
# Wrap the bootstrap in the main plugin file with these markers:
#
#   /* laqi-unit-stock-manager-paid-sdk-start */
#   if ( ! function_exists( '<prefix>_fs' ) ) { ... }
#   /* laqi-unit-stock-manager-paid-sdk-end */
#
# Order matters if a wordpressorg block above consumes the paid-file manifest to
# delete paid files: this strip has to run after it.
if [ "$CHANNEL" != "freemius" ]; then
  sed -i.bak \
    -e "/laqi-unit-stock-manager-paid-sdk-start/,/laqi-unit-stock-manager-paid-sdk-end/d" \
    -e '/^[[:space:]]*\*[[:space:]]*Paid-only code\./d' \
    -e '/^[[:space:]]*\*[[:space:]]*@fs_premium_only[[:space:]]/d' \
    "$DEST/$SLUG.php"
  rm -f "$DEST/$SLUG.php.bak"

  # Collapse the runs of bare ` *` and blank lines those deletions leave, so the
  # shipped file reads as written rather than excised. A reviewer opens it first.
  awk '
    /^[[:space:]]*\*$/ { if (bare) next; bare = 1; blank = 0; print; next }
    /^[[:space:]]*$/    { if (blank) next; blank = 1; bare = 0; print; next }
                        { bare = 0; blank = 0; print }
  ' "$DEST/$SLUG.php" > "$DEST/$SLUG.php.tmp"
  mv "$DEST/$SLUG.php.tmp" "$DEST/$SLUG.php"

  # A gate, not a comment: one surviving reference means the archive ships it.
  # Scoped to executable code on purpose — readme.txt may legitimately state that
  # this edition carries no licensing SDK, which is what a reviewer wants to read.
  RESIDUE="$(grep -rniE 'freemius|fs_dynamic_init|fs_premium_only' \
    --include='*.php' --include='*.js' --include='*.json' "$DEST" || true)"
  if [ -n "$RESIDUE" ]; then
    echo "$RESIDUE" >&2
    echo "refusing to package: licensing-SDK references survived the $CHANNEL strip" >&2
    exit 1
  fi
fi
#
# Adding a free tier later? Freemius generates the free build from __premium_only
# markers, and inline is__premium_only() guards fatal THIS channel (the *_fs()
# accessor returns null once vendor/ is stripped). Split premium code out by file
# instead. See docs/freemius-free-paid-split.md in the wordpress_ai_dev meta-repo;
# release.yml fails the build if a marker reaches the WooCommerce archive.

OUT="$ROOT/dist/$SLUG-$VERSION-$CHANNEL.zip"
rm -f "$OUT"
if command -v zip >/dev/null 2>&1; then
  ( cd "$STAGE" && zip -rq "$OUT" "$SLUG" )
elif command -v python3 >/dev/null 2>&1; then
  # Fallback when the `zip` binary isn't installed (e.g. minimal WSL hosts).
  ( cd "$STAGE" && python3 -c 'import shutil,sys; shutil.make_archive(sys.argv[1],"zip",".",sys.argv[2])' "${OUT%.zip}" "$SLUG" )
else
  echo "need either 'zip' or 'python3' to build the archive" >&2
  rm -rf "$STAGE"; exit 1
fi
rm -rf "$STAGE"

echo "Built $OUT"
