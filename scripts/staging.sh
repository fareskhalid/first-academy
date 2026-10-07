#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
[ -f .env.staging ] || cp .env.staging.example .env.staging
mkdir -p .local/tls
compose=(docker compose --env-file .env.staging -f compose.yaml -f compose.staging.yaml)
# Only localhost is included by default. See docs/installation.md for trusted device HTTPS.
if [ ! -f .local/tls/localhost.key ]; then
  "${compose[@]}" run --rm --no-deps laravel.test openssl req -x509 -newkey rsa:2048 -nodes -days 30 -keyout .local/tls/localhost.key -out .local/tls/localhost.crt -subj /CN=localhost -addext 'subjectAltName=DNS:localhost,IP:127.0.0.1'
  chmod 600 .local/tls/localhost.key
fi
"${compose[@]}" run --rm --no-deps laravel.test php -r '
$p=".env.staging"; $s=file_get_contents($p);
if (preg_match("/^APP_KEY=\s*$/m",$s)) file_put_contents($p,preg_replace("/^APP_KEY=\s*$/m","APP_KEY=base64:".base64_encode(random_bytes(32)),$s));
'
"${compose[@]}" up -d --wait mysql redis
"${compose[@]}" run --rm --no-deps laravel.test php artisan migrate --force
"${compose[@]}" up -d --wait laravel.test queue scheduler tls
"${compose[@]}" exec -T laravel.test php artisan system:check --wait=75
echo 'Local HTTPS staging ready: https://localhost:8448 (self-signed development certificate).'
