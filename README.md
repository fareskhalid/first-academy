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
docker compose exec -e DEMO_PASSWORD='local-demo-password' laravel.test php artisan db:seed --class=DemoSeeder
```

The demo instructor uses phone `01000000001` and the password supplied in the command. `DatabaseSeeder` remains empty and no historical-data import exists.

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

Production is live at <https://first-academy.fareskhalid.serv00.net>. Use [the Serv00 operations runbook](docs/deployment-serv00.md) for the GitHub Actions deployment, persistent environment, cron worker, health check, and troubleshooting commands.

## Documentation

- [Full requirements](docs/project-requirements.md)
- [Installation and operations](docs/installation.md)
- [Serv00 deployment](docs/deployment-serv00.md)
- [Sprint 1 implementation status](docs/sprint-1-status.md)
