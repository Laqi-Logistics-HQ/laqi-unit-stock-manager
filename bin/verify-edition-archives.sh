#!/usr/bin/env bash
# Prove that exact Free and Pro release archives keep their runtime boundaries.
set -euo pipefail

if [ "$#" -ne 2 ] || [ ! -f "$1" ] || [ ! -f "$2" ]; then
  echo "usage: bin/verify-edition-archives.sh <wordpressorg-free.zip> <pro-woocommerce.zip>" >&2
  exit 1
fi

FREE_ARCHIVE="$1"
PRO_ARCHIVE="$2"
AUDIT_DIR="$(mktemp -d)"
trap 'rm -rf "$AUDIT_DIR"' EXIT

unzip -q "$FREE_ARCHIVE" -d "$AUDIT_DIR/free"
unzip -q "$PRO_ARCHIVE" -d "$AUDIT_DIR/pro"

FREE_ROOT="$AUDIT_DIR/free/laqi-unit-stock-manager"
PRO_ROOT="$AUDIT_DIR/pro/laqi-unit-stock-manager-pro"
[ -f "$FREE_ROOT/laqi-unit-stock-manager.php" ] || { echo "Free archive has the wrong root or bootstrap" >&2; exit 1; }
[ -f "$PRO_ROOT/laqi-unit-stock-manager-pro.php" ] || { echo "Pro archive has the wrong root or bootstrap" >&2; exit 1; }

FREE_RESIDUE="$(grep -RniE 'LaqiUnitStockManagerPro|laqi_lusmp_|fs_dynamic_init|fs_premium_only|__premium_only' \
  --include='*.php' --include='*.js' --include='*.json' "$FREE_ROOT" || true)"
if [ -n "$FREE_RESIDUE" ]; then
  echo "$FREE_RESIDUE" >&2
  echo "Free archive contains Pro implementation, SDK, or Premium-marker residue" >&2
  exit 1
fi

PRO_FREE_NAMESPACE="$(grep -RniE '^[[:space:]]*namespace[[:space:]]+LaqiUnitStockManager(\\|;)' \
  --include='*.php' "$PRO_ROOT" || true)"
if [ -n "$PRO_FREE_NAMESPACE" ]; then
  echo "$PRO_FREE_NAMESPACE" >&2
  echo "Pro archive declares Free implementation namespaces" >&2
  exit 1
fi

grep -q '^[[:space:]]*\*[[:space:]]*Requires Plugins:.*laqi-unit-stock-manager' \
  "$PRO_ROOT/laqi-unit-stock-manager-pro.php" || {
  echo "Pro archive does not declare its Free plugin dependency" >&2
  exit 1
}

find "$FREE_ROOT" -type f -name '*.php' -exec sha256sum {} + | awk '{print $1}' | sort -u > "$AUDIT_DIR/free-php-hashes"
find "$PRO_ROOT" -type f -name '*.php' -exec sha256sum {} + | awk '{print $1}' | sort -u > "$AUDIT_DIR/pro-php-hashes"
DUPLICATE_HASHES="$(comm -12 "$AUDIT_DIR/free-php-hashes" "$AUDIT_DIR/pro-php-hashes")"
if [ -n "$DUPLICATE_HASHES" ]; then
  echo "$DUPLICATE_HASHES" >&2
  echo "Free and Pro archives contain byte-identical PHP implementations" >&2
  exit 1
fi

echo "Edition archive boundary verified."
