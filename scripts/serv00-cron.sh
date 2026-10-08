#!/usr/local/bin/bash
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PHP_BIN="${PHP_BIN:-/usr/local/bin/php85}"

cd "$APP_ROOT"

status=0

"$PHP_BIN" artisan schedule:run --no-interaction || status=$?
"$PHP_BIN" artisan queue:work database \
    --stop-when-empty \
    --max-jobs=100 \
    --max-time=45 \
    --timeout=30 \
    --tries=3 \
    --memory=128 \
    --no-interaction || status=$?

exit "$status"
