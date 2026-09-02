# Booking

Operational documentation for the portal.

## Setup & Installation (`setup/`)

Plain-language, no-assumed-background guides for getting the portal running
the first time — on a laptop or on a server, with Docker or without — in
English and Russian. Start at
[`setup/README.md`](setup/README.md).

| Guide | English | Русский |
| --- | --- | --- |
| Start here — what this is, which guide to follow | [`setup/en/overview.md`](setup/en/overview.md) | [`setup/ru/overview.md`](setup/ru/overview.md) |
| Local, with Docker | [`setup/en/local-with-docker.md`](setup/en/local-with-docker.md) | [`setup/ru/local-with-docker.md`](setup/ru/local-with-docker.md) |
| Local, without Docker | [`setup/en/local-without-docker.md`](setup/en/local-without-docker.md) | [`setup/ru/local-without-docker.md`](setup/ru/local-without-docker.md) |
| Production, with Docker (the designed path) | [`setup/en/production-with-docker.md`](setup/en/production-with-docker.md) | [`setup/ru/production-with-docker.md`](setup/ru/production-with-docker.md) |
| Production, without Docker | [`setup/en/production-without-docker.md`](setup/en/production-without-docker.md) | [`setup/ru/production-without-docker.md`](setup/ru/production-without-docker.md) |
| First run, sign-in, everyday tasks | [`setup/en/first-run-and-daily-tasks.md`](setup/en/first-run-and-daily-tasks.md) | [`setup/ru/first-run-and-daily-tasks.md`](setup/ru/first-run-and-daily-tasks.md) |
| Troubleshooting the setup | [`setup/en/troubleshooting.md`](setup/en/troubleshooting.md) | [`setup/ru/troubleshooting.md`](setup/ru/troubleshooting.md) |

The English and Russian copy of each file carries the same name and the same
steps — change one, change the other in the same edit.

## System Runbooks

Developer audience, English, technical — how the system itself is built and
operated.

- [`database-schema.md`](database-schema.md) — the applied schema, generated from migrations.
- [`backups.md`](backups.md) — scheduled backups, retention, and integrity verification.
- [`restore-rehearsal.md`](restore-rehearsal.md) — a real artefact restored into an empty database: what was rehearsed, what it discovered, and how long it took.
- [`production-provisioning.md`](production-provisioning.md) — the object storage provider switch and the CDN in front of the application and media bucket.
- [`mail-and-error-tracking.md`](mail-and-error-tracking.md) — the SMTP relay switch, and error tracking across web requests, queued jobs, and the scheduler.
- [`queues-and-observability.md`](queues-and-observability.md) — Horizon's queue topology, the scheduler process, Pulse's ingest worker, and dashboard authorization.
- [`php-fpm-capacity.md`](php-fpm-capacity.md) — the web-facing worker pool's concurrency ceiling, what shipped by default, and how to size it for a real instance.

## Release (`release/`)

Developer audience, English — how a change reaches production, and back.

- [`release/branching.md`](release/branching.md) — the five Git Flow lines, the rules each carries, and why the merge-back obligation is a detector rather than a gate.
- [`release/pipeline.md`](release/pipeline.md) — both GitHub Actions workflows: what each job proves, how to change one, and what breaks if a step is skipped.

## Operations (`operations/`)

The same six procedures — deploy, roll back, restore from backup, rotate a
credential, run a scheduled job by hand, read a failed pipeline — in three
renderings. Each rendering holds the identical file names, checked by an
automated parity test and a pull-request gate, so the set below never drifts
out of sync with what actually exists on disk.

| Procedure | English | Russian | Agent |
| --- | --- | --- | --- |
| Deploy a release | [`operations/en/deploy.md`](operations/en/deploy.md) | [`operations/ru/deploy.md`](operations/ru/deploy.md) | [`operations/agent/deploy.prompt.md`](operations/agent/deploy.prompt.md) |
| Roll back | [`operations/en/rollback.md`](operations/en/rollback.md) | [`operations/ru/rollback.md`](operations/ru/rollback.md) | [`operations/agent/rollback.prompt.md`](operations/agent/rollback.prompt.md) |
| Restore from backup | [`operations/en/restore.md`](operations/en/restore.md) | [`operations/ru/restore.md`](operations/ru/restore.md) | [`operations/agent/restore.prompt.md`](operations/agent/restore.prompt.md) |
| Rotate a credential | [`operations/en/rotate-credentials.md`](operations/en/rotate-credentials.md) | [`operations/ru/rotate-credentials.md`](operations/ru/rotate-credentials.md) | [`operations/agent/rotate-credentials.prompt.md`](operations/agent/rotate-credentials.prompt.md) |
| Run a scheduled job by hand | [`operations/en/run-scheduled-job.md`](operations/en/run-scheduled-job.md) | [`operations/ru/run-scheduled-job.md`](operations/ru/run-scheduled-job.md) | [`operations/agent/run-scheduled-job.prompt.md`](operations/agent/run-scheduled-job.prompt.md) |
| Read a failed pipeline | [`operations/en/read-a-failed-pipeline.md`](operations/en/read-a-failed-pipeline.md) | [`operations/ru/read-a-failed-pipeline.md`](operations/ru/read-a-failed-pipeline.md) | [`operations/agent/read-a-failed-pipeline.prompt.md`](operations/agent/read-a-failed-pipeline.prompt.md) |

- **English and Russian** — plain language, no assumed technical background. Use these yourself, or hand one to an AI assistant acting on your behalf.
- **Agent** — the same procedures, machine-addressed: explicit preconditions, an explicit expected outcome per step, and an explicit condition under which the agent must stop and hand back to a person. English-only by design — its reader is a model, not a person.

Whenever a change touches any cell in this table, every other rendering of
that same row must change with it in the same pull request — enforced, not
merely requested.
