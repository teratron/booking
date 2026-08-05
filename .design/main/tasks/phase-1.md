---
phase: 1
name: "Foundation, Schema & Authorization"
status: Todo
subsystem: "docker/, database/, app/Models, app/Services, app/Policies"
requires: []
provides: []
key_files:
  created: []
  modified: []
patterns_established: []
duration_minutes: ~
---

# Stage 1 Tasks — Foundation, Schema & Authorization

**Phase:** 1
**Status:** Todo
**Strategic Goal:** A running Laravel 13 + Filament 5 monolith with the complete schema
applied from empty, every registry seeded as data, scoped authorization enforced
server-side, feature modules gated at the middleware boundary, and continuous quality
gates wired — so that no later phase has to retrofit any of them.

## Track Ordering

Phase 1's tracks are **not** independent. The real ordering is:

```plaintext
A (scaffold)  →  B (schema)  →  C (seeders)  ∥  D (domain core)  →  T (validation)
```

Track B cannot begin before `T-1A01` and `T-1A02`. Tracks C and D run concurrently once
Track B lands. Track T consumes all three. Effective parallel degree is two, not four.

## Atomic Checklist

### Track A — Scaffold & Toolchain

- [ ] [T-1A01] Scaffold the Laravel 13 + Filament 5 monolith
- [ ] [T-1A02] Local Docker Compose stack with PostGIS, Redis, MinIO, Mailpit
- [ ] [T-1A03] Quality toolchain and the `composer quality` gate
- [ ] [T-1A04] Asset pipeline — Vite, Tailwind 4, Alpine, Livewire 4

### Track B — Schema

- [ ] [T-1B01] Migrations: identity, access, localization, geography, taxonomy
- [ ] [T-1B02] Migrations: object, media, rooms, prices, reviews, contacts, favorites
- [ ] [T-1B03] Migrations: placement and finance, advertising, content, governance
- [ ] [T-1B04] Migrations: notifications, analytics, platform, dormant booking
- [ ] [T-1B05] Index plan — composite, spatial, trigram, GIN, partial
- [ ] [T-1B06] Retention rules — soft delete, moderation scopes, append-only privileges

### Track C — Registries & Seeders

- [ ] [T-1C01] Registry seeders — languages, countries, territory levels, types, amenities, channels, tiers, packages, modules, notification types
- [ ] [T-1C02] Roles and permissions seeder with the unrevocable chief-administrator grant
- [ ] [T-1C03] Realistic-volume demo seeder for benchmarking

### Track D — Domain Core

- [ ] [T-1D01] Scoped authorization — `role_scopes` resolution and the base policy
- [ ] [T-1D02] Feature-module registry — resolution ladder and server-side gate
- [ ] [T-1D03] Eloquent models — relations, casts, scopes, and package traits only

### Track T — Validation

- [ ] [T-1T01] `migrate:fresh --seed` from empty, plus the generated ER diagram
- [ ] [T-1T02] Architecture tests — conventions enforced mechanically
- [ ] [T-1T03] Authorization test matrix — scoped grants deny across every scope kind
- [ ] [T-1T04] Module inertness test — disabled means absent, both directions
- [ ] [T-1T05] Benchmark harness — catalog ranking and subtree expansion against budgets

## Detailed Tracking

### [T-1A01] Scaffold the Laravel 13 + Filament 5 monolith

- **Spec:** l2-tech-stack.md §5.1, §5.2, §5.8, §6.1
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `php artisan --version` reports Laravel 13.x; `php -v` reports 8.4+; `composer show filament/filament` reports 5.x; both panel providers resolve — `php artisan route:list --path=admin` and `--path=cabinet` each return at least one route.
- **Handoff:** T-1A02 (infrastructure the application connects to), T-1A03 (gates that run over it).
- **Notes:** Directory layout per §5.8 — `app/{Models,Filament/Admin,Filament/Cabinet,Livewire,Services,Policies,Jobs,Console/Commands,Support}`. `declare(strict_types=1)` in every file from the first commit; retrofitting it later is a diff across the whole tree. Install the latest stable release of every package — do not pin back a major version.

### [T-1A02] Local Docker Compose stack with PostGIS, Redis, MinIO, Mailpit

- **Spec:** l2-tech-stack.md §5.3, §5.10; l2-third-party-integrations.md §5.1, §5.4
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `docker compose up -d` brings all services healthy; `docker compose exec postgres psql -U postgres -c "SELECT extname FROM pg_extension"` lists `postgis`, `pg_trgm`, and `unaccent`; `php artisan tinker --execute="DB::select('select postgis_version()')"` returns a version; Redis reachable via `php artisan tinker --execute="Cache::store('redis')->put('k',1); echo Cache::store('redis')->get('k');"`.
- **Handoff:** T-1B01 — no migration can run before the extensions exist.
- **Notes:** Two environment facts already cost this project time and are recorded as constraints. The host's own PostgreSQL occupies port 5432, so map the container to **5433**. `postgres:18+` images store data under a major-version subdirectory of `/var/lib/postgresql`, **not** `/var/lib/postgresql/data` — a volume mounted at the old path silently produces an empty database. Extensions are created by the init SQL in `docker/`, not by a migration, because a migration cannot run before the extension it needs exists.

### [T-1A03] Quality toolchain and the `composer quality` gate

- **Spec:** l2-tech-stack.md §5.9
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `composer quality` exits 0 and runs, in order: `pint --test`, `phpstan analyse` at level 8, `pest`, coverage at the configured minimum, `composer audit`, and the unused-dependency check. CI runs the same single command on push — confirm by inspecting the workflow file and one green run.
- **Handoff:** Every subsequent task in every phase is verified against this command.
- **Notes:** Wired now, not later. A gate introduced at the end of a task reports a pile of failures nobody wants to untangle; a gate that runs continuously reports one. Larastan for the Laravel-aware rules, Rector configured with the dead-code set. The N+1 detector fails the test run rather than warning — a warning in a passing suite is a warning nobody reads.

### [T-1A04] Asset pipeline — Vite, Tailwind 4, Alpine, Livewire 4

- **Spec:** l2-tech-stack.md §5.1
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `pnpm build` produces a manifest Laravel resolves; a scratch Blade view rendering one Livewire component and one Alpine directive returns 200 with both hydrated (assert via a Pest feature test on the rendered HTML).
- **Handoff:** T-1T02 (architecture tests cover `resources/`), Phase 5 (all public markup).
- **Notes:** pnpm is used here and nowhere else — PHP dependencies stay on Composer. Design tokens are **not** invented in this task: they arrive from the Figma source in Phase 5 and land in the Tailwind theme once. Leave the theme minimal rather than guessing values that will be replaced.

### [T-1B01] Migrations: identity, access, localization, geography, taxonomy

- **Spec:** l2-data-model.md §5.1, §5.2, §6.1, §6.2; l1-localization.md §5.2, §6.1; l1-geography.md §5.1, §5.2
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `php artisan migrate:fresh` applies cleanly from empty; `\d territories` shows `parent_id`, `country_id`, `level_id`, and a `geography(Point,4326)` column; every content-bearing table created here has a sibling `*_translations` table with a unique index on `(entity_id, locale)` — assert with a Pest test that enumerates the expected pairs rather than by eye.
- **Handoff:** T-1B02, T-1C01, T-1C02, T-1D01.
- **Notes:** Covers `users`, `sessions`, `two_factor_secrets`, the spatie permission tables, `role_scopes`, `object_user`, `personal_access_tokens`, `api_clients`, `languages`, `countries`, `territories`, `territory_levels`, and the taxonomy registries. `role_scopes` is this project's own addition — spatie supplies roles and permissions but not scoping to a country, territory subtree, or object category. `country_id` is denormalized onto every territory node deliberately: scope queries filter by it on every request and a recursive walk is the wrong cost for a field that never changes in practice.

### [T-1B02] Migrations: object, media, rooms, prices, reviews, contacts, favorites

- **Spec:** l2-data-model.md §5.2, §5.5; l1-object-profile.md §5.2; l1-object-catalog.md §3.1
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `php artisan migrate:fresh` applies cleanly; `objects` carries `attributes` as JSONB, a `geography(Point,4326)` column, and `deleted_at` plus `deleted_by`; a Pest test inserts an object with a type-specific attribute bag and reads it back typed.
- **Handoff:** T-1B05 (indexes over these columns), T-1D03 (models), T-1C03 (volume seeder).
- **Notes:** Filterable attributes are typed columns; the type-specific remainder is the validated JSONB bag. Full EAV is rejected — it turns the catalog query into a self-join over the largest table. Media uses Media Library's single polymorphic `media` table, not per-entity asset tables. `favorites` is modelled as visitor-facing and browser-scoped, so the owner column is nullable; the open question about cross-device persistence does not change the table's shape.

### [T-1B03] Migrations: placement and finance, advertising, content, governance

- **Spec:** l2-data-model.md §5.2, §5.6; l1-placement-monetization.md §5.1; l1-advertising.md §5.1, §5.4; l1-content-publishing.md §5.1; l1-moderation-governance.md §5.2, §5.4
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `php artisan migrate:fresh` applies cleanly; `bump_events` carries `scope_type` and `scope_id` (a bump is scoped, never global); `moderation_requests` carries both `previous_data` and `proposed_data` so a pending edit never overwrites a published record; assert both with a Pest schema test.
- **Handoff:** T-1B05, T-1B06, T-1D03.
- **Notes:** `placement_histories` and `financial_records` are append-only by requirement, enforced at the privilege level in `T-1B06` rather than in application code. Banner targeting is a pivot across territories, categories, and languages — one banner targets several nodes at once.

### [T-1B04] Migrations: notifications, analytics, platform, dormant booking

- **Spec:** l2-data-model.md §5.2, §6.5; l1-notifications.md §5.1; l1-analytics.md §5.1; l1-feature-modules.md §5.1; l1-seo.md §5.5; l1-room-reservation.md §5.1
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `php artisan migrate:fresh` applies cleanly; `stat_events` is **date-partitioned from creation** — confirm with `SELECT relname FROM pg_class WHERE relispartition` returning at least one child partition; `reservations`, `room_availabilities`, and `booking_settings` exist and are empty.
- **Handoff:** T-1C01 (module registry rows), T-1T04 (inertness test).
- **Notes:** Partition `stat_events` on day one — adding partitioning to a populated high-volume table later is a migration nobody wants to run against production. The three booking tables ship in the schema and carry no rows until the module is activated; that is the whole point of the dormant-module design, and it costs three empty tables.

### [T-1B05] Index plan — composite, spatial, trigram, GIN, partial

- **Spec:** l2-data-model.md §5.4; l1-object-catalog.md §5.3; l1-geography.md §5.4
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** every row of the index plan has a corresponding index — assert with a Pest test that queries `pg_indexes` for each expected name; `EXPLAIN (ANALYZE)` on the catalog ordering query against seeded volume shows an index scan on the composite `(country_id, territory_id, object_type_id, status)` and no sequential scan of `objects`; the same on a territory subtree expansion shows the recursive CTE using `territories(parent_id)`.
- **Handoff:** T-1T05 — the benchmark harness measures what this task makes possible.
- **Notes:** Second-largest blast radius in the phase. The catalog ordering contract and territory subtree expansion are the portal's hottest paths; both behave differently at scale, which is why the verification runs against `T-1C03`'s seeded volume rather than fixtures. Includes GiST on both `geom` columns, `gin_trgm_ops` on `object_translations(locale, name)`, GIN on `objects.attributes`, and the partial index on published, non-deleted objects.

### [T-1B06] Retention rules — soft delete, moderation scopes, append-only privileges

- **Spec:** l2-data-model.md §5.6, §6.3, §6.4; l1-moderation-governance.md §3.3, §6.3
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** a Pest test asserts that the application database role is denied `UPDATE` and `DELETE` on `audits` and `financial_records` (expect a query exception, not a silent no-op); a second test asserts that a soft-deleted object and an unmoderated object are both absent from an unqualified query on every public-facing model.
- **Handoff:** T-1T02, Phase 2 moderation surfaces.
- **Notes:** Append-only is enforced by database privilege, not by an Eloquent guard — an application-level guard is one forgotten call away from being bypassed, and the journal is exactly the table where that must not happen. Soft-delete and moderation filtering live in the shared query layer via global scopes: a single forgotten predicate republishes archived or unmoderated content silently, and that failure has no visible symptom.

### [T-1C01] Registry seeders

- **Spec:** l2-data-model.md §6.6; l1-localization.md §5.1, §5.6; l1-geography.md §5.2; l1-feature-modules.md §5.2, §6.5; l1-object-catalog.md §3.1
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** after `php artisan migrate:fresh --seed`, a Pest test asserts: exactly two languages active (`en`, `ru`) and three present-but-inactive (`ro`, `uk`, `ka`); three countries, each referencing its own primary language **even though that language is inactive**, with no validation error raised; the module registry contains `reviews` enabled and `guest_accounts`, `booking`, `payment` disabled, with `booking` declaring a dependency on `guest_accounts` and `payment` on `booking`.
- **Handoff:** T-1D02, T-1C03, Phase 2.
- **Notes:** No launch country's own primary language is active at release — Moldova, Ukraine, and Georgia have Romanian, Ukrainian, and Georgian as primary, and all three ship inactive. The system must treat that as a normal resolvable state, not a validation failure; the assertion above exists because it is the exact thing a naive foreign-key validation would reject. Never encode the language or country **count** anywhere — the difference between two active languages and five must be visible only in data.

### [T-1C02] Roles and permissions seeder with the unrevocable chief-administrator grant

- **Spec:** l1-back-office.md §5.2, §6.4; l1-platform-foundation.md §3.4
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** a Pest test asserts that every role named in the back-office spec exists as a seeded record with its permission set; a second test attempts to revoke the chief administrator's own grant through the normal permission path and asserts the operation is refused.
- **Handoff:** T-1D01, T-1T03.
- **Notes:** Roles are data, not an enumeration in code — the set ships as seed records so an operator can change it without a deployment. The unrevocable chief-administrator grant is not defensive decoration: without it, one permission edit can lock every administrator out of the panel that manages permissions, and the recovery path is a database console.

### [T-1C03] Realistic-volume demo seeder for benchmarking

- **Spec:** l2-tech-stack.md §5.9; l1-geography.md §6.3
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `php artisan db:seed --class=DemoVolumeSeeder` produces at least 50 000 objects across at least 3 000 territory nodes, each with translations in both active languages and a populated `geom`; `SELECT count(*)` on `objects`, `territories`, and `object_translations` confirms the counts; the seeder completes without exhausting memory (chunked inserts, asserted by running it under a constrained memory limit).
- **Handoff:** T-1B05 verification, T-1T05.
- **Notes:** Benchmarks against a dozen fixtures measure nothing. The catalog ranking query and territory subtree expansion behave differently at 50 000 objects than at 12, and this seeder is the only thing that makes the difference observable. Keep it separate from the registry seeders so `migrate:fresh --seed` stays fast for the normal development loop.

### [T-1D01] Scoped authorization — `role_scopes` resolution and the base policy

- **Spec:** l1-back-office.md §3.1, §5.2, §6.1; l2-tech-stack.md §5.6, §6.4; l1-platform-foundation.md §3.4
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** covered by `T-1T03`'s matrix; additionally, a PHPStan-level-8 clean `app/Services/Authorization` and `app/Policies` with no `@phpstan-ignore` entries, and an architecture test asserting no policy delegates its scope decision to a caller-supplied boolean.
- **Handoff:** Blocks every screen in Phases 2 and 4.
- **Notes:** **Highest-cascade task in the plan.** A grant may be unrestricted or bounded to a country, a territory subtree, or an object category; the subtree case resolves through the recursive hierarchy, so a region administrator governs every city beneath them. Implement it as a single server-side check applied uniformly — per-screen checks diverge, and the divergence surfaces as a country administrator editing another country's data, a failure with no visible symptom until it matters. Hiding a Filament action or a Blade block is a usability affordance and never an access control.

### [T-1D02] Feature-module registry — resolution ladder and server-side gate

- **Spec:** l1-feature-modules.md §3, §5.1, §5.3, §6.1, §6.2, §6.4
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** covered by `T-1T04`'s inertness test; additionally, a Pest test asserts most-specific-wins resolution across the full ladder (object → owner → category → country → portal → registry default) and that enabling `booking` while `guest_accounts` is off portal-wide resolves to **inactive** rather than half-enabled.
- **Handoff:** T-1T04, Phase 2 module management screen, Phase 5 gated surfaces.
- **Notes:** Resolve once per request at the boundary and pass the result down — re-resolving inside components produces pages where one section believes a module is on and another does not. Module state belongs in the cache key of any page whose composition it changes, or a toggle serves stale composition until natural expiry. Settings are few and change rarely, so cache the set in its entirety and invalidate on any toggle.

### [T-1D03] Eloquent models — relations, casts, scopes, and package traits only

- **Spec:** l2-tech-stack.md §5.3, §5.5, §5.8; l2-data-model.md §5.1
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `T-1T02`'s architecture test asserts every model lives in `App\Models` and contains no business logic; a Pest test round-trips a translated object through `astrotomic/laravel-translatable`, attaches media through Media Library, records an audit entry through `owen-it/laravel-auditing`, and expands a territory subtree through `staudenmeir/laravel-adjacency-list` — one assertion per package, so a misconfigured trait fails loudly rather than at first use in Phase 2.
- **Handoff:** Every phase from 2 onward.
- **Notes:** Models hold relations, casts, and scopes. Business logic lives in `app/Services/` — ranking, bumps, banner targeting, statistics, module resolution. The four package traits above are configuration, not logic, and belong on the model.

### [T-1T01] `migrate:fresh --seed` from empty, plus the generated ER diagram

- **Spec:** l2-data-model.md §5.7, §6.1
- **Status:** Todo
- **Assignment:** Agent
- **Goal:** Prove the schema deliverables the waived approval gate still requires.
- **Method:** `php artisan migrate:fresh --seed` against an empty database, in CI, on every push.
- **Verify:** the command exits 0 from a genuinely empty database (dropped, not truncated); the generated ER diagram is committed under `docs/` and regenerates from the applied schema rather than being hand-drawn.
- **Notes:** The client waived approval of the database structure, which removed the gate and not the work. The migration set that applies cleanly from empty **is** the field list, the type list, and the key list — and unlike a parallel document it cannot drift from the schema. This is also the reason the diagram is generated: a hand-drawn one drifts the first time a migration lands.

### [T-1T02] Architecture tests — conventions enforced mechanically

- **Spec:** l2-tech-stack.md §5.9
- **Status:** Todo
- **Assignment:** Agent
- **Goal:** Make every convention machine-checkable, since a rule a machine cannot check is a rule that erodes.
- **Method:** Pest `arch()` tests, run as part of `composer test:arch` and `composer quality`.
- **Verify:** `composer test:arch` exits 0 and covers, at minimum: `declare(strict_types=1)` in every file; no `dd`, `dump`, `var_dump`, `ray`, or `print_r` outside tests; models confined to `App\Models` with no business logic; `App\Filament` and `App\Livewire` never touching the `DB` facade; controllers, jobs, and services `final` unless deliberately extended; and **no specification path, task identifier, phase name, or specification filename anywhere under `app/`, `resources/`, or `database/`**.
- **Notes:** The last rule is the containment boundary made mechanical. Releases may ship without the design directory, so any reference to it from product code becomes dead content. Where design rationale matters at a code site, restate it in plain language.

### [T-1T03] Authorization test matrix — scoped grants deny across every scope kind

- **Spec:** l1-back-office.md §3.1, §5.2; l1-platform-foundation.md §3.4
- **Status:** Todo
- **Assignment:** Agent
- **Goal:** Prove `T-1D01` denies as well as it allows, across all four scope kinds.
- **Method:** Pest feature tests over a seeded fixture spanning two countries, nested territories, and two object categories.
- **Verify:** the matrix asserts, for each of `none` / `country` / `territory` / `category` scoping and for each permission verb: an in-scope target is allowed, an out-of-scope target is **denied at the server**, and a territory-scoped grant reaches every descendant of its node but no sibling subtree. Denial is asserted on the policy result, not on the absence of a UI control.
- **Notes:** The asymmetry matters. Allow-path tests pass trivially; the failure this suite exists to catch is a country administrator successfully editing another country's object, which no allow-path test will ever surface.

### [T-1T04] Module inertness test — disabled means absent, both directions

- **Spec:** l1-feature-modules.md §3, §5.5, §6.3
- **Status:** Todo
- **Assignment:** Agent
- **Goal:** Prove that a disabled module is inert rather than hidden, and that a gated path still works when enabled.
- **Method:** Pest feature tests parameterized over module state, run with `booking` both off and on.
- **Verify:** with `booking` disabled — every gated route returns 404 (not 403, which would confirm the capability exists), its scheduled jobs are absent from `php artisan schedule:list`, and its markup and sitemap entries are absent from the rendered object page. With `booking` enabled for one object — the same routes resolve, the contact rail is still present and still above the fold, and non-participating objects are unchanged.
- **Notes:** Both directions are required. A gated capability exercised only in its disabled state decays into one that no longer works when someone finally enables it, which would defeat the design's purpose. The contact-rail assertion encodes an invariant that is easy to break by accident: booking is additive to the portal's proposition, never a replacement for it.

### [T-1T05] Benchmark harness — catalog ranking and subtree expansion against budgets

- **Spec:** l2-tech-stack.md §5.9
- **Status:** Todo
- **Assignment:** Agent
- **Goal:** Make the performance budgets measured rather than assumed, from the first phase.
- **Method:** `composer bench` against the `T-1C03` seeded volume, reporting per-surface timings and query counts.
- **Verify:** `composer bench` runs the catalog ranking query and a territory subtree expansion at seeded volume and reports measured figures against the stated budgets — catalog page under 400 ms on a cache miss and under 100 ms TTFB on a hit, object page under 300 ms on a miss, search p95 under 300 ms, and no single request exceeding 30 queries. The command fails when a budget is breached rather than printing a number for someone to notice.
- **Notes:** No public pages exist yet, so this phase measures the two queries underneath them — the ranking expression and the recursive subtree expansion. Both are the portal's hottest paths and both change behaviour with data volume. Re-run whenever either changes; the harness exists so that "we will benchmark it later" is not a decision anyone has to make.
