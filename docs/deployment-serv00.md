# Serv00 production operations

The application is deployed at <https://first-academy.fareskhalid.serv00.net> through [the production workflow](../.github/workflows/production.yml). Docker remains the local development environment; Serv00 runs PHP, Composer, Node, and MySQL directly.

## Current layout

| Item | Value |
| --- | --- |
| Application directory | `/usr/home/fareskhalid/domains/first-academy.fareskhalid.serv00.net/public_html` |
| Environment file | `public_html/.env` |
| Public URL | `https://first-academy.fareskhalid.serv00.net` |
| PHP binary | `/usr/local/bin/php85` |
| Composer binary | `/usr/local/bin/composer` |
| Runtime state | MySQL-backed sessions, cache, and queues |

The Serv00 website must route requests through Laravel's `public/index.php`. Keep `.env`, logs, application source, and private uploads inaccessible over HTTP.

## GitHub configuration

The workflow uses these repository secrets:

| Secret | Purpose |
| --- | --- |
| `DEPLOY_SSH_KEY` | Complete private SSH key authorized by the Serv00 account |
| `REMOTE_HOST` | Serv00 SSH hostname, such as `s12.serv00.com` |
| `REMOTE_USER` | Serv00 account username |

The workflow deploys pushes to `main` and merged pull requests targeting `main`. It performs these operations:

1. Loads the SSH key and records the remote host key.
2. Uses `rsync --delete` to copy the repository into `public_html` while preserving `.env`, installed dependencies, private files, logs, sessions, and cache data.
3. Installs locked production Composer dependencies and frontend dependencies.
4. Builds Vite assets, applies migrations, refreshes Laravel caches, and restarts the queue.

Do not edit deployed source files. A later deployment replaces tracked application files.

## Persistent environment

The workflow deliberately excludes `.env`. Create it once from `.env.serv00.example`, set the real production values, and keep it on the server:

```bash
cd /usr/home/fareskhalid/domains/first-academy.fareskhalid.serv00.net/public_html
cp .env.serv00.example .env
chmod 600 .env
```

Required production settings include:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://first-academy.fareskhalid.serv00.net
APP_TIMEZONE=Africa/Cairo
DB_TIMEZONE=Africa/Cairo
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
```

Replace every database placeholder. Generate `APP_KEY` once when it is blank, then rebuild the cached configuration:

```bash
/usr/local/bin/php85 artisan key:generate --force
/usr/local/bin/php85 artisan optimize
```

Never regenerate a populated production key. Changing it invalidates encrypted cookies and any data encrypted with the previous key.

`APP_TIMEZONE` controls Laravel and PHP date handling. `DB_TIMEZONE` sets every MySQL session opened by Laravel. Serv00's operating-system clock is provider-managed, so the application applies Cairo time without requiring root access.

After changing either timezone on the existing server, rebuild cached configuration:

```bash
/usr/local/bin/php85 artisan optimize:clear
/usr/local/bin/php85 artisan optimize
```

## Scheduler and queue

Add this minute cron entry in DevilWEB, replacing `LOGIN` and `DOMAIN` with literal values:

```cron
* * * * * /usr/local/bin/flock -n /usr/home/LOGIN/domains/DOMAIN/public_html/storage/framework/serv00-cron.lock /usr/local/bin/bash /usr/home/LOGIN/domains/DOMAIN/public_html/scripts/serv00-cron.sh >/dev/null 2>&1
```

[scripts/serv00-cron.sh](../scripts/serv00-cron.sh) runs the Laravel scheduler and a bounded database queue worker. The lock prevents overlapping runs.

After the cron has run for two minutes, verify the runtime:

```bash
cd /usr/home/fareskhalid/domains/first-academy.fareskhalid.serv00.net/public_html
/usr/local/bin/php85 artisan system:check --wait=75
```

Expected output:

```text
PASS: application/database timezones, database, cache, private storage, queue, scheduler.
```

## Routine operations

Deploy code by pushing to `main` or rerunning the successful production workflow from GitHub Actions. Before a release containing migrations, back up MySQL and `storage/app/private` together.

Useful server commands:

```bash
cd /usr/home/fareskhalid/domains/first-academy.fareskhalid.serv00.net/public_html
/usr/local/bin/php85 artisan about
/usr/local/bin/php85 artisan system:check --wait=75
tail -n 100 storage/logs/laravel.log
```

## Failure map

| Error | Cause | Fix |
| --- | --- | --- |
| `Unknown or incorrect time zone: 'Africa/Cairo'` | The MySQL host has not loaded named timezone data | Ask Serv00 support to enable MySQL timezone tables; do not replace Cairo with a permanent fixed offset because Egypt observes daylight saving time. |
| `No application encryption key has been specified` | `APP_KEY` is blank or cached as blank | Generate it once, then run `php85 artisan optimize`. |
| HTTP 500 after deployment | Laravel logged a server-side exception | Read the latest `storage/logs/laravel.log` entry and fix its first exception. |
| HTTP 403 | Serv00 is not routing to Laravel's public entry point or permissions deny access | Check the website path/routing in DevilWEB and directory permissions. |
| Assets are missing | The npm install or Vite build did not complete | Inspect the workflow log and confirm `public/build/manifest.json` exists. |
| `system:check` reports stale heartbeats | The minute cron is absent or failing | Run `scripts/serv00-cron.sh` over SSH, fix its first error, then inspect `crontab -l`. |

Keep [deploy/serv00/domain.htaccess](../deploy/serv00/domain.htaccess) and [deploy/serv00/domain.user.ini](../deploy/serv00/domain.user.ini) as the PHP 8.5 and production error-log templates used for the Serv00 website.
