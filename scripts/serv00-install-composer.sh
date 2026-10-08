#!/usr/local/bin/bash
set -euo pipefail

PHP_BIN="${PHP_BIN:-/usr/local/bin/php85}"
INSTALL_DIR="${COMPOSER_INSTALL_DIR:-$HOME/bin}"
WORK_DIR="$(mktemp -d "${TMPDIR:-/tmp}/course-system-composer.XXXXXX")"

cleanup() {
    rm -rf "$WORK_DIR"
}
trap cleanup EXIT

[[ -x "$PHP_BIN" ]] || { echo "ERROR: PHP 8.5 was not found at $PHP_BIN." >&2; exit 1; }
mkdir -p "$INSTALL_DIR"
cd "$WORK_DIR"

"$PHP_BIN" -r "copy('https://composer.github.io/installer.sig', 'installer.sig');"
"$PHP_BIN" -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"

expected_checksum="$(cat installer.sig)"
actual_checksum="$("$PHP_BIN" -r "echo hash_file('sha384', 'composer-setup.php');")"

if [[ "$expected_checksum" != "$actual_checksum" ]]; then
    echo 'ERROR: Composer installer checksum verification failed.' >&2
    exit 1
fi

"$PHP_BIN" composer-setup.php --quiet --install-dir="$INSTALL_DIR" --filename=composer
"$PHP_BIN" "$INSTALL_DIR/composer" --version

echo "Composer installed at $INSTALL_DIR/composer"
