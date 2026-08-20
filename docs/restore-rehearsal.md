# Restore Rehearsal

Working backups with an unrehearsed restore is the failure mode this
document exists to prevent. This is a record of an actual rehearsal: a real
artefact, produced by the same `App\Services\Backup\DatabaseBackupService`
the daily schedule calls, replayed by the same
`App\Services\Backup\DatabaseRestoreService` the re-authenticated restore
screen calls, into a database dropped to genuinely empty first — not a
description of the intended procedure, but what actually happened when it
ran.

## Why a third database, never `booking` or `booking_testing`

`DatabaseRestoreService::restore()` drops and recreates the entire `public`
schema of whichever database `database.default` names, via a raw `psql`
subprocess entirely outside any Laravel transaction (see that service's own
docblock). Rehearsing it for real against either the development database or
the shared test database every other Feature test relies on would destroy
one of them outright.

The rehearsal instead provisions a fourth, genuinely disposable database —
`booking_restore_rehearsal` — for the sole duration of one test
(`tests/Feature/Operations/RestoreRehearsalTest.php`, `--group=slow`):

1. A maintenance connection to Postgres's own `postgres` database creates
   `booking_restore_rehearsal` from scratch (dropping it first if a
   previously aborted run left it behind) and enables the same three
   extensions `docker/postgres/init/*.sql` enables on the development and
   test databases (`postgis`, `pg_trgm`, `unaccent`) — the schema will not
   create without them.
2. A throwaway Laravel connection (`rehearsal`) is registered at runtime,
   cloned from the real `pgsql` connection's own host, port, and
   credentials, with only the database name changed.
3. `database.default` and `backup.backup.source.databases` are pointed at
   that connection for the test's duration, so both the schema migration and
   the backup's own dump genuinely operate against the rehearsal database,
   never against `booking_testing`.
4. The rehearsal database is migrated, seeded with three objects (English
   translations, one carrying two media rows and a second carrying a third —
   enough rows across enough tables to make row-count parity and a
   field-by-field comparison meaningful, not `DemoVolumeSeeder`'s full
   launch-scale volume), backed up for real, dropped to a genuinely empty
   `public` schema, and restored for real.
5. A `finally` block drops the rehearsal database and deletes the produced
   backup artefact from the real `backups` disk regardless of whether the
   test passed or failed, so a crashed run never leaves an orphan behind for
   the next one.

No row in this rehearsal is ever written to `booking` or `booking_testing`.

## What the rehearsal discovered

The value of rehearsing is in what it discovers, not in a green checkmark
existing. This rehearsal discovered a real, non-obvious ordering
requirement the first attempt got wrong:

**`Spatie\Backup\Commands\BackupCommand` constructor-injects a `scoped`
`Config` value object, built once from `config('backup')` at resolution
time — and Laravel's console kernel resolves (and permanently caches) every
registered Artisan command, including this one, the first time
`Artisan::call()` runs anywhere in the process.** The first version of this
test set `backup.backup.source.databases` to the rehearsal connection
immediately before calling `DatabaseBackupService::run()`, which reads
naturally — but by then `Artisan::call('migrate', ...)` had already run
earlier in the same test, which was itself the first `Artisan::call()` in
the process. That call silently resolved and cached `BackupCommand` (and
its captured `Config` snapshot) using the *original* configuration, before
the override was ever applied. Neither a later `config()` write nor an
explicit `app()->forgetInstance(Config::class)` could reach an object that
had already captured its own reference to the old value: the backup
produced a real, valid, integrity-verified archive of `booking_testing`
instead of the rehearsal database, and the parity assertions failed for a
data-mismatch reason that had nothing to do with the restore mechanism
itself.

The fix was ordering, not code: the config overrides for
`backup.backup.source.databases` and `backup.backup.name` were moved to
before the *first* `Artisan::call()` in the test (the `migrate` call), so
whichever Artisan command is resolved first captures the correct
configuration. Nothing in `App\Services\Backup\DatabaseBackupService` or
`DatabaseRestoreService` needed to change — this is a caveat for any future
code that programmatically overrides backup configuration mid-process, not
a defect in either service.

No other manual step was required. `pg_dump`/`psql` version compatibility,
the `backups` disk's own credentials, and the zip-archive dump format were
all already proven working by the backup and restore tasks that produced
these services — this rehearsal exercised that existing pipeline rather
than discovering a new gap in it.

## What was measured

Real, measured elapsed time from a full rehearsal run in this project's own
Docker environment (`docker compose exec app ...`, not the Windows host) —
not an estimate:

| Step | Elapsed |
| --- | --- |
| Provision the disposable database (create + extensions) | ~1.0 s |
| Migrate the full schema onto it | ~7.6–8.7 s |
| Seed the rehearsal fixture (3 objects, 3 translations, 3 media rows) | ~4.4–4.6 s |
| Back up for real (dump, zip, integrity-verify, upload) | ~4.0–4.5 s |
| Drop the `public` schema to genuinely empty | ~1.6–1.8 s |
| Restore for real (download, extract, schema reset, `psql` replay) | ~5.4–5.7 s |
| **Total (excluding Pest's own per-test framework bootstrap)** | **~25–27 s** |

Run twice in direct succession to confirm the whole cycle is genuinely
repeatable (the second run recreates the database cleanly from whatever the
first run's cleanup left behind): both runs landed within the ranges above,
with no drift and no leftover state between them.

This is a small, three-object fixture, not `DemoVolumeSeeder`'s launch-scale
volume — the dump-and-replay time for a production-sized database will be
materially larger. `App\Services\Backup\MediaBackupService`'s own docblock
already records that its file-by-file media mirror measured "a little over
two minutes" for 119 demo files in this same environment; a production
restore's own dominant cost is more likely to be the database dump/replay
volume than either of those fixed overheads, and should be re-measured
against realistic volume before this figure is relied on for a maintenance-
window estimate.

## Re-running the rehearsal

```
docker compose exec app php -d memory_limit=1G vendor/bin/pest --group=slow tests/Feature/Operations/RestoreRehearsalTest.php
```

Never run this against the Windows bind mount host directly, and never run
it concurrently with another heavy suite against `booking_testing` — it
does not touch that database, but it does share the same Postgres server
and the same real `backups` disk that other backup-related tests exercise.

## What a real production restore still requires beyond this rehearsal

This rehearsal proves the mechanism — the dump format, the schema-reset
step, and the `psql` replay all work end to end against a real artefact.
Two things a production restore adds that this rehearsal deliberately does
not exercise, because they are operational rather than mechanical:

- **An offline maintenance window.** `DatabaseRestoreService`'s own docblock
  notes it does not terminate other backends holding open connections to
  the target database before resetting its schema — a production restore
  is assumed to run with the application taken out of rotation first, a
  constraint this document records rather than one the service enforces on
  its own.
- **Media.** This rehearsal restores the database only, matching
  `DatabaseRestoreService`'s own scope. Media lives on its own backup
  cadence and its own restore path (`App\Services\Backup\MediaBackupService`)
  — a full disaster-recovery drill restoring both is a larger exercise than
  this one task's own scope, and is worth scheduling separately once this
  mechanism has been rehearsed on its own.
