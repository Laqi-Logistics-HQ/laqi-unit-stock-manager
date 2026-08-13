#!/usr/bin/env bash
# Install a pinned WordPress core and PHPUnit test library for CI/local testing.
set -euo pipefail

if [ "$#" -lt 5 ]; then
  echo "usage: $0 <db-name> <db-user> <db-pass> <db-host> <wp-version>" >&2
  exit 1
fi

DB_NAME="$1"
DB_USER="$2"
DB_PASS="$3"
DB_HOST="$4"
WP_VERSION="$5"
TEST_ROOT="${WP_TEST_ROOT:-/tmp/laqi-unit-stock-manager-wp-tests-$WP_VERSION}"
WP_CORE_DIR="$TEST_ROOT/wordpress"
WP_TESTS_DIR="$TEST_ROOT/wordpress-tests-lib"

download() {
  curl --fail --location --silent --show-error "$1" --output "$2"
}

mkdir -p "$TEST_ROOT"

if [ ! -f "$WP_CORE_DIR/wp-settings.php" ]; then
  download "https://wordpress.org/wordpress-$WP_VERSION.tar.gz" "$TEST_ROOT/wordpress.tar.gz"
  rm -rf "$WP_CORE_DIR"
  mkdir -p "$WP_CORE_DIR"
  tar --strip-components=1 -xzf "$TEST_ROOT/wordpress.tar.gz" -C "$WP_CORE_DIR"
fi

if [ ! -f "$WP_TESTS_DIR/includes/functions.php" ]; then
  download \
    "https://github.com/WordPress/wordpress-develop/archive/refs/tags/$WP_VERSION.tar.gz" \
    "$TEST_ROOT/wordpress-develop.tar.gz"
  rm -rf "$WP_TESTS_DIR" "$TEST_ROOT/wordpress-develop-$WP_VERSION"
  mkdir -p "$WP_TESTS_DIR"
  tar -xzf "$TEST_ROOT/wordpress-develop.tar.gz" -C "$TEST_ROOT"
  mv "$TEST_ROOT/wordpress-develop-$WP_VERSION/tests/phpunit/includes" "$WP_TESTS_DIR/"
  mv "$TEST_ROOT/wordpress-develop-$WP_VERSION/tests/phpunit/data" "$WP_TESTS_DIR/"
  rm -rf "$TEST_ROOT/wordpress-develop-$WP_VERSION"
fi

download \
  "https://raw.githubusercontent.com/WordPress/wordpress-develop/$WP_VERSION/wp-tests-config-sample.php" \
  "$WP_TESTS_DIR/wp-tests-config.php"

WP_CORE_ESCAPED="$(printf '%s/' "$WP_CORE_DIR" | sed 's/[&|]/\\&/g')"
sed -i \
  -e "s|dirname( __FILE__ ) . '/src/'|'$WP_CORE_ESCAPED'|" \
  -e "s|__DIR__ . '/src/'|'$WP_CORE_ESCAPED'|" \
  -e "s/youremptytestdbnamehere/$DB_NAME/" \
  -e "s/yourusernamehere/$DB_USER/" \
  -e "s/yourpasswordhere/$DB_PASS/" \
  -e "s|localhost|$DB_HOST|" \
  "$WP_TESTS_DIR/wp-tests-config.php"

mysql --host="${DB_HOST%%:*}" --port="${DB_HOST##*:}" \
  --user="$DB_USER" --password="$DB_PASS" \
  --execute="DROP DATABASE IF EXISTS \`$DB_NAME\`; CREATE DATABASE \`$DB_NAME\`;"

printf 'WP_TESTS_DIR=%s\n' "$WP_TESTS_DIR"
