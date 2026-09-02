# Run It on Your Own Computer, Without Docker

Use this only if you cannot or do not want to use Docker. It gives the same
result as [`local-with-docker.md`](local-with-docker.md), but you install and
start every piece yourself: PHP, PostgreSQL, Redis, Node.js, file storage,
and the background processes.

This is noticeably more work and more moving parts. If Docker is an option,
use the Docker guide instead.

Read [`overview.md`](overview.md) first if you have not.

## What you will end up with

The same as the Docker guide — the public site, the staff panel, the owner
cabinet, and a starter administrator — but reachable at whatever web address
**you** configure (commonly `http://127.0.0.1:8000` from the built-in server,
or a `.test` domain if you use Laravel Herd).

## Before you start — install the runtimes

You need, at these major versions:

| Software | Version | Notes |
| --- | --- | --- |
| **PHP** | 8.5 | With extensions: `intl`, `pdo_pgsql`, `pgsql`, `redis`, `imagick`, `gd`, `zip`, `bcmath`, `exif`, `pcntl`, `opcache`. |
| **Composer** | 2.x | The PHP package manager. |
| **PostgreSQL** | 18 | With **PostGIS**, and the `pg_trgm` and `unaccent` extensions available. |
| **Redis** | 8 | |
| **Node.js** | 24 | |
| **pnpm** | 11.x | Install with `npm install -g pnpm`, or enable it with `corepack enable`. |
| **postgresql-client** | 18 | Provides `pg_dump`, used by the backup job. Match the server's major version. |

Recommended shortcuts:

- **Windows or macOS:** [Laravel Herd](https://herd.laravel.com) provides PHP
  (multiple versions), nginx, and a `.test` domain out of the box. Herd Pro
  adds PostgreSQL and Redis. This is the smoothest path on those systems.
- **Linux:** install the packages from your distribution or from the
  well-known PHP and PostgreSQL repositories.

Optional, to match the Docker experience:

- **MinIO** (a small S3-compatible storage server) — or point storage at a
  real cloud bucket instead.
- **Mailpit** (a local mail catcher) — or let mail attempts simply fail in
  development.

Verify the core versions before continuing:

```
php -v          # must report 8.5.x
composer -V
psql --version  # must report 18.x
redis-cli --version
node -v          # must report v24.x
pnpm -v
```

> On some Windows setups the plain `php` command points at an older version
> even after installing 8.5. If `php -v` is not 8.5, call the versioned
> binary directly (for example `php85`) for every command below, or make 8.5
> the default in your PHP manager. See
> [`troubleshooting.md`](troubleshooting.md) → "`php -v` shows the wrong
> version".

## Steps

### 1. Create the databases and extensions

Open a PostgreSQL admin shell (`psql -U postgres`) and run:

```
CREATE ROLE booking WITH LOGIN PASSWORD 'booking';
CREATE DATABASE booking OWNER booking;
CREATE DATABASE booking_testing OWNER booking;
\c booking
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS unaccent;
\c booking_testing
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS unaccent;
```

`booking_testing` is only used when running the automated test suite; create
it now so you never have to come back to this step.

**You should see:** `\l` lists both `booking` and `booking_testing`.

### 2. Get the code

```
git clone <the repository address> booking
cd booking
```

Every command below is run inside this folder.

### 3. Create and edit your settings file

```
cp .env.example .env      # Windows: Copy-Item .env.example .env
```

Now **edit `.env`** in a text editor. The example file assumes Docker; you
must repoint it at your local installs:

| Setting | Set it to | Why |
| --- | --- | --- |
| `APP_URL` | The exact address you will type in the browser, including `http://` and any port — e.g. `http://127.0.0.1:8000`, or `http://booking.test` if using a Herd domain. | The site builds its links and redirects from this. A wrong value sends you to an address that does not answer. |
| `DB_HOST` | `127.0.0.1` | Your local PostgreSQL. |
| `DB_PORT` | `5432` (the normal PostgreSQL port) | The example uses `5433` for a Docker-specific reason that does not apply here. |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | `booking` / `booking` / `booking` (matching step 1) | |
| `REDIS_HOST` / `REDIS_PORT` | `127.0.0.1` / `6379` | Your local Redis. |
| `MAIL_HOST` / `MAIL_PORT` | `127.0.0.1` / `1025` if running Mailpit; otherwise leave as is | |
| `AWS_ENDPOINT` | `http://127.0.0.1:9000` if running MinIO locally; otherwise your real bucket's endpoint | |
| `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` / `AWS_BUCKET` | Match your MinIO or cloud bucket | |

If you run MinIO locally, also create the two buckets it needs (`booking-media`
and `booking-backups`) from its console or with its `mc` client.

**You should see:** `.env` now points every address at `127.0.0.1` (or your
chosen hosts), not at Docker service names like `postgres` or `minio`.

### 4. Install the application's parts

```
composer install
php artisan key:generate
php artisan migrate --seed
php artisan filament:assets
pnpm install
pnpm build
```

These do exactly what steps 4.1–4.6 of the Docker guide describe: PHP
libraries, the app secret, the database shape and starter data (including
the `test@example.com` / `password` administrator), the panel styling, the
front-end libraries, and the compiled styling and scripts.

**You should see:** `migrate --seed` ends with `Database seeding completed
successfully`.

### 5. Start the web server

Choose one:

- **Laravel Herd (Windows/macOS):** run `herd link` inside the folder once;
  Herd then serves it at `http://<folder-name>.test` continuously. Make sure
  `APP_URL` in `.env` matches that address.
- **Built-in server (any system):** run

  ```
  composer dev
  ```

  This starts four things together: the web server on `http://127.0.0.1:8000`,
  the queue worker, a live log viewer, and the styling live-reload. Leave the
  terminal open. Make sure `APP_URL` is `http://127.0.0.1:8000`.

  A bare `php artisan serve` also works if you only want the web server.

**You should see:** opening `APP_URL` in a browser shows the styled home
page.

### 6. Start the background processes

Docker ran these for you; here you start them yourself, each in its own
terminal, from the project folder:

```
php artisan horizon         # the queue worker
php artisan schedule:work   # the scheduler
php artisan pulse:work      # the performance dashboard feeder
```

For casual local viewing you can skip these. If you do, be aware:

- Without **`horizon`**: notification emails, imports/exports, and statistics
  rollups never actually run.
- Without **`schedule:work`**: nightly backups, placement expiry, sitemap
  regeneration, and other timed jobs never run.
- Without **`pulse:work`**: the performance dashboard at `/pulse` stays
  empty.

`composer dev` (step 5) already runs the queue worker, so if you use it you
only need `schedule:work` and `pulse:work` separately.

## How to tell it worked

Go through [`first-run-and-daily-tasks.md`](first-run-and-daily-tasks.md).
In short: `APP_URL` + `/up` returns `200`; `APP_URL/en` loads and is styled;
`APP_URL/portal-admin` shows a sign-in page; you can sign in with
`test@example.com` / `password`.

## Updating to newer code later

```
git pull
composer install
php artisan migrate
php artisan filament:assets
pnpm install
pnpm build
php artisan config:clear && php artisan cache:clear
```

Then restart `composer dev` (or the Herd site) and the background processes.

## If something does not work

- **`php -v` is not 8.5** → [`troubleshooting.md`](troubleshooting.md).
- **The site redirects somewhere that does not answer, or styling/links
  point at the wrong address** → `APP_URL` in `.env` does not match the
  address you are opening. Fix it, then `php artisan config:clear` and
  `php artisan cache:clear`.
- **"could not connect" / "connection refused" on startup** → PostgreSQL or
  Redis is not running, or `DB_*` / `REDIS_*` in `.env` do not match where
  they actually listen.
- **`migrate` fails mentioning `postgis`, `pg_trgm`, or `unaccent`** → step 1
  was skipped or ran against the wrong database.
- **The panels render as unstyled text** → run `php artisan filament:assets`
  again.
- **Anything else** → [`troubleshooting.md`](troubleshooting.md).
