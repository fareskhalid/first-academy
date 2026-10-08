# Serv00 deployment

This is the production profile for the Course System on Serv00 shared hosting. Docker remains the local development environment. Serv00 runs the application as a PHP website with PHP 8.5, uses Node 24 only to compile assets, and uses MySQL-backed sessions, cache, and queues. A minute cron invocation runs Laravel's scheduler and drains queued work without a permanent background process.

Allow **30–45 minutes** for the first deployment after DNS points to Serv00. Normal code updates take **3–8 minutes**, depending on Composer and npm download speed.

## 1. Collect the four account values

Set these only in your SSH session; do not commit them:

```bash
export DOMAIN=courses.example.com
export APP_ROOT="$HOME/domains/$DOMAIN/application"
export REPOSITORY_URL='git@github.com:OWNER/REPOSITORY.git'
export SERV00_MYSQL_HOST=mysqlX.serv00.com
```

Replace `X` with the number from the Serv00 server hostname: `s12.serv00.com` uses `mysql12.serv00.com`. The final values required in `.env` are the domain, MySQL host, database name, database user, and database password.

## 2. Create the Serv00 resources

1. In DevilWEB, add `$DOMAIN` as a **PHP** website.
2. In **MySQL**, create a database and user with `utf8mb4` collation, then grant that user all privileges on this database.
3. Point the domain's DNS to the Serv00 web IP and generate a Let's Encrypt certificate under **SSL → WWW websites**.
4. Enable **Force SSL** and **GZIP** in the website details; keep the default WAF level 1 for the first deployment.

Serv00's PHP website, MySQL host naming, certificate flow, and WAF levels are documented in [WWW pages](https://docs.serv00.com/WWW/), [MySQL](https://docs.serv00.com/MySQL/), [SSL](https://docs.serv00.com/SSL/), and [WAF](https://docs.serv00.com/WAF/).

## 3. Clone the application and install Composer

Clone the project outside the public directory:

```bash
mkdir -p "$HOME/domains/$DOMAIN"
git clone "$REPOSITORY_URL" "$APP_ROOT"
cd "$APP_ROOT"
```

Check for Composer, then run the repository's checksum-verifying installer if it is absent. The installer follows [Composer's programmatic installer flow](https://getcomposer.org/doc/faqs/how-to-install-composer-programmatically.md):

```bash
command -v composer || test -f "$HOME/bin/composer" || bash scripts/serv00-install-composer.sh
```

Always run Composer through `php85`; the unversioned Serv00 `php` command can point to an older version.

Create the production environment file:

```bash
cp .env.serv00.example .env
chmod 600 .env
```

Edit `.env` and replace every placeholder. Keep these Serv00 values unchanged:

```dotenv
APP_ENV=production
APP_DEBUG=false
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
```

The MySQL `sessions`, `cache`, `jobs`, and `failed_jobs` tables are already included in the application migrations. Redis and a permanent queue daemon are not required for this profile.

## 4. Configure PHP and run the first deploy

Install the domain-level PHP files, replacing the two placeholders in `.user.ini`:

```bash
cp deploy/serv00/domain.htaccess "$HOME/domains/$DOMAIN/.htaccess"
sed -e "s/LOGIN/$USER/g" -e "s/DOMAIN/$DOMAIN/g" \
    deploy/serv00/domain.user.ini > "$HOME/domains/$DOMAIN/.user.ini"
bash scripts/serv00-deploy.sh
php85 artisan instructor:create
```

The deploy script refuses debug mode, HTTP URLs, and placeholder credentials. It installs locked production Composer packages, builds Vite assets with `npm24`, generates `APP_KEY` only when it is empty, runs migrations, and caches Laravel configuration/routes/views. If an update fails after maintenance mode starts, the site stays in maintenance mode until the error is fixed and deployment succeeds.

Expose only Laravel's `public/` directory. Inspect `public_html` first, then preserve Serv00's initial directory and replace it with a symlink:

```bash
cd "$HOME/domains/$DOMAIN"
ls -la public_html
mv public_html "public_html.before-course-system-$(date +%Y%m%d-%H%M%S)"
ln -s "$APP_ROOT/public" public_html
```

The `.env`, source, private receipts, and logs remain under `application/` and cannot be requested from the web root.

If the public hostname differs from the domain directory, add the full hostname in DevilWEB as a **Pointer** website targeting the existing PHP website. Set `APP_URL` and the optional GitHub value `SERV00_PUBLIC_DOMAIN` to that public hostname, but keep `SERV00_DOMAIN` set to the domain directory containing `application`.

## 5. Add the scheduler and queue cron

Open `crontab -e` or DevilWEB's **Cron jobs** screen and add one entry. Replace `LOGIN` and `DOMAIN` with literal values; cron does not receive the shell variables from the earlier SSH session.

```cron
* * * * * /usr/local/bin/flock -n /usr/home/LOGIN/domains/DOMAIN/application/storage/framework/serv00-cron.lock /usr/local/bin/bash /usr/home/LOGIN/domains/DOMAIN/application/scripts/serv00-cron.sh >/dev/null 2>&1
```

The lock skips overlapping invocations. Each run evaluates the Laravel schedule and processes at most 100 database jobs for at most 45 seconds with a 128 MB worker limit. Serv00 documents minute cron entries and `/usr/local/bin/flock` in its [Cron guide](https://docs.serv00.com/Cron/).

Wait two minutes, then verify all shared-hosting dependencies:

```bash
cd "$APP_ROOT"
php85 artisan system:check
curl -fsS "https://$DOMAIN/login" >/dev/null
```

Expected results are `PASS: database, cache, private storage, queue, scheduler.` and an HTTP success response from the public login page.

## Routine deployment

Take a MySQL and private-file backup before migrations, then run:

```bash
ssh LOGIN@sX.serv00.com
cd "/usr/home/LOGIN/domains/DOMAIN/application"
bash scripts/serv00-deploy.sh
php85 artisan system:check --wait=75
```

The script uses `git pull --ff-only`, so local production edits stop deployment instead of being overwritten. Deploy from a reviewed branch whose upstream is configured. Set `SKIP_GIT_PULL=1` only when an external release process has already checked out the intended commit.

## Automatic GitHub deployment

The [production workflow](../.github/workflows/production.yml) runs after [CI](../.github/workflows/ci.yml) succeeds for a push to `main`. It checks out the exact tested commit and calls `scripts/serv00-github-deploy.sh`, which owns SSH setup, fast-forwards the server's `main` branch to that commit, runs `scripts/serv00-deploy.sh`, waits for scheduler/queue health, and checks the public login page. The `production` concurrency group prevents overlapping releases. Manual dispatch is restricted to `main`.

Create the four required values under **GitHub repository → Settings → Secrets and variables → Actions**. The workflow accepts the host, username, and domains as either repository secrets or variables, preferring secrets when both exist. The private SSH key must be an encrypted secret, following [GitHub's variables](https://docs.github.com/en/actions/concepts/workflows-and-actions/variables) and [secrets](https://docs.github.com/en/actions/concepts/security/secrets) guidance.

| Type | Name | Value |
| --- | --- | --- |
| Secret or variable | `SERV00_HOST` | Serv00 SSH hostname, such as `s12.serv00.com` |
| Secret or variable | `SERV00_USERNAME` | Serv00 account login |
| Secret or variable | `SERV00_DOMAIN` | Domain directory containing `application`, without `https://` |
| Secret or variable | `SERV00_PUBLIC_DOMAIN` | Optional public alias checked after deployment; defaults to `SERV00_DOMAIN` |
| Secret | `SERV00_SSH_PRIVATE_KEY` | Private half of a dedicated Ed25519 deployment key |

Prepare access once from a trusted computer:

```bash
ssh-keygen -t ed25519 -N '' -C github-actions-serv00 -f ./serv00-actions-key
cat ./serv00-actions-key.pub | ssh LOGIN@sX.serv00.com \
    'umask 077; mkdir -p ~/.ssh; cat >> ~/.ssh/authorized_keys'
```

Store the private key in `SERV00_SSH_PRIVATE_KEY`. Base64 is the most reliable single-line format for GitHub Secrets:

```bash
base64 < ./serv00-actions-key | tr -d '\n'
```

Copy that command's complete output into the secret. The workflow also accepts the complete raw multiline private key, but never use `serv00-actions-key.pub`. The key must not have a passphrase. The workflow obtains the current SSH host key with `ssh-keyscan` at deployment time because manual host fingerprint verification was skipped. Do not reuse a personal SSH key.

The checkout on Serv00 must already exist at `/usr/home/LOGIN/domains/DOMAIN/application`, contain its production `.env`, and be able to run `git fetch origin main`. A public GitHub repository needs no extra Git credential. For a private repository, configure a separate read-only GitHub deploy key on the Serv00 checkout; the GitHub Actions SSH key grants server access only.

Push a commit to `main` after all four GitHub values exist, or manually dispatch the workflow against `main`. Pull requests and non-`main` pushes run CI without deploying. GitHub records the job against the `production` environment; optional environment branch protection can restrict it further. [GitHub deployment environments](https://docs.github.com/en/actions/concepts/workflows-and-actions/deployment-environments).

## Storage, backup, and capacity boundaries

- `storage/app/private` contains current and future private uploads; never symlink it into `public_html`.
- Back up MySQL and `storage/app/private` together before releases that migrate data. Keep an encrypted copy outside Serv00.
- Serv00's shared limits make this profile suitable for a controlled pilot. Validate attendance-time concurrency before enrolling the proposed 2,000-student target.
- A daily provider backup does not meet the project's proposed 15-minute recovery-point target. Treat that target as open until off-host database and file backups are automated and restored in rehearsal.
- If queue delay grows beyond two minutes, inspect `jobs`, `failed_jobs`, `storage/logs`, and `system_heartbeats` before increasing cron frequency or process usage.

## Failure map

| Location | Cause | Fix |
| --- | --- | --- |
| `scripts/serv00-deploy.sh` stops before install | `.env` is missing, unsafe, or still contains placeholders | Copy `.env.serv00.example`, set all production values, and rerun. |
| Browser shows 500 | Wrong PHP version, unreadable `.env`, unwritable `storage`, or stale cache | Confirm domain `.htaccess`, run `chmod -R u+rwX storage bootstrap/cache`, then `php85 artisan optimize:clear`. |
| Browser shows 404 except `/` | `public_html` does not point to `application/public` or rewrite rules are unavailable | Check `readlink public_html` and confirm `public/.htaccess` exists. |
| `system:check` reports stale heartbeats | Cron is absent, paths still contain placeholders, or queue execution failed | Run `scripts/serv00-cron.sh` manually, fix its first error, then inspect `crontab -l`. |
| Assets are missing | `npm24 ci` or `npm24 run build` failed | Rerun the deploy script and confirm `public/build/manifest.json` exists. |
