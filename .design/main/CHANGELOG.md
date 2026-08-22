# Workspace Changelog (main)

Internal phase journal — summarizes what each completed phase delivered, extracted from Done task `Changes` fields in `archives/tasks/phase-{N}.md`. Distinct from the root `CHANGELOG.md` (user-facing release notes).

Covers the current Laravel 13 + Filament 5 tourism-portal implementation, from its
Phase 1 (2026-08-06) onward. The superseded Next.js/TypeScript implementation's own
phase journal (pre-2026-08-05 pivot) is archived at
[archives/CHANGELOG-v1-nextjs.md](archives/CHANGELOG-v1-nextjs.md).

## Phase 1 — 2026-08-06

**Foundation, Schema & Authorization** — the running monolith, the complete schema applied from empty, every registry seeded as data, scoped authorization enforced server-side, and continuous quality gates wired so no later phase has to retrofit any of them. 21/21 tasks across four tracks.

### Track A — Scaffold & Toolchain

- Laravel 13.24.0 + PHP 8.5.9 scaffolded, Filament v5.7.5 with `AdminPanelProvider` (`/admin`) and `CabinetPanelProvider` (`/cabinet`).
- Docker Compose stack: Postgres 18 + PostGIS 3.6, Redis, MinIO, Mailpit, nginx, PHP-FPM.
- `composer quality` gate wired from the first commit — Pint, PHPStan level 8 (Larastan), Pest including architecture and containment tests, coverage, `composer audit`, `composer unused` — with CI running the identical single command.
- Vite + Tailwind CSS 4 + Livewire 4 asset pipeline; Biome and `fallow` as the JS-side quality gate.

### Track B — Schema

- 102 migrations across identity, localization, geography, taxonomy, object/media, placement/finance, advertising, content, governance, notifications, analytics, platform, and dormant booking.
- Full index plan — composite scope-ordering, partial, spatial (GiST), trigram (GIN), and JSONB GIN indexes.
- Soft delete and moderation visibility as global scopes (`ModerationScope`/`FiltersModeration`); append-only enforcement on the audit journal and financial ledger via a database trigger, since the connecting role is also the cluster's bootstrap superuser and no `GRANT`/`REVOKE` scheme could bind it.

### Track C — Registries & Seeders

- Ten registry seeders — languages, countries, territory levels, object types, amenities, contact channel types, placement tiers/packages, modules, notification types and channels.
- Roles and permissions seeder with an unrevocable chief-administrator grant (`RoleGrantService::revokeRole()` refuses to strip the last holder).
- Realistic-volume demo seeder — 52,800 objects across 6,270 territories, chunked and kept out of the default seed path.

### Track D — Domain Core

- Scoped authorization: the `role_scopes` ladder (none/country/territory/category), recursive subtree resolution, `ScopeAuthorizer` and the `ScopedPolicy` base class.
- Feature-module resolution ladder (object → owner → category → country → portal → default) and the server-side `EnsureModuleEnabled` gate.
- Eleven core Eloquent models with all four required package traits proven — translatable, media, auditing, adjacency-list.

### Track T — Validation

- `migrate:fresh --seed` proven from empty; a generated ER diagram (`docs/database-schema.md`, 99 tables / 176 foreign keys).
- Architecture tests mechanizing every convention — `strict_types`, no debug functions, thin models, service-layer discipline, and the containment rule barring spec/task/phase references from product code.
- Authorization test matrix and module-inertness test, each proving denial as rigorously as allowance.
- Benchmark harness (`composer bench`) proving catalog ordering and territory subtree expansion against budgets at seeded volume.

## Phase 2 — 2026-08-13

**Back Office Core** — a staff panel a portal can actually be operated from: objects, owners, geography, taxonomy, translations, moderation, and the action journal, built on Phase 1's scoped authorization rather than around it. 25/25 tasks across four tracks.

### Track A — Panel Foundation

- Admin panel moved to a configurable path, sign-in journalling, mandatory two-factor for configured roles, permission-filtered navigation.
- `ScopedResource` — the shared resource contract (policy binding, persisted filters, unsaved-change guard, counted bulk confirmation) every later resource in the panel builds on.
- Portal settings registry — 33 settings across ten groups, with critical-write protection restricted to the chief administrator.
- Module management screen with per-scope toggles and a real blast-radius confirmation.
- Dashboard with scope-narrowed counters and a permission-gated finance block.

### Track B — Objects, Owners & Availability

- Object list, tabbed form, and full lifecycle — save as draft, publish, hide, return for revision, archive, restore, duplicate, transfer ownership — each journalled.
- Bulk operations behind counted confirmations, sharing one entry point between the inline and queued paths, refusing an out-of-scope selection in full.
- Owner management: accounts, object attachment, access block/restore.
- Support-mode impersonation, journalled without exception and correctly attributed to the impersonating administrator.
- Availability administration (override/history/revert) and staleness surfacing (cadence, quick filters, bulk reset, optional auto-reset).

### Track C — Geography, Taxonomy & Translations

- Territory administration with guarded reparenting — whole-subtree blast-radius confirmation, cycle guard, denormalized-country cascade.
- Object type registry driving a dynamic, per-type attribute tab on the object form.
- A database-backed interface-catalog overlay on the file translation catalogs, editable without a deployment.
- Translation completeness report and an untranslated-material filter, discovering translatable entities generically by contract.

### Track D — Moderation & Governance

- Moderation mode resolution ladder and the change-request pipeline, snapshotting proposed changes rather than referencing the live record.
- Moderation queue, side-by-side review, and the full decision set — approve, reject, request revision, partial acceptance.
- Action journal with search/filter/before-after/export, and a scheduled archival job that exports rather than deletes, since audits are append-only by design.
- Archive: restore, transfer, and permanent deletion, restricted to the chief administrator and gated by re-authentication.

### Track T — Validation

- Panel authorization matrix generated from the live resource registry rather than hand-enumerated.
- Moderation invariants: a rejected edit never touches the published record.
- Journal completeness across every enumerated event class, with append-only enforcement re-proven.
- Panel query budget proven under seeded volume.

## Phase 3 — 2026-08-13

**Commerce, Advertising & Platform Services** — the revenue mechanics and background machinery both panels and the public site depend on: placement ordering, bumps, banner targeting, analytics ingest, notifications, and the content pipeline. 23/23 tasks across five tracks.

### Track A — Placement & Monetization

- Tier and package registry — four structural ranks, editable labels/badges, per-category package sets.
- `PlacementOrderingService` — the shared tier-ordered retrieval contract every catalog surface calls from Phase 5 onward.
- Bump engine: scoped events, interval/allowance limits, a back-office bump action.
- Expiry sweep with configurable warning offsets and expired-placement demotion.
- Financial ledger and commerce reports — closed a real gap where the ledger had existed since Phase 1 but nothing had ever written to it.

### Track B — Advertising

- Banner slot registry and banner model: scheduling, desktop/mobile creatives, per-slot targeting.
- Banner selection and serving service — schedule → language → category → territory specificity ranking, with fire-and-forget impression/click capture.
- Promotional labels and card decoration modelled as a closed data shape, structurally excluding free-form CSS or animation.

### Track C — Analytics

- Two-tier `StatEvent` model (date-partitioned from creation), fire-and-forget capture across eleven event kinds, a coarse rotating dedup token.
- Daily rollup and compaction, idempotent and transactional per day.
- Portal-wide reporting and first-touch-only traffic-source recording — channel and host only, never a full referrer.

### Track D — Notifications

- Notification/dispatch split with a channel-adapter registry (inbox, email).
- Dispatch pipeline: queue, retry with backoff, suppression by recipient preference, inbox.
- Scheduled jobs: staleness, availability-confirmation, and dispatch-retry sweeps.
- Administrator broadcast targeted by country, territory, or package — rate-limited and deduplicated per owner.

### Track E — Content Publishing

- Article model and admin CMS, with no moderation checkpoint since an administrator publishing is already the trusted act.
- News and promotions models, moderation-gated, plus an auto-archival job for elapsed promotions.
- A shared publication pipeline consolidating cache-tag invalidation across all three content types in one place.

### Track T — Validation

- Catalog ordering and bump invariants proven at seeded volume — 2 queries, well under budget.
- Analytics privacy and fidelity invariants: no durable visitor identifier, capture never blocks or leaks a failure.
- Notification delivery completeness across all ten types — closed two real gaps where moderation-decision and object-status notifications were never actually triggered.
- Commerce/content panel query budget — fixed a systemic per-navigation-item authorization cost regression.
- Containment cleanup: removed six plan-phase references from product code and strengthened `ContainmentTest`'s own coverage.

## Phase 4 — 2026-08-14

**Owner Cabinet** — the second Filament panel, the same toolkit and interface conventions as the staff panel, scoped to the authenticated owner in both the base query and the policy, usable by someone with no technical training. 16/16 tasks across four tracks.

### Track A — Cabinet Foundation

- `CabinetPanelProvider` built on Filament's own tenancy contract (an owner's objects as tenants), with an owner-scoped `CabinetResource` base every later resource extends.
- Dashboard: placement/tier/expiry, view and click counts, quick actions into every other cabinet screen.

### Track B — Object Management

- Object editing routed through `ModerationPipeline` exactly as an administrator edit would be, for an already-published object.
- Media management (upload, reorder, caption, primary photo) via `spatie/laravel-medialibrary`, applying immediately regardless of moderation state.
- Rooms and prices, gated to accommodation-type objects by the type's own declared capability.
- Services: amenity selection from the administrator registry only, no free-text escape hatch.
- Availability one-tap toggle on its own narrow write path, structurally bypassing moderation.

### Track C — Owner Content

- Owner-authored news and promotions — exactly five fields each, routed through the same Phase 3 lifecycle services and moderation pipeline.
- Reviews: reply and report only — edit and delete refused server-side unconditionally, including for the object's own owner.

### Track D — Statistics, Bump & Settings

- Statistics page over `AnalyticsReportingService`'s owner-scoped query, plus favorite count.
- Bump entry point calling the existing `BumpService`, free-bump type only, rate-limited.
- Settings and notification preferences — closed the per-user locale gap flagged since Phase 3.
- Staleness surfacing: an advisory-only banner that clears itself once the object is edited past the flagging notification.

### Track T — Validation

- Ownership isolation swept across every registered cabinet resource by live panel-registry discovery.
- Moderation-gating and availability-bypass invariant proven live within one scope, switched mid-test.
- Cabinet panel query budget under realistic per-owner volume — fixed three real N+1s the volume exposed.

## Phase 5 — 2026-08-15

**Public Site** — the portal's acquisition surface: server-rendered Blade with Livewire for catalog interactivity, built node by node against the Figma source, instrumented so the contact click is never lost. 18/18 tasks across four tracks.

### Track A — Site Shell, Catalog Query & Card Foundation

- Public layout shell built against the Figma home-page frame: header, data-driven navigation, language/country switchers, footer, feedback overlay.
- 404 page (noindex) and static legal pages.
- `CatalogQueryService` — the shared tier-ordered retrieval contract every listing surface calls, delegating ordering to `PlacementOrderingService`.
- `ContactChannelType` deep-link resolution (`tel:`, `wa.me`, `viber://`, `mailto:`, website) purely from registry data.
- Object card component with card-view event emission, uniform geometry across every placement tier.
- Clustered map: MapLibre GL JS, PostGIS bbox-filtered pins, the tile provider structurally barred from the OSMF-prohibited public OpenStreetMap host.

### Track B — Object Profile

- Type-varying profile composition from one generic template, never a per-type branch.
- Contact rail with contact-click emission before navigation — the portal's only conversion signal.
- Reviews rendering, module-gated through the full ladder (object → owner → category → country → portal).
- Nearby/similar objects and the object's own news/promotions feed, all through existing shared components.

### Track C — Catalog & Territory Listing Pages

- Catalog/search page: the full parameter set round-tripped through the URL, grid/list persisted per visitor, map and results synchronized in one round trip.
- Territory landing pages: per-type catalog blocks, transitive scoping down the hierarchy, tier ordering restarting per territory.

### Track D — Home Page & Content Surfaces

- Public blog — listing and article detail.
- Public news feed and promotions section.
- Home page: all sixteen blocks composed from existing services only, country-aware curation, no home-page-only query.

### Track T — Validation

- Placement-tier ordering invariant swept across every public listing surface.
- Event-emission invariant: capture never blocks and never fails visibly, proven against a forced queue failure.
- Public performance budget at seeded volume — added response caching, fixed several N+1s, reverted one regression found along the way; the object page's own 68-query gap against its 30-query budget logged as a genuine, documented shortfall rather than hidden.

## Phase 6 — 2026-08-19

**Discovery, Reporting & Public API** — making the portal findable and its performance legible: URL grammar, metadata, structured data, sitemaps and redirects; portal-wide reporting; a versioned read-only REST contract behind issued tokens. 16/16 tasks across four tracks.

### Track A — Addressing Foundation

- Per-language slug resolution and the territory-hierarchy URL grammar (flat object URLs, hierarchical territory paths) — a retrofit across 21 existing call sites.
- Redirect table, administration screen, and same-operation redirect creation on every slug-changing write path.

### Track B — Metadata, Indexation, Structured Data & Sitemaps

- Per-language SEO metadata fields and a three-rung resolution ladder — explicit value, administrator template, derived from entity data.
- Indexation policy: filtered catalog views excluded by default, promotable only through an explicit administrator allowlist entry; pagination canonicalizes to itself.
- Structured data (`LodgingBusiness`/`FoodEstablishment`/`Place`/`Article`/`Offer`) and breadcrumbs, gated on booking-module state so unavailable offers are never overstated.
- Sitemap index: paginated per-language artefacts, regenerated hourly by a scheduled job, never computed per request.
- SEO administration screen with six health warnings, plus a new custom-error-page mechanism.

### Track C — Portal-Wide Reporting

- Derived figures across every aggregation dimension — most-viewed objects, popular categories, banner click-through rate, new owners/objects, bumps, published promotions, pending moderation.
- Traffic-source and page-popularity reporting, always aggregated — never a per-visitor row.

### Track D — Public API

- API module gate (disabled by default), versioned routing, an identical 404 for a disabled module and an unregistered route.
- API client and token model: issuance, scoping through the same `ScopeConstraint` shape as administrator grants, revocation, journalling.
- Read endpoints layered over `CatalogQueryService` — never a second retrieval path — carrying the same tier ordering and visibility filter as the public site.
- Per-token rate limiting checked before the query runs, consumption measurement, and a generated documentation endpoint.

### Track T — Validation

- Indexation invariant swept across the live route registry — found and fixed a `robots.txt` path mismatch.
- API parity invariant at seeded volume — found and fixed a real 500 (an unvalidated string-to-int cast) no other test had reached.
- Redirect permanence and slug stability, including a genuine multi-level reparent and a two-hop rename chain.

## Phase 7 — 2026-08-20

**Operations & Launch Readiness** — everything standing between a working portal and an operable one: the import pipeline, export across every listed entity, backups with a rehearsed restore, production provisioning and observability, and a load test run before launch. 16/16 tasks across four tracks.

### Track A — Data-Type Registry & Import Pipeline

- `TransferableRegistry`: one declaration per transferable entity (13 kinds) shared by both import and export, checked mechanically against every exporter's own column list.
- Import pipeline built on Filament's native `Importer`: upload → column mapping → validate/preview → confirm → durable report, queued.
- Five-signal duplicate detection (name, phone, website, address, coordinates, via `pg_trgm` and PostGIS) surfaced in the import preview — never an automatic merge.
- Administrator-confirmed merge: reattaches media, placement, and statistics, registers a permanent redirect, journals both identities.

### Track B — Export

- Entity export across ten kinds in XLSX/CSV/JSON — JSON layered onto Filament's own export pipeline rather than a new job; three pre-existing exporters converted into registry readers.
- Financial and personal-data column narrowing by permission, with one journal entry per completed export.

### Track C — Backups, Integrity & Restore

- Scheduled off-server backups: database daily, media weekly, retained by generation count, with layered integrity verification beyond a bare file-count check.
- Backup administration screen — last-backup date, manual run, technical report, and a real failure-notification listener.
- Administrator-triggered restore behind Filament's native re-authenticated two-factor confirmation, a genuine three-step flow.

### Track D — Production Provisioning & Observability

- Production object storage and CDN in front of both the application and media, credential exposure checked against the committed tree.
- Production SMTP confirmed; Sentry/GlitchTip error tracking with personal-data scrubbing covering queue and scheduler failures.
- Horizon (five declared queues) and Pulse (Redis-backed ingest, zero added query cost) wired as first-class Docker Compose services alongside a real scheduler process.

### Track T — Validation & Launch Readiness

- Rehearsed restore: a real backup/restore cycle against a genuinely disposable fourth database, run twice in succession, with a recorded runbook.
- Import/export invariants: a real corrupt-and-reimport round trip, plus zero-automatic-merge and zero-unpermitted-column sweeps.
- Load test at 52,800 seeded objects — found and fixed a significant, previously invisible bug (`cache.serializable_classes` defaulting to `false` was silently corrupting every real Redis cache hit); surfaced two genuine budget breaches (search p95, catalog cache-miss) for a pre-launch decision.
- Coverage floor: both named services reached 100% individually; the suite-wide 78.3% floor remains open as a separate, tracked finding across roughly twenty pre-existing Phase 1–6 files — closed on its own literal scope with the project owner's confirmation.

Phase 7 closed the plan: all seven phases done, 135/135 tasks. A plan-wide retrospective ran on close — see `RETROSPECTIVE.md` for DORA metrics, observations, and recommendations, most notably promoting qualifying specifications to `Stable` and scoping the residual coverage gap as its own follow-up.

## Phase 9 — 2026-08-22

**Post-Launch QA Remediation** — a full-surface functional sweep against the running instance (every public, cabinet, admin, and API route exercised, not inferred from reading code) found five confirmed product defects and one test-suite defect; every one checked directly against its governing specification and found already correct, so this phase is implementation-only, no spec amendment. 15/15 tasks across six independent tracks.

### Track A — Contact Channel Type Selection

- Admin and cabinet object forms' contact-channel repeaters were missing the type selector entirely — no channel could ever be saved through either UI (a `NOT NULL` violation on `contact_channel_type_id`, the portal's whole conversion mechanism silently broken). Both forms now select from the active `ContactChannelType` registry.
- Found in passing: `derived_link` on `contact_channels` has no write path anywhere in the app — the real deep-link resolution happens dynamically at click time via `ContactChannelLinkResolver`, not from that stored column. Left the column alone rather than wiring a write path nothing reads.

### Track B — API Guest-Redirect JSON Contract

- Every authenticated `api/v1/*` route 500d instead of 401 when the caller omitted an `Accept: application/json` header — Laravel's guest-redirect fallback tried `route('login')`, a name this app never registers. `api/*` now never attempts a guest redirect; the admin and cabinet panels' own login redirects are unaffected.

### Track C — Canonical Host Consistency

- Canonical links, Open Graph tags, and API response URLs followed the incoming request's Host header instead of the configured `APP_URL` — both host and scheme, since `URL::forceRootUrl()` alone doesn't pin the scheme once TLS terminates upstream of the app (this project's own production topology). Both are now pinned from the same `config('app.url')` value.
- Found live during Track D: the catalog page's own canonical used `url()->full()`, a code path that bypasses the root/scheme pin entirely — the one page Track C's own fix had missed. Fixed alongside it.

### Track D — hreflang Alternate Links

- `ResolvedMetadata` now carries per-language alternate URLs, computed through the identical `LocaleSwitchResolver::targetUrl()` call the language switcher itself uses, so hreflang tags and the switcher can never independently drift. The public layout emits one `<link rel="alternate">` per active language plus one `x-default`.

### Track E — Cabinet Settings Crash

- `cabinet/settings` 500d for every owner — the one cabinet route with no tenant segment, inside a panel whose full layout (sidebar, topbar, tenant menu) builds tenant-scoped URLs unconditionally with no null-tenant guard anywhere in that shared Filament chrome. The first, narrower patch fixed the one reported crash and immediately surfaced two more unguarded call sites in the same layout; switched to Filament's own `isSimple: true` default for tenant-independent pages instead of patching each vendor call site in turn.

### Track F — Test-Suite Correction

- `PublicRootEntryTest` asserted a fallback-to-primary-language branch its own bare `$this->get('/')` never actually reached — the HTTP test client silently attaches a default `Accept-Language` header that already matches. Fixed the assumption, not the resolver: `PublicEntryLocaleResolver` was already correct.

### Track G — Full-Suite Regression Gate

- 974 tests passed, 3 skipped, 0 failed across the full non-slow suite with all six tracks' fixes applied together, plus a clean `pint`/`composer analyse`/`composer audit`/`composer unused` pass.

Two of Track A/B/D's governing specifications — `l1-object-profile.md` and `l1-public-api.md` — carried a live, unrelated `TBD` that briefly blocked their tracks entirely (this SDD engine's spec-status gate is document-level, not section-level); `l1-platform-shell.md` carried a third. All three were closed and promoted `RFC → Stable` the same day: `l1-platform-shell`'s technical modeling question had a single defensible answer already matching shipped code, while `l1-object-profile`'s (review authorship) and `l1-public-api`'s (API consumer/rate-limit/licensing policy) were genuine product decisions put to the project owner directly rather than inferred.
