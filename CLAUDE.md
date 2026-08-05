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

## Design Source — Figma First

Every page and component is built **against the Figma source**, not from a written
description of it. Before writing markup for any screen:

- File: `N2cVVIS5wvjHIviP27peuX` — <https://www.figma.com/design/N2cVVIS5wvjHIviP27peuX/Booking>
- Local copy: `.drafts/Booking.fig`

Workflow: load the `figma-design-to-code` guidance, then pull the node with the Figma
MCP tools (`get_design_context` for layout and tokens, `get_screenshot` to verify,
`get_metadata` to locate nodes). Adapt the returned reference code to Blade + Tailwind
and this project's existing components — never paste it verbatim.

Rules:

- **Design tokens come from Figma**, not from invented values. Colours, spacing, radii,
  and type scale go into the Tailwind theme once and are reused; no magic numbers in
  templates.
- **Extract shared components on second use**, not speculatively — the header, footer,
  object card, badge, and filter controls repeat across nearly every frame.
- **Figma governs visual language and page composition only.** Scope, domain rules, and
  behaviour come from `.design/` specifications. Where the two disagree, the
  specification wins and the divergence is noted.
- Frames exist in desktop and mobile pairs; build one responsive template per page, not
  two.
- Frames with the node prefix `1306:*` belong to an unrelated pasted document and are
  **out of scope**.

## Specification Layer

`.design/` holds the specifications this project implements. Treat them as the source
of truth for behaviour, and as **read-only** during implementation work — they change
through the `/magic.spec` workflow, not by editing files directly.

Never reference specification artefacts from product code: no task IDs, no phase
names, no `.design/…` paths, no spec file names in comments, identifiers, or strings.
If design rationale matters at a code site, restate it in plain language.

## Engineering Discipline

Quality gates run **continuously, not terminally**. After every meaningful change —
not once at the end of a task, and never only before a commit. A gate that runs late
reports a pile of failures nobody wants to untangle; a gate that runs constantly
reports one.

### Toolchain

Declared as Composer scripts so the commands are identical locally and in CI:

| Command | What it runs |
| --- | --- |
| `composer fix` | `pint` — format the codebase |
| `composer lint` | `pint --test` — fail on any formatting drift |
| `composer analyse` | `phpstan analyse` — Larastan, level 8 |
| `composer test` | `pest` — unit, feature, architecture |
| `composer test:arch` | Architecture tests only (fast convention check) |
| `composer test:coverage` | Coverage with the configured minimum |
| `composer bench` | Performance benchmarks (§ below) |
| `composer audit` | `composer audit` — known security advisories |
| `composer unused` | Detect declared-but-unimported dependencies |
| `composer quality` | All of the above, in order — the pre-commit gate |

CI runs `composer quality` on every push. Set it up during scaffolding, not later.

### Architecture Tests

Conventions are enforced by Pest `arch()` tests, not by review discipline — a rule a
machine cannot check is a rule that erodes. At minimum:

- `declare(strict_types=1)` in every file.
- No `dd`, `dump`, `var_dump`, `ray`, or `print_r` anywhere outside tests.
- Models live only in `App\Models` and hold no business logic.
- `App\Filament` and `App\Livewire` never use the `DB` facade directly — they go
  through `App\Services`.
- Controllers, jobs, and services are `final` unless deliberately extended.
- **No `.design` path, task ID, phase name, or specification filename appears anywhere
  in `app/`, `resources/`, or `database/`** — the containment rule below, made
  mechanical.

### Testing

- Pest for unit and feature tests; browser tests for the flows a broken selector would
  silently kill — contact-channel clicks, the availability toggle, moderation approve
  and reject.
- Every bug fix starts with a failing test that reproduces it.
- Seeders produce **realistic volume** for the tests that care about volume. The
  catalog ranking query behaves differently against 12 fixtures and against 50 000
  objects; only the second tells you anything.
- `php artisan migrate:fresh --seed` must apply cleanly from empty, every time.

### Benchmarking & Performance Budgets

`[TZ]` §18 and §94 make performance a requirement, not an aspiration. Budgets are
measured, not assumed:

| Surface | Budget |
| --- | --- |
| Catalog / territory page, cache hit | < 100 ms TTFB |
| Catalog / territory page, cache miss | < 400 ms |
| Object page, cache miss | < 300 ms |
| Search, p95 | < 300 ms — the escalation trigger to Typesense |
| Any single request | ≤ 30 queries |

- **N+1 detection is enabled in development and fails the test run.** Eloquent plus
  Filament plus nested relations is the exact shape that produces them, and they do not
  announce themselves.
- Benchmark the catalog ranking query and territory subtree expansion against seeded
  volume whenever either changes — these are the portal's hottest paths.
- Laravel Pulse in production for ongoing visibility.
- Run a load test against catalog and territory pages before launch, not after.

### Documentation

Written in **English**, for a developer who did not build this and may be maintaining
it for the client after handover.

- **Docblocks on every public service method**: what it guarantees, what it throws,
  what it assumes — not a line-by-line narration of the body.
- **Comment the *why*, never the *what*.** Non-obvious constraints, business rules with
  a surprising shape, and deliberate deviations get a sentence. Obvious code gets
  nothing — noise costs more than it explains.
- **`README.md`**: setup from zero to a running local instance, architecture map,
  common tasks.
- **`docs/`**: deployment, operations runbook, and the **backup and restore procedure**
  — `[TZ]` §97 and §131 require a documented, rehearsed restore, not just working
  backups.
- Filament labels, table columns, and form fields go through translation keys, never
  literal strings.

### Cleanliness

- **Delete, never comment out.** Git holds the history; commented-out blocks hold
  confusion.
- No dead code, no unused dependencies. The previous implementation of this project
  accumulated eleven unused packages before anyone noticed — `composer unused` and
  Rector's dead-code rules exist to prevent the repeat.
- A `TODO` carries plain-language context and an owner, never a task ID or a
  specification reference.
- Migrations are never edited after being applied to a shared environment — add a new
  one.
- Prefer deleting an abstraction to generalizing it further.

## Completion Protocol (Mandatory Checklist)

Before declaring any task complete, verify every item:

- [ ] **Quality gates**: `composer quality` passes end to end — Pint, PHPStan level 8, Pest (including architecture tests), coverage minimum, `composer audit`, `composer unused`.
- [ ] **Migrations**: `php artisan migrate:fresh --seed` applies cleanly from empty.
- [ ] **Tests**: new behaviour has tests; every bug fix has a test that failed before it.
- [ ] **Performance**: no N+1 introduced; touched hot paths still meet their budget.
- [ ] **Documentation**: public service methods have docblocks; non-obvious decisions have a *why* comment; README and `docs/` updated if setup or operations changed.
- [ ] **Language policy**: all code, identifiers, comments, documentation, and commit messages in English; all chat interaction in Russian.
- [ ] **Architecture**: business logic in `app/Services/`; models thin; authorization enforced in Policies; no logic in Filament resources or Blade.
- [ ] **Localization**: no hard-coded user-facing strings; no hard-coded language or country counts.
- [ ] **Ordering**: placement-tier precedence preserved wherever objects are listed.
- [ ] **Design fidelity**: markup built against the Figma node, tokens from the Tailwind theme, no magic values.
- [ ] **Specification containment**: no `.design/` references, task IDs, or spec file names in product code.
- [ ] **Cleanliness**: nothing commented out, no dead code, no stray `dd()`/`dump()`, no unused dependency added.
- [ ] **Formatting**: no horizontal rules (`---`) in document bodies except in a footer.
