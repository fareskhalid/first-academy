#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
# TestCase rejects non-test DB names before RefreshDatabase can run migrations.
docker compose exec -T laravel.test php artisan test "$@"
