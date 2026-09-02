# Overview — What This Is and Which Guide to Follow

Read this page once before picking a guide. It explains, in plain language,
what the project is made of and what the four setup guides are for.

## What the project is

An **international tourism portal-directory** for Moldova, Ukraine, and
Georgia. It publishes tourism objects (places to stay, things to visit) and
sends the visitor straight to the owner's phone or messenger. It is **not a
booking system** — no reservations, no payments between visitor and owner.
The business earns money from **paid placement**: object owners pay to be
listed and to rank higher.

It has three faces:

- **The public website** — what visitors see. Every page sits under a
  two-letter language prefix, for example `/en/...` (English) or `/ru/...`
  (Russian). Opening the site's bare address redirects to one of these.
- **The staff panel** — where your team moderates objects, manages owners,
  sells placement, edits content. It lives at a deliberately non-obvious
  address (`/portal-admin` by default, changeable), never at `/admin`.
- **The owner cabinet** — where an object owner manages their own listings.
  Lives at `/cabinet` by default.

## What it is made of

You do not need to understand these to follow a guide, but the words appear
in every guide, so here is what each piece does:

| Piece | Plain-language job |
| --- | --- |
| **The application** | The website itself. Written in PHP with the Laravel framework. |
| **PostgreSQL** (with PostGIS) | The database — the permanent store of every object, owner, territory, booking of paid placement, and so on. PostGIS is the add-on that answers "what is near this point on the map". |
| **Redis** | A fast temporary memory used for caching, login sessions, and the queue of background work. |
| **Object storage** (S3-compatible) | Where uploaded photos and files live. On your own computer this is a small program called **MinIO**; in production it is a cloud bucket (Cloudflare R2 or Backblaze B2). |
| **The queue worker** (Horizon) | A background process that does slow work out of sight — sending notification emails, processing imports, building monthly statistics. |
| **The scheduler** | A background process that runs jobs on a clock — nightly database backup, expiring finished placement, regenerating the sitemap. |
| **Pulse** | A background process plus a dashboard that shows how the site is performing. |
| **Mail** | Outgoing email. On your own computer a catcher called **Mailpit** shows the mail without sending it; in production a real mail relay sends it. |
| **Error tracking** (Sentry) | Optional. Collects crash reports. Off until you give it an address. |

With **Docker**, all of these start together with one command and you never
install them yourself. Without Docker, you install and start each one by
hand.

## The four guides — pick one

There are two questions: **where** is it running, and **how** are the pieces
installed.

| | **With Docker** (recommended) | **Without Docker** |
| --- | --- | --- |
| **On your own computer** (to try it, or to develop) | [`local-with-docker.md`](local-with-docker.md) | [`local-without-docker.md`](local-without-docker.md) |
| **On a real server** (the live site visitors use) | [`production-with-docker.md`](production-with-docker.md) | [`production-without-docker.md`](production-without-docker.md) |

**Recommendation:** use Docker for both. The project is designed and tested
around it — the release process, the automatic health check, and the
one-command rollback all assume the Docker production setup. The "without
Docker" guides exist for the case where Docker genuinely cannot be used;
they are more work and give you fewer safety nets.

After any guide, go to
[`first-run-and-daily-tasks.md`](first-run-and-daily-tasks.md) to confirm it
worked and to sign in for the first time. If a step misbehaves, see
[`troubleshooting.md`](troubleshooting.md).

## A few facts every guide relies on

- The code needs **PHP 8.5**, **PostgreSQL 18**, **Redis 8**, **Node.js 24**,
  and **pnpm** (a package manager for the website's styling and scripts).
  Docker brings the right versions; without Docker you must install them
  yourself.
- The application checks its own health at the address **`/up`**. A plain
  `200 OK` there means it started correctly.
- The bundled example settings file, `.env.example`, is already set up for
  the **local Docker** guide with no edits. Every other guide needs edits,
  which that guide spells out.
- A fresh local install seeds a starter administrator account,
  `test@example.com` with the password `password`. It is for local use only
  — never carry it to a real server. See
  [`first-run-and-daily-tasks.md`](first-run-and-daily-tasks.md).

## Glossary

- **Terminal** / **command line** — the text window where you type the
  commands in these guides. On Windows this is *PowerShell* or *Windows
  Terminal*; on macOS and Linux it is *Terminal*.
- **Repository** / **repo** — the project's folder of code, tracked by the
  tool *Git*.
- **`.env` file** — a plain text file of settings (addresses, passwords,
  switches) that the application reads on startup. It is never shared and
  never committed to Git.
- **Container** — one packaged, running piece (the app, the database, …)
  managed by Docker. "Bring the stack up" means "start all the containers".
- **Migration** — a single, ordered change to the database's shape. "Run
  migrations" means "bring the database's shape up to date with the code".
- **Seeding** — filling a fresh database with the starter data the site
  needs to work at all: the list of languages, countries, territory levels,
  object types, roles, permissions, and so on.
- **Build the assets** — turn the website's raw styling and scripts into the
  compact files a browser downloads. Done with `pnpm build`.
- **Panel assets** — the staff panel's and owner cabinet's own styling and
  scripts. Published with `php artisan filament:assets`. If the panels look
  broken and unstyled, this step was missed.
- **Queue** — the list of background jobs waiting to run. Worked through by
  Horizon.
- **Production** — the real, live website on its real server, as opposed to
  a copy on your computer.
