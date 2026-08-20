# Queues, Scheduler & Observability

How queued work is supervised, how the scheduler actually fires, and how
production performance is watched — Horizon, the scheduler process, and
Pulse.

## Queue Topology

Every `ShouldQueue` job under `app/Jobs` declares its own queue name in its
constructor (`$this->onQueue('…')`) rather than falling through to the
connection's implicit `default` queue. Filament's own import/export jobs
route the same way, via `getJobQueue()` on `ObjectImporter` and the
`ReadsTransferableRegistry` trait every Filament exporter uses.

| Queue | Jobs | Why isolated |
| --- | --- | --- |
| `notifications` | `DispatchNotificationJob`, `DispatchRetryJob` | A recipient is actively waiting on delivery — shortest wait threshold, most processes relative to per-job cost. |
| `analytics` | `CaptureStatEventJob`, `AnalyticsRollupJob`, `AnalyticsCompactionJob` | Highest volume by job count (every public page interaction), lightweight per job. |
| `bulk` | `ExecuteObjectBulkActionJob`, every Filament import/export job | Long-running, spreadsheet-sized work with a generous timeout — capped at a low process count so it cannot starve the other queues' Redis connections. |
| `backups` | `DatabaseBackupJob`, `MediaBackupJob`, `DatabaseRestoreJob` | The heaviest, slowest, and most destructive jobs in the codebase — a single process, single retry (a retried restore is unsafe). |
| `default` | Every scheduled lifecycle sweep (expiry, staleness, availability confirmation, promotion/news archival, journal archival, sitemap regeneration) | Daily/hourly cadence, no urgency, ordinary retry posture. |

`config/horizon.php`'s own `defaults`/`environments` arrays are the single
source of truth for balance strategy, process counts, retries, and
timeouts per queue — this table only names the assignment and the reason.

A directory-and-reflection sweep
(`tests/Feature/Operations/QueueTopologyTest.php`) asserts every job under
`app/Jobs`, plus every Filament exporter and the object importer, declares
a queue that matches one of Horizon's own configured supervisors — a job
added later without an explicit `onQueue()` call fails that test rather
than silently landing on an undeclared queue.

## Running Horizon

`docker-compose.yml`'s `worker` service runs `php artisan horizon` — the
master supervisor process that spawns and monitors the per-queue worker
processes `config/horizon.php` declares. This replaced the project's
earlier placeholder `queue:work` command once Horizon was installed.

In production, run the same command under a process manager (systemd,
Supervisor) rather than Docker Compose's own `restart: unless-stopped`,
and deploy with `php artisan horizon:terminate` — Horizon finishes each
in-flight job before restarting, rather than killing one mid-execution.

Horizon's own metrics tab (job/queue throughput graphs) stays blank until
`horizon:snapshot` is scheduled — `routes/console.php` registers it every
five minutes, the cadence Horizon's own documentation recommends.

## Running the Scheduler

Laravel's scheduler is a set of declarations (`routes/console.php`), not a
daemon — something has to evaluate them once a minute. `docker-compose.yml`
adds a dedicated `scheduler` service running `php artisan schedule:work`,
a long-running loop equivalent to a crontab entry running `schedule:run`
every 60 seconds without requiring real cron inside the container. Every
`Schedule::job()`/`Schedule::command()` entry — the backup, rollup, sweep,
sitemap, and cleanup jobs alike — only actually fires with this service
running. Horizon supervises queues; it does not evaluate cron time, which
is why the scheduler is its own process rather than folded into the
`worker` service.

In production, either run `schedule:work` the same way, or point a real
crontab entry at `php artisan schedule:run` every minute — both are
equivalent; Compose uses `schedule:work` because it needs no host crontab.

## Running Pulse's Ingest Worker

`config/pulse.php` sets its ingest driver to `redis`, not the package's own
`storage` default — see that file's own docblock for why: the `storage`
driver writes every recorded entry straight to the database inside the
same request/response cycle a recorder observed, which would cost the
public catalog and territory pages a query each, and those pages are
tuned to their stated budget with zero headroom
(`tests/Feature/Public/PublicPerformanceBudgetTest.php`). The `redis`
driver instead pushes entries onto a Redis stream with no SQL involved.

`docker-compose.yml`'s `pulse` service runs `php artisan pulse:work`,
which drains that stream into Pulse's storage tables off the request path
entirely. Without this process running, Pulse would keep accumulating
entries in Redis and the dashboard would never show anything beyond what
`pulse:check`'s own periodic snapshot records.

## Dashboard Authorization

Both `/horizon` and `/pulse` are gated behind the identical door every
other staff surface in this codebase uses — `User::canAccessPanel()`
against the admin panel, the same permission (`admin_panel_access`) that
lets an account into the staff panel at all. Neither dashboard has its own
separate password or allow-list:

- `App\Providers\HorizonServiceProvider::gate()` defines Horizon's
  `viewHorizon` ability.
- `App\Providers\AppServiceProvider::registerPulseAuthorization()` defines
  Pulse's `viewPulse` ability.

An unauthenticated request, or a request from an authenticated account
without `admin_panel_access` (an object owner in the cabinet panel, for
instance), receives a 403 from either dashboard —
`tests/Feature/Operations/QueueTopologyTest.php` asserts both cases for
both dashboards, alongside the case of a staff account being let through.

## Query Budget Note

`Recorders\SlowQueries`, Pulse's own slow-query recorder, ignores its own
tables (`pulse_*`) by default so it never reports on itself. Combined with
the `redis` ingest driver above, enabling every one of Pulse's shipped
recorders costs the public request path nothing measurable in query count
— confirmed by re-running
`tests/Feature/Public/PublicPerformanceBudgetTest.php` with Pulse enabled
and seeing it stay at its existing ≤30-query ceiling on the territory
page.
