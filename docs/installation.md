# Installation and operations

## Clean installation

1. Copy or clone the project onto a machine with Docker and Compose.
2. Run `bash scripts/setup.sh`. The script creates `.env` only when absent, builds PHP 8.5/Node 24, installs locked dependencies, creates the schema, compiles assets, starts the web/queue/scheduler services, and checks their heartbeats.
3. Run `docker compose exec laravel.test php artisan instructor:create` to create the first controlled instructor account.
4. Open `http://localhost:8088`. Students create their own regular accounts and then sign in.

The bootstrap never runs a destructive reset and never seeds accounts automatically. Re-running it keeps the application key, schema data, Redis data, and private files. PHP, Laravel, and MySQL sessions use `Africa/Cairo`; absolute class times remain consistent across Cairo daylight-saving changes.

## Services and data

| Service | Purpose | Browser exposure |
| --- | --- | --- |
| `laravel.test` | Laravel web application | `127.0.0.1:8088` |
| `mysql` | MySQL 8.4 application and isolated test schemas | None |
| `redis` | Sessions, cache, queue | None |
| `queue` | Queued in-app notices and jobs | None |
| `scheduler` | Minute scheduler and retry heartbeat | None |
| `vite` | Optional development asset server | `127.0.0.1:5178` |

Named volumes preserve MySQL, Redis, and `storage/app/private` through stop/start and container recreation. Tests refuse any database except `course_system_testing`; browser tests use `course_system_browser` and separate sessions/files.

## Checks

```bash
bash scripts/test.sh
docker compose exec laravel.test vendor/bin/pint --test
bash scripts/browser-test.sh
docker compose exec laravel.test php artisan system:check
```

The browser suite covers mobile Chromium, mobile WebKit, and desktop Chromium in English and Arabic. Emulation verifies responsive flows; a physical Android/iPhone check is still required before a classroom pilot.

## Recovery and lifecycle

An instructor can issue a one-hour temporary password from an enrolled student's roster. A trusted operator can also run `docker compose exec laravel.test php artisan account:reset IDENTIFIER`. Both require a reason, increment the session version, and force a password change. Students cannot recover accounts through email, SMS, or WhatsApp.

Use `docker compose stop` for normal shutdown. Volume deletion is intentionally absent from project scripts. Backups and public deployment are outside the local Docker configuration.

## Staging and production boundary

`bash scripts/staging.sh` starts a separate local HTTPS Compose project with its own database, Redis, private files, key, session cookie, and self-signed certificate. It proves TLS proxying and process topology locally. It does not constitute public staging or production readiness.

Production is deployed on Serv00 at <https://first-academy.fareskhalid.serv00.net>. [The Serv00 operations runbook](deployment-serv00.md) records the active GitHub Actions flow, persistent environment, cron-based scheduler/queue worker, and recovery commands. Off-host backup automation, monitoring, and a separate public staging environment remain operational work. The Sail-compatible development image includes build/debug tools and must not be deployed to Serv00.
