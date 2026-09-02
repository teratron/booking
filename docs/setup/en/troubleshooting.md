# Troubleshooting Setup

Concrete problems seen while getting this project running, and what fixes
each one. Find your symptom.

## A port is already in use

**Symptom:** `docker compose up` fails with "port is already allocated" or
"bind: address already in use", naming a number like `8300`, `5433`, `6379`,
`9000`, `9101`, `1025`, or `8325`.

**Cause:** another program on your machine already listens on that number.
On Windows, whole ranges can also be **reserved by Hyper-V** — a bind inside
a reserved range fails with a *permissions* error rather than "in use". Check
reserved ranges with `netsh interface ipv4 show excludedportrange protocol=tcp`.

**Fix:** remap the clashing port without editing the tracked compose file.
Create `docker-compose.override.yml` next to `docker-compose.yml` — Docker
merges it automatically. Example, moving PostgreSQL off `5433`:

```yaml
services:
  postgres:
    ports: !override
      - "15432:5432"
```

Then in `.env` set `DB_PORT` to the **new** number (`15432` here) so
host-side tools connect to the right place. The application containers are
unaffected — they always reach PostgreSQL as `postgres:5432` on Docker's own
network. The same pattern works for any service; `redis`, `minio`, `nginx`
(`8300:80`), and `mailpit` each expose their host port the same way.

## Wrong address / broken links / redirects that will not open

**Symptom:** the site bounces you to a domain your browser cannot reach; or
the page loads but has no styling; or every link and image points at the
wrong host.

**Cause:** `APP_URL` in `.env` does not match the address you actually open
the site on. The application builds every link, redirect, asset URL, and
canonical tag from `APP_URL`.

**Fix:**

1. Set `APP_URL` to the **exact** address you type in the browser — scheme,
   host, and port. Examples: `http://localhost:8300` (local Docker),
   `http://127.0.0.1:8000` (built-in server), `https://your-domain`
   (production).
2. Clear the caches — some navigation fragments are cached with the old
   address baked in:

   ```
   php artisan config:clear
   php artisan cache:clear
   ```

   (Prefix with `docker compose exec app` under Docker.) On production with
   Docker this is handled by the deploy automation; on a manual server run
   `php artisan config:cache` afterwards.

## `php -v` shows the wrong version (not 8.5)

**Symptom:** `composer install` or `php artisan` complains about the PHP
version, or `php -v` reports 8.1 / 8.3 / 8.4.

**Cause:** the plain `php` on your `PATH` is an older install. Common on
Windows with Laravel Herd, where switching the default does not always
rewrite the global shortcut.

**Fix (any of):**

- Call the versioned binary explicitly for every command — e.g. `php85`
  instead of `php`, or the full path such as
  `C:\Users\<you>\.config\herd\bin\php85\php.exe`.
- In your PHP version manager, make 8.5 the global default (in Herd: the
  tray icon → PHP → 8.5), then open a **new** terminal.
- Point your project's IDE / task runner at the 8.5 binary directly.

The Docker guide avoids this entirely — the container always has 8.5.

## The panels (staff / cabinet) render as unstyled text

**Symptom:** `BASE/portal-admin` and `BASE/cabinet` load, but look like a
bare HTML document with no colours, spacing, or working buttons.

**Cause:** the panel styling and scripts have not been published. They are
deliberately not stored in Git, so a fresh install must generate them.

**Fix:**

```
php artisan filament:assets
```

(Prefix with `docker compose exec app` under Docker.) Re-run this after every
update of the panel toolkit, too.

## HTTP 500 with nothing in the log (Docker)

**Symptom:** pages return a blank `500`, and `storage/logs/laravel.log` has
no matching entry.

**Cause:** the `storage/` or `bootstrap/cache/` folder is not writable by the
web user, so even the logger cannot open its file. Happens when a bind mount
carries host ownership.

**Fix:**

```
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
```

The container's start-up script does this automatically, but a permission
change made afterwards on the host can re-break it.

## Everything is slow under Docker (Windows / macOS)

**Symptom:** each page takes several seconds; the admin panel is painful to
click through.

**Cause:** Docker on Windows and macOS shares your project folder into the
container over a slow file-sharing layer. PHP reads thousands of files per
request; the overhead adds up.

**Fix / workaround:** for heavy interactive work, run **PHP on the host**
while keeping the Dockerised database, Redis, and storage:

1. Keep `docker compose up -d` running (for `postgres`, `redis`, `minio`,
   `mailpit`).
2. In `.env`, point `DB_HOST`/`REDIS_HOST`/`AWS_ENDPOINT`/`MAIL_HOST` at
   `127.0.0.1` and the **published** ports (`DB_PORT=5433` etc.).
3. Set `APP_URL` to the address you will serve on, e.g.
   `http://127.0.0.1:8000`.
4. Run `php artisan serve --port=8000` on the host (PHP 8.5).

Pages then respond in a fraction of the time. Switch the `.env` hosts back to
the Docker service names when you go back to the all-Docker setup.

## `pnpm install` stops and then fails inside a container

**Symptom:** `docker compose exec app pnpm install` prints a question about
removing packages and then aborts, because it cannot read your answer.

**Fix:** run it non-interactively:

```
docker compose exec -e CI=true app pnpm install
```

## `migrate` or `migrate:fresh` fails

- **Mentions `postgis`, `pg_trgm`, or `unaccent`:** the required PostgreSQL
  extensions are not installed in the target database. Under Docker they are
  created automatically on first start; without Docker, run the
  `CREATE EXTENSION` statements from
  [`local-without-docker.md`](local-without-docker.md) step 1 against the
  right database.
- **Mentions a cache or connection error:** Redis is not reachable. Some
  migrations touch the cache as they run, so Redis must be up for a full
  `migrate:fresh`, not just for the queue.
- **"database ... does not exist":** create it, or (Docker) remove the
  database volume and let it re-initialise: `docker compose down -v` then
  `docker compose up -d` (this erases local data).

## Uploaded images 404, or "bucket does not exist"

**Symptom:** the site runs but images fail to load or upload.

**Cause (Docker):** the storage buckets were not created. A one-shot
container (`minio-init`) creates them on `docker compose up`; if it did not
run, or you started only some services:

```
docker compose up -d minio-init
```

**Cause (without Docker):** create the `booking-media` and `booking-backups`
buckets in your MinIO console or with the `mc` client, matching `AWS_BUCKET`
and `BACKUP_AWS_BUCKET` in `.env`.

## PostgreSQL data disappeared after changing images

**Symptom:** after a Docker image change the database looks empty or says it
is uninitialised.

**Cause:** PostgreSQL 18 images store their data one directory level
different from older images. The project's compose file already accounts for
this; a hand-edited volume path is the usual culprit.

**Fix:** keep the `postgres-data:/var/lib/postgresql` volume line from the
tracked `docker-compose.yml` exactly as it is. If the data is already gone,
re-run `migrate --seed`.

## Background jobs never happen (without Docker)

**Symptom:** notification emails never arrive; scheduled sweeps, backups, and
sitemap regeneration never run.

**Cause:** the three background processes are not running. Docker starts them
for you; a manual setup must too.

**Fix:** start (and keep alive, via Supervisor in production):

```
php artisan horizon
php artisan schedule:work
php artisan pulse:work
```

## Locked out of the panel by two-factor authentication

**Symptom:** you set up a second factor, lost the device and the recovery
codes, and cannot sign in.

**Fix:** clear that account's second-factor settings from a terminal, then
sign in with just the password and set it up again:

```
php artisan tinker
```

```php
App\Models\User::where('email', 'you@your-domain')->update([
    'two_factor_secret' => null,
    'two_factor_recovery_codes' => null,
    'two_factor_confirmed_at' => null,
]);
```

## Still stuck

- A failed **release pipeline** on production has its own guide:
  [`../../operations/en/read-a-failed-pipeline.md`](../../operations/en/read-a-failed-pipeline.md).
- For anything else, collect the exact command you ran and its full output,
  and give both to a developer. A screenshot of a red error without its text
  is not enough to act on.
