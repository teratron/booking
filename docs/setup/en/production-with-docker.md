# Put It on a Real Server, With Docker

This is the **designed** way to run the live site. It is a **one-time
setup**, normally done by a developer. After it is done once, every new
version of the site ships **by itself** through the release process in
[`../../operations/en/deploy.md`](../../operations/en/deploy.md) — you never repeat
this page.

If the server is already set up and you just need to release a new version,
you are on the wrong page: use
[`../../operations/en/deploy.md`](../../operations/en/deploy.md).

Read [`overview.md`](overview.md) first if you have not.

## What this setup gives you

- The live site on your domain, over HTTPS.
- Every release built once by an automated pipeline, approved by a named
  person, deployed with a brief maintenance window, and health-checked
  automatically — with **one-command rollback** if the check fails.
- Nightly database backups and weekly media backups to storage that is
  separate from the site itself.

## Who should do this

A developer, or someone comfortable with a Linux server, a domain's DNS, and
a GitHub project's settings. It involves creating accounts and secrets with
outside providers. Budget an afternoon for a first-time run.

## Before you start

Have these ready:

- **A Linux server** you control (a virtual machine at any hosting provider
  is fine). Reasonable starting size: 2 CPU, 4 GB RAM, 40 GB disk; see
  [`../../php-fpm-capacity.md`](../../php-fpm-capacity.md) for sizing the web
  worker pool to real traffic.
- **A domain name** with its DNS pointed at that server.
- **Docker Engine** and the **Compose plugin** installed on the server.
- **An S3-compatible storage account** — Cloudflare R2 or Backblaze B2 —
  with **two buckets**: one for media, one for backups. They must be
  separate. See [`../../production-provisioning.md`](../../production-provisioning.md).
- **A mail relay** account (Postmark, Amazon SES, Resend, or a self-hosted
  relay) with SMTP credentials. See
  [`../../mail-and-error-tracking.md`](../../mail-and-error-tracking.md).
- **A map tile key** from MapTiler (or another provider). Public OpenStreetMap
  tile servers are not allowed for production use.
- Optional but recommended: a **Sentry** or self-hosted **GlitchTip**
  project, for its crash-report address (DSN).
- **Access to this project on GitHub**, with permission to change repository
  and environment settings.

## Steps

### 1. Prepare the server

Install Docker and the Compose plugin, then confirm:

```
docker --version
docker compose version
```

Put a copy of the project on the server (the deploy automation checks the
code out itself, but the server needs `docker-compose.production.yml`, the
`docker/` folder, and your `.env`):

```
git clone <the repository address> /opt/booking
cd /opt/booking
```

### 2. Provision the outside services

Create, and write down the credentials for:

1. The **media bucket** and the **backup bucket** (separate).
2. The **mail relay** — SMTP host, port, username, password.
3. The **map tile key**.
4. Optionally the **Sentry / GlitchTip DSN**.

Details and provider-specific notes:
[`../../production-provisioning.md`](../../production-provisioning.md),
[`../../mail-and-error-tracking.md`](../../mail-and-error-tracking.md).

### 3. Create the production settings file

```
cp .env.production.example .env
```

Then edit `.env` and fill **every blank**. The important ones:

| Setting | Value |
| --- | --- |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://your-domain` (exactly, with `https://`) |
| `APP_KEY` | Generate one: `docker compose -p booking-production -f docker-compose.production.yml run --rm app php artisan key:generate --show`, then paste the `base64:...` value here. |
| `ADMIN_PANEL_PATH` | A non-obvious path of your choice (not `admin`). Write it down — it is how your staff reach the panel. |
| `CABINET_PANEL_PATH` | The owner cabinet path (default `cabinet` is fine). |
| `DB_PASSWORD` | A strong password you choose now. |
| `REDIS_PASSWORD` | A strong password you choose now. |
| `SESSION_SECURE_COOKIE` | `true` (the site is HTTPS-only). |
| `AWS_*` | The media bucket's endpoint, region, key, secret, bucket name. |
| `MEDIA_CDN_URL` | Your CDN address in front of the media bucket, if you have one. |
| `MAIL_*` | The relay's host, port, username, password; `MAIL_FROM_ADDRESS` on your domain. |
| `SENTRY_LARAVEL_DSN` | The DSN from step 2, if you created one. Do not leave this empty once you are live. |
| `MAP_TILE_KEY` | Your map tile key. |
| `BACKUP_AWS_BUCKET` | The **backup** bucket name (not the media one). |

Keep this file readable only by the owner (`chmod 600 .env`). It never goes
into Git and never leaves this server.

### 4. Set up HTTPS in front of the site

The production setup's own web entrance listens on plain port 80 inside the
server. Put one of these in front of it to terminate HTTPS:

- **Cloudflare** (or another CDN) in front of the domain, set to encrypt to
  the origin; or
- **A reverse proxy on the server itself** (nginx, Caddy, or Traefik) holding
  the TLS certificate and forwarding to port 80.

See [`../../production-provisioning.md`](../../production-provisioning.md) for the
CDN arrangement the project expects.

### 5. Wire up the automated release pipeline

This is what makes future releases a one-click affair. On GitHub, in this
project's settings:

1. **Container registry** — the pipeline publishes the built image to GitHub's
   own container registry using the built-in token; no extra credential
   needed.
2. **A `production` environment** with a **required reviewer** — the one
   person who must approve every release before it touches the server.
3. **A self-hosted runner** installed **on this server**, labelled
   `self-hosted` and `production`. The server then *pulls* its work from
   GitHub; nothing needs inbound access to the server.
4. **Secrets** the pipeline reads:
   - `MAP_TILE_KEY` — the same key as in `.env` (the pipeline bakes the
     public half into the compiled scripts).
   - `MAINTENANCE_BYPASS_SECRET` — a random string you choose; it lets the
     release operator preview the new version during the maintenance window.

Full description of the pipeline and its jobs:
[`../../release/pipeline.md`](../../release/pipeline.md).

### 6. Run the first release

Follow [`../../operations/en/deploy.md`](../../operations/en/deploy.md) from step 1.
The first release is special in one way only: because nothing is running yet,
the deploy automation skips the maintenance window and just starts
everything. Everything else — pushing a version tag, the safety scan, the
build, the reviewer's approval, the health check, the release record — is
identical to every later release.

When it finishes, the site's own health address answers:

```
curl -i https://your-domain/up      # expect: HTTP/.. 200
```

### 7. Load the starter data

The deploy automation brings the database's **shape** up to date, but it does
**not** load the starter reference data (languages, countries, territory
levels, object types, roles, permissions). The site cannot function without
it. Run once, on the server:

```
docker compose -p booking-production -f docker-compose.production.yml \
  exec app php artisan db:seed --force
```

This also creates a `test@example.com` administrator. **Immediately** either
delete it or change its email and password — see step 8.

**You should see:** `Database seeding completed successfully`.

### 8. Create the real administrator

Do **not** keep the seeded `test@example.com` account on a live server. Open
a terminal inside the app and create a proper account (replace the name,
email, and password):

```
docker compose -p booking-production -f docker-compose.production.yml exec app php artisan tinker
```

Then paste:

```php
$user = App\Models\User::create([
    'name' => 'Site Administrator',
    'email' => 'you@your-domain',
    'password' => Illuminate\Support\Facades\Hash::make('a-long-strong-password'),
    'email_verified_at' => now(),
]);
app(App\Services\Authorization\RoleGrantService::class)
    ->grantRole($user, 'chief_administrator', $user);
App\Models\User::where('email', 'test@example.com')->delete();
```

Type `exit` to leave. The `chief_administrator` role requires a second login
factor, so your first sign-in walks you through setting up an authenticator
app. See [`first-run-and-daily-tasks.md`](first-run-and-daily-tasks.md).

### 9. Confirm and hand over

- `https://your-domain/en` loads over HTTPS, styled, with your content.
- `https://your-domain/<your admin path>` shows a sign-in page and you can
  sign in as the administrator from step 8.
- `https://your-domain/horizon` shows the queue dashboard (signed in as the
  administrator).
- A GitHub "Release" entry exists for the version you shipped, marked
  successful.

## From here on

- **New versions:** [`../../operations/en/deploy.md`](../../operations/en/deploy.md).
- **Rolling back a bad release:**
  [`../../operations/en/rollback.md`](../../operations/en/rollback.md).
- **Backups and restoring from one:** [`../../backups.md`](../../backups.md),
  [`../../operations/en/restore.md`](../../operations/en/restore.md).
- **Rotating a password or key:**
  [`../../operations/en/rotate-credentials.md`](../../operations/en/rotate-credentials.md).
- **Running a scheduled job by hand:**
  [`../../operations/en/run-scheduled-job.md`](../../operations/en/run-scheduled-job.md).
- **Watching performance and the queues:**
  [`../../queues-and-observability.md`](../../queues-and-observability.md).

## If something does not work

- **The release pipeline shows a red step** → do not touch the server by
  hand. Follow
  [`../../operations/en/read-a-failed-pipeline.md`](../../operations/en/read-a-failed-pipeline.md).
- **`/up` returns anything other than 200 after the first release** → the
  release automation rolls back on its own; if it cannot restore health it
  stops and leaves the site in maintenance mode for a person. See
  [`../../operations/en/restore.md`](../../operations/en/restore.md).
- **The panels render unstyled on the live site** → the built image is
  missing its panel assets; this is a build problem, not something to patch
  on the server. Tell a developer.
- **Anything else** → [`troubleshooting.md`](troubleshooting.md), then a
  developer.
