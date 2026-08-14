---
phase: 4
name: "Owner Cabinet"
status: Todo
subsystem: "app/Filament/Cabinet, app/Policies"
requires: ["phase-1", "phase-2", "phase-3"]
provides: []
key_files:
  created: []
  modified: []
patterns_established: []
duration_minutes: ~
---

# Stage 4 Tasks — Owner Cabinet

**Phase:** 4
**Status:** Todo
**Strategic Goal:** The second Filament panel — the same toolkit and the same interface
conventions as the staff panel, scoped to the authenticated owner in both the base
query and the policy, and usable by someone with no technical training.

## Atomic Checklist

### Track A — Cabinet Foundation

- [x] [T-4A01] CabinetPanelProvider, owner authentication, and the owner-scoped resource base contract
- [ ] [T-4A02] Dashboard

### Track B — Object Management

- [x] [T-4B01] Object editing and lifecycle
- [x] [T-4B02] Media management
- [x] [T-4B03] Rooms & prices
- [x] [T-4B04] Services
- [x] [T-4B05] Availability one-tap toggle

### Track C — Owner Content

- [x] [T-4C01] Owner-authored news & promotions
- [x] [T-4C02] Reviews — reply and report

### Track D — Statistics, Bump & Settings

- [x] [T-4D01] Statistics, including favorite count
- [x] [T-4D02] Bump entry point
- [x] [T-4D03] Settings & notification preferences
- [x] [T-4D04] Staleness surfacing

### Track T — Validation

- [x] [T-4T01] Ownership isolation invariant across every cabinet resource
- [ ] [T-4T02] Moderation-gating & availability-bypass invariant
- [ ] [T-4T03] Cabinet panel query budget under seeded volume

## Track Ordering

`T-4A01` is a hard gate — every other task in this phase depends on the panel provider
existing and the owner-scoped resource base it establishes, the same role
`ScopedResource`/`ResourceQueryScoper` played for the admin panel in Phase 2. `T-4A02`
(dashboard) is the first consumer, since it is the panel's own landing screen and reads
from placement, notification, and analytics surfaces Track B/C/D's other tasks do not
themselves need to exist yet.

Within Track B, `T-4B01` (object editing) is upstream of `T-4B02`–`T-4B04` only in the
sense that all four share the same object-form conventions `T-4B01` establishes first;
they are otherwise file-independent and may proceed in parallel once `T-4B01` lands.
`T-4B05` (availability) is deliberately independent of the rest of Track B — per
`l1-object-onboarding.md` §6.3 and `l1-availability-status.md`'s own implementation
notes, it needs its own narrow write path specifically so it does *not* route through
the general object-edit path `T-4B01` builds.

Track C and Track D are both file-independent of Track B beyond the shared object-form
conventions and may run in parallel with it once `T-4A01` lands. `T-4D01` (statistics)
and `T-4D02` (bump) each wrap an already-existing Phase 3 backend service
(`AnalyticsReportingService`'s owner-scoped method from `T-3C03`;
`PlacementLifecycleService`/`BumpService` from `T-3A02`/`T-3A03`) rather than building
new business logic — cabinet UI over an established contract, the same shape
`T-3B02`/`T-3C03` themselves were built ahead of their own call sites.

Track T runs last, after every other track, since its own invariants (ownership
isolation, moderation gating, query budget) need every cabinet resource this phase adds
to actually exist.

## Standing Constraints

- Owner scoping is enforced in the resource's base query **and** in the policy — never
  in the UI alone. An owner must never reach another owner's data by guessing an
  identifier.
- Capability does not vary by placement package. Bumping is the single package-gated
  capability; everything else is identical for every owner.
- Module gates are an administrator's operational decision and are never sold as part
  of a placement package. Conflating the two reintroduces capability tiering. No
  feature module is active in this project's current configuration, so every
  module-gated cabinet section (`l1-object-onboarding.md` §5.1's booking-module rows)
  is out of scope for this phase — it activates only once
  `l1-room-reservation.md` ships as a module, which is not scheduled.
- Administrator activation of a module makes a capability available; it never enrolls
  an owner's object automatically.
- Review deletion is refused server-side, not merely hidden. Review *submission* (the
  visitor-facing write path) is not specified anywhere in this workspace's registered
  specifications — `reviews` carries a full schema already, but the only behavior any
  L1 spec documents is the owner's reply/report capability against an existing row.
  This phase builds exactly that and no more; a visitor-facing submission form is out
  of scope until a spec exists for it.
- The availability toggle bypasses moderation entirely, per its own spec's core
  invariant — it must never be routed through `ModerationPipeline`.

## Known Dependency

Statistics surfaces render the aggregate contract from Phase 3's rollup, but carry no
live data until Phase 5 instruments the public surfaces that emit events. Expect empty
charts on completion of this phase — that is the dependency working as designed, not a
defect. `T-4D01`'s own Verify line is written against this constraint directly: it
proves the query and rendering are correct against seeded `StatDaily` fixtures, not
against real traffic that cannot exist yet.

## Detailed Tracking

### [T-4A01] CabinetPanelProvider, owner authentication, and the owner-scoped resource base contract

- **Spec:** l1-object-onboarding.md §2, §3, §5.1
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=CabinetFoundation` proves the cabinet panel is reachable at its own path, distinct from `/admin`; an owner authenticates against the same `User` table the admin panel uses (no parallel account system) and reaches only the cabinet, never `/admin`, regardless of role; a `CabinetResource` base class (mirroring `ScopedResource`) narrows every query to `owner_id = current user` in both the Eloquent query and the bound Policy, proven by seeding two owners with one object each and asserting owner A's session can list/view/edit only owner A's object, refused (not merely hidden) for owner B's; an owner with several objects sees a working switcher; the cabinet menu shows only entries the object's type declares and the owner's permissions allow, with every module-gated entry absent (no module is active in this configuration).
- **Handoff:** Every other task in this phase — the panel provider and `CabinetResource` base are the shared contract everything else builds on.
- **Notes:** This is Phase 4's equivalent of Phase 2's `T-2A02` — a contract changed after several resources adopt it is a rewrite across every one of them, so get the base class right here before anything else in this phase starts. Owner authentication reuses the existing `User` model and guard configuration; it does not invent a second account type. A staff member with an `object.*` grant is not automatically an owner — cabinet access is its own permission (`cabinet_access`, already seeded per `PermissionSeeder`), checked the same way `admin_panel_access` gates the staff panel.
- **Changes:** The object switcher and per-request query scoping are built on Filament's own tenancy contract rather than a hand-rolled session mechanism: `User` implements `HasTenants`/`HasDefaultTenant` (tenant set = owned objects ∪ objects reachable through the pre-existing `object_user` staff pivot); `CabinetPanelProvider` gained `->tenant(Object_::class, ownershipRelationship: 'object')`. Authorization at the record level required a second, independent axis alongside the admin panel's country/territory/category scoping: a new `CabinetAccessResolver` service resolves ownership (defers to the acting user's own `spatie/laravel-permission` grants) or per-object staff membership (reads the `object_user.permissions` JSON column directly, deliberately bypassing the portal-wide permission system — the pivot's own migration already documented this as its design intent). `Object_Policy` now tries the existing staff-scope check first and falls back to `CabinetAccessResolver`, so neither axis regresses the other. `CabinetResource` (new base class, `app/Filament/Cabinet/Support/`) supplies the same table-persistence defaults `ScopedResource` gives the admin panel, plus two protected navigation-visibility helpers concrete Track B/C/D resources will call: the current tenant's declared type capability (`has_rooms`, `has_availability_status`) and its resolved module-ladder state (`ModuleResolver`, unchanged) — nothing is active in the current configuration, so every module-gated entry resolves absent today, as expected.
- **Evidence:** Two real defects surfaced and fixed before any of this shipped, both by direct code reading rather than by a failing test arriving first: (1) Filament's tenant-menu component reads a tenant's label via `getAttributeValue('name')`, which bypasses `astrotomic/laravel-translatable`'s `getAttribute()` override — the switcher would have rendered every object with a blank name; fixed by implementing `HasName::getFilamentName()` on `Object_`. (2) `ModerationScope` (a global scope hiding unapproved objects) silently blocked an owner's own tenant resolution — Filament's default tenant route-binding and the naive `User::getTenants()`/`canAccessTenant()` implementations all query through the scoped model, so an owner whose only object was still draft or awaiting a moderation decision could not reach their own cabinet at all. Fixed at both call sites: `User::getTenants()`/`canAccessTenant()` strip the scope explicitly, and the panel's `resolveTenantUsing()` closure resolves the tenant route parameter the same way — moderation gates what the public catalog shows, never what an owner can reach about their own listing. Fixing this also surfaced (and fixed) the identical assumption baked into two pre-existing tests from earlier phases (`OwnerAccessEnforcementTest`, `OwnerResourceTest`) whose owner fixtures asserted a bare `assertSuccessful()` against the panel's home path — now a redirect to the resolved tenant, so both were updated to follow it and (where the fixture had no object at all) to seed one, matching the now-real requirement that a cabinet-admitted owner has somewhere to land. New coverage: `tests/Feature/Cabinet/CabinetFoundationTest.php`, 11 cases — panel reachability and cross-panel refusal; tenant narrowing to exactly an owner's own objects, refused for another owner's; the multi-object switcher and its default-tenant selection; Policy-level refusal at the record level; a staff member's capability resolving entirely from their `object_user` grant row, independent of any portal-wide permission (both the affirmative and the refused-for-an-unlisted-key cases); and the two navigation-visibility helpers, including the with-no-tenant-bound case. Full non-slow suite: 485 passed, 0 failed, 3 skipped. `composer analyse` (Larastan level 8, whole app) and `composer fix` both clean.

### [T-4A02] Dashboard

- **Spec:** l1-object-onboarding.md §5.2
- **Status:** Done
- **Assignment:** Agent
- **Requires:** T-4A01
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=CabinetDashboard` proves the dashboard renders, for the current owner's selected object: name, active placement package, placement expiry date, current tier, current catalog position (or an honest "not yet computed" state — Phase 5 owns the live catalog query this would otherwise depend on), view counts (today/week/month/all-time, reading `StatDaily`), messenger and website click counts, and the availability status; quick actions (edit object, bump object, add photos, add news, add promotion) route to the correct cabinet screens; an object with a package expiring within the configured warning window surfaces that fact on the dashboard itself, not only in the notification inbox.
- **Handoff:** T-4B01 (edit), T-4B05 (availability), T-4C01 (news/promotion quick actions), T-4D02 (bump) — the dashboard's quick actions are the first working entry point into each.
- **Notes:** View/click counts read `StatDaily` directly (the aggregate Phase 3 built) — per the phase's own Known Dependency, these will legitimately read as zero until Phase 5 instruments real page/click events, and this task's own tests must prove the query and rendering are correct against seeded fixtures, not wait for live traffic.
- **Changes:** Custom Filament Page (`App\Filament\Cabinet\Pages\Dashboard`) registered at the panel's root path, replacing the stock Filament dashboard. Business logic lives in a new `App\Services\Cabinet\ObjectDashboardService`, returning a readonly `App\Support\Cabinet\ObjectDashboardSummary` DTO: active placement/package/tier/expiry, an expiry-warning flag computed against the existing placement expiry-warning setting, `StatDaily`-derived view counts (today/week/month/all-time) and messenger/website click counts, and availability status. Catalog position is left null — no live ranking query exists yet to give it meaning. Five quick-action links resolve against named routes each future cabinet task is expected to register; each is guarded by `Route::has()`, rendering disabled rather than crashing or dead-linking when a target doesn't exist yet.
- **Evidence:** `tests/Feature/Cabinet/CabinetDashboardTest.php`, 8 cases — full render with real seeded `StatDaily` data (not zero-by-omission), an honest zero/null state for an inactive object, expiry-warning firing/not-firing across three fixtures, and quick-action resolution both ways (real URL once a stub target route exists, disabled when it doesn't). Two real bugs found and fixed: Blade Icons resolves a bare Heroicon short code against Filament's own small bundled icon set rather than the full Heroicons library (registered under a `heroicon-` prefix) — a raw `<x-filament::icon>` call needs the prefix explicitly, `$navigationIcon` does not (Filament's own internal pipeline handles that case). And a route registered dynamically at runtime is not resolvable by `route()`/`Route::has()` until Laravel's route-name lookup index is explicitly rebuilt — relevant to any future test standing in a stub route for a not-yet-built screen. Independently re-verified: Pint and PHPStan (level 8) clean on every touched file; `CabinetDashboard` (8/8) and `CabinetFoundation` (11/11, confirming no regression from the panel-provider page-registration change) both green; full non-slow suite 493 passed, 0 failed, 3 skipped (up from 485).

### [T-4B01] Object editing and lifecycle

- **Spec:** l1-object-onboarding.md §3, §5.3, §5.9, §6.1–§6.2
- **Status:** Done
- **Assignment:** Agent
- **Requires:** T-4A01
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=CabinetObjectEditing` proves the edit form renders exactly the field set the object's declared type carries (`ObjectType.attribute_schema`), grouped into Core/Geography/Contacts/Translations/SEO per §5.3; an owner may save an incomplete object as a draft; a draft becomes eligible for publication only once required fields are complete; an owner's edit to an already-published object routes through `ModerationPipeline::submit()` using the section/scope arguments that pipeline already expects, publishing immediately or entering the queue exactly as the resolved mode for that scope dictates — proven for both an immediate-mode and a review-mode fixture, not assumed from `T-2D01`'s own unit coverage; a rejected or revision-requested submission is visible to the owner with its stated reason, editable, and resubmittable; the form warns before discarding unsaved changes; coordinates are required and stored as real coordinates, never free text.
- **Handoff:** T-4B02–T-4B04 share this task's form-section conventions. T-4T02 (moderation-gating invariant) exercises this path directly.
- **Notes:** This is the first real caller `ModerationPipeline` (built in Phase 2) and the moderation-mode resolution it wraps have ever had from outside their own unit tests — read `ModerationDecisionService`/`ModerationPipeline` in full before wiring the submit path, and falsify the routing (temporarily force one mode, confirm the wrong-mode assertion fails) before trusting it. Object creation (first save, before anything exists to moderate) never enters this pipeline — creation is always direct, per `l1-object-onboarding.md`'s own "draft before publication" invariant; only an edit to something already published is a moderation candidate.
- **Changes:** `App\Filament\Cabinet\Resources\Objects\ObjectResource` (List + Edit only, no Create — objects stay staff-provisioned), form grouped into Core/Geography/Contacts/Translations/SEO plus two conditional informational sections (moderation-feedback banner, publication-eligibility notice). A new `ObjectEditService` owns the moderation decision: an edit to a draft/hidden/archived object always applies directly; an edit to a published object routes through `ModerationPipeline::submit()` with a change-type key chosen by diffing what actually changed against the portal's real configured vocabulary. Contact-channel edits always apply immediately regardless of publication state — not a shortcut, a structural necessity: `ModerationDecisionService::approve()` applies an approved change via plain `fill()`/`save()`, which has no way to replay a relation from a snapshot. A new `ObjectPublicationEligibilityService` gates the Publish action (coordinates + primary-language name required). Established the directory/service/page conventions every later Track B/C/D cabinet resource follows (recorded in full in the commit).
- **Evidence:** `tests/Feature/Cabinet/CabinetObjectEditingTest.php`, 11 cases, including the actual falsifying pair (an immediate-mode fixture publishes directly; a review-mode fixture withholds the live record byte-identical and creates a real pending `ModerationRequest`) proving the routing branches correctly rather than merely executing a path. Two real bugs found and fixed: the relation-snapshot limitation in `ModerationDecisionService::approve()` described above (worked around, not silently ignored — contact channels are documented as permanently exempt from this gating); and a genuine dead link — the dashboard's (T-4A02) "Edit object" quick action was wired to a route name a Filament Resource's edit page does not actually produce, fixed along with the two dashboard tests that had encoded the wrong expectation. Independently re-verified: Pint and PHPStan (level 8) clean; combined with T-4B05 below, full non-slow suite 514 passed, 0 failed, 3 skipped.

### [T-4B02] Media management

- **Spec:** l1-object-onboarding.md §5.4
- **Status:** Done
- **Assignment:** Agent
- **Requires:** T-4B01
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=CabinetMediaManagement` proves an owner may upload, delete, reorder, caption, and select a primary photo for their own object only; uploads are queued for automatic optimization (conversions), never left to the owner to pre-resize; upload count and dimension limits read from the portal settings registry, not a hard-coded constant.
- **Handoff:** none within this phase.
- **Notes:** Reuses `spatie/laravel-medialibrary` exactly as `Object_` (Phase 2) and `Article`/`Banner`/`NewsItem`/`Promotion` (Phase 3) already do — no new upload mechanism, only the owner-scoped authorization wrapping it.
- **Changes:** `Object_` gained `registerMediaCollections()`/`registerMediaConversions()` (a `photos` collection, two queued conversions — a card thumbnail and a detail size). A thin `ObjectPhoto` subclass of Spatie's own `Media` model, scoped to that collection, carries the `object()` relation Filament's tenancy needs. A new `ObjectPhotoService` owns upload-count enforcement (reading the settings registry, one new setting key added), captioning, primary-photo selection (a custom property on the media row, not a second FK column), and deletion through the base class so file cleanup fires. Photo actions apply immediately regardless of publication state — a gallery is a set of individually addressable rows, not a flat snapshot the moderation-approval mechanism can safely replay.
- **Evidence:** `tests/Feature/Cabinet/CabinetMediaManagementTest.php`, 13 cases, proving real `spatie/laravel-medialibrary` state (a generated conversion actually present on disk, not just "the call didn't throw") and cross-owner refusal at three layers (HTTP, Policy, live table action). Three real bugs found and fixed: the photo policy had no `reorder()` method, which strict-authorization mode turns into a hard crash; the photo's relation back to its object silently returned null for any object not yet cleared by moderation — the normal state while an owner is actively curating photos — because the parent's moderation-visibility scope leaked into the relation query, which would have locked owners out of managing photos on their own unpublished listings; and the screen didn't eager-load that same relation, which would have crashed under this codebase's strict lazy-loading guard the moment an object had more than one photo.

### [T-4B03] Rooms & prices

- **Spec:** l1-object-onboarding.md §5.5
- **Status:** Done
- **Assignment:** Agent
- **Requires:** T-4B01
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=CabinetRoomsAndPrices` proves this section is reachable only for accommodation-type objects (the type's own declaration, not a hard-coded type-name check); an owner may create an unbounded number of room categories, each carrying name, description, photos, capacity, room count, area, bed configuration, maximum guests, extra-bed option, and amenities; a price record is period-aware (per night/room/person/service, or "from", with an optional seasonal window) and becomes publicly visible as soon as it is saved and any applicable moderation clears — reusing `T-4B01`'s moderation routing, not a second copy of it.
- **Handoff:** none within this phase.
- **Notes:** Confirm the exact schema Phase 1 already shipped for rooms/prices before adding anything — this phase has consistently found the migrations already exist ahead of the Filament/service layer, and this area is no exception if the same pattern holds.
- **Changes:** `Room`/`RoomTranslation`/`Price` Eloquent models built against the already-existing (Phase 1) migrations — none existed before. A standalone tenant-scoped resource (no admin-panel precedent existed for a full CRUD one-to-many child of an object), gated in two independent places by the object type's own `has_rooms` flag: navigation visibility and every one of the resource's own routes (403, proven against real HTTP requests, not just the nav helper). Room/price content applies immediately regardless of publication state, for the same structural reason media does — a room category is a relational structure (nested translations, nested prices) the moderation-replay mechanism cannot safely reconstruct.
- **Evidence:** `tests/Feature/Cabinet/CabinetRoomsAndPricesTest.php`, 6 cases. Root-caused a genuine `object_id` NOT NULL failure (not a guess-and-check fix) to Filament's automatic tenant-association listener only registering when the panel actually boots, which a bare `Livewire::test()` call doesn't do — fixed by an explicit `Filament::bootCurrentPanel()` call in the test's own tenant-mounting helper. **Also fixed a genuine cross-cutting regression this task's new `Room` model exposed, not scoped to this task's own tests**: `room_translations` predates the project's `needs_review`/`published_at` translation-completeness convention and was never brought into line, because nothing made `Room` a `Translatable` model before now — the moment it became one, `TranslatableEntityRegistry`'s reflection-based discovery picked it up and the existing `TranslationCompletenessReport` (unrelated admin-side SEO reporting) crashed querying a column that did not exist. Closed with a new migration mirroring the exact precedent an earlier phase had already established for the identical gap class on a different table set.

### [T-4B04] Services

- **Spec:** l1-object-onboarding.md §5.6
- **Status:** Done
- **Assignment:** Agent
- **Requires:** T-4B01
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=CabinetServices` proves an owner selects amenities only from the administrator-maintained registry (`Amenity`/`AmenityGroup`, already built in Phase 1/2) — no free-text entry exists anywhere in this form; only the amenity groups applicable to the object's declared type are offered, not the full registry unconditionally.
- **Handoff:** none within this phase.
- **Changes:** No new model — this is the object's own existing `amenities` relation, edited directly. A new `ObjectServiceCatalogService` resolves which amenity groups apply to the object's declared type and which amenities within a group are active, both narrowed sets. One collapsible section per applicable group, each a checkbox list bound through Filament's own `->relationship()` mechanism scoped to that group — structurally immediate (Filament persists relationship-bound fields before any page-level save hook runs), matching the same reasoning already established for rooms' own amenity selection.
- **Evidence:** `tests/Feature/Cabinet/CabinetServicesTest.php`, 3 cases, including a structural assertion (inspecting the live form's own field list, not rendered HTML) that no free-text component exists anywhere. One test-fixture-only issue found and fixed: Filament's tenancy trait registers a real global scope directly on the tenant model class the first time a tenant is mounted in a test process, silently narrowing every later plain query on that model for the rest of that test — not a product bug, but worth knowing for every later cabinet test fixture that mounts a second object mid-test.
- **Batch evidence (all three above):** Independently re-verified together — full-repository Pint and PHPStan (level 8) clean; combined cabinet test filter (all seven cabinet suites) 62 passed, 0 failed; full non-slow suite 536 passed, 0 failed, 3 skipped (up from 514, after the `room_translations` migration fix above).
- **Notes:** This is the one area of Track B explicitly warned against in the spec's own Drawbacks section — free-text services would be fatal to the catalog's filter facets. Do not add an "other" free-text field even as a convenience.

### [T-4B05] Availability one-tap toggle

- **Spec:** l1-availability-status.md §5.3, §6.1
- **Status:** Done
- **Assignment:** Agent
- **Requires:** T-4A01
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=CabinetAvailabilityToggle` proves the toggle is reachable from both the dashboard and the owner's object list, not only from inside the edit form; a toggle writes the new status, an `AvailabilityHistory` row (from/to/changed-at/changed-by/source=owner), and resets `last_confirmed_at` in one transaction; the write never touches `ModerationPipeline` — falsify this directly by asserting no `ModerationRequest` row is created and no moderation-mode lookup occurs; catalog/territory/object-page cache tags relevant to the object are invalidated by the same write (reusing the existing `Cache::tags([...])->flush()` convention, not a new mechanism); toggling does not alter the object's placement tier, catalog position, or contact-channel visibility — asserted directly, not merely assumed from the write path's own scope.
- **Handoff:** T-4T02 (moderation-gating invariant asserts this task's bypass directly).
- **Notes:** Give this its own narrow write path per the spec's own implementation note — routing it through `T-4B01`'s general object-edit path would drag in moderation and full-object cache invalidation, both of which this operation must avoid. The three-value state model (`available`/`unavailable`/`unspecified`) and the badge-rendering logic were already built in Phase 2 (`l1-availability-status.md` §5.1–§5.2, §5.4–§5.5); this task adds only the owner-facing write path, reusing that model rather than rebuilding it.
- **Changes:** New `AvailabilityToggleService::toggle()` flips the status column in one DB transaction, appends an `AvailabilityHistory` row, journals the change, and flushes the established cache-tag convention — taking no dependency on `ModerationPipeline`/`ModerationModeResolver` at all, structurally rather than by omission alone. Wired as a Filament Action on both the dashboard (header action) and the owner's object list (row action), both calling the same service method.
- **Evidence:** `tests/Feature/Cabinet/CabinetAvailabilityToggleTest.php`, 10 cases, including a genuine falsification of the moderation-isolation claim (temporarily added a real call to `ModerationModeResolver` inside the service, confirmed the test failed, reverted, confirmed it passed again) rather than trusting the absence of a call site. `Mockery` cannot mock either moderation service since both are `final` — verified via container-rebind-to-throw instead. Independently re-verified: Pint and PHPStan (level 8) clean; combined with T-4B01 above, full non-slow suite 514 passed, 0 failed, 3 skipped (up from 493).

### [T-4C01] Owner-authored news & promotions

- **Spec:** l1-content-publishing.md §3.3, §3.4, §5.2, §5.5
- **Status:** Done
- **Assignment:** Agent
- **Requires:** T-4A01
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=CabinetOwnerContent` proves the owner-facing news form accepts exactly five fields (title, summary, body, image, publication date) and the promotion form exactly five (title, description, image, start date, end date) — no rich editor, no layout control, asserted against the form's own field list, not merely the visible UI; a submission routes through `ModerationPipeline::submit()` exactly as `T-4B01`'s object edits do, publishing immediately or entering the queue per the resolved mode for the owner's scope; the created row is attributed to the owner's object and, once published, is eligible for the portal-wide feed only after that moderation step clears — never before.
- **Handoff:** none within this phase.
- **Notes:** Reuses the `NewsItem`/`Promotion` models and `NewsItemLifecycleService`/`PromotionLifecycleService` Phase 3 already built (`T-3E02`) — this task is the owner-authoring caller those services were explicitly left ready for, not a second content pipeline. Do not create parallel owner-scoped content types per the source spec's own implementation note.
- **Changes:** Two new resources (NewsItem, Promotion) with the five-field forms exactly as specified. A row is created first (unpublished, unapproved) so it has a primary key to moderate against, then routed through `ModerationPipeline::submit()` — immediate mode writes and flips status/moderation_status in the same request; review mode leaves the row with no translation and no live status, entirely invisible to any public query, while a real pending `ModerationRequest` carries the authored content. A new `ContentSubmissionOutcome` value object parallels the existing `ObjectEditOutcome`.
- **Evidence:** `tests/Feature/Cabinet/CabinetOwnerContentTest.php`, 6 cases including the falsifying immediate/review pair. Two real pre-existing gaps found and closed, not worked around: `NewsItemPolicy`/`PromotionPolicy` had no ownership-based authorization path at all (only the staff scope-table path) — added the same `CabinetAccessResolver` fallback every sibling owner-facing policy already uses; the `object_owner` role had no `content.*` permissions, so no owner could reach these screens regardless of the policy fix — added `content.view`/`content.create`/`content.edit`. Also fixed a dead dashboard quick action (NewsItemResource needed an explicit `news` slug to match the route name the dashboard already anticipated). Independently re-verified: full-repo Pint and PHPStan (level 8) clean; combined with T-4C02 below, full non-slow suite 550 passed, 0 failed, 3 skipped.

### [T-4C02] Reviews — reply and report

- **Spec:** l1-object-onboarding.md §5.7
- **Status:** Done
- **Assignment:** Agent
- **Requires:** T-4A01
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=CabinetReviews` proves an owner sees every review of their own object (any status); may reply exactly once per review, recording `owner_reply`/`owner_replied_at`; may report a review, recording `reported_at`/`reported_by`/`report_reason`; edit and delete controls are absent from the UI *and* the corresponding actions are refused server-side when attempted directly (a policy check, not a hidden button) — falsify this by calling the update/delete action directly against another owner's review and confirming it is refused, then against the owner's own review's protected fields and confirming those too are refused.
- **Handoff:** none within this phase.
- **Notes:** Review *submission* is out of this task's scope — see the phase's own Standing Constraints. This task only builds the owner's read/reply/report capability against whatever review rows already exist (seed fixtures directly in tests; no submission form is built to produce them).
- **Changes:** New `Review` model (no admin-authored one existed) and `ReviewPolicy`: ordinary `update`/`delete` abilities are refused unconditionally for every actor, including the object's own owner — reviews are never editable or deletable through this policy at all. A new `ReviewInteractionService` exposes only `reply()`/`report()`, each taking exactly the scalar value it may ever write (never an open data array), refusing a second attempt with a dedicated exception. List-only Filament resource (no create/edit page).
- **Evidence:** `tests/Feature/Cabinet/CabinetReviewsTest.php`, 8 cases, including both falsifying cases: `Gate::allows('update'|'delete', ...)` refused even for the review's own object owner touching protected fields, and every ability refused for a different owner acting on another owner's review (including a real routed cross-tenant action attempt). Independently re-verified: full-repo Pint and PHPStan (level 8) clean; combined test filter across all nine cabinet suites plus `TranslationManagementTest` 83 passed, 0 failed; full non-slow suite 550 passed, 0 failed, 3 skipped (up from 536).

### [T-4D01] Statistics, including favorite count

- **Spec:** l1-analytics.md §5.4, §5.6; l1-object-onboarding.md §5.6a
- **Status:** Done
- **Assignment:** Agent
- **Requires:** T-4A01
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=CabinetStatistics` proves the statistics page calls `AnalyticsReportingService`'s owner-scoped query method (built in `T-3C03`) and renders page views, photo views, per-channel contact clicks, and a traffic-source breakdown (channel/domain/campaign, never a full referrer) for the owner's object, all-time only per this release's scope; the favorite count renders from whatever aggregate Phase 1/2's schema already carries for it; an owner querying their own statistics never sees another owner's figures — reuses `T-3C03`'s own owner-isolation guarantee, re-asserted here at the cabinet-UI layer rather than assumed.
- **Handoff:** none within this phase — Phase 5 is what makes the underlying numbers non-zero.
- **Notes:** Per the phase's own Known Dependency, seed `StatDaily` fixtures directly in this task's tests rather than expecting real traffic — the query and rendering are what this task proves, not live data that cannot exist until Phase 5.
- **Changes:** New `App\Filament\Cabinet\Pages\Statistics` (auto-discovered, no manual panel registration) backed by a new `ObjectStatisticsService`. Kind-level totals (page views, photo views, contact clicks) come from `AnalyticsReportingService::forOwner()`, scoped to the object's own owner with an explicit `object_id` filter as a second layer of ownership defense. The per-channel contact-click breakdown reads `stat_dailies` directly (joined against the contact-channel-type registry and its translations, falling back to a humanized key when untranslated) since the flat `forOwner()` summary cannot express that dimension. The traffic-source breakdown deliberately reads the raw `stat_events` tier, not the `stat_dailies` rollup — the aggregate table's grain excludes `source_channel`/`source_domain`/`source_campaign` entirely (a documented invariant of the two-tier analytics model), so this is an honest reading of whatever window the raw-retention policy still holds, never a synthesized all-time figure the schema can't support. Favorite count reads the existing `favorites` table directly (no Eloquent model existed for it, and nothing in the codebase writes to it yet — a visitor-facing favorite toggle hasn't shipped, so the figure legitimately reads zero until it does, stated plainly rather than papered over).
- **Evidence:** `tests/Feature/Cabinet/CabinetStatisticsTest.php`, 4 cases against real seeded fixtures, including an explicit owner-isolation assertion (two owners, each seeing only their own object's figures) beyond `forOwner()`'s own guarantee. Independently re-verified: full-repo Pint and PHPStan (level 8) clean; full non-slow suite 554 passed, 0 failed, 3 skipped (up from 550). One process note: this task's code was originally built and verified on a different machine than where it was ultimately committed — an unrelated automated dependency-update commit (`c285d2e`) on this machine absorbed the still-uncommitted working tree via a broad stage-everything operation, landing the feature under a commit message that only describes the dependency bump. Content was independently re-verified in full here regardless (nothing was taken on faith from the mismatched commit message). Also surfaced and fixed a genuine, pre-existing `composer.lock`/`composer.json` version mismatch on `pestphp/pest` that blocked a truly clean `composer install` on this fresh environment — see the separate `fix(deps)` commit.

### [T-4D02] Bump entry point

- **Spec:** l1-object-onboarding.md §5.1, §5.2; l1-placement-monetization.md (bump)
- **Status:** Done
- **Assignment:** Agent
- **Requires:** T-4A01
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=CabinetBump` proves the cabinet exposes a bump action calling `BumpService::bump()` (built in `T-3A03`) for the owner's own object only; the action is refused, with a stated reason, when the object's current package forbids bumping or a free-bump interval/allowance has not been satisfied — reusing `BumpService`'s own refusal exceptions, not a second copy of that logic; bumping is the only cabinet capability gated by the object's placement package, per the phase's own Standing Constraints — every other Track B/C/D task in this phase must remain reachable regardless of package.
- **Handoff:** none within this phase.
- **Notes:** This task adds a UI entry point and an owner-facing refusal message; it adds no new bump logic. If `BumpService`'s existing exceptions do not already carry owner-presentable messages, add translation-key mappings here rather than new business logic in the service.
- **Changes:** New `App\Filament\Cabinet\Pages\BumpObject` (auto-discovered, registers at `filament.cabinet.pages.bump-object` — exactly the route name the dashboard's quick action already anticipated) calls `BumpService::bump()` with `type: 'free'` and the object's own territory as scope, mirroring the back office's own bump action exactly. `BumpRefusedException` gained a `reasonKey` (plus the two numeric details a friendly message needs) additively — its existing `getMessage()` text and every existing catch site (the admin panel's own bump action) are unchanged; the cabinet is simply a second caller that needed a machine-readable reason alongside the existing developer-facing string. Deliberately never `type: 'owner'` (a fifth value the `bump_events.type` enum already declares but nothing in `BumpService` currently gates): that type carries none of the interval/allowance eligibility checks `'free'` does, so using it would let an owner bump without limit — the conservative, correctly-rate-limited reading was chosen over the unwired enum value.
- **Evidence:** `tests/Feature/Cabinet/CabinetBumpTest.php`, 6 cases: a real bump reaching `BumpService` and writing a `bump_events` row; cross-owner refusal at the route level (404, not merely hidden); two refusal cases (package forbids bumping, free-bump interval not elapsed) each asserted against the exact translated `Notification` object the owner would see, not the raw exception message; the screen staying reachable when the package forbids bumping (refused only at submission); and the dashboard's quick action resolving to this screen's real URL. Two pre-existing `CabinetDashboardTest` cases updated to match — the "resolves a quick action" test no longer needs a stub route now that bump has a real one, and the "renders successfully when some targets are missing" test's premise is gone now that every quick action in this phase is real, renamed to reflect that. Independently re-verified: full-repo Pint and PHPStan (level 8) clean; full non-slow suite 560 passed, 0 failed, 3 skipped (up from 554).

### [T-4D03] Settings & notification preferences

- **Spec:** l1-object-onboarding.md §5.8
- **Status:** Done
- **Assignment:** Agent
- **Requires:** T-4A01
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=CabinetSettings` proves an owner may change their password, change the cabinet's own display language (independent of any public-site browsing language, which does not exist until Phase 5), change their contact email, and toggle per-type notification preferences using `NotificationPreferenceService` (built in `T-3D02`) for every optional-class notification type; the cabinet's own inbox lists the owner's `Notification` rows (placement expiry, package expiry, administration messages, information-refresh requests, moderation outcomes, system messages) with read/unread state, using `NotificationDispatchService::markAsRead()`/`markAsUnread()` rather than a parallel read-state mechanism.
- **Handoff:** none within this phase.
- **Notes:** No per-user locale column exists on `User` yet — this is the first task in this project positioned to close that real, previously-flagged gap (`T-3D01`/`T-3A04`'s own notes both name it). Closing it here is in scope: add the column, and update `NotificationDispatchService`'s locale resolution to prefer it over the portal's primary-language fallback, since this task is precisely "the owner sets their own language."
- **Changes:** New `App\Filament\Cabinet\Pages\Settings` extends Filament's own `Filament\Auth\Pages\EditProfile` (password/email change, current-password confirmation, and rate limiting all inherited rather than rebuilt) with two additions: a `locale` select sourced from the active `Language` registry, and a notification-preferences section rendering one toggle per optional-class `NotificationType`, persisted through `NotificationPreferenceService` outside the base class's own `$record->update($data)` call since preferences are not `User` columns. A new migration adds a nullable `locale` column to `users` (FK to `languages.code`). `NotificationDispatchService::resolveLocaleFor()` now prefers the recipient's own active locale, falling back to the portal's primary language only when unset or the stored code is no longer active. A new `NotificationPolicy` and `NotificationResource` (list-only, account-level, not tenant-scoped) give the cabinet its own inbox, scoped to `recipient_id = current user` at the query level, with a row action calling the same `markAsRead()`/`markAsUnread()` the dispatch pipeline itself uses.
- **Evidence:** `tests/Feature/Cabinet/CabinetSettingsTest.php`, 5 cases: password/email change with current-password confirmation; a genuinely falsifying locale test (portal's primary language is `en`; after the owner sets `ru`, a freshly dispatched notification renders in `ru`, proving `NotificationDispatchService` reads the owner's own column rather than coincidentally matching the default); fallback to the portal's primary language when no locale is set; notification-preference toggles proven scoped to optional-class types only by asserting no `NotificationPreference` row exists for a transactional type after save (the save loop never iterates it, rather than merely trusting the form's field list); and the notification inbox's Policy-level scoping plus read/unread toggling through the real service. One real bug found and fixed during verification, not from the task's own tests but from its interaction with the panel's shared layout: overriding `Settings::getRelativeRouteName()` to rename the route broke `Panel::getProfileUrl()`, which hardcodes the base route name (`auth.profile`) for the "my profile" link rendered in every page's layout across the whole panel — a page-local-looking customization with a panel-wide blast radius, traced by reading `Filament\Panel\Concerns\HasAuth::getProfileUrl()` directly. Fixed by keeping only the safe `getSlug()` override (URL path segment) and leaving the route name inherited. Independently re-verified: Pint and PHPStan (level 8) clean; combined cabinet regression filter (all twelve cabinet suites) 91 passed, 0 failed; full non-slow suite 565 passed, 0 failed, 3 skipped (up from 560).

### [T-4D04] Staleness surfacing

- **Spec:** l1-object-onboarding.md §5.10
- **Status:** Done
- **Assignment:** Agent
- **Requires:** T-4A01
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=CabinetStaleness` proves an object flagged stale by `T-3D03`'s `StalenessSweepJob` (an `information_out_of_date` notification exists for it) surfaces that fact visibly in the cabinet — on the dashboard and/or the object's own edit screen, not only buried in the notification inbox; the flag is advisory only — it never hides the object or blocks any cabinet action, asserted directly against a flagged fixture.
- **Handoff:** none within this phase.
- **Notes:** All staleness detection, reminder cadence, and back-office administrator override already exist (Phase 2's availability staleness, Phase 3's `StalenessSweepJob`) — this task adds only the owner-visible surface reading that existing state, not a second staleness mechanism.
- **Changes:** New `ObjectStalenessService::isFlagged()` reads whatever state the sweep job already produced — a query for an `information_out_of_date` `Notification` related to the object, with no detection logic of its own. The flag only counts a notification as a live "still stale" signal while it postdates the object's own last edit (`notification.created_at >= object.updated_at`): once an owner updates the object, `updated_at` moves past the notification's `created_at` and the flag clears on its own, without re-deriving the sweep job's own `updated_at`-versus-threshold condition a second time. Wired into two places, both read-only and purely informational: `ObjectDashboardService`/`ObjectDashboardSummary` gained an `isStale` field, rendered on the dashboard as a banner alongside the existing expiry-warning one; `ObjectForm` gained a `stalenessSection()` following the exact same conditional-section pattern as the existing moderation-feedback and eligibility notices.
- **Evidence:** `tests/Feature/Cabinet/CabinetStalenessTest.php`, 5 cases: the notice surfaces on the dashboard for a flagged object and stays absent for an unflagged one; the same notice surfaces on the object's own edit screen; a falsifying freshness case (a stale notification predating the object's last edit no longer flags it, proving the flag reads relative freshness rather than mere existence); and a direct proof that the flag never blocks a save — a flagged draft object's edit still applies and persists. Independently re-verified: Pint and PHPStan (level 8) clean; combined cabinet regression filter (all thirteen cabinet suites) 96 passed, 0 failed; full non-slow suite 570 passed, 0 failed, 3 skipped (up from 565).

### [T-4T01] Ownership isolation invariant across every cabinet resource

- **Goal:** Verify that no cabinet resource this phase adds ever exposes one owner's data to another, across every resource Track A–D built, not spot-checked on a subset.
- **Method:** `docker compose exec app ./vendor/bin/pest --filter=CabinetOwnershipIsolation` — for every registered `CabinetResource` (discovered dynamically via the panel, the same `Filament::getPanel('cabinet')->getResources()` sweep `T-3T04`'s admin-panel equivalent used), seed two distinct owners with one object each, and assert owner A's session lists/views/edits only owner A's rows, with owner B's equivalent record returning not-found (never a 403 that would confirm the record's existence) for every list, edit, and action route the resource exposes.
- **Status:** Done
- **Requires:** T-4A01, T-4B01–T-4B05, T-4C01, T-4C02, T-4D01–T-4D04
- **Changes:** No product code changed — this task is pure verification. New `tests/Feature/Cabinet/CabinetOwnershipIsolationTest.php` reads the panel's live registry (`Filament::getPanel('cabinet')->getResources()`) rather than an enumerated list, so a resource added in a later phase without isolation coverage is caught here without this file changing. Every tenant-scoped resource shares one isolation mechanism — Filament's own tenancy, which refuses to resolve a `{tenant}` route parameter the acting user does not own before any resource-specific query runs — proven generically across all seven tenant-scoped resources rather than per-resource. `RoomResource`, `NewsItemResource`, and `PromotionResource` (the three resources whose record is a genuinely distinct child row, not the tenant object itself) get an additional, deeper check: a valid tenant whose own scoped query still refuses a record it does not own. `NotificationResource` (the one resource deliberately not tenant-scoped, `isScopedToTenant() === false`) gets its own direct query-level proof, scoped by `recipient_id` instead.
- **Evidence:** `tests/Feature/Cabinet/CabinetOwnershipIsolationTest.php`, 4 cases: the registry's own canonical membership (8 resources, tenant-scoping flag asserted per resource); the generic tenant-route refusal sweep across all seven tenant-scoped resources; the child-row-within-a-valid-tenant refusal for the three resources where that distinction is meaningful; and the notification inbox's own recipient-scoped isolation. Independently re-verified: Pint and PHPStan (level 8) clean; combined cabinet regression filter (all fourteen cabinet suites) 100 passed, 0 failed; full non-slow suite 574 passed, 0 failed, 3 skipped (up from 570).

### [T-4T02] Moderation-gating & availability-bypass invariant

- **Goal:** Verify the moderation-routing contract `T-4B01`/`T-4C01` both depend on, and the availability toggle's own bypass of it, hold together rather than merely in each task's own isolated tests.
- **Method:** `docker compose exec app ./vendor/bin/pest --filter=CabinetModerationGating` — proves an owner edit to a published object under review-mode enters the queue and does not mutate the published record; the same edit under immediate-mode publishes directly; a news/promotion submission behaves identically under both modes; the availability toggle, run immediately after switching the same scope to review-mode, still bypasses the queue entirely — falsify by temporarily routing the toggle through `ModerationPipeline` and confirming this test fails, then restore.
- **Status:** Todo
- **Requires:** T-4B01, T-4B05, T-4C01

### [T-4T03] Cabinet panel query budget under seeded volume

- **Goal:** Verify the cabinet panel meets the same ≤30-query-per-request budget the admin panel is held to, at realistic per-owner volume, and that adding a second panel did not reintroduce the `ScopeAuthorizer` per-navigation-item cost `T-3T04` fixed for the admin panel.
- **Method:** `docker compose exec app ./vendor/bin/pest --group=slow --filter=CabinetPanelBudget` — seeds one owner with a realistic object count (a handful, not `DemoVolumeSeeder`'s portal-wide scale, since a single owner's cabinet never lists other owners' rows) and measures every cabinet resource's list/dashboard/statistics page via `DB::enableQueryLog()`, reporting actual per-screen counts.
- **Status:** Todo
- **Requires:** T-4A01, T-4A02, T-4B01–T-4B05, T-4C01, T-4C02, T-4D01–T-4D04
