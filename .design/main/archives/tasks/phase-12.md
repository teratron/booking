---
phase: 12
name: "SDD Reference Containment Cleanup"
status: Done
subsystem: "app/Services, app/Jobs, app/Console, app/Models, app/Filament/Admin, app/Livewire/Public, app/Providers, resources/views/public, database/migrations, database/seeders, tests/Feature, tests/Architecture"
requires: []
provides:
  - "31 genuine `.design/`-spec-section leaks (bare `§N.N`) rewritten in plain language across app/Services, app/Jobs, app/Console, app/Models, app/Filament/Admin, app/Livewire/Public, app/Providers, resources/views/public, database/migrations, database/seeders, tests/Feature"
  - "ContainmentTest.php now mechanically catches a bare `§N.N` spec-section leak, while correctly leaving the client's own `[TZ] §N` citations — 23 of them, verified individually — untouched via a SKIP/FAIL regex branch"
  - "The 50-file estimate that opened this phase corrected to 31 real leaks + 18 legitimate `[TZ]` citations, discovered by actually classifying every occurrence rather than trusting a bare grep count"
key_files:
  created: []
  modified:
    - tests/Architecture/ContainmentTest.php
    - app/Console/Commands/RunBenchmarks.php
    - app/Filament/Admin/Pages/CommerceReports.php
    - app/Filament/Admin/Resources/FinancialRecords/Schemas/FinancialRecordForm.php
    - app/Jobs/PlacementExpirySweepJob.php
    - app/Livewire/Public/CatalogSearch.php
    - app/Models/FinancialRecord.php
    - app/Models/NewsItem.php
    - app/Models/NotificationPreference.php
    - app/Models/NotificationTemplate.php
    - app/Models/PlacementHistory.php
    - app/Providers/AppServiceProvider.php
    - app/Services/Catalog/CatalogQueryService.php
    - app/Services/Notifications/Channels/InboxChannelAdapter.php
    - app/Services/Placement/BumpService.php
    - app/Services/Placement/CommerceReportingService.php
    - app/Services/Reviews/ReviewModerationService.php
    - app/Services/Seo/SeoHealthReport.php
    - app/Services/Settings/SettingsRegistry.php
    - database/migrations/2026_08_05_232353_create_languages_table.php
    - database/migrations/2026_08_05_232407_create_countries_table.php
    - database/migrations/2026_08_05_232748_create_territories_table.php
    - database/migrations/2026_08_06_071130_create_stat_events_table.php
    - database/migrations/2026_08_06_071245_create_module_conflicts_table.php
    - database/seeders/BannerSlotSeeder.php
    - database/seeders/NotificationTemplateSeeder.php
    - resources/views/components/public/hero-search.blade.php
    - resources/views/public/home/show.blade.php
    - tests/Feature/Admin/AvailabilityStalenessTest.php
    - tests/Feature/Public/PublicCatalogTest.php
    - tests/Feature/Public/PublicObjectProfileTest.php
    - tests/Feature/Public/SeoUrlGrammarTest.php
patterns_established:
  - "A bare `§N.N` in a code comment is a leak only when it points at this project's own `.design/` specification set. A citation of the client's own original technical specification — marked `[TZ]` throughout this codebase, e.g. `[TZ] §17/§100` — is a permanent, legitimate reference to a document that predates and outlives the design scaffolding, and must never be mechanically flagged or rewritten alongside a real leak."
  - "A PCRE SKIP/FAIL branch (`/\\[TZ\\][^.]*?§\\d+(?:\\.\\d+)?(?:[^.]*?§\\d+(?:\\.\\d+)?)*(*SKIP)(*FAIL)|§\\d/`) is the working idiom for \"flag X, but not when Y appears anywhere earlier in the same clause, regardless of distance\" — ordinary fixed-width negative lookbehind cannot express this when the earlier text (a `[TZ]` citation naming several sections joined by \"and\"/\"/\") has no fixed length."
  - "Before adding any mechanical pattern to ContainmentTest.php, classify every real occurrence individually rather than trusting the raw match count as the leak count — this phase's own initial scope (50 files) was wrong until each occurrence was actually read; almost half turned out to be a different, legitimate citation class entirely."
duration_minutes: ~45
---

# Stage 12 Tasks — SDD Reference Containment Cleanup

**Phase:** 12
**Status:** Done (7/7, 2026-08-30). Opened from `/magic.task main` after a prior session's
task-ID containment fix found a second, uncovered leak class; closed the same session once
the corrected scope was verified file-by-file and every real leak rewritten.
**Strategic Goal:** Close a mechanical enforcement gap and the leaks it was silently
missing. `ContainmentTest`'s own task-ID pattern was fixed and its 24 genuine leaks cleaned
in a prior session (`aa7b7d0`), but that same audit surfaced what looked like a second,
wider leak class the test had never checked at all: a raw `grep -rn '§'` across `app/`,
`resources/`, `database/`, and `tests/` returned 50 files. **That raw count was wrong as a
leak count** — see What Makes This Phase Different.

## What Makes This Phase Different

**The phase's own opening estimate did not survive contact with the actual content, and
the correction is the most useful thing this phase produced.** A bare `§N.N` is not
always a `.design/`-spec leak: this codebase also cites the client's own original
technical specification — marked `[TZ]` throughout, e.g. `` `[TZ]` §17/§100 `` — a
permanent, legitimate reference to an external document that predates and outlives the
design scaffolding entirely, the same way citing a real external standard or regulation
would be. Reading every one of the 50 files' actual sentences (not just the grep line)
found **23 occurrences across 18 files were `[TZ]` citations that must never be touched**,
and only **33 occurrences across 31 files were genuine `.design/`-spec leaks**. Had the
phase's own opening plan been executed as first written — rewrite all 50 — it would have
silently destroyed 18 files' worth of correct, permanent citations of the client's actual
contract while claiming a clean `ContainmentTest` pass. This is exactly the same class of
mistake the task-ID regex fix corrected one level up: **a mechanical check's match count is
not automatically its leak count**, and the fix here is the same discipline applied one
step earlier — classify before rewriting, not after.

**The corrected mechanical pattern encodes the same distinction the manual read found.**
`ContainmentTest.php`'s new forbidden pattern uses a PCRE SKIP/FAIL branch: it first
consumes any `` `[TZ]` ... §N(.N)? ... `` citation — including one naming several sections
joined by "and" or "/" within the same clause — up to the next full stop, and only then
checks for a bare `§\d` in what remains. Verified against all 56 real occurrences in the
tree (23 `[TZ]`, 33 leak) with zero false positives and zero missed leaks before any file
was rewritten.

**This is engineering hygiene, not a product-behavior fix.** No task here changes what the
application does — every edit rewrites a comment or docblock in plain language, matching
the Reference Rule's own worked example (`.claude/rules/magic.md` §6). None of it has a
governing `.design/main/specifications/*.md` spec, because the rule being enforced is a
project convention, not a product requirement — the same reason Phase 8's Track F (the
sensitive-zone boundary) cited `.claude/rules/magic.md`/`CLAUDE.md` rather than an L1/L2
source.

**Tracks B, C, and D touch this project's own declared sensitive zones**
(`CLAUDE.md`'s Release & Deployment table: authorization/policies, financial records and
placement/commerce) even though every edit inside them is comment-only. Touching the path
is what the release policy tests, not the nature of the edit, so those tracks need a
person's review grant before merging under the interim single-branch policy, same as
Phase 11's Tracks A and B. `app/Policies/Object_Policy.php` — originally listed under
Track C — turned out to carry only `[TZ]` citations and was dropped from this phase
entirely once classified; Track C's remaining sensitive-zone file is
`app/Models/FinancialRecord.php` alone.

**File independence is real and total.** Every one of the 31 files with a genuine leak is
touched by exactly one track, no two tracks share a file, and a comment-only edit in one
file cannot conflict with a comment-only edit in another — six build tracks plus one
validation track that waits on all of them.

## Atomic Checklist

### Track A — Database Layer (Migrations & Seeders)

- [x] [T-12A01] Rewrite genuine `§N.N` leaks in 5 migrations and 2 seeders

### Track B — Services, Jobs & Console

- [x] [T-12B01] Rewrite genuine `§N.N` leaks in 9 service/job/console files (`Sensitive-Zone: yes`)

### Track C — Models

- [x] [T-12C01] Rewrite genuine `§N.N` leaks in 5 model files (`Sensitive-Zone: yes`)

### Track D — Filament Admin

- [x] [T-12D01] Rewrite genuine `§N.N` leaks in 2 admin Filament files (`Sensitive-Zone: yes`)

### Track E — Public Surface

- [x] [T-12E01] Rewrite genuine `§N.N` leaks in 4 Livewire/provider/view files

### Track F — Tests

- [x] [T-12F01] Rewrite genuine `§N.N` leaks in 4 test files

### Track T — Mechanical Enforcement & Validation

- [x] [T-12T01] Extend `ContainmentTest` with a TZ-aware `§` pattern; verify it fails against the real leak set first, then passes clean

## Task Detail

### Track A — Database Layer (Migrations & Seeders)

**[T-12A01] Rewrite genuine `§N.N` leaks in 5 migrations and 2 seeders**

- **Spec:** N/A — mechanical containment fix per `.claude/rules/magic.md` §6, not a product
  specification.
- **Status:** Done
- **Assignment:** Agent
- **Files:** `2026_08_05_232353_create_languages_table.php`,
  `2026_08_05_232407_create_countries_table.php`,
  `2026_08_05_232748_create_territories_table.php`,
  `2026_08_06_071130_create_stat_events_table.php`,
  `2026_08_06_071245_create_module_conflicts_table.php`,
  `database/seeders/BannerSlotSeeder.php`, `database/seeders/NotificationTemplateSeeder.php`.
  Six further migrations this directory's raw grep also matched
  (`two_factor_secrets`, `role_scopes`, `object_types`, `amenity_groups`, `amenities`,
  `contact_channel_types`) carry only `[TZ]` citations and were left untouched.
- **Verify:** `docker compose exec app php -d memory_limit=1G vendor/bin/pest tests/Architecture/ContainmentTest.php` — passes. `php artisan migrate:fresh --seed --force` — exit 0, applies cleanly from empty.
- **Handoff:** `T-12T01`.
- **Notes:** Migrations are never edited for schema after being applied to a shared
  environment; this touched only comment text, never a `Schema::`/`DB::statement()` call's
  own body.

### Track B — Services, Jobs & Console

**[T-12B01] Rewrite genuine `§N.N` leaks in 9 service/job/console files**

- **Spec:** N/A — mechanical containment fix per `.claude/rules/magic.md` §6.
- **Status:** Done
- **Assignment:** Agent
- **Sensitive-Zone:** Yes — `app/Services/Placement/BumpService.php` and
  `app/Services/Placement/CommerceReportingService.php` are placement/commerce paths under
  `CLAUDE.md`'s Release & Deployment table; this track needs a person's review grant before
  it merges.
- **Files:** `app/Console/Commands/RunBenchmarks.php`, `app/Jobs/PlacementExpirySweepJob.php`,
  `app/Services/Catalog/CatalogQueryService.php`,
  `app/Services/Notifications/Channels/InboxChannelAdapter.php`,
  `app/Services/Placement/BumpService.php`, `app/Services/Placement/CommerceReportingService.php`,
  `app/Services/Reviews/ReviewModerationService.php`, `app/Services/Seo/SeoHealthReport.php`,
  `app/Services/Settings/SettingsRegistry.php`. Five further files this directory's raw grep
  also matched (`ErrorPageResource`'s sibling services `PortalReportingService`,
  `TrafficSourceReportingService`, `ModerationModeResolver`, `ModerationPipeline`,
  `ObjectLifecycleService`) carry only `[TZ]` citations and were left untouched.
- **Verify:** `ContainmentTest` passes; `composer analyse` (PHPStan level 8) clean.
- **Handoff:** `T-12T01`.
- **Notes:** Same pattern as the prior task-ID cleanup (`aa7b7d0`) — restate the cited
  rationale in plain language, never leave a dangling "as above" once the section number is
  gone.

### Track C — Models

**[T-12C01] Rewrite genuine `§N.N` leaks in 5 model files**

- **Spec:** N/A — mechanical containment fix per `.claude/rules/magic.md` §6.
- **Status:** Done
- **Assignment:** Agent
- **Sensitive-Zone:** Yes — `app/Models/FinancialRecord.php` is a financial-records path;
  this track needs a person's review grant before it merges.
- **Files:** `app/Models/PlacementHistory.php`, `app/Models/NewsItem.php`,
  `app/Models/NotificationTemplate.php`, `app/Models/NotificationPreference.php`,
  `app/Models/FinancialRecord.php`. `app/Policies/Object_Policy.php` — originally scoped
  into this track — carries only `[TZ]` citations and was dropped once classified.
- **Verify:** `ContainmentTest` passes; `composer analyse` clean; no model docblock
  introduces an `App\Services` import in the process (the `@see`-auto-import trap a prior
  session already hit once).
- **Handoff:** `T-12T01`.
- **Notes:** Watched for the exact hazard already recorded in project memory — Pint
  auto-imports a class named in an `@see` tag, and an `App\Services` import on a model
  fails the "models are thin" architecture test. Named services in prose, never in a
  linkable tag.

### Track D — Filament Admin

**[T-12D01] Rewrite genuine `§N.N` leaks in 2 admin Filament files**

- **Spec:** N/A — mechanical containment fix per `.claude/rules/magic.md` §6.
- **Status:** Done
- **Assignment:** Agent
- **Sensitive-Zone:** Yes — both files are financial/commerce paths; this track needs a
  person's review grant before it merges.
- **Files:** `app/Filament/Admin/Resources/FinancialRecords/Schemas/FinancialRecordForm.php`,
  `app/Filament/Admin/Pages/CommerceReports.php`. Three further files this directory's raw
  grep also matched (`EditObject.php`, `ObjectForm.php`, `ErrorPageResource.php`) carry
  only `[TZ]` citations and were left untouched.
- **Verify:** `ContainmentTest` passes; `composer analyse` clean.
- **Handoff:** `T-12T01`.
- **Notes:** `app/Filament/Admin/Resources/FinancialRecords/FinancialRecordResource.php`
  was already fixed in a prior session alongside the SEO-template cache-key bug
  (`4cc91c3`) — not re-touched here.

### Track E — Public Surface

**[T-12E01] Rewrite genuine `§N.N` leaks in 4 Livewire/provider/view files**

- **Spec:** N/A — mechanical containment fix per `.claude/rules/magic.md` §6.
- **Status:** Done
- **Assignment:** Agent
- **Files:** `app/Livewire/Public/CatalogSearch.php` (two occurrences),
  `app/Providers/AppServiceProvider.php`,
  `resources/views/components/public/hero-search.blade.php`,
  `resources/views/public/home/show.blade.php`. `routes/web.php` and
  `resources/views/components/public/banner-creative.blade.php` — originally scoped into
  this track — carry only `[TZ]` citations and were dropped once classified.
- **Verify:** `ContainmentTest` passes; full `tests/Feature/Public` suite unaffected (part
  of the phase's own full non-slow regression run, below).
- **Handoff:** `T-12T01`.
- **Notes:** None of these files sit in a declared sensitive zone — ordinary, no
  review-grant gate.

### Track F — Tests

**[T-12F01] Rewrite genuine `§N.N` leaks in 4 test files**

- **Spec:** N/A — mechanical containment fix per `.claude/rules/magic.md` §6.
- **Status:** Done
- **Assignment:** Agent
- **Files:** `tests/Feature/Public/SeoUrlGrammarTest.php`,
  `tests/Feature/Public/PublicObjectProfileTest.php`,
  `tests/Feature/Public/PublicCatalogTest.php`,
  `tests/Feature/Admin/AvailabilityStalenessTest.php`.
  `tests/Feature/Public/ErrorPageOverrideTest.php` and
  `tests/Feature/Admin/PortalReportingTest.php` — originally scoped into this track —
  carry only `[TZ]` citations and were dropped once classified.
- **Verify:** `ContainmentTest` passes; each touched file's own test still passes (part of
  the phase's own full non-slow regression run, below).
- **Handoff:** `T-12T01`.
- **Notes:** `PublicObjectProfileTest.php`'s own `F-17:` QA-finding-number comment was left
  untouched — a finding-tracker ID is not one of the six SDD-artefact reference classes the
  Reference Rule names, and the project's own `PLAN.md` cites the same finding numbering
  convention throughout.

### Track T — Mechanical Enforcement & Validation

**[T-12T01] Extend `ContainmentTest` with a TZ-aware `§` pattern**

- **Goal:** Close the enforcement gap this phase exists to fix — a leak class with no
  mechanical check is a leak class that erodes — without also flagging the client's own
  legitimate `[TZ]` citations as leaks.
- **Method:** Added
  `` /\[TZ\][^.]*?§\d+(?:\.\d+)?(?:[^.]*?§\d+(?:\.\d+)?)*(*SKIP)(*FAIL)|§\d/ `` to
  `ContainmentTest.php`'s `$forbidden` array. Verified the pattern standalone against all
  56 real occurrences in the tree (23 `[TZ]`, 33 leak) before touching any product file —
  zero false positives, zero missed leaks. Ran the test once before Tracks A–F (failed,
  listing exactly the 31 genuine-leak files, proving the pattern detects the real class)
  and once after (passed).
- **Status:** Done.
- **Verify:** `docker compose exec app php -d memory_limit=1G vendor/bin/pest tests/Architecture/ContainmentTest.php` — passes. Full `composer analyse` (PHPStan level 8) — 0 errors. Full non-slow Pest suite — 1394 passed, 3 skipped, 0 failed. `php artisan migrate:fresh --seed --force` — exit 0, clean from empty.
