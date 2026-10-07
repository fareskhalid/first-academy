#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
compose=(docker compose -f compose.yaml -f compose.browser.yaml)
"${compose[@]}" up -d --wait browser-app
"${compose[@]}" exec -T browser-app php artisan test:prepare-browser
"${compose[@]}" exec -T -u root -e PLAYWRIGHT_BROWSERS_PATH=/ms-playwright browser-app npx playwright install --with-deps chromium webkit
"${compose[@]}" exec -T -e PLAYWRIGHT_BROWSERS_PATH=/ms-playwright -e E2E_BASE_URL=http://browser-app browser-app npm run test:e2e -- "$@"
