#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
command -v docker >/dev/null || { echo 'Install Docker Engine/Desktop with Compose first.'; exit 1; }
docker compose version >/dev/null
mkdir -p storage/framework/{cache/data,sessions,views} storage/logs storage/app/private bootstrap/cache
if [ ! -f .env ]; then
  cp .env.example .env
  sed -i "s/^WWWUSER=.*/WWWUSER=$(id -u)/; s/^WWWGROUP=.*/WWWGROUP=$(id -g)/" .env
fi
# Build context is committed; vendor/Sail is not required for the bootstrap.
if [[ "${SKIP_DOCKER_BUILD:-0}" == "1" ]]; then
  docker image inspect course-system-dev:php8.5 >/dev/null
else
  docker compose build laravel.test
fi

composer_cache="${COMPOSER_CACHE_DIR:-$HOME/.cache/composer}"
npm_cache="${NPM_CACHE_DIR:-$HOME/.npm}"
mkdir -p "$composer_cache" "$npm_cache"

docker compose run --rm --no-deps \
  -e COMPOSER_CACHE_DIR=/tmp/composer-cache \
  -v "$composer_cache:/tmp/composer-cache" \
  laravel.test composer install --no-interaction --prefer-dist
docker compose run --rm --no-deps laravel.test php -r '
$p=".env"; $s=file_get_contents($p);
if (preg_match("/^APP_KEY=\s*$/m", $s)) { $s=preg_replace("/^APP_KEY=\s*$/m", "APP_KEY=base64:".base64_encode(random_bytes(32)), $s); file_put_contents($p,$s); }
'
docker compose up -d --wait mysql redis
docker compose run --rm --no-deps laravel.test php artisan migrate --force
docker compose run --rm --no-deps \
  -e npm_config_cache=/tmp/npm-cache \
  -v "$npm_cache:/tmp/npm-cache" \
  laravel.test npm ci
docker compose run --rm --no-deps laravel.test npm run build
docker compose up -d --wait laravel.test queue scheduler
if [[ "${SKIP_SYSTEM_CHECK:-0}" != "1" ]]; then
  docker compose exec -T laravel.test php artisan system:check --wait=75
fi
echo 'Setup complete. Create your instructor: docker compose exec laravel.test php artisan instructor:create'
