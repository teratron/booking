# Tourism Portal

International tourism portal-directory for **Moldova, Ukraine, and Georgia**.
It publishes tourism objects and hands the visitor straight to the owner's
phone or messenger — **not a booking system**. Revenue is paid placement
sold to object owners.

## Stack

- **PHP 8.5** / **Laravel 13** monolith, `declare(strict_types=1)` everywhere.
- **Blade + Livewire 4 + Alpine + Tailwind 4**, bundled by **Vite**; public site.
- **Filament 5** — two panels from one toolkit: staff panel and owner cabinet.
- **PostgreSQL 18 + PostGIS** (`pg_trgm`, `unaccent`); **Redis 8**; queues via **Horizon**.
- **S3-compatible** object storage (MinIO locally, R2/B2 in production).
- **MapLibre GL** maps; **Sentry** error tracking; **Pulse** performance monitoring.
- Package managers: **Composer** (PHP), **pnpm** (assets, JS lint/analysis).

## Quick start (local, Docker)

Needs only **Docker** and **Git**.

```bash
git clone <repository-url> booking && cd booking
cp .env.example .env
docker compose up -d --build

docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan filament:assets
docker compose exec -e CI=true app pnpm install
docker compose exec app pnpm build
```

Then open [`http://localhost:8300`](http://localhost:8300) (redirects to `/en`).

Starter administrator (local only): `test@example.com` / `password`.

For every other path — local without Docker, production with or without
Docker, in English or Russian, written for a non-technical reader — see
[`docs/setup/`](docs/setup/README.md).

## Key addresses (local)

| Surface | URL |
| --- | --- |
| Public site | `http://localhost:8300/en`, `/ru` |
| Staff panel | `http://localhost:8300/portal-admin` (path is configurable) |
| Owner cabinet | `http://localhost:8300/cabinet` |
| Health check | `http://localhost:8300/up` |
| Queue dashboard (Horizon) | `http://localhost:8300/horizon` |
| Performance (Pulse) | `http://localhost:8300/pulse` |
| Mail catcher (Mailpit) | `http://localhost:8325` |
| Object storage console (MinIO) | `http://localhost:9101` |

## Common tasks

| Task | Command (prefix with `docker compose exec app` under Docker) |
| --- | --- |
| Run the dev stack natively (server, queue, logs, Vite) | `composer dev` |
| Format code | `composer fix` / `pnpm run fix` |
| Full quality gate (lint, PHPStan L8, Pest, coverage, audit, unused) | `composer quality` |
| JS/CSS quality gate (Biome, Fallow) | `pnpm run quality` |
| Architecture tests only | `composer test:arch` |
| Realistic-volume tests | `composer test:slow` |
| Rebuild the database | `php artisan migrate:fresh --seed` |
| Clear all caches | `php artisan optimize:clear` |

CI runs `composer quality` on every push (`.github/workflows/quality.yml`).

## Project layout

```
app/Models/        Eloquent models — relations, casts, scopes only
app/Services/       Business logic (ranking, placement, statistics, …)
app/Filament/       Admin/ and Cabinet/ panels — resources, pages, widgets
app/Livewire/       Public-site interactive components (catalog, map, filters)
app/Policies/       Server-side authorization, incl. geo/category-scoped rules
app/Jobs/           Queued work: expiry sweeps, rollups, backups, sitemaps
database/           migrations, factories, seeders (registries)
resources/          Blade views, lang/ catalogs (en, ru), css/ js/
routes/console.php  Scheduled jobs (dispatched by the scheduler process)
docker/             Local + production infrastructure
docs/               Setup guides, operational runbooks, release process
.design/            Specifications — read-only for implementation work
```

## Documentation

- **Setup, first run, everyday tasks** (EN + RU, non-technical):
  [`docs/setup/`](docs/setup/README.md)
- **Day-to-day operations** — deploy, roll back, restore, rotate a
  credential, run a scheduled job, read a failed pipeline (EN + RU + an
  agent rendering): [`docs/operations/`](docs/operations/)
- **System runbooks** — database schema, backups, storage/CDN, mail and
  error tracking, queues, worker capacity: [`docs/README.md`](docs/README.md)
- **Release process** — branching model and the CI pipelines:
  [`docs/release/`](docs/release/)
