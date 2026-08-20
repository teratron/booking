# Technology Stack

**Version:** 2.2.0
**Status:** RFC
**Layer:** implementation
**Implements:** l1-platform-foundation.md

## Overview

Concrete technology selection for the tourism portal: **Laravel 13 + Filament 5 +
PostgreSQL/PostGIS + Redis**, deployed as a self-hosted monolith.

[MODIFIED — v2.0.0] This is a **stack replacement**, not an amendment. Versions 0.x
and 1.x resolved a Next.js/TypeScript stack — first for a hotel booking marketplace,
then re-evaluated against the client technical specification. That re-evaluation was
anchored on an existing codebase rather than derived from requirements, and the
anchoring produced a wrong answer. §1 records why.

## Related Specifications

- [l1-platform-foundation.md](l1-platform-foundation.md) - Invariants this stack must satisfy.
- [l2-third-party-integrations.md](l2-third-party-integrations.md) - Sibling L2; selects the packages and external services layered on this stack.
- [l2-data-model.md](l2-data-model.md) - Table inventory realized through this stack's ORM and migration tooling.
- [l1-back-office.md](l1-back-office.md) - The requirement that decides the stack (§1).
- [l1-object-onboarding.md](l1-object-onboarding.md) - Owner cabinet, delivered by the same admin toolkit.
- [l1-geography.md](l1-geography.md) - Recursive hierarchy and spatial queries.
- [l1-localization.md](l1-localization.md) - Translation model.
- [l1-analytics.md](l1-analytics.md) - High-volume event storage.
- [l1-notifications.md](l1-notifications.md) - Scheduled jobs.
- [l1-seo.md](l1-seo.md) - Rendering strategy.
- [l1-feature-modules.md](l1-feature-modules.md) - Runtime gating enforced server-side.

## 1. Motivation

The specification's centre of gravity is operational tooling. Of 134 sections, §99–§134
are the back office and §29–§43 are the owner cabinet — together more than a third of
the document. Add §44–§53 moderation and §54–§63 monetization administration and the
majority of the specified work is administrative surface over a catalog.

That is the single most solved problem in the PHP ecosystem, and Filament solves it
twice over: **one toolkit delivers both the staff panel and the owner cabinet**.

Three further requirements land on mature packages rather than bespoke work, each
mapping almost verbatim: `[TZ]` §97's backup requirements (scheduled, multiple
generations, integrity verification, failure notification, off-server storage),
`[TZ]` §91's audit journal with old and new values, and `[TZ]` §71's separate
translation tables. Ten packages cover eleven specification sections.

**Why the previous answer was wrong.** Version 1.x argued that Next.js was required
because "SEO failure is fatal and fast". That conflated *search-engine indexability*
with *client-side interactivity*. Blade renders server-side HTML; Next.js renders
server-side HTML. For a crawler there is no difference. The real driver of the earlier
conclusion was the presence of an existing TypeScript codebase, presented as an
engineering argument. Since the data model required full replacement regardless
([l2-data-model.md](l2-data-model.md) §2), that sunk cost was smaller than it appeared.

**Why not Go**, the other candidate considered: the performance premise does not apply
at this scale — a catalog of tens of thousands of objects behind Redis caching is
bound by Postgres and cache strategy, not by runtime speed. And Go has no toolkit of
Filament's class, so §99–§134 would be hand-built or delegated to a second JavaScript
stack.

## 2. Constraints & Assumptions

- Versions below are the target majors; install the latest stable patch at scaffold
  time and keep dependencies current rather than pinning back
  ([CLAUDE.md](../../../CLAUDE.md)).
- Self-hosted deployment, resolving the fork left open in v1.x §5.9. Driven by
  `[TZ]` §97 and §131: administrator-triggered restore and off-server backup retention
  are awkward on a managed platform.
- Realistic scale: roughly 30–60k objects and several thousand territory nodes at
  maturity, read-dominated traffic. This is a small database; the architecture is
  sized for operational breadth, not for throughput.
- The previous implementation is preserved at git tag `v0.1.34` and is not a migration
  source — the schema, not just the language, is replaced.

## 4. Invariant Compliance

| L1 Invariant | Implementation |
| --- | --- |
| Responsive parity (4 viewports) | Blade + Tailwind, mobile-first, one template per page. Back office exempt from phone parity per `[TZ]` §132. |
| Localization-completeness | `astrotomic/laravel-translatable` for entity text in separate tables; Laravel's `lang/` catalogs for interface strings. |
| Public discoverability | Server-rendered Blade; `spatie/laravel-sitemap` for sitemaps; SEO fields on translation records. |
| Geographic scoping | `staudenmeir/laravel-adjacency-list` — recursive CTE relations on Eloquent, no per-request tree walk. |
| Object as central entity, type-varying attributes | Typed columns for filterable fields plus a validated JSONB attribute bag keyed by the type's declared schema (§5.4). |
| Paid-placement ordering | Query-builder expression in `app/Services/`, backed by the composite indexes in [l2-data-model.md](l2-data-model.md) §5.4. |
| Configuration over code | Registries and settings read from PostgreSQL and cached in Redis; no build-time constants. |
| Capability modules toggleable | Resolved at the middleware boundary; gated routes abort rather than hide ([l1-feature-modules.md](l1-feature-modules.md) §6.2). |
| Soft deletion, accountability | Eloquent `SoftDeletes` with a global scope; `owen-it/laravel-auditing` for the append-only journal. |
| Privacy-minimal measurement | Partitioned raw event table compacted into daily aggregates by a scheduled job. |
| Performance at scale | Redis response and query caching, Media Library conversions, MapLibre clustering, the `[TZ]` §94 index set. |
| Defense baseline | Laravel's built-in CSRF, output escaping, and parameter binding; `google2fa` for `[TZ]` §17; rate limiting middleware for `[TZ]` §100; Turnstile for `[TZ]` §130. |

## 5. Detailed Design

### 5.1 Core

| Component | Target | Rationale |
| --- | --- | --- |
| **PHP** | 8.5+ | `declare(strict_types=1)` everywhere; typed properties throughout. |
| **Laravel** | 13.x | Queues, scheduler, localization, policies, API resources, and mail in the framework core rather than assembled from packages. |
| **Blade + Livewire** | 4.x | Server-rendered HTML with interactive catalog filters, pagination, and map updates — without a separate frontend application. |
| **Alpine.js** | 3.x | Local interactivity where Livewire round trips are unnecessary. |
| **Tailwind CSS** | 4.x | The Figma design translates directly into utility classes. |
| **Vite** | latest | Asset pipeline. pnpm is used here and nowhere else. |

### 5.2 Administrative Surface

| Component | Target | Covers |
| --- | --- | --- |
| **Filament** | 5.x | `[TZ]` §99–§134 (staff panel) **and** §29–§43 (owner cabinet) as two panels of one installation. |

This is the decision the whole stack turns on. A Filament resource declares a model's
list columns, filters, sort, form, relation managers, and bulk actions; the panel is
generated from that declaration. Twenty-four sections become twenty-four resource
classes rather than twenty-four hand-built screens.

The owner cabinet is a **second panel**, not a second application: the same resources
with an owner-scoped base query and owner-scoped policies. `[TZ]` §29.1's requirement
that the cabinet be usable without technical knowledge is met by the same interface
conventions the staff panel uses.

### 5.3 Data Layer

| Component | Target | Rationale |
| --- | --- | --- |
| **PostgreSQL** | 18.x | `[TZ]` §22. |
| **PostGIS** | 3.5+ | `[TZ]` §10 distance filters, §7 nearby objects, §15 map bbox and radius queries — indexed via GiST instead of computed in application code. |
| **pg_trgm, unaccent** | — | Fuzzy name matching and diacritic-insensitive search for Romanian and Ukrainian (`[TZ]` §14). |
| **Eloquent + Query Builder** | Laravel 13 | Eloquent for CRUD and relations; the query builder for the catalog ranking expression, where an abstraction would obscure the ordering contract. |
| **Redis** | 8.x | `[TZ]` §18, §22 — cache, session store, and queue broker. |

Three modelling decisions carry over unchanged from the previous analysis because they
are properties of the requirements, not of the stack:

**Territory hierarchy.** Recursive CTE relations via `staudenmeir/laravel-adjacency-list`,
with a denormalized `country_id` on every node. Descendant expansion runs on every
catalog view and must not be a per-request tree walk.

**Type-varying object attributes.** `[TZ]` §69 requires new object types without a
developer and §109 requires per-type field sets. Filterable attributes stay typed
columns; the type-specific remainder lives in a JSONB column validated against the
type's declared schema, with GIN indexes on filterable keys. Full EAV is rejected — it
would turn the catalog query into a self-join over the largest table.

**Translation tables.** Separate `*_translations` tables keyed on
`(entity_id, locale)`, per `[TZ]` §71 and [l1-localization.md](l1-localization.md) §7.
`astrotomic/laravel-translatable` implements exactly this shape;
`spatie/laravel-translatable`'s JSON-column approach is rejected for the reasons in
that spec's §7.

### 5.4 Background Execution

| Component | Target | Rationale |
| --- | --- | --- |
| **Laravel Horizon** | latest | Queue supervision, retries, backoff, and failure visibility over Redis. |
| **Laravel Scheduler** | core | `[TZ]` §22's cron requirement. |

The recurring workload from [l1-notifications.md](l1-notifications.md) §5.4 — placement
expiry sweeps, staleness reminders, availability-confirmation prompts, promotion
archival, dispatch retries, statistics rollups, sitemap regeneration, backups — runs as
queued jobs under a Horizon worker process, separate from web request handling.

This removes the awkwardness the previous stack had here: in Laravel a worker is a
first-class deployment mode of the same application, not a second deployable requiring
its own entry point.

### 5.5 Package Set

Each entry replaces work that would otherwise be bespoke. Hand-building any of these is
a defect, not a preference.

| Package | Specification section |
| --- | --- |
| `spatie/laravel-permission` | §73, §121 — roles and permissions; geo/category scoping added via Policies (§5.6) |
| `astrotomic/laravel-translatable` | §16, §71 — separate translation tables |
| `spatie/laravel-medialibrary` | §33, §75 — upload, conversions, thumbnails, ordering, collections |
| `owen-it/laravel-auditing` | §48, §53, §91, §129 — journal with actor, old value, new value, IP |
| `spatie/laravel-backup` | §97, §131 — schedule, retention, integrity check, failure notification, off-server destination |
| `spatie/laravel-sitemap` | §13 |
| `staudenmeir/laravel-adjacency-list` | §68 — recursive territory hierarchy |
| `laravel/sanctum` | §19 — API tokens |
| `laravel/horizon` | §22, §62 |
| `laravel/scout` | §14 — Postgres driver first, Typesense when measured (§5.7) |
| `pragmarx/google2fa-laravel` | §17, §100 |
| Filament import/export actions | §96, §127, §128 — queued, with column mapping and error report |

### 5.6 What This Project Builds

The bespoke surface, stated explicitly so it is not underestimated:

- **Catalog ranking** — tier-ordered with scoped bumps ([l1-placement-monetization.md](l1-placement-monetization.md) §5.2).
- **Bump engine** — scoped per territory and category, with limits and journal.
- **Banner targeting and selection** — specificity ranking, scheduling, rotation ([l1-advertising.md](l1-advertising.md) §5.2).
- **Statistics ingest and rollup** — batched writes, partitioned table, daily aggregation.
- **Scoped authorization** — `[TZ]` §121's permissions bounded by country, territory subtree, or object category. `spatie/laravel-permission` supplies roles and permissions; the *scope* resolution is ours, implemented in Policies against the territory hierarchy.
- **Moderation revisions** — pending changes held apart from the published record, with a field-level diff view ([l1-moderation-governance.md](l1-moderation-governance.md) §5.3).
- **Feature-module gating** — registry resolution at the middleware boundary.
- **Public site** — Blade templates against the Figma design.

### 5.7 Search

Start with PostgreSQL full-text search plus `pg_trgm` and `unaccent`, behind
`laravel/scout` so the driver is swappable.

**Recorded weakness**: PostgreSQL ships no full-text dictionary for Georgian or
Ukrainian. Romanian is covered. Trigram matching works regardless of language and will
carry name search, but stemmed full-text search across all five languages will not be
complete. Escalation trigger: **p95 search latency above 300 ms on the real catalog**,
or demonstrated recall failure in Georgian — then Typesense behind the same Scout
interface.

### 5.8 Project Structure

```plaintext
app/
├── Models/                 # Eloquent models — relations, casts, scopes only
├── Filament/
│   ├── Admin/              # Staff panel resources, pages, widgets
│   └── Cabinet/            # Owner panel, owner-scoped
├── Livewire/               # Catalog filters, map, search
├── Services/               # Ranking, bumps, banner targeting, statistics, modules
├── Policies/               # Authorization incl. geo/category scoping
├── Jobs/                   # Expiry, staleness, rollups, notifications, sitemaps
├── Console/Commands/       # Scheduler entry points
└── Support/

resources/views/            # Blade — public site
resources/lang/             # Interface catalogs (en, ru at launch)
database/migrations|seeders/
docker/                     # Postgres init SQL, local infrastructure
```

### 5.9 Quality Tooling & Performance Budgets

| Tool | Purpose |
| --- | --- |
| **Pest** | Unit, feature, browser, and **architecture** tests. `[TZ]` §23 lists testing as a delivery stage. |
| **PHPStan + Larastan** | Level 8 static analysis. |
| **Laravel Pint** | Formatting. |
| **Rector** | Dead-code rules and automated upgrades between framework majors. |
| **Laravel Pulse** | Production performance visibility. |
| **N+1 detector** | Development-time; fails the test run rather than warning. |

Gates are wired as Composer scripts (`composer quality`) so local and CI invocation are
identical, and they run continuously rather than at task boundaries. Conventions are
enforced by Pest `arch()` tests rather than by review — including the
specification-containment rule, which is mechanically checkable: no `.design` path,
task ID, or specification filename may appear anywhere under `app/`, `resources/`, or
`database/`.

`[TZ]` §18 and §94 make performance a requirement rather than an aspiration, so the
budgets are stated and measured:

| Surface | Budget |
| --- | --- |
| Catalog / territory page, cache hit | < 100 ms TTFB |
| Catalog / territory page, cache miss | < 400 ms |
| Object page, cache miss | < 300 ms |
| Search, p95 | < 300 ms — the §5.7 escalation trigger to Typesense |
| Any single request | ≤ 30 queries |

Benchmarks run against **seeded realistic volume** — tens of thousands of objects, not
a dozen fixtures. The catalog ranking query (§5.6) and territory subtree expansion
(§5.3) behave differently at scale, and a benchmark against fixtures measures nothing
about either.

Full working conventions live in [CLAUDE.md](../../../CLAUDE.md) under "Engineering
Discipline"; this section fixes the tooling selection and the numeric budgets that the
plan schedules work against.

### 5.10 Deployment

Self-hosted Docker Compose, resolving the previously open fork.

```plaintext
app         PHP-FPM + Nginx (web)
worker      Horizon (queues + scheduler)
postgres    PostgreSQL 18 + PostGIS
redis       Redis 8
storage     S3-compatible (MinIO locally; Cloudflare R2 / Backblaze B2 in production)
cdn         Cloudflare in front ([TZ] §18)
```

Backups are `spatie/laravel-backup` writing to a storage destination separate from the
application server, satisfying `[TZ]` §97's off-server requirement and §131's
administrator-triggered restore.

### 5.11 Environment Configuration

Development and production diverge in `.env` values and in how Vite serves built
assets — never in code path, and never in the domain registries the "Configuration
over code" invariant (§4) governs. That invariant is about business data —
languages, tiers, object types — staying in PostgreSQL regardless of environment.
Infrastructure bootstrapping is a different problem: which database and which
credentials to use is necessarily resolved before the application can reach
PostgreSQL at all. A setting that needs an `if (environment === 'production')`
branch in application code is a defect either way — Laravel's own environment
resolution and Vite's build mode cover this without one.

| Concern | Development | Production |
| --- | --- | --- |
| `APP_ENV` / `APP_DEBUG` | `local` / `true` — stack traces aid debugging | `production` / `false` — the app fails closed, never rendering internals to a visitor |
| Object storage | MinIO, local container | Cloudflare R2 / Backblaze B2 ([l2-third-party-integrations.md](l2-third-party-integrations.md) §5.1) |
| Asset origin | Vite dev server, unbundled, HMR | `vite build` output behind the CDN (§5.10); the framework's asset-manifest directive resolves the versioned path, never a hard-coded one |
| Mail | Mailpit, local catch-all | The configured SMTP provider ([l2-third-party-integrations.md](l2-third-party-integrations.md) §5.4) |
| Bot-protection challenge | Turnstile's published always-pass test keys | Turnstile production keys ([l2-third-party-integrations.md](l2-third-party-integrations.md) §5.5) |
| Map tile credential | Sandbox key, or self-hosted tiles | Production tile-provider key ([l2-third-party-integrations.md](l2-third-party-integrations.md) §5.3) |
| Error tracking | Off, or a local instance | Active ([l2-third-party-integrations.md](l2-third-party-integrations.md) §5.8) |
| Cookie transport | Plain HTTP on localhost | HTTPS-only, secure-cookie flag set — TLS terminates at the CDN edge ([l2-third-party-integrations.md](l2-third-party-integrations.md) §5.2) |

Two mechanisms carry these values, kept deliberately separate:

- **Laravel's `.env`** supplies every server-side value above — credentials,
  feature toggles, service endpoints. It is never committed
  ([l2-third-party-integrations.md](l2-third-party-integrations.md) §2); production
  values are injected at the host or orchestrator, not shipped in an image.
- **Vite's build-time environment** supplies the small subset of values a
  client-side script legitimately needs — the map tile credential above is the one
  case in this stack. Only those variables cross into shipped JavaScript; a
  server-only secret placed there is a leak, not a configuration choice, since
  built assets are public by construction.

## 6. Implementation Notes

Sequenced by dependency.

1. **Scaffold**: Laravel 13, Filament 5, Docker Compose with PostGIS, Redis, MinIO,
   Mailpit. Verify the Postgres extensions are created by the init script.
2. **Schema in one migration pass** per [l2-data-model.md](l2-data-model.md). The
   previous schema is not a starting point.
3. **Registries and seeders** before any feature: languages, countries, territory
   levels, object types, amenities, contact channel types, placement tiers, roles,
   permissions, modules, notification types.
4. **Scoped authorization** before any panel screen. Retrofitting authorization is how
   authorization gaps happen.
5. **Filament panels** in `[TZ]` §134's priority order.
6. **Public site** against the Figma design.
7. Keep the booking module's tables migrated and its code gated but inert
   ([l1-feature-modules.md](l1-feature-modules.md) §6.3).
8. **Provision production's own credentials before the first deploy** (§5.11): its
   own storage bucket, database, and service keys, never copied from development —
   a shared bucket or key between environments is the easy way to a production
   incident traced back to a development mistake.

## 7. Drawbacks & Alternatives

**Next.js / TypeScript** — the previous selection, preserved at tag `v0.1.34`. Genuinely
stronger for image optimization tooling and for reusing the shadcn component set already
built, and genuinely weaker on the specification's dominant cost: the admin surface, the
owner cabinet, and the eleven sections covered by packages here. Roughly twice the code
to write for the same requirements. The public-site loss is real but bounded — Blade
renders equally crawlable HTML, and Livewire covers the catalog interactivity.

**Go** — rejected. The performance premise does not hold at this scale, and no
Filament-class admin toolkit exists in the ecosystem, so the largest requirement block
would be hand-built or delegated to a second stack.

**Symfony** — a credible alternative with EasyAdmin. Rejected because EasyAdmin is less
capable than Filament, the Spatie package ecosystem is Laravel-oriented, and the
regional hiring pool skews Laravel.

**Managed platform instead of self-hosting** — lower operational burden, and awkward
against `[TZ]` §97 and §131, which want direct control of backup destinations and an
administrator-triggered restore. Revisit if operational capacity proves the constraint.

**A dedicated search engine from day one** — Typesense would outperform Postgres FTS,
particularly in Georgian. Deferred rather than rejected: §5.7 records the trigger, and
Scout makes the swap a configuration change.

**Payload CMS, react-admin** — both were considered while the stack was JavaScript. Both
are moot now; Filament covers what they were being asked to cover, natively.

## Canonical References

| Alias | Path | Purpose |
| --- | --- | --- |
| `[TZ]` | `.drafts/booking.md` | §10, §14–§22, §69, §94, §97, §102, §109, §121, §127–§134 — requirements driving these selections. |
| `[L1]` | `.design/main/specifications/l1-platform-foundation.md` | Invariants this stack must satisfy. |
| `[L2-INTEGRATIONS]` | `.design/main/specifications/l2-third-party-integrations.md` | External services layered on this stack. |
| `[L2-DATA]` | `.design/main/specifications/l2-data-model.md` | Table inventory realized here. |

## Document History

| Version | Date | Change |
| --- | --- | --- |
| 0.1.0 | 2026-07-30 | Initial draft; resolved framework and styling forks for the hotel-booking product. |
| 0.2.0 | 2026-07-30 | Reconciled against l2-third-party-integrations.md. |
| 0.3.0 | 2026-07-30 | Added component architecture principles. |
| 0.3.1 | 2026-07-30 | Clarification: admin REST surface. |
| 1.0.0 | 2026-08-05 | Major restructure against the client technical specification: dependency audit, missing-capability ledger, background-worker finding, deployment fork. |
| 1.0.1 | 2026-08-05 | Clarification: active-language phrasing. |
| 2.0.0 | 2026-08-05 | **Stack replacement.** Laravel 13 + Filament 5 + PostgreSQL/PostGIS + Redis, self-hosted monolith. Records why the v1.x reasoning was wrong (SEO conflated with client interactivity; conclusion anchored on an existing codebase). Resolves the deployment fork to self-hosted. Replaces the missing-capability ledger with a package set covering eleven specification sections, and states the remaining bespoke surface explicitly. |
| 2.1.0 | 2026-08-05 | Minor: expanded §5.9 with architecture tests, dead-code and N+1 tooling, Laravel Pulse, the `composer quality` gate, and numeric performance budgets tied to `[TZ]` §18/§94 — including the requirement that benchmarks run against seeded realistic volume rather than fixtures. |
| 2.2.0 | 2026-08-20 | Minor: added §5.11 Environment Configuration — the dev/production divergence table across storage, assets, mail, bot-protection, map tiles, error tracking, and cookie transport; disambiguated from the §4 "configuration over code" invariant, which governs business data, not infrastructure bootstrapping. |
