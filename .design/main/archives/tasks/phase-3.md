---
phase: 3
name: "Commerce, Advertising & Platform Services"
status: Done
subsystem: "app/Services, app/Jobs, app/Filament/Admin, app/Models"
requires: ["phase-1", "phase-2"]
provides: []
key_files:
  created: []
  modified: []
patterns_established: []
duration_minutes: ~
---

# Stage 3 Tasks — Commerce, Advertising & Platform Services

**Phase:** 3
**Status:** Todo
**Strategic Goal:** The revenue mechanics and the background machinery both panels and
the public site depend on — placement ordering, bumps, banner targeting, analytics
ingest, notifications, and the content pipeline.

## Track Ordering

Phase 3 is five-wide, one track per spec, because each track's model and service
layer touches a disjoint set of files under `app/Services/`, `app/Models/`, and
`app/Filament/Admin/Resources/`. Unlike Phase 2, no shared resource contract has to
land first — Phase 2 already built it (`ScopedResource`, `CountedBulkAction`,
`ScopeAuthorizer`, the settings registry, the moderation pipeline) and every track
here builds new resources on top of it rather than establishing it.

```plaintext
A (Placement & Monetization) ∥ B (Advertising) ∥ C (Analytics) ∥ D (Notifications) ∥ E (Content Publishing)
                                                                                   → T (validation)
```

Two cross-track edges are real and are scheduled rather than discovered, exactly as
Phase 2 sequenced `T-2D01` ahead of `T-2B02`:

- **`T-3D01` (notification/dispatch model) before `T-3A04` (expiry sweep).** The
  expiry sweep's 30/14/7/3-day and day-of/after warnings are notifications
  (`l1-placement-monetization.md` §5.4 delegates delivery to
  `l1-notifications.md`); the sweep cannot raise them against a model that does not
  exist yet.
- **`T-3C01` (event ingestion) before `T-3B02` (banner selection & serving).** Banner
  impressions and clicks are `StatEvent` rows (`l1-analytics.md` §5.1's `kind` enum
  includes `banner_impression`/`banner_click`); the serving service records through
  the ingestion path this task builds, not a second counting scheme.

No other cross-track dependency exists — Track E's publication pipeline reuses
Phase 2's moderation mode resolution (external, already built) and introduces
nothing the other four tracks read.

## Atomic Checklist

### Track A — Placement & Monetization

- [x] [T-3A01] Tier & package registry — ranks, editable labels, per-category package sets
- [x] [T-3A02] Placement lifecycle & the catalog ordering service
- [x] [T-3A03] Bump engine — scoped events, limits, back-office bump action
- [x] [T-3A04] Expiry sweep and the configured expiry action
- [x] [T-3A05] Financial ledger and commerce reports

### Track B — Advertising

- [x] [T-3B01] Banner slot registry and the banner model
- [x] [T-3B02] Banner selection & serving service
- [x] [T-3B03] Promotional labels and card decoration

### Track C — Analytics

- [x] [T-3C01] Event ingestion — the two-tier `StatEvent` model, fire-and-forget capture
- [x] [T-3C02] Daily rollup and compaction
- [x] [T-3C03] Reporting surfaces and traffic sources

### Track D — Notifications

- [x] [T-3D01] Notification & dispatch model, channel registry
- [x] [T-3D02] Dispatch pipeline — queue, retry with backoff, suppression, inbox
- [x] [T-3D03] Scheduled jobs — staleness, availability confirmation, dispatch retry
- [x] [T-3D04] Administrator broadcast

### Track E — Content Publishing

- [x] [T-3E01] Article model and admin CMS
- [x] [T-3E02] News & promotions models, auto-archival job
- [x] [T-3E03] Shared publication pipeline and cache invalidation

### Track T — Validation

- [x] [T-3T01] Catalog ordering & bump invariants under seeded volume
- [x] [T-3T02] Analytics privacy & fidelity invariants
- [x] [T-3T03] Notification delivery completeness
- [x] [T-3T04] Commerce & content panel query budget under seeded volume
- [x] [T-3T05] Containment cleanup — remove standing plan-phase references from product code

## Detailed Tracking

### [T-3A01] Tier & package registry — ranks, editable labels, per-category package sets

- **Spec:** l1-placement-monetization.md §3.1, §3.2, §3.5, §5.1
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=PlacementRegistry` proves: the four ranks are structural (seeded, not creatable/deletable from the admin screen) while label, badge colour, badge icon, and active flag are editable per language; a package's tier, validity period, bump-allowed flag, bump interval, free-bumps-per-period, paid-bump price, and active flag are all administrator-editable with no code change; package sets differ per object category (a hotel package is invisible on a restaurant's package select); creating/renaming/deactivating a package requires no code change and is journalled.
- **Changes:** `PlacementTier`/`PlacementTierTranslation`, `PlacementPackage`/`PlacementPackageTranslation` models over the migrations Phase 1 already shipped; `PlacementTierPolicy` (view/update only — no create/delete, matching the resource's own page set), `PlacementPackagePolicy` (full CRUD). `PermissionSeeder` gained four new resource keys in one pass, since Track B/C/D need their own the moment they start: `commerce`, `advertising`, `analytics`, `notification` (`finance` and `content` already existed). `PlacementTierResource` (index + edit only, portal-wide, no scope axis — same posture as `ObjectTypeResource`/`ModuleResource`) and `PlacementPackageResource` (full CRUD, category-select via `object_type_id`) registered under a new `commerce` navigation group (translation keys added in both `en`/`ru` `panel.php`, registered in `AdminPanelProvider::navigationGroups()`).
- **Evidence:** `pest tests/Feature/Admin/PlacementRegistryTest.php` · exit 0 · 5 passed (22 assertions) — no create/delete route exists for tiers; a tier's label/badge/colours edit without touching its rank; a category-scoped package creates with bump terms and an uppercase-normalized currency; the package table's column set is exactly the package-parity list (a future content-gating column added to `placement_packages` fails this test by design); an unrestricted grant reaches the registry and a country-scoped one reaches zero, since packages are portal-wide configuration. `docker compose exec app php artisan route:list --path=admin` confirms `placement-tiers` exposes only index/edit and `placement-packages` exposes index/create/edit.
- **Handoff:** T-3A02 (`ObjectPlacement.package` references this), T-3A03 (bump limits read from the package), T-3A05 (ledger records reference the package).
- **Notes:** Four ranks are structural per §3.1 — no tier-management screen can add or remove a rank; only labels/badges/colours/icons are data. Phase 1's own migrations already carried the full §5.1 column set (including the bump-term columns on `placement_packages`) and `PlacementTierSeeder` already seeds all four ranks plus one launch package each — this task built the model/policy/resource layer on schema and data that already existed, not new tables. Package definitions differ per object category per §25.2; the category scope is the existing nullable `object_type_id` foreign key, not a separate table per category.
- **Execution findings:** Filament's navigation-group matching (`NavigationManager::navigationItems()`) compares a resource's `$navigationGroup` string against a registered group's *resolved label*, not a separate key — every existing resource sets `$navigationGroup` to a lowercase raw string (`'catalog'`) while the registered group's label resolves to a capitalized, translated string (`'Catalog'`), so the match fails and Filament silently falls back to an ad-hoc, untranslated group. Confirmed empirically via `tinker`, not just read from source. Pre-existing across every resource since Phase 1/2, cosmetic only (sidebar section casing, no effect on routing/authorization/data), and out of this task's scope to fix — the new `commerce` group follows the exact same established convention rather than diverging into a second pattern, and the defect is recorded as `FILAMENT_NAV_GROUP_LABEL_MISMATCH` via `record-diagnostic` for a dedicated fix later.

### [T-3A02] Placement lifecycle & the catalog ordering service

- **Spec:** l1-placement-monetization.md §3.1, §3.4, §5.1, §5.2, §6.1
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=PlacementLifecycleAndOrderingTest` proves the §5.2 `ORDER BY` contract exactly — tier rank dominates every later term; a bump recorded for one scope does not affect ordering in a sibling scope; the service accepts a scope (territory or object-type) and returns objects ordered without a lower-tier object ever preceding a higher one. It also proves a package change never deletes the previous `PlacementHistory` row (append-only) and a manual pin never changes the object's tier.
- **Changes:** `PlacementHistory` model added (`ObjectPlacement` already existed from `T-2B07`, now gained the `package()` relation it was missing); `PlacementOrderingService::apply()` implementing the exact §5.2 expression on any `Object_`-model query builder, reading the within-tier tiebreak from the existing `presentation.within_tier_order` setting rather than hardcoding it; `PlacementLifecycleService` (`grant()`, `pin()`, `unpin()`), each journalled, `grant()` writing the current row and the append-only history row in one transaction; cache invalidation reuses `T-2B06`'s exact tag scheme (`catalog`, `territory:{id}`, `object:{id}`).
- **Evidence:** `pest tests/Feature/PlacementLifecycleAndOrderingTest.php` · exit 0 · 4 passed (10 assertions) — tier dominance survives a pin, a bump, and a promotion all stacked against it; a bump recorded in one territory leaves a sibling territory's ordering byte-identical; granting a second package leaves the first `placement_histories` row intact (2 total) and journals both grants; pin/unpin toggle `pinned_position` without ever touching `placement_package_id`, journalling both. Falsification: the closure that writes `object_placements`/`placement_histories` initially dropped `$actor` from its `use()` list — the grant test failed immediately with `Undefined variable $actor`, confirming the journal actor is genuinely threaded through, not defaulted silently; fixed and the suite returned to green. Full `composer quality` run alongside `T-3A03`.
- **Handoff:** T-3A03 (bump events feed this ordering), T-3A04 (expiry closes a `PlacementHistory` row through `PlacementLifecycleService`), T-3T01 (validation), Phase 5's catalog page (external — consumes this service, does not exist yet).
- **Notes:** `objects.rating` and `rotation_seed` are deliberately absent from the ordering expression — the former column does not exist yet (a documented project constraint), the latter is the specification's own optional term — rather than faked with a placeholder. `object_promotions` (Track B's table) is joined by raw table name since `T-3A02` runs before `T-3B03`'s `ObjectPromotion` model exists; the join needs only the schema, not the model.

### [T-3A03] Bump engine — scoped events, limits, back-office bump action

- **Spec:** l1-placement-monetization.md §3.3, §5.1, §5.3
- **Status:** Done
- **Assignment:** Agent
- **Requires:** T-3A02
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=BumpEngineTest` proves a bump moves an object to the first free position within its own tier and never above it; the recorded scope is the exact territory the bump targeted; a free bump beyond the package's configured interval or per-period allowance is refused, independently of each other; every `BumpEvent` records object, package, scope, timestamp, type, actor, previous and new position, price, and comment; an administrator-initiated bump is journalled distinctly from an automatic one; a package with `bump_allowed = false` refuses any bump type.
- **Changes:** `BumpEvent` model per §5.1; `BumpRefusedException` (four named refusal reasons); `BumpService::bump(object, scope, actor, type, comment)` enforcing the package's interval/count limits, computing an audit-only within-tier position via `PlacementOrderingService`, and invalidating the same cache tags `T-3A02` established. A "Bump" action added to the object edit page's header actions (administrator-initiated only, scope defaulting to the object's own territory per §5.3's own flow diagram — no scope picker), visible only when the current package allows bumping.
- **Evidence:** `pest tests/Feature/BumpEngineTest.php` · exit 0 · 6 passed (20+ assertions) — a bump moves the object ahead of its same-tier sibling while a higher-tier object still outranks it regardless; a second free bump inside the interval is refused; a third free bump once the two-bump allowance is exhausted is refused independently of interval; a paid bump records the package's configured price while an administrator bump records none; a `bump_allowed = false` package refuses every type; two bumps (administrator, then automatic) journal as two distinct `object_bumped` entries with the correct `type` in each. Falsification (via the missing-relation bug caught live, not staged): the first run failed on all four tests touching `$placement->package` with `MissingAttributeException` — `ObjectPlacement` had never gained a `package()` relation in `T-2B07`, so strict mode refused the undeclared magic access rather than silently returning null; added the relation, suite returned to green. Full `composer quality` run alongside `T-3A02`.
- **Handoff:** T-3T01 (bump invariants under volume), Phase 4's owner-facing bump control (external, shares this service per §5.3's flow diagram).
- **Notes:** §3.3's bump types are free, paid, automatic, owner, administrator — the service accepts all five as a matter of contract even though only administrator-initiated has a caller in this phase, so Phase 4's owner-facing control needs no signature change when it arrives. The free-bump "period" is read as the object's current placement term (`object_placements.starts_at`), not a rolling window — a new grant resets the allowance.

### [T-3A04] Expiry sweep and the configured expiry action

- **Spec:** l1-placement-monetization.md §3.4, §5.4
- **Status:** Done
- **Assignment:** Agent
- **Requires:** T-3A02, T-3D01
- **Verify:** `docker compose exec app php artisan schedule:list` shows `placement:sweep-expiry` registered, unreachable from any web route. `docker compose exec app ./vendor/bin/pest --filter=PlacementExpirySweepTest` proves: a placement ending at a configured offset raises exactly one `placement_expiring` notification (no duplicate on re-run); an already-expired placement is demoted to the lowest-active-tier package (category-scoped where one exists), its prior `placement_histories` row is closed, one `package_expired` notification fires, and a re-run neither re-demotes nor re-notifies since the new grant's `ends_at` is no longer in the past; the `hide` override changes only `objects.status`, journalled, leaving the package untouched.
- **Changes:** `PlacementExpirySweepJob`, scheduled daily; a new `placement.expiry_warning_offset_days` setting (array, default `[30,14,7,3,0]`) added to the registry — the "after" point reuses the already-existing `placement.expiry_grace_days` rather than a second offset list, and the action itself reuses the already-existing `placement.expired_behaviour` (demote/hide/review, default demote) rather than declaring a duplicate under a new name.
- **Evidence:** `pest tests/Feature/PlacementExpirySweepTest.php` · exit 0 · 4 passed (12 assertions). Two real, pre-existing bugs found and fixed while writing this task, both recorded via `record-diagnostic`: (1) `database/seeders/PlacementTierSeeder.php` assigned rank 1 (which `ORDER BY rank ASC` sorts first) to the free Standard tier and rank 4 to paid VIP — backwards from `l1-object-catalog.md` §5.3's own ordering comment ("VIP, Recommended, Priority, Standard"); every free listing would have outranked every paying customer in production. Fixed by reassigning ranks (VIP=1 … Standard=4); confirmed via `migrate:fresh --seed` + a direct read. (2) `T-3A02`'s `PlacementLifecycleService::grant()` never closed the previous `placement_histories` row before inserting a new one — two rows sat open (`ends_at` null) simultaneously after a second grant, which is exactly the state this job's "expired and unprocessed" query depends on NOT existing. `T-3A02`'s own test had asserted the prior row still existed but never checked `ends_at`, so it passed without exercising its own name ("closes the prior history row"). Fixed `grant()` to close the open row first; strengthened `T-3A02`'s test to assert closure explicitly; both suites re-run green together.
- **Handoff:** T-3T01, T-3T03 (this job is one of the notification-completeness fixtures).
- **Notes:** No per-user language preference exists on `User` yet — `createNotification()` falls back to the portal's primary language rather than inventing a column inside this job; flagged in the job's own docblock as a real, not-yet-closed gap for §3's "language follows the recipient" (`related_type`/`related_id` on the `Notification` row still let an administrator trace which object triggered it regardless of locale).
- **Notes:** Expiry is scheduled-job-computed, never read-time — §6.1's own warning against a per-query expiry check applies verbatim; a read-time check would also never fire the offset notifications. Idempotency has to survive a re-run of a partially-completed day's sweep without double-notifying, which is the actual failure mode a warning schedule this granular invites.

### [T-3A05] Financial ledger and commerce reports

- **Spec:** l1-placement-monetization.md §3.4, §5.5
- **Status:** Done
- **Assignment:** Agent
- **Requires:** T-3A01
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=FinancialLedgerTest` proves every automatic placement grant produces a real `financial_records` row; an administrator can manually enter a row for exactly one subject (object or banner, never neither or both — the table's own check constraint); ledger read/export are refused without `finance.*` permissions; each of the ten report figures resolves correctly against an unambiguous fixture.
- **Changes:** A `financial_records` table — the unified §5.5 ledger, covering both placement and advertising sales through an exactly-one-of-`object_id`/`banner_id` constraint — turned out to already exist from Phase 1, entirely unpopulated; `T-2A05`'s `FinancialOverview` dashboard widget was already reading from it and had shown zeroes since Phase 2 as a result. `FinancialRecord` model + `FinancialRecordPolicy`; `PlacementLifecycleService::grant()` extended to write a `financial_records` row (status `granted_free`) alongside its existing `placement_histories` write, so every automatic grant now populates the ledger without an administrator re-entering it; `FinancialRecordResource` (full CRUD — a genuinely paid transaction is entered by hand with real payment detail the automatic write doesn't have) with export via `FinancialRecordExporter`; `CommerceReportingService` (the ten §61/§123 figures, filterable by country/period/package); a `CommerceReports` page presenting them.
- **Evidence:** `pest tests/Feature/Admin/FinancialLedgerTest.php` · exit 0 · 4 passed (20 assertions). Real gap closed: `financial_records` existed but nothing had ever written to it — confirmed by reading `DashboardMetrics::financial()`, which has queried this exact table since `T-2A05`, silently returning real zeroes rather than "not built" placeholders (indistinguishable from real data, which is exactly the failure mode that widget's own docblock warns against for a *different* case). Now genuinely populated.
- **Handoff:** T-3T04 (query budget).
- **Notes:** `placement_histories` (T-3A02) and `financial_records` (this task) both carry payment-shaped fields for a grant — not accidental duplication: the former is the placement-specific append-only grant timeline `PlacementLifecycleService` itself reasons about (tier changes, demotion targets), the latter is the cross-revenue-stream accounting ledger `FinancialRecordResource`/`CommerceReportingService` read, and only it can express a banner sale (no `object_id` at all). `grant()` writes both in the same transaction so they cannot drift.
- **Notes:** This is a ledger, not a payment system, per §5.5 — it records that money changed hands elsewhere and never processes a transaction itself. Financial export is a distinct, narrower permission than general placement administration (`[TZ]` §128), already established in Phase 2; reuse it rather than adding a parallel gate.

### [T-3B01] Banner slot registry and the banner model

- **Spec:** l1-advertising.md §3.1, §3.2, §5.1, §5.6
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `docker compose exec app ./vendor/bin/pest tests/Feature/Admin/BannerSlotRegistryTest.php tests/Feature/Admin/BannerResourceTest.php tests/Feature/BannerTargetingTest.php tests/Feature/BannerMediaTest.php tests/Feature/BannerSchedulingTest.php` proves slots are administrator-manageable data (create/edit/deactivate a slot with no code change), each declaring its surface set; a banner may target any combination of territory, category, and language via a single polymorphic pivot, with an untargeted dimension meaning "all"; desktop and mobile creatives are stored as two independent media collections on the same banner; a banner's schedule/active columns round-trip correctly (eligibility filtering itself is `T-3B02`'s concern).
- **Changes:** `BannerSlot` (+`BannerSlotTranslation`) and `Banner` (extended in place, +`BannerTranslation`) models — both Translatable, `Banner` gaining `slot()`, `territories()`/`categories()`/`targetLanguages()` (`morphedByMany()` over `banner_targets`), and `HasMedia`/`InteractsWithMedia` with `desktop_creative`/`mobile_creative` singleFile collections; `BannerSlotPolicy`/`BannerPolicy` (portal-wide, gated on `advertising.*`); full-CRUD `BannerSlotResource` and `BannerResource` (schedule, targeting multi-selects, both creative uploads, per-language sections, a computed click-through-rate column, a trashed filter); both resources registered in the panel authorization matrix's canonical list.
- **Handoff:** T-3B02 (selection reads this registry and targeting).
- **Notes:** Slots are a registry, not template constants, so adding an inventory position is a data operation, not a deployment.
- **Evidence:** `pest tests/Feature/Admin/BannerSlotRegistryTest.php tests/Feature/Admin/BannerResourceTest.php tests/Feature/BannerTargetingTest.php tests/Feature/BannerMediaTest.php tests/Feature/BannerSchedulingTest.php` · exit 0 · 12 passed. Two real, pre-existing gaps found and fixed, both falsified before being trusted: (1) `league/flysystem-aws-s3-v3` was never installed despite `.env` configuring the S3 disk this project's object-storage architecture depends on — any code touching the default disk fatally errored; this task was the first real file-upload flow to exercise it. Installed the package (plus `filament/spatie-laravel-media-library-plugin`), confirmed the fatal was gone. (2) The new upload test itself initially failed with a disk-access error because Livewire stages uploads on the default disk before Media Library sees them, which `Storage::fake('public')` alone does not cover — fixed by also faking the default-disk config for the test's duration. Full `composer test` run alongside `T-3B02`/`T-3B03` — see their shared verification note below.

### [T-3B02] Banner selection & serving service

- **Spec:** l1-advertising.md §3.2, §3.3, §5.2, §6.1, §6.2
- **Status:** Done
- **Assignment:** Agent
- **Requires:** T-3B01, T-3C01
- **Verify:** `docker compose exec app ./vendor/bin/pest tests/Feature/BannerSelectionServiceTest.php tests/Feature/BannerClickRedirectTest.php` proves the exact selection pipeline: schedule and active filters apply first, then language, then category, then territory (exact node or ancestor, walked strictly upward — a sibling territory never matches); when zero banners qualify the slot collapses (asserted as an absent return value, not an empty frame); when several qualify, the most specific territory match wins, ties broken by display order with rotation among equals; a served banner records exactly one `banner_impression` event through the real capture path (queue-faked, never a synchronous write), and following the destination link through the redirect route records exactly one `banner_click` event and actually forwards to the link.
- **Changes:** `App\Services\Advertising\BannerSelectionService::forSlot(slot, context)` implementing the filter-then-rank pipeline, caching the ranked result per `(slot, territory, language, category)` via tagged cache entries, with a round-robin rotation cursor stored under the same tags so any invalidation resets both together; `BannerClickController` (thin redirect route recording the click before forwarding); cache invalidation wired into `AppServiceProvider` against `Banner`'s `saved`/`deleted`/`restored` model events (the resource has no dedicated write-service of its own, since banners are edited directly through the Filament pages).
- **Handoff:** T-3T02 (this is one of the fidelity/no-third-party-script fixtures), Phase 5's page templates (external — mount this service into real slots, do not exist yet).
- **Notes:** Impression recording never sits on the critical path — it goes through the same fire-and-forget analytics capture path every other measured event uses, never a synchronous write. This task builds the service and the click-redirect route in isolation, exercised by feature tests that call them directly; no real page mounts a slot until a later phase.
- **Evidence:** `pest tests/Feature/BannerSelectionServiceTest.php tests/Feature/BannerClickRedirectTest.php` · exit 0 · 20 passed (30 assertions). Real bug found and fixed, falsified before trusting the fix: `staudenmeir/laravel-adjacency-list`'s ancestor-walk depth runs 0 at the requested territory and *negative* going up toward the root — the opposite of the first implementation's assumption — which silently inverted specificity ranking (an ancestor-targeted banner was winning over an exact-node match). Confirmed via the failing assertion's actual output (the wrong banner's id) before patching the sign, then confirmed the correct banner's id came back. `composer unused` (11 used, 0 unused) and `composer audit` (no advisories) both stayed clean despite the two new Composer dependencies `T-3B01` added.

### [T-3B03] Promotional labels and card decoration

- **Spec:** l1-advertising.md §3.4, §5.4, §5.5, §5.6
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `docker compose exec app ./vendor/bin/pest tests/Feature/PromotionalLabelDecorationTest.php` proves a label carries per-language text, border/text/background colour, icon, card position, and active flag; granting a label to a standard-tier object does not change its placement tier (cross-checked against `PlacementOrderingService`'s own ordering query — the object still sorts strictly after every higher-tier object); the label set is open (creating a new label variant requires no code change); the assembled decoration payload's schema is closed to every forbidden treatment by construction (no animate flag, no free-form CSS/size field, `position_on_card` drawn from a fixed enum) — asserted via reflection on the payload's actual shape, not left as an unchecked style guideline; a decorated-card preview is computable before any database write.
- **Changes:** `ObjectPromotion` model (+relations to `Object_`, `PromotionLabel`, granting `User`); `PromotionLabel` extended with a `CardPosition` backed-enum cast; `App\Support\Advertising\{CardPosition,TierBadge,PromotionLabelDecoration,AvailabilityBadge,CardDecorationPayload}` — a closed enum plus four readonly DTOs whose shape itself is what enforces "at most one tier badge, one promotion label, one availability badge, nothing else encodable"; `App\Services\Advertising\CardDecorationService` (`forObject()` assembling the full payload for a persisted object, `previewLabel()` — DB-free, reused by both the admin live preview and `forObject()` internally so the two can never drift apart) full-CRUD `PromotionLabelResource` with a live preview panel; `PromotionLabelPolicy` (portal-wide, gated on `advertising.*`).
- **Handoff:** T-3T04, Phase 5's object card component (external — renders this payload).
- **Notes:** The forbidden-treatment list (no blink, no auto-animation, no oversized captions, no grid-breaking size changes) is enforced as a closed data shape rather than a rendering-time check — there is no field in the payload that could encode any of the four, so a future caller cannot reintroduce one by adding a Blade attribute. Label-granting reuses the catalog's existing bulk "assign promotional label" action rather than adding a second UI path for the same write, per the least-new-surface-area principle.
- **Evidence:** `pest tests/Feature/PromotionalLabelDecorationTest.php` · exit 0 · 10 passed. `pest tests/Feature/Admin/PanelAuthorizationMatrixTest.php` · exit 0 · 5 passed (55 assertions), confirming all three of this track's new resources are correctly scoped and probed. One dead-code PHPStan finding fixed in passing: Larastan's schema reflection knows `objects.availability_status` is a non-nullable Postgres enum, making an initial defensive empty-string guard provably unreachable — simplified to construct the badge directly from the guaranteed-non-empty column. Full independent re-verification across all three of this track's tasks: `composer fix` (456 files, clean), `composer analyse` (0 errors, 361 files), and a full suite run bypassing Composer's process-timeout wrapper (`php artisan config:clear && php -d memory_limit=1G vendor/bin/pest --exclude-group=slow`) returned **399 passed, 3 skipped, 0 failed (1438 assertions)** — matching the authoring agents' own self-reported counts exactly, with no further defect surfaced on independent review. Composer's `process-timeout` bumped from 900 to 1800 in passing, since the growing suite now routinely exceeds the old ceiling when invoked through the `composer test` wrapper itself (the underlying pest run was never at fault).

### [T-3C01] Event ingestion — the two-tier `StatEvent` model, fire-and-forget capture

- **Spec:** l1-analytics.md §3.1, §3.2, §3.3, §5.1, §5.2, §6.2
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `docker compose exec app ./vendor/bin/pest tests/Feature/EventCaptureServiceTest.php` proves capturing any of the eleven measured event kinds never blocks or delays the caller (asserted by timing the call against a queue double, not a live queue); a capture-path exception never propagates to the caller (a forced queue-connection failure still returns normally); the dedup token is coarse and rotating — two captures sharing a token within the dedup window collapse to one `StatEvent`, and the token cannot reconstruct a browsing history (asserted: no durable visitor identifier is persisted anywhere in the row); a dedicated assertion confirms `stat_events` is genuinely partitioned, not a single flat table.
- **Changes:** `StatEvent` model over a date-partitioned table (new migration, partitioned by `occurred_at` range per §6.2); `EventCaptureService::capture(kind, subject, context)` — queues `CaptureStatEventJob` rather than writing inline, swallowing any queue failure so a page render or Livewire action can never fail because analytics could not be recorded; the eleven event kinds from §3.1 registered as `StatEventKind`, an enum-backed registry.
- **Handoff:** T-3B02 (banner impression/click route through this), T-3C02 (rollup consumes `StatEvent`), T-3T02 (privacy/fidelity validation), Phase 5's page instrumentation (external — the actual `object_card_view`/`contact_click` callers arrive with the pages that emit them).
- **Notes:** This task ships the capture pipeline and its own test doubles for the eleven kinds; it does not wire real callers into pages that do not exist until Phase 5, matching how `T-3A02`'s ordering service and `T-3B02`'s selection service are also built ahead of their public-site call sites.
- **Evidence:** `pest tests/Feature/EventCaptureServiceTest.php` · exit 0 · 8 passed — capture never blocks against a queue double, an exception from a forced queue-connection failure never reaches the caller, an unrecognised kind is silently dropped, the dedup token collapses two same-window captures and rotates once the window elapses (two distinct interactions in the same window still record separately), and `stat_events` is confirmed genuinely partitioned. Full `composer test` (354 passed) and `composer analyse` (0 errors, 328 files) run alongside `T-3C02`/`T-3C03` — see their shared falsification note below.

### [T-3C02] Daily rollup and compaction

- **Spec:** l1-analytics.md §3.3, §5.1, §5.2, §6.3
- **Status:** Done
- **Assignment:** Agent
- **Requires:** T-3C01
- **Verify:** `docker compose exec app ./vendor/bin/pest tests/Feature/AnalyticsRollupAndCompactionTest.php` proves the rollup produces exactly one `StatDaily` row per rollup key for the day; running the same day's rollup twice does not double-count (idempotent per §6.3); compaction discards `StatEvent` rows past the retention window only after their day's rollup has completed, never before; a rollup that fails partway leaves no partial `StatDaily` row for the affected day (transactional per-day); both jobs are registered on the schedule and neither is reachable from a web route.
- **Changes:** `AnalyticsRollupJob` (scheduled daily) aggregating `StatEvent` into `StatDaily` — deletes and reinserts each affected day's rows inside one transaction rather than upserting, sidestepping Postgres's NULL-in-unique-index gotcha on the nullable dimension columns; `AnalyticsCompactionJob` (scheduled `dailyAt('01:00')`) discarding rolled-up raw rows past the configured retention (`analytics.raw_retention_days`, added to `SettingsRegistry`). Both registered in `routes/console.php`.
- **Handoff:** T-3C03 (reports read `StatDaily`), T-3T02.
- **Notes:** Rollups must be safe to repeat without double-counting — a failed nightly job is the normal case this design has to survive, not an edge case.
- **Evidence:** `pest tests/Feature/AnalyticsRollupAndCompactionTest.php` · exit 0 · 5 passed — one row per rollup key, idempotent re-run, transactional all-or-nothing failure, compaction ordered strictly after rollup, both jobs scheduled and route-unreachable.

### [T-3C03] Reporting surfaces and traffic sources

- **Spec:** l1-analytics.md §3.4, §5.3, §5.4, §5.6
- **Status:** Done
- **Assignment:** Agent
- **Requires:** T-3C02
- **Verify:** `docker compose exec app ./vendor/bin/pest tests/Feature/AnalyticsReportingTest.php` proves the back-office statistics report rolls up correctly across period, object, city, country, category, language, and banner per §5.3, refused to an actor without the analytics-view permission; a traffic-source record captures only channel, referring host, and campaign tag — never a full referrer URL — on the first event of a visit only, per §5.6's deliberately coarse shape (a second event in the same visit neither overwrites nor duplicates it); an owner-scoped query returns figures for exactly one owner's own objects.
- **Changes:** `TrafficSourceRecorder` (first-touch capture per §5.6, wired into `EventCaptureService`'s capture path as an optional `source` payload); `AnalyticsReportingService` serving both the back-office `AnalyticsReport` page (permission-gated, exports via `StatDailyExporter`) and an owner-scoped query method Phase 4's cabinet will call directly (no cabinet UI yet — this task exposes the service, not a screen).
- **Handoff:** T-3T04, Phase 4's owner statistics page and dashboard (external), Phase 6's portal-wide export surface (external, extends this).
- **Notes:** Traffic-source storage is deliberately coarse — a full referrer URL breaches the privacy-minimal invariant `T-3T02` checks; only channel + host are stored, never a path or query string.
- **Evidence:** `pest tests/Feature/AnalyticsReportingTest.php` · exit 0 · 7 passed (20 total assertions across the three files) — every named dimension rolls up correctly, the permission gate refuses/admits correctly, traffic-source resolution stays coarse (channel/host/campaign only) and first-touch-only, an unrelated owner's figures never leak into another owner's query. Real bug found and fixed while verifying this track's output (not by the authoring agent): `app/Models/StatDaily.php` imported `App\Jobs\AnalyticsRollupJob` solely to support a `{@see}` docblock tag, which tripped the "models are thin — no dependency on the orchestration layers" architecture test (`composer test` initially reported 353 passed / 1 failed). The import was never functionally used — removed it and rewrote the docblock reference as plain prose; full suite returned to 354 passed / 0 failed, `composer analyse` stayed at 0 errors across 328 files.

### [T-3D01] Notification & dispatch model, channel registry

- **Spec:** l1-notifications.md §3, §5.1, §5.2, §6.3
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `docker compose exec app ./vendor/bin/pest --filter=NotificationModelTest` proves a `Notification` exists as a readable record independently of any successful delivery; each `NotificationDispatch` records its own status (queued/sent/failed/suppressed) per channel; message body is rendered and stored at creation time, and a template edit made afterward does not retroactively change an already-created notification's stored body; all ten §5.2 types are seeded with their class (transactional/optional) and default channels; a registered adapter resolves for every seeded channel.
- **Changes:** `NotificationType`, `NotificationChannel`/`NotificationChannelTranslation`, `NotificationTemplate`, `NotificationDispatch` models built (the `Notification` model already existed from Phase 2, record-only — gained `type()`/`dispatches()` relations here); `NotificationChannelAdapter` interface + `InboxChannelAdapter` (no-op — the notification record itself is the inbox entry) + `EmailChannelAdapter` (`Mail::raw()`, no Blade layout yet), resolved by `NotificationChannelAdapterRegistry` reading `config/notifications.php`'s channel-key → adapter-class map — adding Telegram/Viber later is one config line plus one class. `NotificationTemplateSeeder` added (34 bilingual rows: 7 transactional types × 2 channels + 3 optional types × 1 channel × 2 locales) alongside the pre-existing `NotificationChannelSeeder`/`NotificationTypeSeeder`.
- **Evidence:** `pest tests/Feature/NotificationModelTest.php` · exit 0 · 5 passed (50 assertions) — record readability independent of delivery, per-dispatch status independence, template-edit non-retroactivity, all ten types' class/channels verified against the actual seeder (not a hand-rolled duplicate fixture), adapter resolution for both launch channels.
- **Handoff:** T-3D02 (dispatch pipeline sends through this model), T-3A04 (expiry sweep raises notifications through it — done, consumed directly), T-3T03.
- **Notes:** No per-user language/locale column exists on `User` yet — every notification created so far falls back to the portal's primary language; a real, flagged gap for §3's "language follows the recipient", not yet closed.
- **Notes:** The `Notification`/`NotificationDispatch` split is what makes §3's contract hold — do not collapse them into one row with a status column, or a channel retry cannot happen without mutating the notification itself.

### [T-3D02] Dispatch pipeline — queue, retry with backoff, suppression, inbox

- **Spec:** l1-notifications.md §3, §5.3, §6.1
- **Status:** Done
- **Assignment:** Agent
- **Requires:** T-3D01
- **Verify:** `docker compose exec app ./vendor/bin/pest tests/Feature/NotificationDispatchPipelineTest.php` proves the exact dispatch flow: a transactional notification always dispatches outbound regardless of recipient preference, with the inbox entry retained even if every outbound channel is disabled; an optional notification with the recipient's preference off is recorded as `suppressed`, not silently skipped; a failed dispatch retries with backoff and is marked `failed` only after retries are exhausted, visible to an administrator; dispatch is idempotent per `(notification, channel)` — retrying a job that already dispatched successfully does not send a second message; a template is rendered and stored at creation time, and editing it afterward never retroactively changes an already-created notification.
- **Changes:** `NotificationDispatchService` (`create()` renders and stores title/body once from the recipient-locale template, then runs `send()`; `send()` resolves `type.default_channels ∩ active channels`, branches transactional-vs-optional using the new preference store, creates `queued`/`suppressed` dispatch rows, and queues one job per attempted channel; `markAsRead()`/`markAsUnread()` for the inbox); `DispatchNotificationJob` (backoff `[10, 30, 60, 120]`s, idempotent — a dispatch already `sent` is left alone, only `failed()` on retry exhaustion marks a dispatch `failed`); `notification_preferences` table (+model) — recipient control over optional-class types only, absence of a row meaning "not yet configured" and defaulting to enabled, never "disabled"; a uniqueness constraint added to `notification_dispatches` on `(notification_id, notification_channel_id)`, the schema-level backstop for the idempotency guarantee (the table's original migration had declared none).
- **Handoff:** T-3D03 (scheduled jobs call this to actually send), T-3D04 (broadcasts dispatch through it), T-3T03.
- **Notes:** Never send from a request handler — every caller into this pipeline is a job or a decision handler, already queued before it reaches this service.
- **Evidence:** `pest tests/Feature/NotificationDispatchPipelineTest.php` · exit 0 · 8 passed (31 assertions). Three invariants deliberately falsified and confirmed failing before being trusted, then restored: the idempotency guard, the suppression branch, and the `failed()`-only-on-exhaustion rule — each removal produced the expected test failure. `migrate:fresh --seed` confirmed clean from empty with both new migrations. Full suite run alongside `T-3D03`/`T-3D04` — see their shared verification note below.

### [T-3D03] Scheduled jobs — staleness, availability confirmation, dispatch retry

- **Spec:** l1-notifications.md §5.2, §5.4
- **Status:** Done
- **Assignment:** Agent
- **Requires:** T-3D02
- **Verify:** `docker compose exec app php artisan schedule:list` shows the staleness sweep, availability-confirmation sweep, and dispatch-retry job registered, none reachable from a web route. `docker compose exec app ./vendor/bin/pest tests/Feature/StalenessSweepTest.php tests/Feature/AvailabilityConfirmationSweepTest.php tests/Feature/DispatchRetryTest.php` proves the staleness sweep raises an "information out of date" notification for objects past the configured staleness period and none for fresh ones, without re-raising a duplicate on a repeat run within the same window; the availability-confirmation sweep respects the existing confirmation-cadence setting and never fires for an object type that does not track availability; the dispatch-retry job only touches dispatches in `failed` status with retries remaining, and a `sent` dispatch is provably untouched by it.
- **Changes:** `StalenessSweepJob` (daily; reuses `moderation.stale_object_days`, not a second setting), `AvailabilityConfirmationSweepJob` (daily; reuses `availability.confirmation_period_days`), `DispatchRetryJob` (every five minutes; re-queues `failed` dispatches under a retry budget), all raising/retrying exclusively through `T-3D02`'s pipeline, never writing a dispatch row directly; a new `retry_count` column on `notification_dispatches` and a `notifications.dispatch_max_retries` setting (default 3) giving the retry job its own budget, kept deliberately separate from `DispatchNotificationJob`'s own queue-level attempt count — the former resurrects a dispatch that fully exhausted the latter and still ended up `failed`, for an outage longer than one delivery cycle.
- **Handoff:** T-3T03.
- **Notes:** Placement expiry's own warning schedule is a separate, earlier task's job, not this one — it is owned by the placement track since its trigger condition (days to expiry) is placement state, not a notification-side concern. This task's three jobs are the ones whose sole product is a notification. Idempotency for both sweeps is windowed to their own cadence setting, not to "today" — a day-based check would re-nag an owner daily for as long as the underlying condition keeps matching.
- **Evidence:** `pest tests/Feature/StalenessSweepTest.php tests/Feature/AvailabilityConfirmationSweepTest.php tests/Feature/DispatchRetryTest.php` · exit 0. Four invariants deliberately falsified and confirmed failing, then restored: the staleness window guard, the availability-tracking type filter, the retry-budget filter, and the sent-dispatch exclusion. One genuine test-fixture bug (not a product bug) found and fixed in passing: a dispatch-retry assertion compared a freshly-created `attempted_at` timestamp (microsecond precision) against the same value round-tripped through its `datetime` cast (second precision), failing spuriously — fixed by truncating the expectation to the second. `composer analyse` — 0 errors across 371 files at this checkpoint.

### [T-3D04] Administrator broadcast

- **Spec:** l1-notifications.md §5.5, §6.4
- **Status:** Done
- **Assignment:** Agent
- **Requires:** T-3D02
- **Verify:** `docker compose exec app ./vendor/bin/pest tests/Feature/Admin/NotificationBroadcastTest.php` proves a broadcast targeted by country, resort (a territory node — this schema deliberately has no fixed, code-known "resort" level, since territory-level vocabularies are a per-country administrator registry, so "resort" targeting is "target by a specific territory node", exact match), or placement package reaches exactly the matching owners and no others, with one owner holding several qualifying objects still messaged exactly once; sending is refused without a confirmation naming the actual resolved recipient count; a broadcast is refused (visibly, never by an unhandled exception) once the configured daily rate limit is spent, and allowed again once the period rolls over; every dispatch — recipients, body, dispatch date, delivery status, read status — is recorded and queryable through the same dispatch pipeline every other notification uses.
- **Changes:** `BroadcastComposer` resolving a target set (country/resort/package, via `objects.country_id`/`territory_id`/current `ObjectPlacement.placement_package_id`, always deduplicated to distinct owners before anything is created) and dispatching one `administration_message` notification per recipient through `NotificationDispatchService` (extended with optional `$title`/`$body` overrides so a broadcast can supply its own composed message through the same shared pipeline, backward compatible with every existing call site); rate limiting reuses the action-journal entry the send itself writes as its own daily counter, rather than a second, purpose-built table; `BroadcastRateLimitedException` (a typed, catchable refusal, never an unhandled crash); an admin broadcast-composition page behind a counted-confirmation action whose modal reads the live recipient count from current form state, and which is disabled outright once the daily quota is spent.
- **Handoff:** T-3T03, T-3T04.
- **Notes:** A mis-targeted broadcast reaches every owner in a country and cannot be recalled — the confirmation gate naming a real count is not optional polish here, it is the only safeguard against an irreversible mistake.
- **Evidence:** `pest tests/Feature/Admin/NotificationBroadcastTest.php` · exit 0 · 10 passed (35 assertions). Two invariants deliberately falsified and confirmed failing, then restored: the per-owner deduplication (a naive per-object loop double-messaged a two-object owner) and the rate-limit guard (removing it left the refusal exception unthrown). Full independent re-verification across all three of this track's tasks: `composer fix` (474 files, clean), `composer analyse` (0 errors, 374 files), and the full suite bypassing Composer's process-timeout wrapper returned **433 passed, 3 skipped, 0 failed (1541 assertions)** — matching the authoring agents' own self-reported counts exactly, with no further defect surfaced on independent review.

### [T-3E01] Article model and admin CMS

- **Spec:** l1-content-publishing.md §3.1, §3.2, §5.1, §5.4
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `docker compose exec app ./vendor/bin/pest tests/Feature/Admin/ArticleContentTest.php` proves an article carries author, category, per-language title/summary/body/SEO fields, many-to-many related objects/territories/tags (the source requirement's own asymmetry against `NewsItem`/`Promotion`'s single optional association), publication date, and status; a draft is never returned by `Article::published()` (the model's own published scope — no other public-facing query exists yet); a scheduled article whose date has not arrived is excluded, including the embargo case (status flipped to `published` early while `publish_at` is still future must stay hidden); article categories and tags are administrator-managed registries requiring zero code change to add a new one; a cover image round-trips through its media collection, replacing rather than accumulating.
- **Changes:** `Article` (+`ArticleTranslation`), `ArticleCategory` (+`ArticleCategoryTranslation`), `ArticleTag` models with the many-to-many object/territory/tag pivots; `ArticleLifecycleService` (publish/schedule/archive/restore, journaled, cache-invalidating) — no moderation routing, since this model carries no `moderation_status` column at all; full-CRUD `ArticleResource`/`ArticleCategoryResource`/`ArticleTagResource` admin CMS. The `content` permission resource and navigation group already existed from an earlier schema/provider pass — no seeder or provider change needed.
- **Handoff:** T-3E03 (publication pipeline governs status transitions for this model too), Phase 5's blog listing and article page (external).
- **Notes:** Articles are administrator-authored only — no moderation checkpoint applies, since an administrator publishing is already the trusted act. Does not route article publication through the moderation queue the other two models in this track use.
- **Evidence:** `pest tests/Feature/Admin/ArticleContentTest.php` · exit 0 · 7 passed (41 assertions). Built via a background Workflow agent that hit the session's usage cap right at its own final-report step — the agent's real work (every file listed above, run through its own `composer fix`/`composer analyse` already) had completed and landed on disk before that cap tripped, so nothing was lost, only its own self-report. Independently verified from scratch rather than assumed: `composer fix` (504 files, clean), `composer analyse` (0 errors, 402 files), the task's own test file (7/7), and the full suite (**441 passed, 3 skipped, 0 failed**, up from 434 — the 7 new tests) all green.

### [T-3E02] News & promotions models, auto-archival job

- **Spec:** l1-content-publishing.md §3.3, §3.4, §5.1, §5.4, §5.5
- **Status:** Done
- **Assignment:** Direct (Workflow dispatch was unavailable — see Evidence)
- **Verify:** `docker compose exec app ./vendor/bin/pest tests/Feature/Admin/NewsAndPromotionsTest.php` proves a news item carries author, optional object, optional territory, optional category, per-language fields, publish/end dates, status, moderation status, and view count; a portal-wide news item (no object) is supported; an administrator-published news item is genuinely visible under `NewsItem`'s own default (moderation-scoped) query; a promotion carries object, territory, per-language title/description, an image collection, start/end dates, status, and moderation status; a promotion past its end date is archived by `PromotionArchivalJob` within one sweep — not a render-time check — and a second run does not re-process an already-archived row.
- **Changes:** `NewsItem` (+`NewsTranslation`) and `Promotion` (+`PromotionTranslation`) models — both `Translatable` and, unlike `Article`, both using `FiltersModeration` (a real `moderation_status` column, since both support owner-authored input that passes moderation and administrator-authored input that does not); `NewsItemLifecycleService`/`PromotionLifecycleService` (publish/schedule/pin-unpin/withdraw/archive/restore — every publish path sets `moderation_status = 'approved'` alongside `status`, per the fix below); `PromotionArchivalJob` (scheduled daily, idempotent by construction — its own query excludes already-archived rows); full-CRUD `NewsItemResource`/`PromotionResource`, each scoped by `territory_id` (the only scope column either table actually carries).
- **Handoff:** T-3E03, Phase 4's owner authoring surface (external — the five-field minimal form per §5.5, sharing these models and the publication pipeline built here).
- **Notes:** The owner-facing form is deliberately minimal per §5.5 (five fields, no rich editor) — this task does not build that form (Phase 4 does); it builds the model and administrator path the owner form will later write through via the same publication pipeline. Both resources are excluded, correctly, from the panel authorization matrix's axis-less-resource sweep — they declare a real territory scope column, unlike that sweep's portal-wide registries, so no change to that test file was needed.
- **Evidence:** `pest tests/Feature/Admin/NewsAndPromotionsTest.php` · exit 0 · 6 passed (28 assertions). Built directly rather than via a Workflow dispatch — the account's session usage cap (tripped mid-`T-3E01`, see that task's Evidence) meant a fresh Workflow run would have failed identically before its stated reset time, so this task and `T-3E03` are executed in the main session instead. Two real bugs found and fixed via the falsification discipline before trusting the tests: (1) `NewsItemResource`'s navigation icon (`Heroicon::OutlinedSpeakerphone`) does not exist in the installed Filament version — caught immediately by `composer analyse`'s own bootstrap failure, fixed by picking a real enum case (`OutlinedBellAlert`). (2) `PromotionLifecycleService::publish()` guarded `starts_at` with a null check, but `promotions.starts_at` is a NOT NULL column (unlike `Article`/`NewsItem`'s nullable `publish_at`) — Larastan's own schema reflection flagged the check as provably always-false dead code; fixed by removing the impossible branch and making the create form's `starts_at` field required with a `now()` default, so the NOT NULL constraint can never be violated by an admin-created row. A third issue was a bug in my own test, not the product: a portal-wide news item fixture asserted through the model's default (moderation-scoped) query while still a moderation-pending draft — fixed the assertion to use `withUnmoderated()`, since that test's actual subject was `object_id` nullability, not moderation visibility. Full suite re-run clean afterward: **447 passed, 3 skipped, 0 failed** (1628 assertions), up from 441.

### [T-3E03] Shared publication pipeline and cache invalidation

- **Spec:** l1-content-publishing.md §3.1, §5.2, §6.1, §6.3
- **Status:** Done
- **Assignment:** Direct (Workflow dispatch was unavailable — see `T-3E02`'s Evidence)
- **Requires:** T-3E01, T-3E02
- **Verify:** `docker compose exec app ./vendor/bin/pest tests/Feature/Admin/ContentPublicationPipelineTest.php` proves: content scheduled for the future is excluded from `NewsItem::published()`/`Promotion::published()` until the date arrives (`Article::published()` was already proven by `T-3E01`); publishing invalidates the exact cache tags a content item's own record and its related object/territory carry, verified against real cache entries rather than a blanket flush, with an unrelated tag proven to survive untouched; an elapsed-end-date news item withdraws from feeds (`status = 'withdrawn'`) while its own record stays fully reachable, distinct from a promotion's full archival; all three content types produce the same `ContentSummary` shape from `toContentSummary()`.
- **Changes:** `ContentPublicationService::invalidate()` — the one place all three content types' cache-tag enumeration lives; `ArticleLifecycleService`, `NewsItemLifecycleService`, and `PromotionLifecycleService` (all built by the two prior tasks) refactored to call it instead of each keeping its own private tag-building copy. `NewsItemWithdrawalJob` (scheduled daily) — the news-specific counterpart to `T-3E02`'s `PromotionArchivalJob`, withdrawing rather than archiving, since a news item's own detail page stays reachable past its end date while a promotion's does not. `scopePublished()` added to `NewsItem` and `Promotion` (mirroring `Article`'s own, built by `T-3E01`) — the future-date exclusion neither model had until now. `App\Support\Content\ContentSummary` (a readonly DTO) and `Summarizable` (the interface `Article`/`NewsItem`/`Promotion` all implement) — the presentation-contract stub; no template consumes it yet, a later phase builds the actual card/detail views against this shape.
- **Handoff:** T-3T04, Phase 5's public blog/news/promotion surfaces (external — the actual templates).
- **Notes:** No owner-authored-content caller exists yet to route through Phase 2's moderation mode resolution — that arrives with a later phase's cabinet: this task's services are shaped so that caller can route through `NewsItemLifecycleService`/`PromotionLifecycleService` directly once it exists, not through a second, parallel pipeline. Enumerating the invalidation keys once, in `ContentPublicationService`, rather than three independent copies, is what this task actually delivers on the "shared pipeline" name — the alternative (three content types each inventing their own list) is exactly the drift a shared service exists to prevent.
- **Evidence:** `pest tests/Feature/Admin/ContentPublicationPipelineTest.php` · exit 0 · 5 passed (22 assertions). Built directly, same as `T-3E02` (Workflow dispatch still unavailable). PHPStan caught two real gaps before trusting the code: `Article`/`NewsItem`/`Promotion` were each missing `@property-read` docblock annotations for their `summary`/`slug` virtual translation proxies (only `title` had been declared), which `toContentSummary()`'s new access to both surfaced immediately as `property.notFound`; and `ArticleLifecycleService`'s territory-id array needed an explicit `array_values()` — Larastan couldn't otherwise prove a `Collection::pluck()->map()->all()` chain produces a genuine `list<int>` rather than a merely-integer-keyed `array<int>`. Full suite re-run clean afterward: **452 passed, 3 skipped, 0 failed** (1650 assertions), up from 447 — Track E is now fully closed.

### [T-3T01] Catalog ordering & bump invariants under seeded volume

- **Spec:** l1-placement-monetization.md §3.1, §3.3
- **Status:** Done
- **Assignment:** Agent
- **Requires:** T-3A02, T-3A03
- **Verify:** `docker compose exec app ./vendor/bin/pest --group=slow --filter=PlacementOrderingVolume` proves, against the existing seeded volume (52,800 objects, 6,270 territories), that `PlacementOrderingService` never emits a lower-tier object above a higher-tier one across a full scope sweep (a depth-1, depth-2, and depth-3 territory scope plus an object-category scope, four independent checks); that a bump recorded in one scope leaves a sibling scope's ordering byte-identical while the bumped scope itself provably changed; and that the ordering query resolves within the catalog-page query budget at this volume.
- **Changes:** None to production code — `PlacementOrderingService`/`BumpService` were both already correct; this task is purely the volume proof.
- **Handoff:** Phase 5's catalog page inherits this proof; a regression there re-runs this suite before touching the ordering service again.
- **Notes:** Falsification is mandatory per the project's testing discipline, not optional polish: temporarily invert the tier-rank sort direction and confirm the test fails at the exact assertion, then restore; repeat for the scope-isolation assertion by temporarily dropping the scope filter on the bump-recency term.
- **Evidence:** `pest --group=slow --filter=PlacementOrderingVolume` · exit 0 · 1 passed (422,410 assertions, ~86s). Ordering query budget measured at **2 queries** against a 30-query ceiling. Both mandatory falsifications performed and reverted: inverting the rank-comparison direction failed immediately at the country-level scope; commenting out the scope filter on the bump-recency subquery failed exactly at the sibling-scope isolation assertion. `git diff` on both service files is empty — the task changed no production code. Full suite re-run clean alongside `T-3T02`–`T-3T04`: **474 passed, 3 skipped, 0 failed**, see `T-3T04`'s Evidence for the shared final run.

### [T-3T02] Analytics privacy & fidelity invariants

- **Spec:** l1-analytics.md §3.2, §3.3
- **Status:** Done
- **Assignment:** Agent
- **Requires:** T-3C01, T-3C02
- **Verify:** `docker compose exec app ./vendor/bin/pest tests/Feature/AnalyticsPrivacyInvariantsTest.php` proves no `stat_events`/`stat_dailies` row contains a durable visitor identifier — asserted against the tables' own column set, not sampled rows; a forced write failure in the capture path never surfaces to the caller; the dedup token rotates across the 900-second window; raw events past the retention window are absent after compaction while their `stat_dailies` aggregate remains, and compaction alone (no prior rollup) leaves a raw row untouched.
- **Changes:** None to production code (`EventCaptureService`, `AnalyticsRollupJob`, `AnalyticsCompactionJob` are all byte-identical to git HEAD). A real defect was found and fixed in two EXISTING tests instead: `EventCaptureServiceTest.php`'s and this task's own draft used Pest's `->not->toThrow(Throwable::class)` — `Throwable` is an interface, and Pest's class-vs-message-substring dispatch keys on `class_exists()`, which returns false for interfaces, silently degrading the check into a message-substring search that passed regardless of whether anything actually threw. Fixed by calling the capture path as a bare, unwrapped statement instead, so a real exception leak now fails the test outright.
- **Handoff:** T-3T04 (this track's own screens read the same tables).
- **Notes:** Falsify by temporarily letting a capture exception propagate and confirming the corresponding test fails (proving the swallow is load-bearing), then restore. `App\Support\Analytics\StatEventKind` currently declares six kinds (the specification's finer-grained list of contact methods collapses onto `contact_click`, disambiguated by `contact_channel_type_id`), not the larger count an earlier draft of this phase's planning assumed — verified directly against the enum before writing assertions.
- **Evidence:** `pest tests/Feature/AnalyticsPrivacyInvariantsTest.php tests/Feature/EventCaptureServiceTest.php tests/Feature/AnalyticsRollupAndCompactionTest.php` · exit 0 · 18 passed (40 assertions). Falsification performed and reverted: removed `EventCaptureService::capture()`'s try/catch — with the original vacuous assertion the test still reported PASS (proving the assertion was worthless before the fix); with the corrected assertion the same falsified code correctly failed with an uncaught `InvalidArgumentException`. `git diff` on `EventCaptureService.php` is empty. `composer analyse` — 0 errors, 428 files, zero new findings in the analytics services/jobs.

### [T-3T03] Notification delivery completeness

- **Spec:** l1-notifications.md §3, §5.2
- **Status:** Done
- **Assignment:** Agent
- **Requires:** T-3A04, T-3D01, T-3D02, T-3D03, T-3D04
- **Verify:** `docker compose exec app ./vendor/bin/pest tests/Feature/NotificationCompletenessTest.php` proves each of the ten §5.2 types fires from its named trigger exactly once; every optional-class notification with the recipient's preference off is recorded `suppressed` rather than absent; re-running `PlacementExpirySweepJob`, `StalenessSweepJob`, `AvailabilityConfirmationSweepJob`, and `DispatchRetryJob` against an already-processed state produces zero additional dispatches.
- **Changes:** Two real, confirmed gaps closed — three of the ten seeded notification types were fully modeled, seeded, and templated but never actually triggered by anything. `ModerationDecisionService` (`approve()`/`reject()`/`requestRevision()`) now dispatches `moderation_approved`/`moderation_rejected`/`revision_requested` to `ModerationRequest::submittedBy()`, inside the same transaction as the existing journal write, via a new private `notifySubmitter()` helper that never throws on a missing type or recipient. `ObjectLifecycleService` (`publish()`/`hide()`) now dispatches `object_status_changed` to `Object_::owner()`, skipping silently when the object has no owner; `saveAsDraft()`/`archive()`/`restore()` deliberately left unwired (routine editing churn, not a publication-state change, per the type's own trigger wording). `partiallyAccept()` was read in full and deliberately left unwired too — none of the three moderation-decision types cleanly describes a partial acceptance, and forcing one would misrepresent what happened to the owner.
- **Handoff:** none — this is the phase's terminal cross-track check for the notification surface.
- **Notes:** The rejection reason is not embedded in the dispatched notification body — the seeded templates already point the owner to their cabinet for it, and hand-assembling an English "Reason: …" string would have introduced hard-coded, untranslated user-facing copy. This task's fixtures span the expiry sweep, all four Track D tasks, and Phase 2's moderation decision events — genuinely cross-track, unlike the other three validation tasks.
- **Evidence:** `pest tests/Feature/NotificationCompletenessTest.php` · exit 0 · 17 passed (41 assertions). Two of the four required idempotency re-run checks falsified and reverted: neutering `StalenessSweepJob`'s `raisedWithin()` guard produced a duplicate dispatch (2 vs expected 1); neutering `DispatchRetryJob`'s `status = 'failed'` filter produced an extra retry attempt (3 vs expected 2) — both restored, re-run green. `composer analyse` — 0 errors, 428 files. Full non-slow suite run alongside the rest of this track: **474 passed, 3 skipped, 0 failed** (1700 assertions) — this task touches two widely-used services, so the full suite (not just its own file) was the bar for done.

### [T-3T04] Commerce & content panel query budget under seeded volume

- **Spec:** l2-tech-stack.md §5.9 (performance budgets); l1-back-office.md §5.6 (reports)
- **Status:** Done
- **Assignment:** Direct (Workflow dispatch hit the session's usage cap partway through — see Evidence)
- **Requires:** T-3A05, T-3B03, T-3C03, T-3E03
- **Verify:** `docker compose exec app ./vendor/bin/pest --group=slow --filter=PanelQueryBudget` (the existing, dynamic resource sweep from an earlier phase) proves every registered resource — including all eleven this phase added — resolves within the 30-query budget at seeded volume; a new `docker compose exec app ./vendor/bin/pest --group=slow --filter=ContentAndCommercePanelBudget` extends the same proof to the three custom Pages this phase added (`CommerceReports`, `AnalyticsReport`, `NotificationBroadcast`), which the dynamic sweep cannot see since `Filament::getPanel('admin')->getResources()` returns Resources only.
- **Changes:** `tests/Feature/Admin/PanelQueryBudgetTest.php` — added the four new permission keys (`content.view`, `commerce.view`, `finance.view`, `advertising.view`) this phase's resources gate on, without which the dynamic sweep's actor 403s on every new resource before a query count is even measured. New `tests/Feature/Admin/ContentAndCommercePanelBudgetTest.php` for the three pages. A real, systemic regression found and fixed: `ScopeAuthorizer::authorize()`/`constraintFor()` re-ran their full role/permission/scope resolution (two queries) on every single call with zero memoization — harmless at Phase 2's resource count, but the admin navigation sidebar calls this once per registered resource on every page render, and this phase's eleven additional resources pushed `ActionJournalResource`'s list page from comfortably under budget to **41 queries against the 30-query ceiling**. Not a defect in `ActionJournalResource` itself — every resource pays this same fixed per-navigation-item cost, and this one simply had the least headroom. Fixed by adding a per-(user, permission) memo to `ScopeAuthorizer`, bound as a singleton in `AppServiceProvider` specifically so the memo survives for a whole request rather than resetting on every fresh container resolution.
- **Handoff:** none — terminal check for this phase's admin-panel surface.
- **Notes:** Run last within Track T, after every other track's admin resource existed. The `ScopeAuthorizer` fix is a genuine, request-scoped optimization, not a workaround — a user's role/scope grants are immutable within one request, so re-querying them once per navigation item was pure waste regardless of whether any single resource happened to notice.
- **Evidence:** Both test files pass with real per-screen counts recorded, not just pass/fail: `PanelQueryBudgetTest` — `ActionJournalResource` **1** query (down from the pre-fix 41), every other resource comfortably under budget (`ObjectResource` highest at 15, dashboard at 9). `ContentAndCommercePanelBudgetTest` — `CommerceReports` **20**, `AnalyticsReport` **9**, `NotificationBroadcast` **3**, all against the 30-query ceiling. `pest tests/Feature/ScopeAuthorizerTest.php` (the pre-existing test for the changed class) — 7 passed, confirming the memoization changed no observable behavior. `composer fix`/`composer analyse` — clean, 0 errors, 428 files. Full non-slow suite, run once at the end of this track covering all four Track T tasks together: **474 passed, 3 skipped, 0 failed** (1700 assertions). Built directly rather than via Workflow — the agent got partway through (found the missing-permissions gap, wrote both test files) before the session's usage cap tripped again (a second, later reset than `T-3E01`'s); its real work was inspected and kept, the `ScopeAuthorizer` root-cause fix was completed directly. This closes Phase 3's implementation — all 23 tasks across five feature tracks and Track T validation are now Done.

### [T-3T05] Containment cleanup — remove standing plan-phase references from product code

- **Spec:** none (housekeeping — enforces the project's own specification-containment convention, not a domain requirement)
- **Status:** Done
- **Assignment:** Agent
- **Verify:** a repository-wide search for a bracketed plan-phase number (the pattern `\bphase[-_ ]?[0-9]+\b`, case-insensitive) across `app/`, `resources/`, `database/`, and `tests/` returns zero matches. `composer test` still passes with the same test count as before this task — the cleanup rewords comments, docblocks, test names, and one CLI output string; it changes no behavior and skips no test that was not already skipped.
- **Changes:** Six known sites reworded to the underlying fact, plan-phase number removed:
  - `app/Console/Commands/RunBenchmarks.php:52` — "no public page or cache layer exists yet", plan-phase number dropped.
  - `tests/Feature/Admin/JournalCompletenessTest.php` — the append-only trigger's docblock reference and three `it()`/`->skip()` pairs (package/position/bump changes) reworded to name the missing model/feature directly.
  - `tests/Feature/Admin/PanelAuthorizationMatrixTest.php`, `tests/Feature/ModuleInertnessTest.php`, `tests/Feature/EnsureModuleEnabledTest.php`, `tests/Feature/ModelPackageTraitsTest.php` — each docblock reworded to the underlying fact without the plan-phase number.
  - Found one further leak while widening the guard below: `tests/Architecture/ArchitectureTest.php`'s own docblock named `.design/RULES.md` by its SDD system-file name ("RULES.md") — reworded to "the engineering conventions CLAUDE.md states in prose".
  - Strengthened `tests/Architecture/ContainmentTest.php` itself: added a `/\bPhase\s+\d+\b/` pattern (the existing `phase-\d+` pattern only caught the hyphenated file-slug form, not prose) and widened its `Finder` to also scan `tests/` — the six sites above were invisible to the previous version of this exact guard because it never scanned the directory most of them live in.
- **Evidence:** `pest --filter=ContainmentTest` · exit 0 · 1 passed — zero offenders across `app/`, `resources/`, `database/`, `tests/` against all six forbidden patterns, including the widened prose-phase and `tests/`-scope additions. Full `composer quality` (Pint, PHPStan level 8, Pest, coverage, audit, unused) run alongside `T-3A01`'s own changes · exit 0.
- **Handoff:** none. The strengthened `ContainmentTest` itself is the standing guard against recurrence — no further task depends on this one.
- **Notes:** This task exists because the project's own specification-containment convention forbids a plan-phase reference in product code, comments, or test names — the same rule that keeps `.design/…` paths and task IDs out of `app/`. None of the six sites are wrong on their engineering merits; they are reworded, not removed, so the reasoning they record survives with the plan-phase number replaced by the plain-language fact it stood for. Discovered by this phase's own finalize diagnostics scan rather than assigned a domain spec, so it runs once, early, in Track T rather than blocking any feature track.
