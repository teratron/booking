---
phase: 7
name: "Operations & Launch Readiness"
status: Todo
subsystem: "app/Jobs, app/Filament/Admin, docker/, docs/"
requires: ["phase-2", "phase-5", "phase-6"]
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
one — the import pipeline that content population depends on, backups with a rehearsed
restore, production service provisioning, and a load test against the stated budgets.

## Atomic Checklist

Not yet decomposed. See §Scope.

## Scope

| Area | Spec |
| --- | --- |
| Import pipeline — upload, column mapping, validation, preview, report | l1-back-office.md §5.7 |
| Duplicate detection on name, phone, website, address, coordinates | l1-back-office.md §5.7 |
| Export across every listed entity, respecting active filters and permissions | l1-back-office.md §5.7 |
| Backups — schedule, retention, integrity verification, failure notification | l1-back-office.md §5.6 |
| Administrator-triggered restore behind re-authentication | l1-back-office.md §5.6 |
| Production provisioning — CDN, object storage, SMTP, error tracking | l2-third-party-integrations.md §5.1, §5.2, §5.4, §5.8 |
| Load test against catalog and territory pages | l2-tech-stack.md §5.9 |
| Laravel Pulse in production | l2-tech-stack.md §5.9 |
| Operations runbook and the documented restore procedure | l2-tech-stack.md §5.9 |

## Standing Constraints

- **Duplicates are never merged automatically.** Every merge is confirmed by an
  administrator; a merged object leaves a permanent redirect behind.
- Import runs long and must not block a request — it is a background job with a
  progress report.
- Backups write to a destination separate from the application server. A backup on the
  machine it protects is not a backup.
- **The restore procedure is rehearsed, not merely documented.** Working backups with
  an unrehearsed restore is the failure mode this requirement exists to prevent.
- Financial and personal-data export each require their own permission.
- The load test runs **before** launch, not after.

## Decomposition Trigger

Decomposed into atomic `T-7XXX` tasks by `/magic.task main` once Phase 6 completes.
