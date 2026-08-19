# Backups

Automated, scheduled, off-server backups for the database and for media,
each on its own cadence, with integrity verification and generation-based
retention. This document covers the backup side only — the rehearsed
restore procedure lives in a separate document once one has been performed.

## Destination

Both backups write to the `backups` filesystem disk
(`config/filesystems.php`), never to `local` (the application server) and
never to `s3` (the media disk this backup protects). A backup living beside
what it protects is not a backup: a lost server or a lost media bucket must
not also mean a lost backup.

Locally, `backups` is a second bucket on the same MinIO instance the media
disk already uses — a genuinely separate bucket, not a second name for the
same one. Create it once per environment:

```
docker compose exec minio mc alias set local http://localhost:9000 $AWS_ACCESS_KEY_ID $AWS_SECRET_ACCESS_KEY
docker compose exec minio mc mb local/booking-backups --ignore-existing
```

In production the two disks should resolve to different buckets and, where
practical, different providers or regions — configuring that is a
deployment concern, not a code change, since the application only ever
talks to the `backups` disk by name.

## Schedule

Registered in `routes/console.php`, alongside every other scheduled job:

| Job / command | Cadence | Purpose |
| --- | --- | --- |
| `App\Jobs\DatabaseBackupJob` | Daily | Dumps the database (`backup:run --only-db`) to the `backups` disk and verifies the resulting archive. |
| `App\Jobs\MediaBackupJob` | Weekly | Mirrors the media disk into a fresh, timestamped generation on the `backups` disk and verifies the copy. |
| `backup:clean` (Spatie) | Daily | Prunes database backup generations beyond the configured retention count. |
| `backup:monitor` (Spatie) | Daily | Independently checks the destination is reachable, recent, within its storage budget, and holds an intact artefact. |

Database and media are split because they differ in both size and restore
urgency: the database is small and changes constantly, so it backs up
daily; media is bulkier and changes more slowly, so a weekly mirror is
enough without paying a much larger, more frequent transfer cost.

## Retention

Both backup types keep a fixed number of generations and prune the rest —
configured in `config/booking.php` under `backups`:

- `database_generations_to_keep` (default 7) — enforced by
  `App\Services\Backup\GenerationCountCleanupStrategy`, wired as Spatie's
  own cleanup strategy in `config/backup.php`.
- `media_generations_to_keep` (default 5) — enforced directly by
  `App\Services\Backup\MediaBackupService::pruneGenerations()`.

Override either via `BACKUP_DATABASE_GENERATIONS_TO_KEEP` /
`BACKUP_MEDIA_GENERATIONS_TO_KEEP`.

## Integrity Verification

An artefact is never assumed sound because the write call succeeded. Two
independent checks apply:

1. **Immediately after writing** — `App\Services\Backup\DatabaseBackupService`
   and `App\Services\Backup\MediaBackupService` both verify their own output
   before returning: the database service re-reads the archive from the
   destination disk and confirms it opens as a structurally consistent zip
   (`App\Services\Backup\BackupIntegrityService`, `ZipArchive::CHECKCONS`);
   the media service compares the copied file count against the source. A
   failure here throws and fails the producing job outright.
2. **Independently, on a schedule** — `backup:monitor` re-checks the
   destination's reachability, freshness, and storage size, plus (via the
   custom `App\Services\Backup\HealthChecks\BackupArchiveIntegrityHealthCheck`)
   the same zip-consistency check against whatever the newest artefact
   happens to be at check time. This catches an artefact that degraded
   after being written, or a destination that silently stopped receiving
   backups altogether.

## Notifications

Spatie's own mail-based notification channel is deliberately left
unconfigured (`config/backup.php`'s `notifications.notifications` is empty).
Instead, `App\Listeners\NotifyAdministratorsOfBackupFailure` subscribes to
Spatie's own `BackupHasFailed` (a `backup:run` that threw) and
`UnhealthyBackupWasFound` (a `backup:monitor` finding the destination
unreachable, stale, oversized, or holding a corrupted artefact) events and
raises one `system_message` notification, through the platform's own
notification model, to every account holding the `settings_management`
permission — the same audience the administration screen below is gated to.

## Administration Screen

`App\Filament\Admin\Pages\BackupAdministration` (staff panel, under
"System") is the screen `[TZ]` §131 describes:

- The real timestamp of the last successful database and media backup,
  read live from `App\Services\Backup\BackupAdministrationService` — never
  a separately tracked date column, since that could drift from what
  actually landed on the destination disk.
- A staleness warning once the last database backup is older than
  `booking.backups.staleness_threshold_hours` (default 48, override via
  `BACKUP_STALENESS_THRESHOLD_HOURS`) — deliberately looser than the daily
  schedule itself, so one missed run does not read as an emergency the
  moment the next one is due.
- A "Run backup now" button that queues `App\Jobs\DatabaseBackupJob` — the
  same job the daily schedule dispatches — rather than running it inline.
- The database and media backup log (every retained generation, newest
  first).
- A downloadable plain-text technical report combining the destination
  disk, the latest artefacts, staleness, and the current health-check
  outcome (the same checks `backup:monitor` runs, read directly rather than
  by invoking that command).

Restoring an artefact is deliberately not offered on this screen — it is a
separate, re-authentication-gated action.
