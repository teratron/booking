# Put It on a Real Server, Without Docker

> **Read this first.** This is **not** the way the project is designed to run
> in production. If you take this path you give up, by definition:
>
> - the automated release pipeline (build once, deploy by exact fingerprint);
> - the automatic post-release health check;
> - one-command rollback to the previous version;
> - the guarantee that what you deployed is exactly what was tested.
>
> Every one of those becomes a manual procedure you perform carefully by
> hand. Only choose this path if Docker genuinely cannot run on your server.
> A developer must own this setup and every future update.

Read [`overview.md`](overview.md) first. This guide assumes comfort with a
Linux server, nginx, and system services.

## What you are building

A classic server stack, assembled by hand: nginx serving the site, PHP-FPM
running the application, PostgreSQL storing the data, Redis for cache and
queues, and a process supervisor keeping the three background processes
alive.

## Before you start

- A **Linux server** with a domain pointed at it and a TLS certificate
  (Let's Encrypt / certbot is fine).
- Ability to install system packages and manage services (`root` or `sudo`).

Install, at these versions:

| Software | Version | Notes |
| --- | --- | --- |
| **PHP** | 8.5 | With `php-fpm` and extensions `intl`, `pdo_pgsql`, `pgsql`, `redis`, `imagick`, `gd`, `zip`, `bcmath`, `exif`, `pcntl`, `opcache`. |
| **Composer** | 2.x | |
| **PostgreSQL** | 18 | With **PostGIS**, plus `pg_trgm` and `unaccent`. |
| **postgresql-client** | 18 | For `pg_dump`, used by the backup job. Must match the server's major version. |
| **Redis** | 8 | |
| **Node.js** | 24 | Only needed on the server if you build assets there; you may also build them elsewhere and upload the result. |
| **pnpm** | 11.x | |
| **nginx** | any current | The public web server. |
| **Supervisor** | any current | Keeps the background processes running. |

## Steps

### 1. Create the database

```
sudo -u postgres psql
```

```
CREATE ROLE booking_app WITH LOGIN PASSWORD 'choose-a-strong-password';
CREATE DATABASE booking OWNER booking_app;
\c booking
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS unaccent;
```

### 2. Put the code on the server

```
sudo git clone <the repository address> /var/www/booking
cd /var/www/booking
sudo git checkout <the version tag you are deploying, e.g. v1.0.0>
```

Deploying a **specific tag**, never a moving branch, is what lets you go back
later.

### 3. Install the application's parts

```
composer install --no-dev --optimize-autoloader
pnpm install --frozen-lockfile
pnpm build
php artisan filament:assets
```

`--no-dev` leaves the testing and development tools out of a production
install. If you build assets on another machine, upload the resulting
`public/build/` folder instead of running `pnpm` here.

### 4. Create the production settings file

```
cp .env.production.example .env
chmod 600 .env
```

Edit `.env` and fill every blank. Same table as
[`production-with-docker.md`](production-with-docker.md) step 3, with these
differences for a non-Docker host:

| Setting | Value |
| --- | --- |
| `DB_HOST` / `DB_PORT` | `127.0.0.1` / `5432` |
| `REDIS_HOST` / `REDIS_PORT` | `127.0.0.1` / `6379` |
| `DB_USERNAME` / `DB_PASSWORD` | `booking_app` / the password from step 1 |
| `AWS_ENDPOINT` etc. | Your real S3-compatible media bucket |
| `APP_KEY` | `php artisan key:generate` writes it straight into `.env` here |

### 5. Initialise the application

```
php artisan key:generate          # if not already set
php artisan migrate --force
php artisan db:seed --force        # starter reference data + a test admin
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
```

Then create a real administrator and remove the seeded one — the same
`tinker` snippet as
[`production-with-docker.md`](production-with-docker.md) step 8, run as
`php artisan tinker` directly.

### 6. Set file ownership

The web server runs as `www-data`. The application writes only to two
folders:

```
sudo chown -R www-data:www-data /var/www/booking/storage /var/www/booking/bootstrap/cache
```

### 7. Configure nginx

Create a server block. Adapt it from the project's own reference at
[`../../../docker/nginx/default.conf`](../../../docker/nginx/default.conf) — keep
these parts:

- `root` pointing at `/var/www/booking/public`;
- the `try_files ... /index.php?$query_string` fallback;
- the `location ~ \.php$` block forwarding to your PHP-FPM socket (for
  example `unix:/run/php/php8.5-fpm.sock` instead of `app:9000`);
- the per-client rate limit on `^/[a-z]{2}/catalog` (the heaviest page) and
  its `429` response;
- `location ~ /\. { deny all; }` — never serve dotfiles; `.env` sits just
  above the web root.

Add the TLS certificate (certbot can edit the block for you) and a redirect
from port 80 to 443. Reload nginx.

### 8. Configure the background processes

Create a Supervisor config, for example
`/etc/supervisor/conf.d/booking.conf`:

```
[program:booking-horizon]
command=php /var/www/booking/artisan horizon
user=www-data
autostart=true
autorestart=true
stopwaitsecs=3600

[program:booking-scheduler]
command=php /var/www/booking/artisan schedule:work
user=www-data
autostart=true
autorestart=true

[program:booking-pulse]
command=php /var/www/booking/artisan pulse:work
user=www-data
autostart=true
autorestart=true
```

Then:

```
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status      # all three should show RUNNING
```

(As an alternative to `schedule:work`, a single crontab line also works:
`* * * * * cd /var/www/booking && php artisan schedule:run >> /dev/null 2>&1`.)

### 9. Confirm

- `curl -i https://your-domain/up` returns `200`.
- `https://your-domain/en` loads over HTTPS, styled.
- `https://your-domain/<your admin path>` signs in.
- `supervisorctl status` shows all three background programs `RUNNING`.

## Updating to a new version later (manual release)

There is no pipeline here; you perform the release by hand. In order:

```
cd /var/www/booking
php artisan down --secret="some-random-string"     # maintenance mode; the secret lets you preview
sudo git fetch --tags
sudo git checkout <new version tag>
composer install --no-dev --optimize-autoloader
pnpm install --frozen-lockfile && pnpm build       # or upload a prebuilt public/build/
php artisan filament:assets
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan event:cache && php artisan view:cache
sudo chown -R www-data:www-data storage bootstrap/cache
sudo systemctl reload php8.5-fpm
sudo supervisorctl restart booking-horizon booking-scheduler booking-pulse
php artisan up                                     # leave maintenance mode
```

Then check `https://your-domain/up` returns `200` and the site loads.

**To roll back:** repeat the sequence with the *previous* tag. If the failed
release ran a migration that removed or changed data, a code rollback is not
enough — you must restore the database from a backup. See
[`../../operations/en/restore.md`](../../operations/en/restore.md) and
[`../../backups.md`](../../backups.md).

## If something does not work

- **`/up` is not 200** → check `storage/logs/laravel.log`, then PHP-FPM's
  own log. A 500 with an empty application log is almost always folder
  ownership (step 6).
- **The site redirects to an address that does not answer, or assets 404** →
  `APP_URL` in `.env` does not exactly match `https://your-domain`. Fix it,
  then `php artisan config:cache`.
- **Panels render unstyled** → `php artisan filament:assets` was not run, or
  the `public/build/` folder is missing.
- **Background jobs never run** → `supervisorctl status`; check the programs
  are `RUNNING` and read their logs.
- **Anything else** → [`troubleshooting.md`](troubleshooting.md), then a
  developer.
