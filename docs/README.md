# Booking

Operational documentation for the portal.

- [`database-schema.md`](database-schema.md) — the applied schema, generated from migrations.
- [`backups.md`](backups.md) — scheduled backups, retention, and integrity verification.
- [`production-provisioning.md`](production-provisioning.md) — the object storage provider switch and the CDN in front of the application and media bucket.
- [`mail-and-error-tracking.md`](mail-and-error-tracking.md) — the SMTP relay switch, and error tracking across web requests, queued jobs, and the scheduler.
- [`queues-and-observability.md`](queues-and-observability.md) — Horizon's queue topology, the scheduler process, Pulse's ingest worker, and dashboard authorization.
