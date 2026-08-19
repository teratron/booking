---
phase: 7
name: "Operations & Launch Readiness"
status: Todo
subsystem: "app/Services/DataTransfer, app/Services/Backup, app/Filament/Admin, app/Jobs, docker/, docs/"
requires: ["phase-2", "phase-3", "phase-5", "phase-6"]
provides: []
key_files:
  created: []
  modified: []
patterns_established: []
duration_minutes: ~
---

# Stage 7 Tasks — Operations & Launch Readiness

**Phase:** 7
**Status:** Todo
**Strategic Goal:** Everything that stands between a working portal and an operable
one — the import pipeline that content population depends on, export across every
listed entity, backups with a rehearsed restore, production service provisioning and
observability, and a load test run before launch rather than after.

## Atomic Checklist

### Track A — Data-Type Registry & Import Pipeline

- [x] [T-7A01] Data-type registry — one declaration per transferable entity, shared by import and export
- [x] [T-7A02] Import as a background job — upload, column mapping, validation, preview, confirm, report
- [x] [T-7A03] Duplicate detection on name, phone, website, address and coordinates
- [x] [T-7A04] Administrator-confirmed merge, leaving a permanent redirect behind

### Track B — Export

- [x] [T-7B01] Export across every listed entity in XLSX, CSV and JSON, respecting active filters
- [x] [T-7B02] Financial and personal-data export permissions, and journalling of every export

### Track C — Backups, Integrity & Restore

- [ ] [T-7C01] Scheduled off-server backups — database daily, media separately, retained generations, integrity verification
- [ ] [T-7C02] Backup administration — last backup date, manual run, log, technical report, failure notification
- [ ] [T-7C03] Administrator-triggered restore behind re-authentication

### Track D — Production Provisioning & Observability

- [ ] [T-7D01] Production object storage and the CDN in front of both application and media
- [ ] [T-7D02] Production SMTP and error tracking, including queue and scheduler failures
- [ ] [T-7D03] Horizon for queues and the scheduler; Pulse for production visibility

### Track T — Validation & Launch Readiness

- [ ] [T-7T01] Rehearsed restore — a real artefact restored into an empty database, and the runbook that records it
- [ ] [T-7T02] Import and export invariants — round trip, never an automatic merge, never an unpermitted column
- [ ] [T-7T03] Load test against catalog and territory pages at seeded volume
- [ ] [T-7T04] Coverage floor — backfill the two services holding `composer quality` below its own gate

## Task Detail

### Track A — Data-Type Registry & Import Pipeline

**[T-7A01] Data-type registry — one declaration per transferable entity, shared by import and export**
The specification lists the same thirteen entity kinds twice — once as import targets and
once as export targets — and lists them in one breath: objects, owners, contacts, prices,
services, geographic reference data, packages, payments, banners, news, promotions,
statistics, and the action journal. Build **one** registry that declares, per kind: its
model, its column set with per-column label and type, which columns are personal data,
which are financial, the permission required to move it, and which formats it supports.
Import and export are then two readers of one declaration rather than two parallel
inventories that drift a column apart. The registry is code, not configuration: a column
added to a model without a registry entry must fail an architecture test, not ship
silently absent from every export.
*Verify:* `docker compose exec app php -d memory_limit=1G vendor/bin/pest tests/Unit/DataTransfer/TransferableRegistryTest.php`
— asserts all thirteen declared kinds resolve to an existing model and that every declared
column exists on that model's table; plus an `arch()` case asserting no exporter or
importer class names a column the registry does not declare.

**[T-7A02] Import as a background job — upload, column mapping, validation, preview, confirm, report**
The pipeline in the order the specification states it: upload a file, choose the data
type, map its columns onto the registry's, validate, list the errors, preview what would
change, confirm, and produce a report. It runs as a queued job with a progress readout —
a spreadsheet of objects with translations and coordinates will not finish inside a
request, and an import that times out halfway is the failure mode this requirement exists
to prevent. Validation is a separate pass from the write: nothing is written until the
operator has seen the error list and confirmed the preview. The report survives the
session — an operator who closes the tab must still be able to read what happened.
*Verify:* `docker compose exec app php -d memory_limit=1G vendor/bin/pest tests/Feature/Admin/ObjectImportPipelineTest.php`
— drives a real XLSX fixture through every stage: a deliberately malformed row appears in
the error list and is *absent from the database* after the validation pass, the preview
reports the exact created/updated counts, and the post-confirm report is retrievable after
the importing session ends.

**[T-7A03] Duplicate detection on name, phone, website, address and coordinates**
Five signals, all named by the specification, evaluated as candidates rather than as a
single equality test — the same object arrives as "Hotel Astoria" and "Astoria Hotel" with
one shared phone number, or with coordinates a hundred metres apart. Surface candidates in
the preview with the matching signal named, so the operator sees *why* two rows were
paired. Coordinate proximity uses the PostGIS distance already indexed for the catalog,
not a bounding-box approximation. Detection never decides: it presents.
*Verify:* `docker compose exec app php -d memory_limit=1G vendor/bin/pest tests/Feature/Admin/ImportDuplicateDetectionTest.php`
— one case per signal, each asserting the candidate is flagged with that signal named, plus
a negative case proving two genuinely distinct objects in the same settlement are not
paired, and a case asserting an import run with duplicates present writes **zero** merges
of its own accord.

**[T-7A04] Administrator-confirmed merge, leaving a permanent redirect behind**
Every merge is an explicit administrator action on a named candidate pair, choosing which
record survives. The merged-away object leaves a permanent redirect at its own public URL —
the redirect table and its resolution middleware already exist, and this is another writer
into it, in the same operation as the merge rather than as a later step. Media, placements,
statistics and the action journal follow the surviving record; the merge itself is
journalled with both identities.
*Verify:* `docker compose exec app php -d memory_limit=1G vendor/bin/pest tests/Feature/Admin/ObjectMergeTest.php`
— asserts the merged-away object's URL serves 301 to the survivor, that the redirect row is
written inside the merge's own transaction, that placements and media reattach to the
survivor, and that the journal entry names both records.

### Track B — Export

**[T-7B01] Export across every listed entity in XLSX, CSV and JSON, respecting active filters**
One export action driven by `T-7A01`'s registry, offered on every listed entity's table in
all three formats. **Active filters are respected**: exporting a filtered table exports the
filtered set, not the whole table — an operator who filters to one country and receives all
three has been handed a data leak, not a convenience. Large exports queue rather than
stream from the request. Two exporters already exist (financial records, action journal);
they are converted to registry readers rather than left as a third implementation.
*Verify:* `docker compose exec app php -d memory_limit=1G vendor/bin/pest tests/Feature/Admin/EntityExportTest.php`
— a case per format asserting a parseable artefact with the registry's declared header row,
a case asserting a table filtered to one country exports only that country's rows, and a
case asserting the two pre-existing exporters produce the same columns after conversion as
before it.

**[T-7B02] Financial and personal-data export permissions, and journalling of every export**
Financial export and personal-data export each require their own permission, distinct from
the general export permission — the specification separates them, and a role that may
export the object catalog is not thereby a role that may export owner telephone numbers.
Enforce it in the Policy, and narrow the *columns*, not merely the action: a permitted
export of a table that happens to carry a personal-data column must omit that column rather
than refuse the whole export. Every export is journalled with the actor, the entity kind,
the row count and the filter set in force.
*Verify:* `docker compose exec app php -d memory_limit=1G vendor/bin/pest tests/Feature/Admin/ExportPermissionTest.php`
— asserts a role holding only the general export permission receives an artefact with the
personal-data columns absent (not a 403), that the financial permission is separately
required, and that each export writes one journal entry naming actor, kind, count and
filters.

### Track C — Backups, Integrity & Restore

**[T-7C01] Scheduled off-server backups — database daily, media separately, retained generations, integrity verification**
Install and configure `spatie/laravel-backup`, which the project's own package list names
and which is not yet installed. Database backups run daily; media backs up on its own
schedule, because the two have different sizes and different restore urgency. Several
generations are retained. Each artefact is integrity-verified after writing, not assumed
sound. **The destination is a disk separate from the application server, and separate from
the bucket holding the media it protects** — a backup living beside what it protects is not
a backup. Locally that is a dedicated MinIO bucket; production is `T-7D01`'s concern and
must not repoint this one at the media bucket.
*Verify:* `docker compose exec app php -d memory_limit=1G vendor/bin/pest tests/Feature/Operations/BackupScheduleTest.php`
— asserts the scheduler registers the database and media backups on their stated cadences,
that the configured destination disk is neither the application's local disk nor the media
disk, that retention keeps the stated generation count and prunes beyond it, and that a
deliberately corrupted artefact fails the integrity check rather than passing it.

**[T-7C02] Backup administration — last backup date, manual run, log, technical report, failure notification**
The screen the specification describes, in the staff panel: the date of the last successful
backup, a button that runs one now, the backup log, and a downloadable technical report.
A failed backup raises a notification through the notification model the platform already
has — a new channel is not needed, an existing one is. Staleness is surfaced on the screen
itself: "last backup 9 days ago" must read as a warning, not as a neutral date.
*Verify:* `docker compose exec app php -d memory_limit=1G vendor/bin/pest tests/Feature/Admin/BackupAdministrationTest.php`
— asserts the page renders the last artefact's real timestamp, that the manual action
dispatches the backup job, that a simulated failure raises exactly one notification to the
administrator role, that the technical report downloads, and that an artefact older than
the configured staleness threshold renders as a warning state.

**[T-7C03] Administrator-triggered restore behind re-authentication**
Restore is the most destructive action in the portal, and the specification gates it behind
re-authentication — the operator confirms their password at the moment of the restore, not
merely at sign-in hours earlier. Selecting an artefact, confirming, and re-authenticating
are three distinct steps. The confirmation names what is about to be overwritten and the
artefact's own timestamp. The restore itself runs as a job with its outcome journalled and
notified, whether it succeeds or fails.
*Verify:* `docker compose exec app php -d memory_limit=1G vendor/bin/pest tests/Feature/Admin/BackupRestoreTest.php`
— asserts the action is refused outright without a fresh re-authentication, that the
confirmation text names the selected artefact's timestamp, that a non-administrator role
cannot reach the action at all (Policy, not UI), and that both outcomes are journalled.

### Track D — Production Provisioning & Observability

**[T-7D01] Production object storage and the CDN in front of both application and media**
Point the S3-compatible disk at the production provider and put the CDN in front of both
the application and the media bucket. The interface does not change — the platform was
already built against `s3`, and the provider is configuration — so the work is the
configuration surface, the cache and TLS posture, and the documented switch, not new code.
Media URLs must resolve through the CDN host, and the media disk stays distinct from
`T-7C01`'s backup destination.
*Verify:* `docker compose exec app php -d memory_limit=1G vendor/bin/pest tests/Feature/Operations/StorageProvisioningTest.php`
— asserts a media URL is generated against the configured CDN host when one is set and
against the origin when it is not, that no credential appears in any committed file
(scanned, not assumed), and that the backup and media disks resolve to different
destinations. Plus `docs/` recording the provider switch as a runbook step.

**[T-7D02] Production SMTP and error tracking, including queue and scheduler failures**
SMTP through Laravel's mailer, provider per environment, Mailpit unchanged locally.
Error tracking (Sentry, or self-hosted GlitchTip) wired to capture **queue and scheduler
failures**, not only web requests — the backup, rollup, sweep and import jobs all run
there, and an unreported failed job is precisely the blindness the backup-failure
notification requirement exists to close. Personal data is scrubbed from event payloads
before transmission.
*Verify:* `docker compose exec app php -d memory_limit=1G vendor/bin/pest tests/Feature/Operations/ErrorTrackingTest.php`
— asserts a deliberately failed queued job produces a captured event through the configured
transport (faked, not sent), that an event payload carrying an owner's telephone number is
scrubbed before transmission, and that mail configuration resolves to Mailpit in the local
environment and to the configured relay otherwise.

**[T-7D03] Horizon for queues and the scheduler; Pulse for production visibility**
Install Horizon — the project's package list names it, `docker-compose.yml`'s worker
service is annotated as a placeholder awaiting it, and the deployment topology names it as
the worker deployable. Queue names, worker balance and retry posture are declared rather
than defaulted. Install Pulse for production performance visibility, behind the same
authorization as the staff panel.
**Guard the query budget.** `PublicPerformanceBudgetTest` holds the territory page at
exactly 30 queries with zero headroom; a Pulse recorder that adds a per-request query fails
it. Configure Pulse's recorders and sampling so the public request path is unaffected, and
re-run that test as part of this task rather than discovering it in the next one.
*Verify:* `docker compose exec app php -d memory_limit=1G vendor/bin/pest tests/Feature/Operations/QueueTopologyTest.php`
plus `docker compose exec app php -d memory_limit=1G vendor/bin/pest --group=slow tests/Feature/Public/PublicPerformanceBudgetTest.php`
— the first asserts every dispatched job maps to a declared queue and that both dashboards
refuse an unauthenticated and a non-staff request; the second must stay green at its
existing ≤30-query ceiling with Pulse enabled.

### Track T — Validation & Launch Readiness

**[T-7T01] Rehearsed restore — a real artefact restored into an empty database, and the runbook that records it**
The requirement is a *rehearsed* restore, not a documented one: working backups with an
unrehearsed restore is the exact failure this exists to prevent. Take a real artefact
produced by `T-7C01`, restore it into a genuinely empty database, and assert the restored
state matches what was backed up — row counts across the principal tables, and a sampled
record compared field by field. Write the procedure into `docs/` as it was actually
performed, including how long it took and what had to be done by hand.
*Verify:* `docker compose exec app php -d memory_limit=1G vendor/bin/pest --group=slow tests/Feature/Operations/RestoreRehearsalTest.php`
— seeds, backs up, drops to empty, restores, and asserts parity on row counts plus a
field-by-field comparison of one sampled object with its translations and media rows. The
runbook section in `docs/` is part of the deliverable, not a follow-up.

**[T-7T02] Import and export invariants — round trip, never an automatic merge, never an unpermitted column**
The cross-track assertions Tracks A and B each half-own. Export an entity, re-import the
artefact, and assert the result is identical rather than merely plausible — a round trip is
the only test that catches a column the exporter writes and the importer silently ignores.
Assert holistically, across every registered kind, that no import path merges without an
administrator action and no export path emits a column the actor's permissions do not
cover.
*Verify:* `docker compose exec app php -d memory_limit=1G vendor/bin/pest tests/Feature/Operations/DataTransferInvariantTest.php`
— registry-driven, so a kind added later without a round-trip-safe mapping fails the test
rather than shipping: for every declared kind, export → re-import → assert field parity;
plus a sweep asserting zero automatic merges and zero unpermitted columns across all kinds.

**[T-7T03] Load test against catalog and territory pages at seeded volume**
Run before launch, not after. Drive concurrent load at the catalog and territory pages
against `DemoVolumeSeeder`'s 50,000+ objects and report measured latency against the stated
budgets: cache hit < 100 ms TTFB, cache miss < 400 ms, object page < 300 ms, search p95
< 300 ms. **Measure and report; do not hard-assert wall-clock milliseconds** — the Windows
bind mount is not a benchmark host, a constraint this project has confirmed twice. The
query-count budget (≤ 30) is deterministic and *is* asserted. A search p95 over budget is
the stated trigger to escalate to Typesense, so the report must state the figure plainly
enough to make that call.
*Verify:* `docker compose exec app php artisan bench:run --scenario=load --report=storage/app/benchmarks/phase-7-load.json`
inside the container, producing a committed report with per-surface p50/p95 and query
counts; plus `docker compose exec app php -d memory_limit=1G vendor/bin/pest --group=slow tests/Feature/Public/PublicPerformanceBudgetTest.php`
green on its deterministic query-count assertions.

**[T-7T04] Coverage floor — backfill the two services holding `composer quality` below its own gate**
`composer test:coverage` sits at 78.9% against its own 80% floor, and has since before this
phase began. The debt is two services carrying almost no tests — `PromotionLifecycleService`
at 0% and `NewsItemLifecycleService` at 35.9%. It is not this phase's regression, but
shipping a launch-readiness phase whose own quality gate fails is not launch-ready, and
this is the last phase in the plan. Backfill both services' behaviour — publication,
withdrawal, archival, and the scheduled transitions — as real behavioural tests, not
coverage padding.
*Verify:* `docker compose exec app composer test:coverage` — passes its configured 80%
minimum, with `PromotionLifecycleService` and `NewsItemLifecycleService` each above it
individually rather than the total dragged over the line by unrelated code.

## Track Ordering

**Phase 7 is three-wide: `(A → B) ∥ C ∥ D → T`.** Three concerns that genuinely do not
touch each other — moving data in and out, protecting it, and provisioning the services it
runs on — followed by a validation track that is cross-track by construction.

`T-7A01` is the phase's hard gate. Both Track A's import and the whole of Track B read the
registry it declares, and it is the phase's highest-cascade task: six of sixteen tasks
consume it. It is deliberately the smallest kind of gate — a declaration, not machinery —
because everything downstream of it is cheap to write and expensive to reconcile if two
inventories exist. Within Track A the ordering is strict: `A01 → A02 → A03 → A04`.
Duplicate detection has nothing to detect until rows are being read, and a merge has no
candidate pair until detection produces one.

**Track B waits only on `T-7A01`, not on the rest of Track A.** Export is a registry reader
and does not depend on the import pipeline existing. `B01 → B02` internally: the permission
narrowing operates on columns the export action must already emit.

**Track C is fully independent** of A, B and D. It touches no route, no registry and no
public page. Internally `C01 → (C02 ∥ C03)`: both the administration screen and the restore
act on artefacts that must already exist and already be integrity-verified.

**Track D is independent of A, B and C**, with one scheduled cross-track contract below.
Internally its three tasks are genuinely parallel — storage, mail/errors, and queues/metrics
share no code.

Two cross-track contracts are scheduled rather than left to be discovered mid-run:

1. **`T-7C01` and `T-7D01` must resolve to different destinations.** The backup disk and the
   media disk are separate by requirement, and `T-7D01` is the task most likely to
   "simplify" by pointing both at the single production bucket it is configuring. Whichever
   lands first owns the disk names; the second consumes them.
2. **`T-7D03` must not cost the territory page a query.** `PublicPerformanceBudgetTest` was
   tuned to exactly 30 with no headroom, so Pulse's recorders are configured against that
   ceiling inside `T-7D03` itself — not left for `T-7T03` to discover as a budget failure
   that looks like a performance regression in already-completed public-site work.

Track T runs last and consumes all three: `T-7T01` restores what Track C produced,
`T-7T02` round-trips Tracks A and B against each other, `T-7T03` measures the public site
under the configuration Track D provisions. `T-7T04` is independent of every other task in
the phase and may run at any point — it is scheduled in Track T because it is a gate on the
phase's completion, not because anything blocks it.

## Planning Audit

**Optimism bias.** `T-7B01` is the one to distrust. "Export every listed entity in three
formats" reads as one action and is thirteen entity kinds across XLSX, CSV and JSON, each
with a column set, a permission posture and a filter contract. `T-7A01` exists specifically
to collapse it from thirteen bespoke exporters into thirteen declarations — but that only
works if the registry is genuinely built first, which is why it is the phase's gate rather
than a convenience extracted later.

`T-7A02` is the second underestimation. The specification states seven pipeline stages in
one sentence; each is a screen or a job step, validation is a distinct pass from the write,
and the report must outlive the session that produced it. Filament's import action covers
the upload-and-map portion and nothing after it.

`T-7T01` is the third, and it is underestimated in a different way: it is the only task in
the plan whose deliverable is partly a rehearsal rather than code. The value is in what the
rehearsal *discovers* — the manual step nobody documented, the media that restores
separately, the elapsed time. Budgeting it as "write a test" misses the point of the
requirement.

**Hidden dependencies.** The effective parallel degree is three at the start and drops to
two once Track C's short chain finishes. The two scheduled contracts above are the genuine
cross-track resource bottlenecks; both were written into §Track Ordering rather than left to
surface as a disagreement between two finished implementations. One further dependency is
worth naming: `T-7A04`'s merge redirect is a **fourth writer** into the redirect table
already built, alongside slug edits, territory reparenting and archived content. It consumes
that contract; it must not reimplement it.

**Cascade risk.** `T-7A01` blocks six tasks — the largest blast radius in the phase, and the
cheapest to get right, which is a good trade. `T-7C01` is second: `T-7C02`, `T-7C03` and
`T-7T01` all act on artefacts it produces, and a slip there does not merely delay three
tasks, it leaves the portal's single mandatory-in-first-release operational guarantee
unmet. `T-7D03` carries the smallest task with a disproportionate risk: Pulse is a
one-command install that can silently fail a zero-headroom performance test the phase does
not otherwise touch.

**Sequencing risk specific to this phase.** `T-7T03` is a *pre-launch* requirement by
specification — the load test runs before launch, not after. It sits last in the plan by
dependency, which makes it the natural casualty of a compressed schedule. It is not
optional, and deferring it inverts the one ordering the specification states explicitly.

**Plan stability.** All four specifications this phase reads remain `RFC`, the same posture
under which every prior phase was planned and executed without a single Pre-flight HALT.
No open question in the set touches this phase's tasks: the domain and URL model that could
have reshaped `T-7A04`'s redirect was settled by the project owner on 2026-08-15, and the
rate-limit question closed in the preceding phase. This phase's genuine uncertainty is
environmental rather than editorial — which storage, mail and error-tracking providers the
client actually procures — and every task in Track D is deliberately written as a
configuration surface over an unchanged interface so that the answer costs a settings change
rather than a rewrite.

**Scope divergence recorded rather than applied silently.** Horizon is not named in this
phase's own scope table, which lists CDN, object storage, SMTP and error tracking. It is
required by the stack specification's background-execution and deployment sections and by
the project's package list, `docker-compose.yml`'s worker service is annotated as a
placeholder awaiting it, and this is the final phase in the plan — leaving it out orphans a
stated requirement with no later phase to catch it. It is therefore folded into `T-7D03`
alongside Pulse, which shares its production-observability purpose.

## Scope

| Area | Spec |
| --- | --- |
| Import pipeline — upload, column mapping, validation, preview, report | l1-back-office.md §5.7 |
| Duplicate detection on name, phone, website, address, coordinates | l1-back-office.md §5.7 |
| Export across every listed entity, respecting active filters and permissions | l1-back-office.md §5.7 |
| Backups — schedule, retention, integrity verification, failure notification | l1-back-office.md §5.6 |
| Administrator-triggered restore behind re-authentication | l1-back-office.md §5.6 |
| Production provisioning — object storage, CDN, SMTP, error tracking | l2-third-party-integrations.md §5.1, §5.2, §5.4, §5.8 |
| Queue and scheduler topology, production performance visibility | l2-tech-stack.md §5.4, §5.9, §5.10 |
| Load test against catalog and territory pages | l2-tech-stack.md §5.9 |
| Operations runbook and the documented restore procedure | l2-tech-stack.md §5.9 |

## Standing Constraints

- **Duplicates are never merged automatically.** Every merge is confirmed by an
  administrator; a merged object leaves a permanent redirect behind.
- Import runs long and must not block a request — it is a background job with a
  progress report.
- **Nothing is written before the operator confirms the preview.** Validation is a
  separate pass from the write.
- Backups write to a destination separate from the application server, **and separate
  from the media bucket they protect**. A backup on the machine it protects is not a
  backup.
- **The restore procedure is rehearsed, not merely documented.** Working backups with
  an unrehearsed restore is the failure mode this requirement exists to prevent.
- Restore is gated on re-authentication at the moment of the action, not on an
  hours-old session.
- Financial and personal-data export each require their own permission, and the
  narrowing is applied to **columns**, not only to the action.
- Export respects the filters in force. Exporting a filtered table must never return
  the whole table.
- Import and export read **one** data-type registry. Two inventories drift a column
  apart, and the drift is silent.
- The load test runs **before** launch, not after.
- Credentials live in the environment, never in a committed file.
- Wall-clock millisecond figures are measured and reported, never hard-asserted — the
  bind-mounted host is not a benchmark machine. Query counts are deterministic and are
  asserted.

## Detailed Tracking

### [T-7A01] Data-type registry — one declaration per transferable entity, shared by import and export

- **Spec:** l1-back-office.md §5.7
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `docker compose exec app php -d memory_limit=1G vendor/bin/pest tests/Unit/DataTransfer/TransferableRegistryTest.php` — 7 cases: all thirteen declared kinds resolve to an existing model; every declared column exists on that model's table (checked via `Schema::hasColumn`, not asserted); labels and formats are non-empty per kind; `payments` is flagged financial and `owners`/`contacts` are flagged personal data; an unknown key throws; and a sweep (by directory walk + reflection, not Pest's `arch()` DSL — that DSL cannot inspect a static method's returned column list) asserts every `Filament\Actions\Exports\Exporter` subclass under `app/Filament` names only columns its matching registry entry declares. Full non-slow suite: 752 passed (up from 745), 0 failed, 3 skipped. `composer analyse` (PHPStan level 8) and the full architecture suite (8/8, including `ContainmentTest`) clean on the changed files.
- **Handoff:** Gates `T-7A02`, `T-7A03`, `T-7A04`, `T-7B01`, `T-7B02`, `T-7T02`.
- **Notes:** The sweep test is a genuine cross-check, not a tautology: it independently confirmed all three pre-existing exporters (`FinancialRecordExporter`, `ActionJournalExporter`, `StatDailyExporter`) already emit only columns this registry declares, without those exporters having been changed — the registry's column choices were derived from the migrations directly, then verified against production code's own column names. Dotted relation-display columns (`package.name`, `responsibleStaff.name`, `user.name`) are outside the registry by design; they address a related model's own attribute, not the entity's own row. `T-7A02`–`T-7A04` and `T-7B01`/`T-7B02` are this registry's first real *consumers*; converting the three existing exporters into registry readers is `T-7B01`'s own task, not done here. Personal-data and financial column flags live here because `T-7B02` narrows exports on them — a new standalone `personal_data_access` permission (mirroring the existing `financial_access`) was added to `PermissionSeeder`, since the specification requires personal-data export to require its own permission and no such gate previously existed; granting it to any role is deliberately left to `T-7B02`, which owns the enforcement policy. "Geographic reference data" is declared as `Territory` alone (not `Country`/`TerritoryLevel`), since those are small bootstrap registries rather than bulk-import targets — recorded here since the specification names the kind in the singular ("geographic reference data") without enumerating its constituent tables.
- **Changes:** New `App\Services\DataTransfer\{Transferable,TransferableColumn,TransferableRegistry}` — 13 declared kinds (`objects`, `owners`, `contacts`, `prices`, `services`, `geography`, `packages`, `payments`, `banners`, `news`, `promotions`, `statistics`, `action_journal`), each with its model, column set, formats (`xlsx`/`csv`/`json`), permission, and personal-data/financial flags. `PermissionSeeder::standaloneKeys()` gained `personal_data_access`. `resources/lang/{en,ru}/panel.php` gained a `data_transfer` block (kind and column labels, both languages) — no literal copy. New `tests/Unit/DataTransfer/TransferableRegistryTest.php` (7 cases, listed above).

### [T-7A02] Import as a background job — upload, column mapping, validation, preview, confirm, report

- **Spec:** l1-back-office.md §5.7
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `docker compose exec app php -d memory_limit=1G vendor/bin/pest tests/Feature/Admin/ObjectImportPipelineTest.php` — 2 cases, 38 assertions, all passing. A real XLSX fixture (built with `openspout`, the same reader the pipeline itself reads with) drives upload, kind selection, column mapping, validation/preview, and confirm through the real Livewire page: a deliberately malformed row (a foreign key naming a nonexistent object type) is named in the error list by its row number and is absent from the `objects` table both after the preview pass and after confirming; the preview's create/update/error counts (1/1/1) match exactly what the confirmed import writes; the post-confirm report (`imports` + `failed_import_rows`) is read back through fresh queries, not the calling test's own in-memory return values. A second case asserts a staff account without `object.create` receives a 403 on the page itself (`canAccess()`), not merely a hidden nav item. `composer analyse` (PHPStan level 8) clean on every new/changed file. Full architecture suite: 8/8 passing, including `ContainmentTest`. Full non-slow suite: 754 passed (up from 752), 0 failed, 3 skipped.
- **Handoff:** `T-7A03` attaches duplicate candidates to this pipeline's preview stage — the seam is `ImportPipelineService::preview()`'s per-row loop, where a row's resolved record and its create/update outcome are already known before the next row starts; duplicate detection needs its own signal match there too, surfaced into `ImportPreviewResult` alongside the error list rather than reworking this loop's shape.
- **Notes:** Filament's own `ImportAction` reads CSV only (`League\Csv\Reader`); the XLSX branch (a new `SpreadsheetParser`, via `openspout`) is genuinely new work — CSV parsing is implemented too for the pipeline's own generic design, though this task's own fixture and tests exercise only XLSX per its stated scope. The two-phase design is built by extending Filament's own `Importer` (`App\Filament\Admin\Imports\ObjectImporter`), reusing its real per-row remap/cast/validate/fill logic for BOTH passes and short-circuiting only `saveRecord()` behind a `dryRun` option during preview — a row that previews clean is provably the same code path that writes clean, not a hand-rolled parallel validator that could drift from the real write. Confirming dispatches Filament's own `ImportCsv` job (batched, so it queues genuinely in production and only runs inline in tests because `QUEUE_CONNECTION=sync`), reusing its existing chunking, `failed_import_rows` persistence, and `imports.processed_rows`/`successful_rows` bookkeeping rather than rebuilding any of it. A new `ImportKindRegistry` intentionally covers only `objects` for now — a kind present in the data-type registry but absent here is an honest "not wired yet" boundary the UI itself respects (the data-type select only offers supported kinds), not a silent no-op an operator would discover only after uploading a file. Gated behind the existing `object.create` permission rather than a new standalone verb — bulk import is the bulk form of the same action a staff member already takes one record at a time through `CreateObject`, and the seeder's fixed verb list has no "import" verb to reuse; adding one nothing else checks was judged not warranted. Row-level country/category scope narrowing (the kind `CreateObject` applies per manually-created record) is deliberately NOT yet applied per import row — recorded here since a future task may need it if a scoped administrator is ever handed this screen; today only an unrestricted administrator is expected to run bulk import. Uploaded rows are held in cache (one-hour TTL) keyed by a short token kept in Livewire state, rather than in the component's own public properties, so the wire payload between steps stays constant-size regardless of row count. Found and fixed a real vendor-docblock gap while testing: Filament's own `Import` model documents `completed_at` as `CarbonInterface|null`, but its own `casts()` declares `'completed_at' => 'timestamp'`, which Laravel casts to a plain Unix-epoch integer, not a Carbon instance — calling a Carbon method on it fatals; worked around by reading the raw attribute and converting explicitly. `openspout/openspout` and `league/csv` are used directly without adding either to `composer.json`'s own `require` — both are already transitive dependencies of `filament/filament` (via `filament/actions`), and this codebase's three pre-existing exporters already establish the same "use the Filament import/export machinery without declaring its own package directly" pattern.
- **Changes:** New `App\Services\DataTransfer\{ParsedSpreadsheet,SpreadsheetParser,ImportRowError,ImportPreviewResult,ImportKindRegistry,ImportPipelineService}` — file parsing (XLSX/CSV) and the two-phase preview/confirm orchestration, generic over any future `ImportKindRegistry` entry. New `App\Filament\Admin\Imports\ObjectImporter` — the "objects" kind's Filament `Importer` subclass, translating the data-type registry's declaration into real per-column validation and casting rules. New `App\Filament\Admin\Pages\DataImport` and `resources/views/filament/admin/pages/data-import.blade.php` — the five-stage admin screen (upload, map, validate/preview, confirm, report), gated on `object.create`. `resources/lang/{en,ru}/panel.php` gained a `data_transfer.import` translation block (labels, actions, pluralized notifications), key-symmetric across both languages. New `tests/Feature/Admin/ObjectImportPipelineTest.php` (2 cases, listed above).

### [T-7A03] Duplicate detection on name, phone, website, address and coordinates

- **Spec:** l1-back-office.md §5.7
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `docker compose exec app php -d memory_limit=1G vendor/bin/pest tests/Feature/Admin/ImportDuplicateDetectionTest.php` — 8 cases, 29 assertions, all passing: one case per signal (name, phone, website, address, coordinates) asserting the flagged candidate names exactly that one signal and no other; a negative case proving two objects sharing a territory but with a distinct name, address, phone, website, and a pin roughly 1.1 km apart are never paired; a case confirming that a confirmed import with a flagged duplicate still writes the incoming row as its own, separate object — the existing candidate untouched, the `redirects` table still empty — nothing merges on its own; an eighth case drives the real `DataImport` Livewire preview screen end to end and asserts the candidate surfaces there, not only at the service layer. `composer analyse` (PHPStan level 8, project-wide, 665 files): 0 errors. `pest tests/Architecture`: 8/8 passing (19 assertions), including `ContainmentTest`. `pint --test` on every new/changed file: pass. Full non-slow suite: 762 passed (up from 754), 0 failed, 3 skipped — exactly the 8 new cases added, no regressions.
- **Handoff:** `T-7A04` acts on the candidate pairs this produces. The candidate's `objectId` is the survivor/loser pair T-7A04 needs; `DuplicateCandidate::signalKinds()` is already the list of fired signals if the merge screen wants to display them without re-deriving anything.
- **Notes:** Coordinate proximity does **not** read `objects.geom` alone as originally assumed here — a real, confirmed gap found while implementing this task: nothing in this codebase's own object-creation paths (the admin form, the cabinet form, or the objects importer built earlier in this track) ever writes that column, only `latitude`/`longitude`. Comparing against `geom` alone would have made this signal silently never fire against any object created through the normal application flow, only against rows a seeder or a test wrote directly with raw SQL. `DuplicateDetectionService` instead computes `ST_DWithin` against `COALESCE(geom, ST_SetSRID(ST_MakePoint(longitude, latitude), 4326)::geography))` — still a real PostGIS distance, never a bounding box, just resilient to whichever of the two columns a given object actually carries. This is recorded here rather than silently patched because it is a pre-existing gap this task's own signal exposed, not something this task was scoped to fix everywhere `geom` is written (or isn't); a future task populating `geom` on every write path can drop the `COALESCE` fallback once that gap closes. Radius is 100 m, the same figure the specification's own illustrative scenario uses ("coordinates a hundred metres apart"). Name and address both use `similarity() >= 0.3` — `pg_trgm`'s own default operator threshold, verified directly against Postgres (`show_limit()`) rather than assumed; empirically confirmed to score a reordered two-word phrase ("Hotel Astoria" vs "Astoria Hotel") at 1.0 and two genuinely unrelated short phrases at 0.0. Phone and website match after normalization (digits-only for phone; scheme/`www.`/trailing-slash stripped for website) rather than raw equality, since the same real number or site is routinely typed differently. Name, phone, and website are **not** part of the "objects" registry entry's own declared column set (that registry is scoped to a model's own table columns only — name lives on `object_translations`, phone and website on `contact_channels`, each its own registered kind), so the operator-driven column-mapping step never offers them a slot; `NewObjectSignals::fromRow()` reads them directly from the uploaded file's own raw headers via a short alias list instead, independent of the registry-driven map. Detection only runs for rows that would **create** a new object — a row already matched to an existing record by `ulid`/`id` is an explicit update, never ambiguous, so it is never compared against the catalog it is already part of. Surfaced in `ImportPipelineService::preview()`'s own per-row loop, added to `ImportPreviewResult` alongside the error list, and rendered in the `DataImport` screen's preview step next to the error list, gated on the same `object.create` permission the rest of the screen already uses — no new permission introduced. `DuplicateDetectionService` is safe to call unconditionally today because the import pipeline's own kind registry supports only the `objects` kind; a comment at the call site flags that a second kind would need its own guard.
- **Changes:** New `App\Services\DataTransfer\{DuplicateSignal,DuplicateCandidate,ImportRowDuplicate,NewObjectSignals,DuplicateDetectionService}` — the five-signal comparison against the existing catalog and the value objects that carry a flagged pairing (with every fired signal named) back to the preview stage. Modified `App\Services\DataTransfer\ImportPreviewResult` (new `duplicates`/`duplicateCount()`) and `App\Services\DataTransfer\ImportPipelineService` (constructor-injects `DuplicateDetectionService`; `preview()` now runs duplicate detection against every row that would create a new object). Modified `App\Filament\Admin\Pages\DataImport` and `resources/views/filament/admin/pages/data-import.blade.php` — a fifth summary tile and a candidate list in the preview step, next to the existing error list. `resources/lang/{en,ru}/panel.php` — new `data_transfer.import.duplicates` translation block plus `summary.duplicates`, key-symmetric across both languages (verified programmatically, 8/8 and 38/38 keys match respectively). New `tests/Feature/Admin/ImportDuplicateDetectionTest.php` (8 cases, listed above). One-line change to `tests/Feature/Admin/ObjectImportPipelineTest.php`: its `previewSummary` assertion switched from an exact array match to a partial one (`toMatchArray`), since that summary now also carries the `duplicates` key this task added — that test's own concern is the validate/preview/confirm pipeline, not duplicate detection.

### [T-7A04] Administrator-confirmed merge, leaving a permanent redirect behind

- **Spec:** l1-back-office.md §5.7; l1-seo.md §5.5 (redirect contract)
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `docker compose exec app php -d memory_limit=1G vendor/bin/pest tests/Feature/Admin/ObjectMergeTest.php` — 7 cases, 31 assertions: media/placement/statistics reattach to the survivor, including a `stat_dailies` rollup-collision fold and a plain repoint case; the survivor's own existing placement is left untouched rather than overwritten when both records already carry one; the merged-away URL serves 301 to the survivor; a forced failure injected into the transaction's own last write (the audit insert) proves the redirect, media, and soft-delete only survive if the whole transaction commits — not a re-implementation of the transaction boundary elsewhere; the journal entry names both records; a non-`object.delete` actor is denied via `Gate`, not the UI. `composer analyse` (PHPStan level 8, full project scope — `app`/`database`/`routes` per `phpstan.neon`, 667 files): 0 errors. `pint --test`: clean. Full architecture suite: 8/8, including `ContainmentTest`. `php artisan migrate:fresh --seed`: applies cleanly from empty.
- **Handoff:** Exercised holistically by `T-7T02`.
- **Notes:** `App\Services\Objects\ObjectMergeService` reuses the existing redirect writer (`App\Services\Seo\RedirectRegistrar`) rather than inserting into the `redirects` table directly — the same choice `ModerationDecisionService::registerObjectSlugRedirects()` already made — and consults `PublicUrlGenerator::objectUrl()` as a guard so a locale the survivor carries no translation for is skipped rather than redirected to a page that would not resolve. `object_placements.object_id` is unique (one commercial grant per object), so a placement moves to the survivor only when the survivor does not already hold one — the loser's placement row is left pointing at the now-archived object rather than silently overwritten, matching the "the merge does not decide, an administrator already did" framing. The aggregate `stat_dailies` tier is folded (counts summed) on a rollup-uniqueness collision rather than repointed, since a blind repoint would violate that table's own unique constraint; raw `stat_events` rows have no such constraint and are always repointed. The merge is gated behind the existing `object.delete` permission (a new `ObjectPolicy::merge()` method delegating to the same `authorizeAgainst()` check) rather than a new permission, on the reasoning that a merge permanently removes a record's identity, the same class of action `object.delete` already gates — the caller checks this ability against *both* records in a pair, not only the one currently open in the editor. The Filament action offers both directions (the record currently open may either survive or be merged away) with a live-updating confirmation naming both records by name, extending this panel's existing "confirmation names the affected records" convention from a count to a directional pair — the case that convention exists to prevent here is a wrong-direction merge, not an unexpectedly large blast radius. PHPStan level 8 run *additionally* against the test file itself (outside `phpstan.neon`'s own `app`/`database`/`routes` scope, so not part of `composer analyse`) surfaced 14 nullable-access findings of a shape (`expect($x)->not->toBeNull()->and($x->prop)`) already present, unfixed, throughout this codebase's existing test suite (e.g. `BannerTargetingTest`, `AnalyticsReportingTest`, `BannerSelectionServiceTest`) — left as-is for consistency with that established, if unchecked, convention rather than holding one new file to a bar the project's own quality gate does not apply anywhere else.
- **Changes:** New `App\Services\Objects\ObjectMergeService` (the merge transaction: media/placement/statistics reattachment, redirect registration, soft-delete of the loser, journalling both identities) and `App\Exceptions\ObjectMergeRefusedException`. Modified `App\Policies\Object_Policy` (new `merge()` method) and `App\Filament\Admin\Resources\Objects\Pages\EditObject` (new header action offering a searchable candidate picker, a survivor radio, a live confirmation summary, and the redirect-after-merge). New translation keys under `panel.objects.merge.*` and `panel.objects.lifecycle.merge` in both `resources/lang/en/panel.php` and `resources/lang/ru/panel.php`. New `tests/Feature/Admin/ObjectMergeTest.php` (7 cases, listed above).

### [T-7B01] Export across every listed entity in XLSX, CSV and JSON, respecting active filters

- **Spec:** l1-back-office.md §5.7
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `docker compose exec app php -d memory_limit=1G vendor/bin/pest tests/Feature/Admin/EntityExportTest.php` — 7 cases, 56 assertions, all passing: a real object-list export driven through the real Filament header action (Livewire `callAction('export')`, `QUEUE_CONNECTION=sync` so the queued pipeline runs inline) produces a parseable artefact per format — XLSX (read back with `openspout`), CSV, and JSON (both header row / top-level keys checked against the registry's own declared labels); a case filters the same list to one country and asserts the exported `id` set equals exactly that country's own object rows, not the whole table; three further cases assert the pre-existing `FinancialRecordExporter`, `ActionJournalExporter`, and `StatDailyExporter` still emit every column and label they emitted before this task, plus any registry-declared column they had never emitted. `composer analyse` (PHPStan level 8) clean on every new/changed `app/` file (one real bug found and fixed along the way — see Notes). `pint --test` clean. Full architecture suite: 8/8, including `ContainmentTest`. `migrate:fresh --seed`: applies cleanly. Full non-slow suite: 776 passed (up from the stated 769 baseline — exactly the 7 new cases), 0 failed, 3 skipped, 2371s.
- **Handoff:** `T-7B02` narrows this action's columns by permission — the seam is `ReadsTransferableRegistry`'s `getColumns()`/`appendMissingRegistryColumns()` override point, plus the (not yet built) column-narrowing hook this task deliberately left for it. `T-7T02` round-trips this export against import; the two data types (registry-declared name vs. Filament's translated label as the JSON/CSV header) matter there — the round trip needs to read the SAME header/label the registry declares, which `objectsRegistryHeaderRow()` in this task's own test already demonstrates how to derive. Kinds `contacts`, `prices`, and `services` have no admin table wired (see Notes) — a future task adding one should follow the `ObjectExporter`-style thin exporter using the same trait, not hand-roll a new column list.
- **Notes:** Filament's own `ExportAction`/`Exporter` machinery supports CSV and XLSX only — no JSON. Rather than reimplementing the queued export pipeline for a third format, JSON is added as a third download format alongside Filament's own pair: the queued pipeline already writes CSV chunk files to disk regardless of which formats are declared (XLSX is itself only a download-time conversion of those same chunks, confirmed by reading `Filament\Actions\Exports\Downloaders\XlsxDownloader`'s own source), so a `JsonExportFormat` enum (implementing Filament's own `ExportFormat` contract) plus a `JsonDownloader` that converts the same CSV chunks to a streamed JSON array follows the identical, already-established pattern — no new job, no new query serialization, no new `Export` row bookkeeping. Filament's own download route can't resolve a format it doesn't know about (`DownloadExport::resolveFormatFromRequest()` hard-codes its own enum), so a small parallel route/controller (`exports.download-json` → `DownloadJsonExportController`) mirrors that controller's own two-tier authorization (signed URL carries the auth guard; an `Export` policy's `view` ability if registered, otherwise only the export's own owner) rather than editing vendor code. A shared `ReadsTransferableRegistry` trait (not an abstract base — see below) gives every exporter `getModel()`, a default registry-driven `getColumns()`, the CSV/XLSX/JSON `getFormats()` triple, and a default notification body; seven brand-new per-kind exporters (`ObjectExporter`, `OwnerExporter`, `TerritoryExporter`, `PlacementPackageExporter`, `BannerExporter`, `NewsItemExporter`, `PromotionExporter`) are each three lines naming their registry key, wired onto their resource's existing `ListRecords` page as a new header `ExportAction` alongside `CreateAction`, gated by the exact registry-declared permission (`->authorize(fn () => $this->actor()?->can($permission) ?? false)`, matching this codebase's already-established pattern on `ListFinancialRecords`/`ListActionJournal`/`AnalyticsReport`). The trait is deliberately named `ReadsTransferableRegistry`, not `*Exporter`, because `TransferableRegistryTest`'s own containment sweep discovers every `*Exporter.php` file under `app/Filament` by path and calls `getModel()`/`getColumns()` on it directly — an abstract base named `RegistryExporter.php` would be swept too and fatal on its own abstract `transferableKey()`. **Kinds `contacts` (`ContactChannel`), `prices` (`Price`), and `services` (`Amenity`) are not wired** — no Filament resource anywhere in `app/Filament` administers these models directly (confirmed by search: zero references to any of the three model classes across the whole panel), so there is no existing table to attach an export action to; building one is out of this task's own scope. The three pre-existing exporters (`FinancialRecordExporter`, `ActionJournalExporter`, `StatDailyExporter` — the phase file's own Notes named only two, "financial records, action journal", but a third, `StatDailyExporter` on `AnalyticsReport`, already existed too) are converted to registry readers via `appendMissingRegistryColumns($columns, $relationSubstitutes)`: each keeps its existing hand-written column list — labels, `formatState` callbacks, and dotted relation-display columns (`package.name`, `responsibleStaff.name`, `user.name`) exactly as they were — and the helper appends any registry-declared column the hand-written list doesn't already cover (by name, or via a substitution map for a raw foreign key already covered by its display equivalent). For `payments`, the registry's 14 columns map 1:1 onto the pre-existing 14 (via the two substitutions), so nothing is appended and the visible output is byte-identical to before. For `action_journal` and `statistics`, the registry declares columns (`id`; `user_type`, `user_id` for the journal) the pre-existing exporters had never emitted — these are now appended automatically as new, disclosed additions, which is the literal mechanism this task's own "so a column added to the registry is a column these get automatically" requirement asks for; column ORDER changes for these two (registry order for the appended tail) but no existing label or format changes. A real bug found and fixed while wiring `JsonDownloader`: `Illuminate\Filesystem\FilesystemAdapter::readStream()` returns `resource|null`, but `League\Csv\Reader::from()` requires a non-null argument — PHPStan level 8 caught this on the very column reading the CSV chunk files back for JSON conversion; guarded with an explicit null check.
- **Changes:** New `App\Filament\Admin\Exports\Concerns\ReadsTransferableRegistry` (the shared trait), `App\Filament\Admin\Exports\{JsonExportFormat,JsonDownloader}` (the third download format), `App\Filament\Admin\Exports\{ObjectExporter,OwnerExporter,TerritoryExporter,PlacementPackageExporter,BannerExporter,NewsItemExporter,PromotionExporter}` (one thin `Exporter` per newly-wired kind). New `App\Http\Controllers\Admin\DownloadJsonExportController` and its route in `routes/web.php`. Modified `App\Filament\Admin\Resources\{Objects,Owners,Territories,PlacementPackages,Banners,NewsItems,Promotions}\Pages\List*` — each gained an `ExportAction` header action. Modified `App\Filament\Admin\Resources\FinancialRecords\Exports\FinancialRecordExporter`, `App\Filament\Admin\Resources\ActionJournal\Exports\ActionJournalExporter`, `App\Filament\Admin\Pages\Exports\StatDailyExporter` — converted to `ReadsTransferableRegistry` readers per the Notes above. `resources/lang/{en,ru}/panel.php` gained a `data_transfer.export` block (`actions.trigger`, `actions.download_json`, `notifications.completed`), key-symmetric across both languages (verified programmatically). New `tests/Feature/Admin/EntityExportTest.php` (7 cases, listed above).

### [T-7B02] Financial and personal-data export permissions, and journalling of every export

- **Spec:** l1-back-office.md §5.7, §5.2
- **Status:** Done
- **Assignment:** Agent
- **Verify:** `docker compose exec app php -d memory_limit=1G vendor/bin/pest tests/Feature/Admin/ExportPermissionTest.php` — 6 cases, 45 assertions: an actor holding only `user.export` receives a real, completed owners export (not a 403) with `name`/`email`/`phone`/`company` absent from the header while `id`/`country_id`/`locale`/`blocked_at` remain; granting `personal_data_access` restores them; the identical pattern for `commerce.export` on placement packages and `financial_access` narrowing `price`/`paid_bump_price`; exactly one `data_exported` journal entry per export naming the actor, the kind, and the row count; a country-filtered export's journal entry records that same filter. `composer analyse` (PHPStan level 8, full project scope, 678 files): 0 errors. `pint --test`: clean. Full architecture suite: 8/8, including `ContainmentTest`. `T-7B01`'s own `EntityExportTest` re-run clean (no regression from the narrowing hook). `php artisan migrate:fresh --seed`: applies cleanly from empty. Full non-slow suite: 782 passed (up from 776), 0 failed, 3 skipped — exactly the 6 new cases.
- **Handoff:** Swept holistically by `T-7T02`.
- **Notes:** Narrowing happens at `ReadsTransferableRegistry::getVisibleColumns()` — a real override point on Filament's own `Exporter` base class (confirmed by reading `vendor/filament/actions/src/Exports/Exporter.php`: both the column-selection modal and the column map actually written into the artefact read through this method, never `getColumns()` directly), so a restricted column is omitted from the artefact outright rather than merely hidden in a form — a request cannot smuggle it back in by supplying its own column map. `narrowColumnsForActor()` drops a kind's `personalDataColumnNames()`/`financialColumnNames()` (both already exposed by the registry from `T-7A01`) unless the current actor holds `personal_data_access`/`financial_access` respectively — never a whole-export refusal, matching the specification's own distinction between "may move this resource's rows" and "may move this resource's sensitive columns". Journalling piggybacks on `modifyCompletedNotification()`, a hook Filament's own `ExportCompletion::handle()` already calls exactly once per completed export while building the completion notification — reused as the journalling seam rather than adding a second lifecycle hook to keep in sync with Filament's own. The filter set is read back from `Export::getOptions()['filters']`, which Filament's own `ExportAction` already captures from the triggering Livewire table into the export's options payload and carries through its job chain — nothing new had to be threaded through to make the filter set available at journal time.
- **Changes:** Modified `App\Filament\Admin\Exports\Concerns\ReadsTransferableRegistry` — added `getVisibleColumns()` (Filament's real override point), `narrowColumnsForActor()`, `currentActor()`, `modifyCompletedNotification()`, and `journalExport()`; every exporter built on this trait (from `T-7B01`) gains the narrowing and journalling automatically, with no per-exporter change needed. New `tests/Feature/Admin/ExportPermissionTest.php` (6 cases, listed above).

### [T-7C01] Scheduled off-server backups — database daily, media separately, retained generations, integrity verification

- **Spec:** l1-back-office.md §5.6; l2-tech-stack.md §5.10
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `docker compose exec app php -d memory_limit=1G vendor/bin/pest tests/Feature/Operations/BackupScheduleTest.php` — the scheduler registers both backups on their stated cadences; the destination disk is neither the application's local disk nor the media disk; retention keeps the stated generation count and prunes beyond it; a corrupted artefact fails the integrity check.
- **Handoff:** Gates `T-7C02`, `T-7C03`, `T-7T01`. Shares the disk-naming contract with `T-7D01`.
- **Notes:** `spatie/laravel-backup` is named in the project's package list and is not yet installed. Scheduled work belongs in jobs dispatched by the scheduler, never in a web request.

### [T-7C02] Backup administration — last backup date, manual run, log, technical report, failure notification

- **Spec:** l1-back-office.md §5.6
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `docker compose exec app php -d memory_limit=1G vendor/bin/pest tests/Feature/Admin/BackupAdministrationTest.php` — the page renders the last artefact's real timestamp; the manual action dispatches the job; a simulated failure raises exactly one administrator notification; the technical report downloads; a stale artefact renders as a warning state.
- **Handoff:** None within the phase.
- **Notes:** Failure notification goes through the existing notification model, not a new channel. Every label through a translation key.

### [T-7C03] Administrator-triggered restore behind re-authentication

- **Spec:** l1-back-office.md §5.6
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `docker compose exec app php -d memory_limit=1G vendor/bin/pest tests/Feature/Admin/BackupRestoreTest.php` — refused without fresh re-authentication; the confirmation names the artefact's timestamp; a non-administrator cannot reach the action at Policy level; both outcomes journalled.
- **Handoff:** `T-7T01` rehearses this path against a real artefact.
- **Notes:** Filament's native multi-factor support is already wired to the panel; re-authentication should build on it rather than introduce a parallel mechanism.

### [T-7D01] Production object storage and the CDN in front of both application and media

- **Spec:** l2-third-party-integrations.md §5.1, §5.2
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `docker compose exec app php -d memory_limit=1G vendor/bin/pest tests/Feature/Operations/StorageProvisioningTest.php` — media URLs resolve through the configured CDN host when set and the origin when not; a scan asserts no credential in any committed file; backup and media disks resolve to different destinations. Runbook step recorded in `docs/`.
- **Handoff:** Shares the disk-naming contract with `T-7C01`.
- **Notes:** The interface does not change — the platform was already built against `s3`. This is configuration surface, cache/TLS posture and documentation, not new storage code.

### [T-7D02] Production SMTP and error tracking, including queue and scheduler failures

- **Spec:** l2-third-party-integrations.md §5.4, §5.8
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `docker compose exec app php -d memory_limit=1G vendor/bin/pest tests/Feature/Operations/ErrorTrackingTest.php` — a failed queued job produces a captured event through a faked transport; a payload carrying an owner telephone number is scrubbed before transmission; mail resolves to Mailpit locally and the configured relay otherwise.
- **Handoff:** None within the phase.
- **Notes:** Queue and scheduler capture is the point — the backup, rollup, sweep and import jobs all run there. Administrator-editable templates stay in the portal's own notification model, not the provider's.

### [T-7D03] Horizon for queues and the scheduler; Pulse for production visibility

- **Spec:** l2-tech-stack.md §5.4, §5.9, §5.10
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `docker compose exec app php -d memory_limit=1G vendor/bin/pest tests/Feature/Operations/QueueTopologyTest.php` (every dispatched job maps to a declared queue; both dashboards refuse unauthenticated and non-staff requests) **and** `docker compose exec app php -d memory_limit=1G vendor/bin/pest --group=slow tests/Feature/Public/PublicPerformanceBudgetTest.php` green at its existing ≤30-query ceiling with Pulse enabled.
- **Handoff:** `T-7T03` measures under this configuration.
- **Notes:** `docker-compose.yml`'s worker service is annotated as awaiting Horizon. The territory page has zero query headroom — configure Pulse's recorders against that ceiling inside this task, not after.

### [T-7T01] Rehearsed restore — a real artefact restored into an empty database, and the runbook that records it

- **Spec:** l1-back-office.md §5.6; l2-tech-stack.md §5.9
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `docker compose exec app php -d memory_limit=1G vendor/bin/pest --group=slow tests/Feature/Operations/RestoreRehearsalTest.php` — seed, back up, drop to empty, restore, assert row-count parity across the principal tables plus a field-by-field comparison of one sampled object with its translations and media rows. The `docs/` runbook section is part of the deliverable.
- **Handoff:** Phase completion gate.
- **Notes:** The value is in what the rehearsal discovers — the undocumented manual step, the separately-restoring media, the elapsed time. Record what actually happened, not the intended procedure.

### [T-7T02] Import and export invariants — round trip, never an automatic merge, never an unpermitted column

- **Spec:** l1-back-office.md §5.7
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `docker compose exec app php -d memory_limit=1G vendor/bin/pest tests/Feature/Operations/DataTransferInvariantTest.php` — registry-driven: for every declared kind, export → re-import → assert field parity; plus a sweep asserting zero automatic merges and zero unpermitted columns across all kinds.
- **Handoff:** Phase completion gate.
- **Notes:** Registry-driven so a kind added later without a round-trip-safe mapping fails rather than shipping silently — the same construction the indexation invariant used against the route registry.

### [T-7T03] Load test against catalog and territory pages at seeded volume

- **Spec:** l2-tech-stack.md §5.9
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `docker compose exec app php artisan bench:run --scenario=load --report=storage/app/benchmarks/phase-7-load.json` inside the container, producing a committed report with per-surface p50/p95 and query counts; plus `docker compose exec app php -d memory_limit=1G vendor/bin/pest --group=slow tests/Feature/Public/PublicPerformanceBudgetTest.php` green on its deterministic query-count assertions.
- **Handoff:** Phase completion gate. A search p95 over 300 ms is the stated escalation trigger to Typesense — report it plainly enough to make that call.
- **Notes:** `RunBenchmarks` already exists as a console entry point. Measure and report wall-clock; assert only query counts. Run inside the container, never against the Windows bind mount.

### [T-7T04] Coverage floor — backfill the two services holding `composer quality` below its own gate

- **Spec:** l2-tech-stack.md §5.9
- **Status:** Todo
- **Assignment:** Agent
- **Verify:** `docker compose exec app composer test:coverage` — passes its configured 80% minimum, with `PromotionLifecycleService` and `NewsItemLifecycleService` each above it individually rather than the total dragged over by unrelated code.
- **Handoff:** Phase completion gate.
- **Notes:** Pre-existing debt from the commerce and content work, not a regression introduced here. Independent of every other task in the phase; may run at any point. Behavioural tests — publication, withdrawal, archival, scheduled transitions — not coverage padding.
