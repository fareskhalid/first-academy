#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p .local
if [ ! -f .local/demo-credentials.txt ]; then
  umask 077
  demo_password=$(docker compose exec -T laravel.test php -r 'echo bin2hex(random_bytes(12));')
  printf 'Phone: 01000000001\nPassword: %s\n' "$demo_password" > .local/demo-credentials.txt
fi
DEMO_PASSWORD=$(sed -n 's/^Password: //p' .local/demo-credentials.txt)
export DEMO_PASSWORD
docker compose exec -T -e DEMO_PASSWORD laravel.test php artisan db:seed --class=DemoSeeder
echo 'Demo sign-in details: .local/demo-credentials.txt (private; never commit this file).'
