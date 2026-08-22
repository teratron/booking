# SDD Retrospective

**Last Full Run:** 2026-08-20
**Full Sessions:** 1
**Snapshots:** 3

## Snapshots

Auto-collected after each phase completion. Lightweight metrics only — no analysis.

| Date | Phase | Specs (D/R/S) | Tasks (Done/Blocked/Cancelled) | Rules | Signal |
| --- | --- | --- | --- | --- | --- |
| 2026-07-30 | Phase 1 | 0/0/9 | 14/0/0 | 24 | 🟢 |
| 2026-08-20 | Phase 7 | 0/23/0 | 16/0/0 | 24 | 🟢 |
| 2026-08-22 | Phase 9 | 0/16/9 | 15/0/0 | 24 | 🟢 |

## Session 1 — 2026-08-20

**Scope:** Full system analysis — plan complete (7/7 phases)
**Specs in registry:** 23 (0 Draft / 23 RFC / 0 Stable)
**Tasks total:** 135 (Done: 135, Blocked: 0, Cancelled: 0) — Phase 1: 21, Phase 2: 25, Phase 3: 23, Phase 4: 16, Phase 5: 18, Phase 6: 16, Phase 7: 16
**RULES.md §7 entries:** 24

### 🚀 DORA Metrics (L2 Implementation)

| Metric | Value | Source | Details |
| --- | --- | --- | --- |
| **Deployment Frequency** | N/A | Manual | No production deployment has occurred — the plan is complete but the portal has not launched. `T-7D01`'s production storage/CDN switch and `T-7D03`'s Horizon/Pulse wiring are configuration surfaces awaiting a real environment, not yet exercised against one. |
| **Change Failure Rate** | N/A | Manual | Same reason — no deploys to measure a failure rate against yet. |

### 📊 Observations

| # | Severity | Area | Observation | Evidence |
| ---: | --- | --- | --- | --- |
| 1 | 🟡 Medium | Specification lifecycle | All 23 specifications remain `RFC` through 135 completed tasks across all 7 phases — none was ever promoted to `Stable`, including the 3 `l2-*` implementation specs that fully describe a now-built, tested, and running stack. Not shadow logic (every implemented behaviour traces to a real spec section, cited in each task's own `Spec:` field) — a specification-lifecycle gap: the registry's status field never caught up to the plan's own delivered reality. | `.design/main/INDEX.md` (23/23 RFC); every `Detailed Tracking` entry across `archives/tasks/phase-{1..6}.md` and `tasks/phase-7.md` cites a real `Spec:` section. |
| 2 | 🟡 Medium | Coverage floor debt | `composer test:coverage` sits at 78.3% against its own configured 80% floor. `T-7T04` closed its own literal, named scope (the two services the phase's own plan cited, both now 100%) but the residual gap is a long tail across ~20 pre-existing `Phase 1–6` files (`app/Policies` most heavily: 14 of 24 policy classes below 90%, several below 30%) that predates this phase and was never this phase's own debt to begin with. Confirmed with the project owner to close `T-7T04` on its literal scope rather than open-endedly chase the suite-wide number. | `tests/Feature/Admin/PromotionLifecycleServiceTest.php`, `tests/Feature/Admin/NewsItemLifecycleServiceTest.php`; `tasks/phase-7.md` `T-7T04` Notes carries the full per-file breakdown. |
| 3 | 🟡 Medium | Coverage-tooling scoping artefact | Several of Phase 7's own new backup/restore classes (`DatabaseRestoreService` 11.7%, `MediaBackupService` 40.0%, `DatabaseBackupService` 42.9%) show low coverage in the standard report specifically because their genuine end-to-end exercise lives in `--group=slow` tests (`BackupScheduleTest`, `RestoreRehearsalTest`), deliberately excluded by `composer test:coverage`'s own `--exclude-group=slow` flag. Not a testing gap — a scoping question for whoever next revises the quality-tooling composer scripts: should `test:coverage` read slow-group coverage too? | `composer.json`'s `test:coverage` script; `tasks/phase-7.md` `T-7T04` Notes. |
| 4 | 🔴 Critical (process, not product) | Workflow agent reliability | Across this plan's final phase, every multi-task `Workflow` batch's *last* dispatched agent either errored on an account-wide session-limit cap mid-run (`T-7A04`, `T-7B02`, `T-7C03`, and simultaneously `T-7T03`+`T-7T04`) or returned a stale mid-task status string as its supposed final report instead of a real completion summary (`T-7D01`, `T-7D02`). In every single case the underlying implementation on disk was independently re-verified and found complete and correct — the pattern is specifically about the *last* agent in a batch losing its own turn budget before reaching its final report and bookkeeping steps, not about code quality. This is an orchestration-reliability finding, not a specification-system one, but it shaped this phase's entire execution rhythm (five separate manual completion passes) and is worth surfacing here since no other artefact in this project records it structurally. | `.design/main/STATE.md` Blocking Constraints (session-limit note); `tasks/phase-7.md` Notes on `T-7A04`, `T-7B02`, `T-7C03`, `T-7D01`, `T-7D02`. |
| 5 | 🟡 Medium | Test-suite hygiene | One real regression was self-introduced and caught only by running the *full* suite, not the new file alone: a test file omitting `uses(RefreshDatabase::class)` wrote permanently into the shared `booking_testing` database, corrupting a fixed-registry assumption (`languages.code` unique) for 549 unrelated tests on the very next full run. A second, separate incident (an interrupted `composer test:coverage` run, killed by the wrapper's own 1800s timeout mid-process) left `booking_testing` in a state missing a table that demonstrably existed moments before and after — resolved by an explicit `migrate:fresh` reset before retrying, not a code fix. Both incidents are now recorded as Blocking Constraints for future sessions. | `.design/main/STATE.md` Blocking Constraints; `tasks/phase-7.md` `T-7C03` Notes. |
| 6 | ✨ Positive | Zero Pre-flight HALTs | All 135 tasks across 7 phases executed under the same `RFC`-status posture, and not one Pre-flight gate ever HALTed — the `[Bootstrap]`/RFC-tolerant execution path the plan committed to at Phase 1 held for the plan's entire duration, with no spec-status drift, no orphaned spec, and no phantom parent surfacing across six `/magic.task` re-plannings. | `.design/main/TASKS.md` Execution Notes ("no Pre-flight gate has ever HALTed on any of them"); `.design/main/STATE.md` Recent Decisions history. |
| 7 | ✨ Positive | Real bugs found by real verification | Across this phase alone, independently re-running each task's own Verify line and the full suite (rather than trusting a subagent's self-report) surfaced multiple genuine, otherwise-invisible bugs: Filament's own `Import::$completed_at` cast lying about its type; `objects.geom` never populated by any real write path, silently starving a duplicate-detection signal; `config('cache.serializable_classes')` defaulting to `false`, corrupting every real Redis cache hit in this codebase (invisible to the test suite's own array cache driver); a missing `pg_dump` client version match against Postgres 18. None of these were caught by static analysis or the task's own author — all were caught by actually running the real toolchain end to end. | `tasks/phase-7.md` Notes on `T-7A02`, `T-7A03`, `T-7T03`, `T-7C01`. |

### 💡 Recommendations

| # | Refs Observation | Recommendation | Target File |
| --- | --- | --- | --- |
| R1 | #1 | Run `/magic.spec` to review all 23 specifications against their now-complete, tested implementations and promote qualifying ones to `Stable` — the plan's own delivered reality has outpaced the registry's status field, and a `Stable` baseline is the correct foundation for any post-launch spec revision. | `.design/main/specifications/*.md`, `.design/main/INDEX.md` |
| R2 | #2 | Scope a dedicated, cross-phase coverage-improvement task (not folded into any single phase) targeting `app/Policies` and the low-coverage `app/Models` identified in `T-7T04`'s own Notes — real behavioural tests, not padding, following the same standard `T-7T04` itself just held to. | New task, own workspace scope — not Phase 7's |
| R3 | #3 | Decide explicitly whether `composer test:coverage` should include `--group=slow` tests in its own coverage accounting, given several real, well-tested classes are only exercised there. Either change the script's own flags, or record the exclusion as a deliberate, documented trade-off in `CLAUDE.md`'s own Engineering Discipline section so it stops reading as an open question each time coverage is measured. | `composer.json`, `CLAUDE.md` |
| R4 | #4 | If the session-limit cap is adjustable account-side, consider it for future multi-task `Workflow` batches; failing that, prefer smaller batches (2 tasks, not 3–4) for the *last* track of a plan specifically, since a batch's final agent is the one most exposed to a mid-batch cap. Independently, treat any agent's final report that reads as a mid-task status ("waiting for...", "still running...") as an automatic re-verification trigger rather than trusting it — this held throughout this phase and caught two genuine cases before they were mistaken for completion. | Workflow orchestration practice — no single engine file owns this |
| R5 | #5 | Add "reset `booking_testing` before the first coverage/full-suite run after any process was killed mid-run" as an explicit step in whatever documents this project's local verification routine (already partially captured in `STATE.md` Blocking Constraints) — the failure mode (a table that exists moments later reads as missing mid-run) is confusing enough to misdiagnose as a code regression without that context already in hand. | `.design/main/STATE.md` (already updated this session) |

### 📈 Trends (from Snapshots)

| Metric | Previous Snapshot | Current | Δ |
| --- | --- | --- | --- |
| Specs in registry | 9 (Phase 1) | 23 | +14 |
| Blocked task rate | 0% | 0% | 0% |
| Signal | 🟢 | 🟢 | → |
