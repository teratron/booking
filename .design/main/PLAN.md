# Implementation Plan

**Version:** 2.0.0
**Generated:** 2026-08-05
**Based on:** .design/main/INDEX.md v2.1.0
**Status:** Awaiting generation

## Overview

Delivery plan for the international tourism portal — a self-hosted Laravel 13 monolith
serving visitors (browse and contact object owners), object owners (self-service
cabinet), and portal staff (back office), across three countries and two launch
languages.

**This plan has not yet been generated.** The specification set was re-baselined
against the client technical specification and then re-targeted onto an approved stack
change (Laravel 13 + Filament 5 + PostgreSQL/PostGIS + Redis). All 23 specifications
are `RFC` pending review; planning begins once that review completes.

The previous plan — six phases delivered against a Next.js/TypeScript implementation of
the superseded hotel-booking product — is archived at
[archives/PLAN-v1-nextjs.md](archives/PLAN-v1-nextjs.md) with its task ledger at
[archives/TASKS-v1-nextjs.md](archives/TASKS-v1-nextjs.md). That implementation is
preserved at git tag `v0.1.34`.

## Blocking Gates

Two gates sit ahead of backend development and are not planning items — they are
decisions and approvals.

| Gate | Owner | Status | Blocks |
| --- | --- | --- | --- |
| Specification review (23 RFC documents) | Project | Open | All planning |
| `[TZ]` §98 — client approval of the final database structure | Client | Open | All backend work |

`[TZ]` §98 is explicit: *"Окончательная структура базы данных утверждается заказчиком
до начала основной backend-разработки."* The deliverable status is tracked in
[l2-data-model.md](specifications/l2-data-model.md) §5.7 — five of nine items complete,
three requiring column-level elaboration.

The deployment fork that previously blocked the backup-scheme deliverable is **closed**:
self-hosted ([l2-tech-stack.md](specifications/l2-tech-stack.md) §5.10).

## Client-Stated Delivery Stages

`[TZ]` §23, recorded in [l1-platform-foundation.md](specifications/l1-platform-foundation.md) §5.4:

```plaintext
1. Проектирование   Specification              ← current; 23 specs at RFC
2. Дизайн           Visual design              ← Figma source exists
3. Backend                                     ← gated by [TZ] §98
4. Frontend
5. Личные кабинеты  Owner cabinet              -> Filament cabinet panel
6. Админпанель      Back office                -> [TZ] §134 priority order
7. SEO
8. Тестирование
9. Наполнение       Content population         ← import pipeline is release-one scope
10. Запуск          Launch
```

## Next Step

Review the RFC specification set, then run `/magic.task main` to generate phases
against it.

Recommended reading order:

1. [l1-platform-foundation.md](specifications/l1-platform-foundation.md) §5.3 — what changed and why
2. [l2-tech-stack.md](specifications/l2-tech-stack.md) §1, §5.5 — stack rationale and package set
3. [l2-data-model.md](specifications/l2-data-model.md) §5.7 — the client approval gate
4. [l1-back-office.md](specifications/l1-back-office.md) §5.8 — first-release scope per `[TZ]` §134
