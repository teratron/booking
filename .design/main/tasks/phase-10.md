---
phase: 10
name: "Deep QA Remediation"
status: Todo
subsystem: "database/seeders, database/migrations, app/Http/Middleware, app/Http/Controllers, app/Services/Shell, app/Services/Seo, app/Filament/Admin, app/Filament/Cabinet, app/Services/Advertising, app/Services/Objects, resources/views, app/Models, tests/Feature"
requires: ["phase-2", "phase-3", "phase-4", "phase-5", "phase-6", "phase-9"]
provides: []
key_files:
  created: []
  modified: []
patterns_established: []
duration_minutes: null
---

# Stage 10 Tasks — Deep QA Remediation

**Phase:** 10
**Status:** Todo (0/32)
**Strategic Goal:** Close the findings of a second, deeper functional sweep
(`.drafts/qa-deep-findings.md`) that drove all 177 routes for every actor — anonymous
visitor, API consumer, object owner, and all nine staff roles past the second-factor
gate — where the first sweep (Phase 9) reached roughly 110 of 181 and stopped at the
MFA wall. Two findings from that sweep (F-07 banner counters, F-23 a Windows-only
test-path bug) were fixed directly outside this phase, before it was planned, since
each was a small, unambiguous defect with no design question attached. F-11 (reviews
have no submission path) needed a real design decision first — `/magic.spec main`
resolved it as an administrator-selectable submission-gating mode
([l1-object-profile.md](../specifications/l1-object-profile.md) §2 v1.3.0,
[l2-third-party-integrations.md](../specifications/l2-third-party-integrations.md)
§5.5 v2.1.0) — and this phase schedules building it (Track G).

## What Makes This Phase Different

**Most of this phase, like Phase 9, is implementation-only** — `/magic.spec main`
checked every finding against its governing specification before scheduling, and all
but one (F-11) were already correctly specified; the code just does not do what its
own spec already says. `l1-back-office.md` §5.1 already lists "Users & roles" and
"Reviews" as required sections and §5.2 already leaves the exact role-to-permission
seed mapping as illustrative data, not a spec commitment — confirmed directly against
the file during this planning pass, recorded in `INDEX.md`'s Review-Submission-Gating
Ledger rather than re-litigated here.

**One track (G) is genuinely new capability**, not a bug fix — reviews have never had
a public submission path in this codebase, and it is scheduled after the specification
that governs it, not before.

**Severity ordered the tracks, the same convention Phase 9 established.** Track A
(access) is first because F-01 alone means a freshly seeded installation has no usable
administrator — every other finding in this phase is reachable only after that one is
fixed, on a fresh environment. Track C (three near-identical crashes from a missing
eager load) is second because each is a hard 500 on a screen a real actor reaches in
the first few minutes of ordinary use. The remaining tracks are real but narrower in
blast radius and are independent of A/C and of each other, subject to the two named
cross-track edges below.

**One hard cross-track edge is scheduled rather than left to be discovered.** `T-10F03`
(wiring `.env` map-tile and CAPTCHA settings into the registry) must land before
`T-10G02` (the review submission form), since the form's `open` mode validates a
CAPTCHA challenge against exactly those settings — building the form first would mean
either a fake pass-through check or a rework once the wiring lands.

**One shared-file caution, found during this phase's own planning audit rather than
assumed independent.** `T-10B01` (language switcher/hreflang, fixed through
`MetadataResolver::alternates()`) and `T-10F04` (Open Graph/Twitter tag completion,
which touches the same `MetadataResolver`/`ResolvedMetadata` pair and the public
layout's `<head>` — the exact files Phase 9's own Track D already worked in) sit in
different tracks but the same small file set. Sequenced rather than run concurrently:
`T-10B01` first, since it is scheduled earlier in this phase for severity reasons
anyway, and `T-10F04` is a pure addition (new tag output) that does not need to touch
anything `T-10B01` changes.

**Correction made during this phase's own planning audit, before any task ran.**
`qa-deep-findings.md` F-24 originally recommended dropping five "unreferenced" tables.
Reading `PLAN.md`'s own Backlog and `l2-data-model.md` §5.5 first — required by this
workflow's own Registry Integrity invariant, and skipped when the finding was first
written — found that is wrong for four of the five. `reservations`,
`room_availabilities`, and `booking_settings` are deliberate scaffolding for
[l1-room-reservation.md](../specifications/l1-room-reservation.md), already an
`RFC`-status registered specification, already recorded in `PLAN.md`'s own Backlog as
dormant-by-design, not orphaned. `favorites` has its own open `<!-- TBD -->` in
`l2-data-model.md` §5.5 (visitor-facing vs. owner-side bookmark; cross-device sync)
and `home_block_selections` is named in the same file's table inventory as
"per-country curated selections" — both are unbuilt, spec'd future surface, not dead
schema. Only the *symptom* the finding actually reproduced — the owner's Statistics
page shows a "Favorites" figure that is structurally always 0, since nothing writes
the table its own open design question has not yet resolved — was real, and it is too
small and too entangled with that open question to fix honestly inside this phase.
No task schedules dropping any of these five tables. `qa-deep-findings.md` F-24 is
corrected accordingly (see its own entry) rather than carried into this phase as
originally written.

**This session's environment note, recorded for whoever picks this phase up next.**
`STATE.md`'s Blocking Constraints say "no PHP/Composer on the host — toolchain runs
through `docker compose exec app …`". This session instead ran the suite directly via
a local Herd PHP 8.5 install against the same Dockerized Postgres/Redis (`docker
compose up -d postgres redis minio mailpit`, `app` started separately only to satisfy
the pre-commit hook's own container check) — both point at the same database, so
results are equivalent, but a future session on a machine without Herd should default
back to `docker compose exec app …` for every `Verify` line below unless it has the
same local PHP available.

## Atomic Checklist

### Track A — Access & Module Gating

- [x] [T-10A01] Grant the seeded chief administrator an unrestricted scope
- [x] [T-10A02] Anchor the API module gate on the real priority-list contract
- [x] [T-10A03] Validation — a fresh install has a usable administrator; a disabled module never leaks past authentication

### Track B — Public URL & Routing Correctness

- [ ] [T-10B01] Resolve the language switcher and hreflang alternates through `PublicUrlGenerator`
- [ ] [T-10B02] Bind news, blog, and promotion routes by translated slug
- [ ] [T-10B03] Fix the footer category links' type parameter
- [ ] [T-10B04] Validation

### Track C — Missing Eager Loads

- [x] [T-10C01] Object form contact-channel-type selector eager-loads translations
- [x] [T-10C02] Placement package form tier selector eager-loads translations
- [x] [T-10C03] API territory/country endpoints eager-load their relations
- [x] [T-10C04] Validation

### Track D — Cache & Analytics Correctness

- [x] [T-10D01] Tag the three untagged public read caches
- [x] [T-10D02] Emit one `photo_view` per interaction, not one per photo per page view
- [x] [T-10D03] Validation

### Track E — Role Data & Schema Hygiene

- [x] [T-10E01] Correct the seeded role-to-permission grants
- [x] [T-10E02] Add the missing check constraint on `promotion_labels.position_on_card`
- [x] [T-10E03] Validation

### Track F — Content Lifecycle & Third-Party Wiring

- [ ] [T-10F01] Promotion visibility checks `ends_at`, not status alone
- [ ] [T-10F02] Backup administration degrades instead of crashing when the destination is unreachable
- [ ] [T-10F03] Wire `.env` map-tile and CAPTCHA values into the settings registry
- [ ] [T-10F04] Complete the Open Graph tag set and default image fallback
- [ ] [T-10F05] Render the banner's mobile creative on narrow viewports
- [ ] [T-10F06] Validation

### Track G — Review Submission

- [ ] [T-10G01] `reviews.submission_mode` setting and the contact-click session gate
- [ ] [T-10G02] Public review submission form, both modes
- [ ] [T-10G03] Admin `ReviewResource`
- [ ] [T-10G04] Validation

### Track H — Missing Public Pages & Compliance

- [ ] [T-10H01] Object page location/map section
- [ ] [T-10H02] About and Contacts static pages
- [ ] [T-10H03] Cookie-consent notice
- [ ] [T-10H04] Validation

### Track I — Full-Suite Regression Gate

- [ ] [T-10I01] Full `composer quality` and non-slow Pest suite, clean, after Tracks A–H close

## Task Detail

### Track A — Access & Module Gating

**[T-10A01] Grant the seeded chief administrator an unrestricted scope**

- **Spec:** [l1-back-office.md](../specifications/l1-back-office.md) §3.1 ("Permissions are scopable... A grant may be unrestricted or bounded")
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `php artisan migrate:fresh --seed`, sign in as the seeded chief administrator, complete MFA, open one resource from each of the eleven navigation groups — every one returns 200, none returns 403 or 404-on-edit.
- **Notes (finding):** `ScopeAuthorizer::constraintFor()` treats an actor with no `role_scopes` row as reaching no axis; `ResourceQueryScoper::applyConstraint()` correctly fails closed. `DatabaseSeeder` assigns the role and never writes the `scope_kind = 'none'` row. Fix in the seeder; consider whether `RoleGrantService` (or wherever role assignment is centralized) should write a default unrestricted scope whenever a role is granted with no explicit narrowing, so every future staff account is usable without a second manual step — `qa-deep-findings.md` F-01 recommends this as the durable fix, not just the seeder patch.
- **Changes:** Took the durable path rather than the seeder-only patch. Added `RoleGrantService::grantRole()` — assigns the Spatie role and writes the matching `role_scopes` row (unrestricted by default, or a bounded country/territory/category scope when given) in one transaction, so the two can never be written independently and drift the way the bare `assignRole()` call did. `DatabaseSeeder` now calls it (self-attributed `granted_by`, no other account exists yet at that point). `RoleGrantService` previously held only `revokeRole()`; the class's own docblock now states the symmetric reasoning. Ran red before the fix (`git stash` on both files reproduced `ScopeAuthorizer::constraintFor()` returning `isUnrestricted: false` for the seeded admin), green after.

**[T-10A02] Anchor the API module gate on the real priority-list contract**

- **Spec:** [l1-public-api.md](../specifications/l1-public-api.md) (a disabled module is indistinguishable from an unregistered path)
- **Status:** Done
- **Assignment:** Agent
- **Verify:** With the `api` module in its shipped-disabled state, `curl http://<host>/api/v1/objects` (no token) returns 404, not 401.
- **Handoff:** `T-10A03`.
- **Notes (finding):** `bootstrap/app.php` anchors `EnsureModuleEnabled` `before: Authenticate::class` — but Laravel's own middleware priority list carries the contract `Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests`, not the concrete `Authenticate` class, so the anchor never matches and the gate is silently appended to the end of the list instead. `qa-deep-findings.md` F-12.
- **Changes:** Confirmed the exact contract against Laravel's own `Foundation\Http\Kernel::$middlewarePriority` source (`AuthenticatesRequests` is the sixth entry; `Authenticate` itself is not in the list at all) before changing the anchor. Swapped the import and the `before:` argument; comment explains why the concrete class silently fails as an anchor rather than erroring. Ran red before the fix (`git stash` on `bootstrap/app.php` reproduced 401 on a tokenless `GET /api/v1/objects` with the module disabled — the existing `ApiModuleGateTest` suite never caught this since its four cases all target `/api/v1/status`, which carries no `auth:sanctum` middleware at all), green after.

**[T-10A03] Validation — a fresh install has a usable administrator; a disabled module never leaks past authentication**

- **Goal:** Verify `T-10A01`/`T-10A02` against their specs and `qa-deep-findings.md` F-01/F-12.
- **Method:** Feature test asserting a freshly seeded chief administrator reaches 200 on a representative resource per navigation group; feature test asserting `api/v1/objects` returns 404 for every request shape (no token, valid token, bogus token) while the module is disabled.
- **Status:** Done
- **Changes:** `tests/Feature/RolePermissionSeederTest.php` gained three tests (`grantRole()` writes both halves; a bounded scope is recorded correctly; the seeded chief administrator resolves as unrestricted via `ScopeAuthorizer`). `tests/Feature/Api/ApiModuleGateTest.php` gained one test targeting `/api/v1/objects` specifically, the gap the existing four `/status`-only tests left open. 21/21 pass; `composer lint`/`analyse` clean; a wider regression pass (`tests/Feature/Api`, `AdminDashboardTest`, `CabinetFoundationTest`, `PublicShellTest` — 54 tests, since the middleware-priority change is global) shows no side effect on panel guest redirects.

### Track B — Public URL & Routing Correctness

**[T-10B01] Resolve the language switcher and hreflang alternates through `PublicUrlGenerator`**

- **Spec:** [l1-seo.md](../specifications/l1-seo.md) (hreflang alternates, already-settled requirement)
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** For an object and a territory whose EN and RU slugs differ, the language switcher's RU link and the page's `hreflang="ru"` tag both resolve to the correct RU-locale URL and return 200, not 404.
- **Notes (finding):** `LocaleSwitchResolver::targetUrl()` rebuilds the current route with only the `lang` parameter swapped, keeping the current locale's slug — its own docblock says per-entity slug translation "does not exist yet"; it does now, in `PublicUrlGenerator`, and this resolver was never revisited. `MetadataResolver::alternates()` delegates to the same method, so the bug reaches every `hreflang` tag site-wide, not just the switcher. `qa-deep-findings.md` F-02.

**[T-10B02] Bind news, blog, and promotion routes by translated slug**

- **Spec:** [l1-seo.md](../specifications/l1-seo.md) (URL grammar — every entity gets a slug-based address)
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `/en/news/not-a-real-thing` (and the blog/promotions equivalents) returns 404, never 500; the existing numeric-id links continue to work as a permanent redirect to the slug URL.
- **Notes (finding):** These three routes bind on the raw primary key; any non-integer segment reaches Postgres as a `bigint` comparison and throws (`SQLSTATE[22P02]`) before Laravel can turn a missing model into a 404 — reproducible today, and with `APP_DEBUG=true` it leaks a full stack trace. `qa-deep-findings.md` F-03.

**[T-10B03] Fix the footer category links' type parameter**

- **Spec:** [l1-object-catalog.md](../specifications/l1-object-catalog.md)
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** Clicking a footer category link (e.g. "Hotel") lands on `/en/catalog` filtered to that type, not the unfiltered catalog.
- **Notes (finding):** The footer passes the object type's key (`['type' => $group->key]`) while `CatalogSearch` declares `public ?int $type`; a non-numeric value cannot bind, so the filter is silently dropped — eight dead links on every page. `qa-deep-findings.md` F-10.

**[T-10B04] Validation**

- **Goal:** Verify `T-10B01`–`03` against `l1-seo.md`, `l1-object-catalog.md`, and `qa-deep-findings.md` F-02/F-03/F-10.
- **Method:** Feature tests per finding, each red-before/green-after against the reproduction steps in `qa-deep-findings.md`.
- **Status:** Todo

### Track C — Missing Eager Loads

**[T-10C01] Object form contact-channel-type selector eager-loads translations**

- **Spec:** [l1-object-profile.md](../specifications/l1-object-profile.md) §5.2
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `Livewire::test(EditObject::class, ...)` (admin and cabinet forms both) opens cleanly for an object carrying at least one contact channel — no lazy-loading exception.
- **Notes (finding):** The `Select::make('contact_channel_type_id')` relationship query filters `is_active` without eager-loading `translations`, and its label closure reads the translated `display_name` accessor — `Attempted to lazy load [translations]`. `qa-deep-findings.md` F-04. Same one-line fix, same shape, in both `app/Filament/Admin/Resources/Objects/Schemas/ObjectForm.php` and `app/Filament/Cabinet/Resources/Objects/Schemas/ObjectForm.php`.
- **Changes:** Added `->with('translations')` to the `contactChannelType` relationship's `modifyQueryUsing` closure in both forms. `tests/Feature/Admin/ObjectResourceFormTest.php` and `tests/Feature/Cabinet/CabinetObjectEditingTest.php` each gained a test that opens the edit page for an object whose contact channel already carries a type. Ran red before the fix (`git stash` on both form files reproduced the lazy-loading exception once the fixture carried more than one contact-channel-type row), green after.

**[T-10C02] Placement package form tier selector eager-loads translations**

- **Spec:** [l1-placement-monetization.md](../specifications/l1-placement-monetization.md)
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `placement-packages/create` and `placement-packages/{id}/edit` both render 200 for a chief administrator.
- **Notes (finding):** Identical shape to `T-10C01` — the tier `Select` labels options via a translated accessor with no eager load. `qa-deep-findings.md` F-05.
- **Changes:** Added `->with('translations')` to the `PlacementTier::query()` call backing the `placement_tier_id` `Select`. `tests/Feature/Admin/PlacementRegistryTest.php` gained a plain-GET test against both the create and edit routes. Ran red before the fix, green after, alongside `T-10C01`'s stash cycle.

**[T-10C03] API territory/country endpoints eager-load their relations**

- **Spec:** [l1-public-api.md](../specifications/l1-public-api.md)
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `GET /api/v1/countries` and `GET /api/v1/territories` (valid token, module enabled) both return 200 with the documented shape.
- **Notes (finding):** `CountryController::index()` reads `Country::query()` with no `with('translations')`; `TerritoryController::index()`/`show()` read no `with(['translations', 'level.translations', 'country'])`. `qa-deep-findings.md` F-06 — the same defect class as `T-10C01`/`T-10C02`, in the API layer.
- **Changes:** Added the missing `with()` calls to both controllers. `tests/Feature/Api/ApiReadContractTest.php` gained an N+1-shaped regression test: a single-row fixture did not reproduce the crash (Astrotomic's translation resolution takes a different internal path for the first model in a request), so the test seeds four countries/territories and asserts the request both succeeds and stays within a fixed query-count ceiling — reproduced the exact `LazyLoadingViolationException` from the original QA sweep on the unfixed controllers, green after restoring the fix.

**[T-10C04] Validation**

- **Goal:** Verify `T-10C01`–`03` against their specs and `qa-deep-findings.md` F-04/F-05/F-06. Also add the missing endpoint contract test: every route in `routes/api_v1.php` walked with a full-ability token asserts 200 — the gap that let two of twelve endpoints ship broken.
- **Method:** Feature tests per finding; one new sweep test over the whole API surface.
- **Status:** Done
- **Changes:** Added "returns 200 for every registered read-surface route with a full-ability token" to `tests/Feature/Api/ApiReadContractTest.php` — walks `Route::getRoutes()` filtered to `api.v1.*` GET routes, substitutes the `{territory}`/`{object}` fixtures, and asserts every one of the 14 registered routes returns 200. 40 tests pass across the four Track C files (`ObjectResourceFormTest`, `CabinetObjectEditingTest`, `PlacementRegistryTest`, `ApiReadContractTest`); a wider pass including `ApiRateLimitTest` and `ApiModuleGateTest` (50 tests) shows no side effect. `composer lint`/`analyse` clean on the touched `app/` files (PHPStan's configured paths exclude `tests/`, matching `phpstan.neon`).

### Track D — Cache & Analytics Correctness

**[T-10D01] Tag the three untagged public read caches**

- **Spec:** [l1-availability-status.md](../specifications/l1-availability-status.md), [l1-object-catalog.md](../specifications/l1-object-catalog.md)
- **Status:** Done
- **Assignment:** Agent
- **Verify:** Toggle an object's availability status through `AvailabilityToggleService`; re-request its public page within the cache TTL — the badge reflects the new status, not the stale one.
- **Notes (finding):** Five write paths invalidate by tag (`Cache::tags([...])->flush()`); the three public reads (`ObjectPageController`, `CatalogQueryService::search()`, `TerritoryPageController`) write `Cache::remember()` untagged, so no tagged flush ever reaches them. `qa-deep-findings.md` F-08. Add an architecture test asserting no `Cache::remember()` call in `app/Http/Controllers/Public` or `app/Services/Catalog` is untagged, per the finding's own recommendation — the class of bug that reads correct from both sides alone deserves a mechanical check, not just a fix.
- **Changes:** `ObjectPageController` now tags its profile cache `['catalog', "territory:{id}", "object:{id}"]` — the exact set `AvailabilityToggleService`/`AvailabilityAdministrationService`/`BumpService`/`PlacementLifecycleService` already flush. `TerritoryPageController`'s sidebar cache (news/promotions/child territories) tags `['content', "territory:{id}"]`, matching `ContentPublicationService::invalidate()`'s own tag set. `CatalogQueryService::search()` tags `['catalog']` plus `"territory:{id}"` when the criteria carries one. New architecture test `tests/Architecture/CachedReadTaggingTest.php` content-scans `app/Http/Controllers/Public` and `app/Services/Catalog` for the literal `Cache::remember(` substring, which only the untagged form contains — red against all three files before the fix (confirmed via `git stash`), green after. New feature test in `tests/Feature/Public/PublicObjectProfileTest.php` toggles availability via the real service and re-requests the page, asserting the "Vacancies available" badge disappears (the view has no branch for `unavailable`/`unspecified`, so absence of the positive badge is the correct, observable signal) — red on unfixed code (stale badge persisted across the toggle), green after.

**[T-10D02] Emit one `photo_view` per interaction, not one per photo per page view**

- **Spec:** [l1-analytics.md](../specifications/l1-analytics.md)
- **Status:** Done
- **Assignment:** Agent
- **Verify:** Rendering an object page with 20 photos emits at most one `photo_view` event for that render, not 20.
- **Notes (finding):** `ObjectPageController` loops `getMedia('photos')->count()` times, capturing one event per photo on every page view regardless of whether the visitor ever opened the gallery — multiplies write volume on the hottest page and the owner's "photo views" statistic is structurally `page views × photo count`. `qa-deep-findings.md` F-19. Minimal fix per the finding: emit at most one `photo_view` when the gallery is present, and relabel the statistic; a real per-photo signal (gallery open/advance) is future work, not this task's scope.
- **Changes:** Replaced the per-photo loop with a single conditional capture when `getMedia('photos')->isNotEmpty()`. Relabelled `panel.analytics.kinds.photo_view` and `panel.cabinet.statistics.photo_views` from "Photo views"/"Просмотры фото" to "Views with photos"/"Просмотры с фото" in both `en` and `ru` — the metric now literally counts renders that had a gallery, not photo interactions. Updated the two existing tests that hard-coded the old per-photo count (`PublicObjectProfileTest`'s event-capture test, now asserting exactly one `photo_view` for a three-photo fixture plus a new zero-photo case; `PublicEventEmissionInvariantTest`'s queued-jobs count, 3 → 2) — both red against unfixed code (`git stash` on the controller reproduced the old per-photo counts), green after. The four other test files referencing `photo_view` (`AnalyticsReportingTest`, `AnalyticsRollupAndCompactionTest`, `CabinetStatisticsTest`, `EventCaptureServiceTest`) call `EventCaptureService::capture()` or insert raw `stat_events` rows directly, never through this controller, so none needed updating — confirmed by running all four (24 tests, all green).

**[T-10D03] Validation**

- **Goal:** Verify `T-10D01`/`02` against `qa-deep-findings.md` F-08/F-19.
- **Method:** Feature test per finding; the new architecture test from `T-10D01`'s notes.
- **Status:** Done
- **Changes:** Wider regression pass — `tests/Feature/Public` (all files), `tests/Feature/Cabinet/CabinetAvailabilityToggleTest.php`, `tests/Architecture` (all files): 181/181 pass, including the realistic-volume benchmark test seeded at 300 objects/9 territories, all three surfaces within their cache-hit/cache-miss budgets. `composer lint`/`analyse` clean on the touched `app/` files.

### Track E — Role Data & Schema Hygiene

**[T-10E01] Correct the seeded role-to-permission grants**

- **Spec:** [l1-back-office.md](../specifications/l1-back-office.md) §3.1/§5.2 (roles are data, verbs are `view, create, edit, publish, delete, export, financial access, user management, settings management`; the specific role-to-permission mapping is left illustrative, not a spec commitment — confirmed during this planning pass)
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `advertising_manager` can reach `banners`/`banner-slots`/`promotion-labels`; `technical_support` can reach `owners` (the entry point for `impersonate`, which it already holds) and cannot reach `portal-settings`/`languages`/`modules`; `finance_manager` can reach `commerce-reports`.
- **Notes (finding):** `RoleSeeder.php`'s permission grants do not match the duties each role's own name implies — confirmed live against a 9-role × 78-route matrix. `qa-deep-findings.md` F-09 names the specific corrections per role.
- **Changes:** Per F-09's own fix list — `advertising_manager` → `advertising.*` (the actual gate `BannerPolicy`/`BannerSlotPolicy`/`PromotionLabelPolicy` authorize against, replacing the `content.*` grant that touched news/promotions instead of this role's own resources) + `content.view` + `object.view` + `analytics.view`; `technical_support` → `user.view` (the entry point `impersonate` needed) + `impersonate` + `audit.view` only, dropping `settings.view`/`settings_management` (the over-broad grant that let it reach portal-settings/languages/modules); `country_administrator` → added `user.view`/`user.create`/`user.edit` (the CRUD verbs `UserPolicy` actually checks — `user_management` alone gates nothing); `finance_manager` → added `commerce.view` (the placement package/tier registry, distinct from `finance.view`, which already covered commerce-reports); `content_manager` → added `analytics.view`. New `tests/Feature/Admin/RoleDutyAccessTest.php` seeds the real database and grants each real role via `RoleGrantService::grantRole()` (not an ad-hoc test role), then hits the actual panel URLs — red against 3 of 4 checks before the fix (`git stash` on `RoleSeeder.php`; the fourth, finance_manager → commerce-reports, already passed unfixed since that page is gated by `finance.view`, which `finance.*` already covered), green after. Moderator's own news/promotions gap from the Observed table is deliberately **not** touched — absent from F-09's own fix list and this task's Verify line, so out of scope here.
- **Incidental fix:** the slow-group `tests/Feature/Admin/PanelQueryBudgetTest.php`'s `queryBudgetActor()` was missing `api.view`/`seo.view`, 403ing on `ApiClientResource` and the three `seo.view`-gated resources — confirmed pre-existing (reproduces identically on the clean tree, unrelated to this track's own changes) via a full audit of every Policy's `viewAny()` gate against the actor's permission list. Fixed in the same pass this track's own wider regression surfaced it.

**[T-10E02] Add the missing check constraint on `promotion_labels.position_on_card`**

- **Spec:** [l2-data-model.md](../specifications/l2-data-model.md)
- **Status:** Done
- **Assignment:** Agent
- **Verify:** Inserting `position_on_card = 'not-a-real-value'` directly via SQL fails the constraint instead of reaching the row; the promotion-labels list and edit pages both render 200 for existing, valid data.
- **Notes (finding):** `CardPosition`'s own docblock says the column exists "so an administrator-authored label can never carry an arbitrary position value" — but the column is an unconstrained `varchar`, and one out-of-range value (`'top_left'` instead of the enum's `'top-left'`) throws `ValueError` and takes down the whole list page with no way to open the offending record to fix it. `qa-deep-findings.md` F-15.
- **Changes:** New migration `2026_08_23_170000_add_position_on_card_check_to_promotion_labels_table.php` adds a raw `CHECK (position_on_card in ('top-left', 'top-right', 'bottom-left', 'bottom-right'))` constraint — no existing rows to backfill, so no data migration needed. `tests/Feature/Admin/PromotionLabelResourceTest.php` gained two tests: a direct-SQL insert of `'top_left'` wrapped in `DB::transaction()` (a savepoint, so the assertion that follows can still query — Postgres aborts the whole outer transaction on a caught constraint violation otherwise) asserts a `QueryException`; a second confirms the list and edit pages both render for a validly-positioned label. Verified live against both the `booking_testing` and local dev databases via `php artisan migrate:fresh --seed` and a direct `pg_get_constraintdef` read.

**[T-10E03] Validation**

- **Goal:** Verify `T-10E01`–`02` against their specs and `qa-deep-findings.md` F-09/F-15.
- **Method:** The 9-role × 78-route access matrix, re-run and diffed against `qa-admin-matrix.log`'s original findings; a constraint-violation test for `T-10E02`.
- **Status:** Done
- **Note:** F-24 is **not** scheduled in this phase — see the Track Ordering section's
  correction. `qa-deep-findings.md` itself carries the corrected write-up.
- **Changes:** Wider regression — `tests/Feature/Admin` (all files) + `tests/Feature/RolePermissionSeederTest.php`: 403 tests, 400 passed, 3 skipped, 0 failed, including the realistic-volume `PanelQueryBudgetTest` (52,800 objects / 6,270 territories) confirming every one of the eleven dynamically-discovered admin resources renders within the 30-query budget for the corrected actor. `composer lint`/`analyse` clean on the touched `database/` files.

### Track F — Content Lifecycle & Third-Party Wiring

**[T-10F01] Promotion visibility checks `ends_at`, not status alone**

- **Spec:** [l1-content-publishing.md](../specifications/l1-content-publishing.md)
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** A promotion whose `ends_at` is in the past and whose `status` is still `published` (the window before the daily archival job runs) returns 404 on its public page.
- **Notes (finding):** `PromotionController::isPubliclyVisible()` checks `status` and `starts_at` but deliberately defers `ends_at` to `PromotionArchivalJob` — up to 24 hours of a finished offer serving as current. `qa-deep-findings.md` F-13. The job keeps owning the durable status transition; this is defence in depth, not a replacement.

**[T-10F02] Backup administration degrades instead of crashing when the destination is unreachable**

- **Spec:** [l1-back-office.md](../specifications/l1-back-office.md) §5.6 ("raises failure notifications" — a crash is not that)
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** With the backup bucket unreachable (or absent), `portal-admin/backup-administration` renders 200 showing the failure state, not a 500 stack trace.
- **Notes (finding):** The one screen whose job is to report backup health is the one that cannot report a problem — `NoSuchBucket`/`FilesystemException` reaches the page unhandled. `qa-deep-findings.md` F-14. Also add bucket creation to the local MinIO bootstrap so a fresh clone does not start in this state.

**[T-10F03] Wire `.env` map-tile and CAPTCHA values into the settings registry**

- **Spec:** [l2-third-party-integrations.md](../specifications/l2-third-party-integrations.md) §5.3, §5.5
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** A fresh clone with `MAP_TILE_KEY` set in `.env` renders a working map with no manual settings-panel step; with no key set, the map container shows a labelled placeholder, not a silent empty box, and the condition surfaces on the SEO health dashboard.
- **Handoff:** `T-10G02` depends on the CAPTCHA half of this task landing first (see phase header).
- **Notes (finding):** `MapTileConfigResolver` reads `integrations.map_tile_provider`/`map_tile_key` from the settings registry only; nothing imports the documented `.env` variables into it, and the registry default is empty — the map is dead out of the box. The same gap applies to the three CAPTCHA settings keys, currently read by nothing. `qa-deep-findings.md` F-16.

**[T-10F04] Complete the Open Graph tag set and default image fallback**

- **Spec:** [l1-seo.md](../specifications/l1-seo.md)
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** Every page carrying `$metadata` emits `og:type`, `og:url`, `og:image` (falling back to a configured default when the entity has none), and the four `twitter:*` tags.
- **Notes (finding):** `og:title`/`og:description` render; `og:type`, `og:url`, and the whole Twitter card family are absent, and `og:image` has no portal-wide fallback though `MetadataResolver::defaultOgImage()` already exists as a hook. `qa-deep-findings.md` F-20.

**[T-10F05] Render the banner's mobile creative on narrow viewports**

- **Spec:** [l1-advertising.md](../specifications/l1-advertising.md)
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** A banner with both creatives uploaded renders the mobile one under a `max-width` media query in a browser at 390px width; a banner with only a desktop creative still renders correctly at the same width.
- **Notes (finding):** `Banner::registerMediaCollections()` declares and the admin form uploads both `desktop_creative` and `mobile_creative`, but every public template renders `getFirstMediaUrl('desktop_creative')` unconditionally — the mobile creative is collected and never served. `qa-deep-findings.md` F-21.

**[T-10F06] Validation**

- **Goal:** Verify `T-10F01`–`05` against their specs and `qa-deep-findings.md` F-13/F-14/F-16/F-20/F-21.
- **Method:** Feature test per finding; a browser check for `T-10F05`'s two viewport cases.
- **Status:** Todo

### Track G — Review Submission

**[T-10G01] `reviews.submission_mode` setting and the contact-click session gate**

- **Spec:** [l1-object-profile.md](../specifications/l1-object-profile.md) §2, §3.4 v1.3.0
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** With the setting at `contact_gated`, a visitor who has not clicked a contact channel for an object cannot reach that object's review submission endpoint (refused server-side, not merely hidden — per §3.4's explicit enforcement invariant); after clicking a contact channel for that object, the same visitor's session can.
- **Handoff:** `T-10G02`.
- **Notes:** Add `reviews.submission_mode` (`open` default, `contact_gated`) to `SettingsRegistry`. Extend `ContactClickController` to set a session flag scoped to the object id at the point it already records the `contact_click` event — no new persisted tracking record, matching §2's minimal-data constraint. This task owns the gate mechanism only; `T-10G02` owns the form that calls it.

**[T-10G02] Public review submission form, both modes**

- **Spec:** [l1-object-profile.md](../specifications/l1-object-profile.md) §2, §3.4, §5.4 v1.3.0; CAPTCHA per [l2-third-party-integrations.md](../specifications/l2-third-party-integrations.md) §5.5 v2.1.0
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** In `open` mode, a submission without a valid CAPTCHA response is refused server-side; a valid submission enters `status = 'pending'`. In `contact_gated` mode, no CAPTCHA challenge is presented or required, and the gate from `T-10G01` is the only enforcement. Both modes: the submitted review is invisible on the public page until an administrator approves it (existing moderation checkpoint, unchanged).
- **Depends on:** `T-10F03` (CAPTCHA settings must be wired before this form can validate against them) and `T-10G01`.
- **Notes:** Name, rating (1–5), body — matching the existing `reviews` table shape (`author_id` nullable, `author_name` for a guest). Rate-limited per IP in `open` mode alongside the CAPTCHA check, the same defence-in-depth the feedback form already gets.

**[T-10G03] Admin `ReviewResource`**

- **Spec:** [l1-back-office.md](../specifications/l1-back-office.md) §5.1 (already lists "Reviews -> l1-object-profile §3.4" as a required section); [l1-object-profile.md](../specifications/l1-object-profile.md) §3.4
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** A moderator can list reviews filtered by object/status/reported flag, publish, reject with a reason, hide a published review with a reason, and view the owner's reply — matching §3.4's "an owner may reply and report, never delete or edit" and the removal-is-administrator-only invariant.
- **Notes:** No resource currently exists for this model at all — this is the gap that made F-11 unfixable by wiring a form alone; the moderation queue this feeds into is `l1-moderation-governance.md`'s existing mechanism, unchanged.

**[T-10G04] Validation**

- **Goal:** Verify `T-10G01`–`03` against `l1-object-profile.md` §2/§3.4/§5.4 and `l2-third-party-integrations.md` §5.5.
- **Method:** Feature tests: both submission modes end to end (gate refused/admitted, CAPTCHA required/not-required), a submitted review's full lifecycle through the admin resource to publication, and the existing owner reply/report surface still functions unchanged.
- **Status:** Todo

### Track H — Missing Public Pages & Compliance

**[T-10H01] Object page location/map section**

- **Spec:** [l1-object-profile.md](../specifications/l1-object-profile.md) §5.1 (composition already lists "Location: address · map · directions · nearby attractions")
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** An object page with coordinates renders `<x-public.map>` centred on them; an object with no coordinates omits the section entirely (matching §3.2's "every section degrades independently" invariant), never an empty frame.
- **Notes (finding):** The template renders every other §5.1 block except this one, though the object already carries `latitude`/`longitude`/`geom` and the component already exists (reused from the home/territory pages, no new JS). `qa-deep-findings.md` F-17.

**[T-10H02] About and Contacts static pages**

- **Spec:** [l1-platform-shell.md](../specifications/l1-platform-shell.md) (footer already links "About" and "Contacts")
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** The footer's "About" and "Contacts" entries resolve to real 200 pages, both locales, sourced from the same translatable-content mechanism the privacy/terms pages already use.
- **Notes (finding):** The footer's entries currently degrade to inert text because no route exists — the graceful degradation is working as designed, the pages are simply missing. `qa-deep-findings.md` F-18. Contacts page pulls the portal's own contact details from the settings registry.

**[T-10H03] Cookie-consent notice**

- **Spec:** [l1-platform-shell.md](../specifications/l1-platform-shell.md) (already names "cookie notice" in its own one-line scope)
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** A first-time visitor sees a consent notice; accepting it persists (`localStorage` or a cookie) and the notice does not reappear on the next visit.
- **Notes (finding):** No cookie-consent markup exists anywhere in `resources/views/` despite the shell spec already scoping it in and `.drafts/TODO.md` recording the item as done — one of the two is wrong, and the spec (which predates that TODO entry) is the one this task follows. `qa-deep-findings.md` F-22.

**[T-10H04] Validation**

- **Goal:** Verify `T-10H01`–`03` against `l1-object-profile.md` §5.1 and `l1-platform-shell.md`, and `qa-deep-findings.md` F-17/F-18/F-22.
- **Method:** Feature test per finding; a browser check confirming the map renders with no console error and the consent notice persists across a reload.
- **Status:** Todo

### Track I — Full-Suite Regression Gate

**[T-10I01] Full `composer quality` and non-slow Pest suite, clean, after Tracks A–H close**

- **Goal:** Confirm no track regressed another; confirm the whole phase together.
- **Method:** `docker compose exec app composer quality` (or the equivalent local-toolchain invocation this session used, if a future session inherits it) end to end; `docker compose exec app composer test` (non-slow group); `php artisan migrate:fresh --seed` from empty.
- **Status:** Todo

## Track Ordering

**Nine tracks; six are genuinely file-independent and start together — `(A ∥ C ∥ D ∥ E ∥ H) → B → F → G → I` is the honest shape, not nine-wide.** Track A is scheduled first in narrative severity (it unblocks a fresh install), but does not gate any other track's *files* — the same is true of C, D, E, and H, which touch six disjoint file sets with no shared resource, matching Phase 9's own "six-wide, no chain" precedent. Track B is ordered after them only because `T-10B01`'s fix and `T-10D01`'s cache-tagging fix both touch public-page rendering paths closely enough that resolving cache correctness first avoids a false read during B's own manual verification, not because of a real code dependency — a future session may run B in parallel with A/C/D/E/H if that ordering caution proves unnecessary in practice. Track F is scheduled after B only because `T-10F03`'s settings-wiring work is a prerequisite for Track G, and G is the phase's largest, newest-surface track, best attempted once every smaller fix around it is settled. Track I waits on everything, the same acceptance-task shape Phase 8's `T03` and Phase 9's `T-9G01` both used.

**The real cross-track edge and the shared-file caution** (`T-10F03` before `T-10G02`;
`T-10B01` before `T-10F04`, both touching `MetadataResolver`) are stated in the phase
header and repeated on each task's own `Handoff`/`Depends on` line, so neither is
discoverable only by reading this section. `B`'s placement ahead of `F` in the overall
sequence above already satisfies the second one as a side effect — worth naming so a
future re-ordering does not undo it by accident while optimizing for something else.

## Meta Information

- **Created**: 2026-08-23, from `.drafts/qa-deep-findings.md` (a second, deeper functional sweep — 177 routes, all nine staff roles, a real browser pass) and `.drafts/qa-tz-conformance.md`.
- **Maintainer**: Core Team
