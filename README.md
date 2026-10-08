# First Academy System

Mobile-first English/Arabic course management built with Laravel 13, Livewire 4, Tailwind CSS 4, and MySQL. Docker Compose is the local environment; Serv00 PHP shared hosting is the selected production profile.

## Install

Prerequisites: Git, Docker Engine/Desktop, and Docker Compose. Host PHP, Node, MySQL, and Redis are not required.

```bash
bash scripts/setup.sh
docker compose exec laravel.test php artisan instructor:create
```

Open <http://localhost:8088>. The instructor command asks for the password privately and does not place it in shell history.

For optional synthetic courses with two groups:

```bash
bash scripts/demo.sh
```

The generated local demo password is stored in `.local/demo-credentials.txt`, which is ignored by Git. `DatabaseSeeder` remains empty and no historical-data import exists.

## Daily commands

```bash
docker compose up -d --wait
docker compose --profile assets up -d vite
bash scripts/test.sh
bash scripts/browser-test.sh
docker compose logs -f laravel.test queue scheduler
docker compose stop
```

`docker compose stop` preserves MySQL, Redis, and private-file volumes. Do not add `-v` unless you intentionally want to delete local data.

## Local HTTPS staging

```bash
bash scripts/staging.sh
```

Open <https://localhost:8448>. The generated 30-day certificate is self-signed and intended only for this computer. Testing from a real phone needs a reachable hostname/IP and a certificate trusted by that phone; configure those before QR work in Sprint 2.

## Serv00 deployment

Use [the Serv00 deployment runbook](docs/deployment-serv00.md). It keeps Docker out of production, selects PHP 8.5 and Node 24, stores sessions/cache/queues in Serv00 MySQL, and runs scheduling plus queued jobs from one locked minute cron entry.

## Documentation

- [Full requirements](docs/project-requirements.md)
- [Installation and operations](docs/installation.md)
- [Serv00 deployment](docs/deployment-serv00.md)
- [Sprint 1 implementation status](docs/sprint-1-status.md)
