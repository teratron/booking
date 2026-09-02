# First Run — Check It Worked, Sign In, Everyday Tasks

Do this right after any of the four setup guides. It confirms the site is
genuinely working and gets you signed in, then lists the handful of commands
you will use from day to day.

Throughout, **`BASE`** means the address your site answers on:

- Local with Docker: `http://localhost:8300`
- Local without Docker: whatever you set `APP_URL` to (often
  `http://127.0.0.1:8000`)
- Production: `https://your-domain`

## Part 1 — Confirm it worked

Check these in order. Each one that passes rules out a whole class of
problem.

### 1. The application is alive

Open **`BASE/up`** in a browser, or run `curl -i BASE/up`.

**Pass:** a plain page reading `OK`, or an HTTP `200` status.
**Fail:** a `500` error, a timeout, or "connection refused" → the app is not
running or cannot reach its database/Redis. See
[`troubleshooting.md`](troubleshooting.md).

### 2. The public site renders

Open **`BASE`**. It should redirect to **`BASE/en`**.

**Pass:** the tourism portal home page, **with styling** (colours, layout,
fonts), showing territory names and object cards.
**Fail — unstyled text only:** the front-end build did not happen → run
`pnpm build` (and `filament:assets`).
**Fail — redirects somewhere that will not open:** `APP_URL` is wrong → see
[`troubleshooting.md`](troubleshooting.md) → "Wrong address / broken links".

### 3. The two panels load

- **`BASE/portal-admin`** (or your custom admin path) → a sign-in page.
- **`BASE/cabinet`** → a sign-in page.

**Pass:** both show a proper, **styled** sign-in form.
**Fail — unstyled form:** run `php artisan filament:assets`.

### 4. (Local only) the support tools

- Mail catcher: `http://localhost:8325`
- File storage console: `http://localhost:9101` (sign in with the
  `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` from `.env`)
- Queue dashboard: `BASE/horizon` (after you sign in as an administrator)

## Part 2 — Sign in for the first time

### On a local install

A starter administrator was created by `migrate --seed`:

- **Email:** `test@example.com`
- **Password:** `password`

Go to `BASE/portal-admin`, sign in, and follow the prompt to set up a second
login factor (an authenticator app such as Google Authenticator, 1Password,
or Aegis) — the top administrator role requires it. Keep the recovery codes
it shows you.

This account is **local only**. It must never exist on a real server.

### On a real server

You created your own administrator during the production setup guide (the
`tinker` snippet) and deleted `test@example.com`. Sign in at
`BASE/<your admin path>` with that account and set up its second factor.

If you need to grant administrator rights to an existing user later:

```
php artisan tinker
```

```php
$user = App\Models\User::where('email', 'person@example.com')->firstOrFail();
app(App\Services\Authorization\RoleGrantService::class)
    ->grantRole($user, 'chief_administrator', $user);
```

(Prefix with `docker compose -p booking-production -f docker-compose.production.yml exec app`
on a Docker production host.)

## Part 3 — Everyday commands

Two columns: with Docker, run the command shown; **prefix** each with
`docker compose exec app` (local) or
`docker compose -p booking-production -f docker-compose.production.yml exec app`
(production). Without Docker, run it directly in the project folder.

| Task | Command |
| --- | --- |
| Start the site (Docker, already set up) | `docker compose up -d` |
| Stop the site (Docker) | `docker compose stop` |
| See the live application log | `docker compose logs -f app` — or without Docker, `php artisan pail`, or read `storage/logs/laravel.log` |
| Clear all caches (after odd behaviour) | `php artisan optimize:clear` (clears config, routes, views, events, and the app cache in one go) |
| Apply new database changes after pulling code | `php artisan migrate` |
| Rebuild the front-end after pulling code | `pnpm install && pnpm build` |
| Republish the panel styling | `php artisan filament:assets` |
| See background jobs and failures | open `BASE/horizon` |
| Retry all failed jobs | `php artisan queue:retry all` |
| See site performance | open `BASE/pulse` |
| Run a scheduled job right now | see [`../../operations/en/run-scheduled-job.md`](../../operations/en/run-scheduled-job.md) |
| Check code quality before committing | `composer quality` (PHP) and `pnpm run quality` (styling/scripts) |

### Updating a local copy to the latest code

```
git pull
composer install
php artisan migrate
php artisan filament:assets
pnpm install && pnpm build
php artisan optimize:clear
```

With Docker, prefix each with `docker compose exec app`, and run
`docker compose up -d --build` first if the Docker recipe itself changed.

### Updating a real server

Never by hand on the Docker production setup — use
[`../../operations/en/deploy.md`](../../operations/en/deploy.md). On a non-Docker
server, follow the "manual release" section of
[`production-without-docker.md`](production-without-docker.md).

## Part 4 — Where the deeper guides are

| I need to… | Guide |
| --- | --- |
| Ship a new version to production | [`../../operations/en/deploy.md`](../../operations/en/deploy.md) |
| Undo a bad release | [`../../operations/en/rollback.md`](../../operations/en/rollback.md) |
| Restore the database from a backup | [`../../operations/en/restore.md`](../../operations/en/restore.md) |
| Change a password, key, or token | [`../../operations/en/rotate-credentials.md`](../../operations/en/rotate-credentials.md) |
| Understand a failed automated pipeline | [`../../operations/en/read-a-failed-pipeline.md`](../../operations/en/read-a-failed-pipeline.md) |
| Learn how backups work | [`../../backups.md`](../../backups.md) |
| Learn the database structure | [`../../database-schema.md`](../../database-schema.md) |
| Set up storage, CDN, mail, error tracking | [`../../production-provisioning.md`](../../production-provisioning.md), [`../../mail-and-error-tracking.md`](../../mail-and-error-tracking.md) |
| Watch queues and workers | [`../../queues-and-observability.md`](../../queues-and-observability.md) |
