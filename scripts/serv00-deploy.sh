#!/usr/local/bin/bash
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PHP_BIN="${PHP_BIN:-/usr/local/bin/php85}"
NPM_BIN="${NPM_BIN:-/usr/local/bin/npm24}"
MAINTENANCE_ENABLED=0

cd "$APP_ROOT"

fail() {
    echo "ERROR: $*" >&2
    exit 1
}

[[ -x "$PHP_BIN" ]] || fail "PHP 8.5 was not found at $PHP_BIN."
[[ -x "$NPM_BIN" ]] || fail "Node npm24 was not found at $NPM_BIN."
[[ -f .env ]] || fail "Missing $APP_ROOT/.env. Copy .env.serv00.example and set production values first."

if ! grep -Eq '^APP_ENV=production$' .env; then
    fail 'APP_ENV must be production.'
fi

if grep -Eq '^APP_DEBUG=(true|1|yes|on)$' .env; then
    fail 'APP_DEBUG must be false in production.'
fi

if ! grep -Eq '^APP_URL=https://' .env; then
    fail 'APP_URL must use the public HTTPS URL.'
fi

if grep -Eq 'courses\.example\.com|mysqlX\.serv00\.com|replace-with-' .env; then
    fail 'Replace every placeholder in .env before deployment.'
fi

if [[ -n "${COMPOSER_BIN:-}" ]]; then
    composer_path="$COMPOSER_BIN"
elif command -v composer >/dev/null 2>&1; then
    composer_path="$(command -v composer)"
elif [[ -f "$HOME/bin/composer" ]]; then
    composer_path="$HOME/bin/composer"
else
    fail 'Composer was not found. Install it at ~/bin/composer as documented in docs/deployment-serv00.md.'
fi

report_failure() {
    status=$?
    if [[ "$status" -ne 0 && "$MAINTENANCE_ENABLED" -eq 1 ]]; then
        echo "Deployment failed; the existing site remains in maintenance mode." >&2
        echo "Fix the reported error, rerun this script, then use: $PHP_BIN artisan up" >&2
    fi
}
trap report_failure EXIT

if [[ -f vendor/autoload.php ]]; then
    "$PHP_BIN" artisan down --retry=60 --refresh=15
    MAINTENANCE_ENABLED=1
fi

if [[ "${SKIP_GIT_PULL:-0}" != "1" ]]; then
    git pull --ff-only
fi

mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R u+rwX storage bootstrap/cache

"$PHP_BIN" "$composer_path" install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction

if grep -Eq '^APP_KEY=[[:space:]]*$' .env; then
    "$PHP_BIN" artisan key:generate --force
fi

NODE_OPTIONS="${NODE_OPTIONS:---max-old-space-size=384}" "$NPM_BIN" ci --include=dev --no-audit --no-fund
NODE_OPTIONS="${NODE_OPTIONS:---max-old-space-size=384}" "$NPM_BIN" run build

"$PHP_BIN" artisan config:clear
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan optimize
"$PHP_BIN" artisan schedule:interrupt || true

"$PHP_BIN" artisan up
MAINTENANCE_ENABLED=0

echo "Deployment complete: $APP_ROOT"
echo 'After cron has run for two minutes, verify with: php85 artisan system:check'
