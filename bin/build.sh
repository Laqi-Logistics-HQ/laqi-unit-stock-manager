#!/usr/bin/env bash
# Build the complete WordPress.org Free archive.
#
# Usage: bin/build.sh --channel wordpressorg [--version X.Y.Z]
#
# Run AFTER `composer install --no-dev --optimize-autoloader`. If package-lock.json
# is committed, also run `npm ci && npm run build`. Output:
# dist/<slug>-<version>-<channel>.zip with one top-level <slug>/ directory.
#
# This repository is the complete Free plugin. Paid implementations are packaged
# separately by laqi-unit-stock-manager-pro; this build never strips or rewrites
# source to create an edition.
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
  wordpressorg) ;;
  *) echo "usage: bin/build.sh --channel wordpressorg [--version X.Y.Z]" >&2; exit 1 ;;
esac

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

# Fall back to the Version: header in the main plugin file.
if [ -z "$VERSION" ]; then
  VERSION="$(grep -ioP '^\s*\*\s*Version:\s*\K[0-9][^\s]*' "$SLUG.php" | head -n1 || true)"
fi
[ -n "$VERSION" ] || { echo "could not determine version (pass --version)" >&2; exit 1; }

# A real npm build script marks a generated runtime. The committed lockfile also
# supports linting-only plugins, so it is not evidence that build/ must exist.
HAS_BUILD_SCRIPT=0
if [ -f package.json ] && node -e 'const p=require("./package.json"); process.exit(p.scripts && p.scripts.build ? 0 : 1)' 2>/dev/null; then
  HAS_BUILD_SCRIPT=1
fi
if [ "$HAS_BUILD_SCRIPT" -eq 1 ]; then
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

# Copy the complete Free plugin, excluding dependencies and development tooling.
rsync -a --no-owner --no-group --delete \
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
  --exclude 'phpunit*.xml*' \
  --exclude 'composer.json' \
  --exclude 'composer.lock' \
  --exclude 'package.json' \
  --exclude 'package-lock.json' \
  --exclude 'README.md' \
  --exclude 'vendor' \
  --exclude 'freemius' \
  ./ "$DEST/"

if [ "$CHANNEL" = "wordpressorg" ] && grep -q '^[[:space:]]*\*[[:space:]]*Update URI:' "$DEST/$SLUG.php"; then
  echo "refusing to package: WordPress.org archive must use directory updates" >&2
  rm -rf "$STAGE"
  exit 1
fi

# Reject package pollution even if a future tool writes to a new path that was
# not anticipated by the rsync exclusions above.
FORBIDDEN_ARTIFACT="$(find "$DEST" -type f \( -name '*.zip' -o -name '.*' \) -print -quit)"
if [ -n "$FORBIDDEN_ARTIFACT" ] || [ -d "$DEST/test-results" ]; then
  echo "refusing to package development artifact: ${FORBIDDEN_ARTIFACT:-$DEST/test-results}" >&2
  rm -rf "$STAGE"
  exit 1
fi

if ! grep -qFx ' * Plugin Name:       Laqi Unit Stock Manager for WooCommerce' "$DEST/$SLUG.php"; then
  echo "refusing to package: archive is missing the Free plugin name" >&2
  rm -rf "$STAGE"
  exit 1
fi

# Free is authored as Free source. Reject cross-edition leakage instead of
# deleting or rewriting it during packaging.
PREMIUM_RESIDUE="$(grep -rniE 'freemius|fs_dynamic_init|fs_premium_only|__premium_only|LaqiUnitStockManagerPro|laqi_lusmp_' \
  --include='*.php' --include='*.js' --include='*.json' "$DEST" || true)"
if [ -n "$PREMIUM_RESIDUE" ]; then
  echo "$PREMIUM_RESIDUE" >&2
  echo "refusing to package: Premium implementation or SDK references reached the Free archive" >&2
  rm -rf "$STAGE"
  exit 1
fi

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
