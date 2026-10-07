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
docker compose build laravel.test
docker compose run --rm --no-deps laravel.test composer install --no-interaction --prefer-dist
docker compose run --rm --no-deps laravel.test php -r '
$p=".env"; $s=file_get_contents($p);
if (preg_match("/^APP_KEY=\s*$/m", $s)) { $s=preg_replace("/^APP_KEY=\s*$/m", "APP_KEY=base64:".base64_encode(random_bytes(32)), $s); file_put_contents($p,$s); }
'
docker compose up -d --wait mysql redis
docker compose run --rm --no-deps laravel.test php artisan migrate --force
docker compose run --rm --no-deps laravel.test npm ci
docker compose run --rm --no-deps laravel.test npm run build
docker compose up -d --wait laravel.test queue scheduler
docker compose exec -T laravel.test php artisan system:check --wait=75
echo 'Setup complete. Create your instructor: docker compose exec laravel.test php artisan instructor:create'
