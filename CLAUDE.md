# Project Instructions

International tourism portal-directory for Moldova, Ukraine, and Georgia.
**Not a booking system** — it publishes objects and hands visitors directly to the
owner's phone or messenger. Revenue is paid placement sold to object owners.

## Tech Stack

- **Language**: PHP 8.4+, `declare(strict_types=1)` in every file.
- **Framework**: Laravel 13 — monolith. Blade + Livewire for the public site; no separate frontend application.
- **Admin & owner cabinet**: Filament 5 — two panels from one toolkit (`/admin` for staff, `/cabinet` for object owners).
- **Database**: PostgreSQL 18 + PostGIS. Extensions: `postgis`, `pg_trgm`, `unaccent`.
- **Cache / queue / session**: Redis 8; queues via Laravel Horizon.
- **Object storage**: S3-compatible (MinIO locally, Cloudflare R2 / Backblaze B2 in production).
- **Frontend**: Blade + Livewire 3 + Alpine.js + Tailwind CSS 4, bundled by Vite.
- **Maps**: MapLibre GL JS with a paid or self-hosted tile provider.
- **Package manager**: Composer (PHP), pnpm (asset pipeline only).
- **Quality**: Pest (tests), PHPStan level 8 via Larastan, Laravel Pint (formatting), Rector (upgrades).

Always install the latest stable release of every package; do not pin back a major
version as a precaution.

## Required Packages

Each maps to a specification requirement — do not hand-build what these already cover.

| Package | Covers |
| --- | --- |
| `filament/filament` | Admin panel and owner cabinet |
| `spatie/laravel-permission` | Roles and permissions |
| `astrotomic/laravel-translatable` | Per-entity translations in **separate tables** |
| `spatie/laravel-medialibrary` | Media upload, conversions, thumbnails, ordering |
| `owen-it/laravel-auditing` | Action journal with old/new values |
| `spatie/laravel-backup` | Scheduled backups, retention, integrity checks |
| `spatie/laravel-sitemap` | Sitemap generation |
| `staudenmeir/laravel-adjacency-list` | Recursive territory hierarchy (CTE) |
| `laravel/sanctum` | API tokens |
| `laravel/horizon` | Queue monitoring |
| `laravel/scout` | Search abstraction (Postgres driver first, Typesense later) |
| `pragmarx/google2fa-laravel` | Two-factor authentication |
| Filament import/export actions | XLSX / CSV import and export |

## Project Structure

```plaintext
app/
├── Models/                 # Eloquent models
├── Filament/
│   ├── Admin/              # Staff panel: resources, pages, widgets
│   └── Cabinet/            # Owner panel: resources scoped to the owner
├── Livewire/               # Public-site interactive components (catalog, map, filters)
├── Services/               # Business logic — ranking, bumps, banner targeting, statistics
├── Policies/               # Authorization, including geo/category-scoped rules
├── Jobs/                   # Queued work: expiry sweeps, notifications, rollups
├── Console/Commands/       # Scheduled entry points
└── Support/                # Cross-cutting helpers

resources/
├── views/                  # Blade templates (public site)
├── lang/                   # Interface translation catalogs
├── css/  js/               # Tailwind + Alpine, bundled by Vite

database/
├── migrations/
├── factories/
└── seeders/                # Registries: languages, countries, territory levels,
                            # object types, amenities, tiers, roles, permissions

docker/                     # Local infrastructure (Postgres init SQL, etc.)
.design/                    # Specifications — read-only for implementation work
```

## Implementation Guidelines

- **Business logic lives in `app/Services/`**, not in controllers, Livewire components,
  Filament resources, or models. Models hold relations, casts, and scopes only.
- **Authorization is server-side, always.** Hiding a Filament action or a Blade block is
  a usability affordance and never an access control. Permissions may be scoped to a
  country, territory subtree, or object category — enforce that in Policies.
- **Every user-facing string is translatable.** No literal copy in Blade, Livewire, or
  Filament labels. Entity text lives in translation tables, not JSON columns.
- **Never hard-code the language or country count.** Both are runtime registries.
- **Catalog ordering is placement-tier first.** A lower-tier object must never outrank a
  higher-tier one except by an explicit administrator pin. Do not "improve" this into
  relevance-first ordering.
- **Soft delete by default** for objects, users, news, promotions, banners, articles.
  Filter in a global scope, not per query.
- **Scheduled work belongs in Jobs**, dispatched by the scheduler — never executed
  during a web request.
- Prefer Filament's own abstractions (resources, relation managers, actions, widgets)
  over custom pages. Reach for a custom page only when the resource model genuinely
  does not fit.

## Filament Conventions

- One panel provider per audience: `AdminPanelProvider` and `CabinetPanelProvider`.
- The cabinet panel scopes **every** resource query to the authenticated owner. Enforce
  it in the resource's base query and in the Policy — never in the UI alone.
- Register permissions as Filament resource policies, not as inline `visible()` closures.
- Moderation uses record versions: the published record stays untouched while a pending
  revision exists, so a rejected edit can never damage a live page.
- Bulk actions require a confirmation naming the affected record count.

## Specification Layer

`.design/` holds the specifications this project implements. Treat them as the source
of truth for behaviour, and as **read-only** during implementation work — they change
through the `/magic.spec` workflow, not by editing files directly.

Never reference specification artefacts from product code: no task IDs, no phase
names, no `.design/…` paths, no spec file names in comments, identifiers, or strings.
If design rationale matters at a code site, restate it in plain language.

## Verification

Run and verify before marking any task complete:

- `vendor/bin/pint --test` — zero formatting violations.
- `vendor/bin/phpstan analyse` — level 8, zero errors.
- `php artisan test` — full suite green.
- `php artisan migrate:fresh --seed` on a scratch database — migrations and seeders
  apply cleanly from empty.

## Completion Protocol (Mandatory Checklist)

Before declaring any task complete, verify every item:

- [ ] **Quality gates**: Pint, PHPStan level 8, Pest suite, and a clean `migrate:fresh --seed` all pass.
- [ ] **Language policy**: all code, identifiers, comments, documentation, and commit messages in English; all chat interaction in Russian.
- [ ] **Architecture**: business logic in `app/Services/`; models thin; authorization enforced in Policies; no logic in Filament resources or Blade.
- [ ] **Localization**: no hard-coded user-facing strings; no hard-coded language or country counts.
- [ ] **Ordering**: placement-tier precedence preserved wherever objects are listed.
- [ ] **Specification containment**: no `.design/` references, task IDs, or spec file names in product code.
- [ ] **Formatting**: no horizontal rules (`---`) in document bodies except in a footer.
