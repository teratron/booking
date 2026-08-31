<?php

declare(strict_types=1);

use App\Jobs\AnalyticsCompactionJob;
use App\Jobs\AnalyticsRollupJob;
use App\Jobs\ArchiveJournalEntriesJob;
use App\Jobs\AvailabilityConfirmationSweepJob;
use App\Jobs\DatabaseBackupJob;
use App\Jobs\DispatchRetryJob;
use App\Jobs\GenerateSitemapsJob;
use App\Jobs\MediaBackupJob;
use App\Jobs\NewsItemWithdrawalJob;
use App\Jobs\PlacementExpirySweepJob;
use App\Jobs\PromotionArchivalJob;
use App\Jobs\StalenessSweepJob;
use App\Jobs\SweepStaleAvailabilityJob;
use App\Services\Backup\BackupAdministrationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// A no-op whenever the portal's own auto-reset setting is off — see the
// job itself for why that stays the safe default. Daily is deliberately
// coarse: this corrects a stale badge, not a real-time state.
Schedule::job(new SweepStaleAvailabilityJob)->daily()->name('availability:sweep-stale');

// The journal is the highest-volume table after statistics — daily keeps
// the export current with whatever retention window an administrator has
// configured, rather than letting a large backlog build up between runs.
Schedule::job(new ArchiveJournalEntriesJob)->daily()->name('journal:archive');

// Warnings at each configured offset, then the configured action on
// anything actually past its ends_at — both idempotent, both gated on
// same-day notification history rather than a separate "processed" flag.
Schedule::job(new PlacementExpirySweepJob)->daily()->name('placement:sweep-expiry');

// Rolls up yesterday's raw stat_events into stat_dailies, then discards
// whatever raw rows have both aged past retention and already been rolled
// up. Compaction is ordered after the rollup — same run, later slot — so a
// day's raw rows are never eligible for deletion before its aggregate
// exists.
Schedule::job(new AnalyticsRollupJob)->daily()->name('analytics:rollup');
Schedule::job(new AnalyticsCompactionJob)->dailyAt('01:00')->name('analytics:compact');

// Flags every object whose row has gone untouched past the configured
// staleness period with a reminder — daily is deliberately more frequent
// than the (typically months-long) period itself; see the job's own
// docblock for why the schedule interval and the notification cadence are
// two separate things here.
Schedule::job(new StalenessSweepJob)->daily()->name('objects:sweep-staleness');

// Reminds an owner to reconfirm their availability status once it has gone
// unconfirmed past the configured cadence — distinct from
// SweepStaleAvailabilityJob above, which optionally auto-resets rather
// than reminds.
Schedule::job(new AvailabilityConfirmationSweepJob)->daily()->name('availability:sweep-confirmation');

// Gives a permanently-failed dispatch further attempt cycles, up to its own
// sweep-level retry budget — frequent because a channel outage worth
// retrying is worth retrying promptly, not once a day.
Schedule::job(new DispatchRetryJob)->everyFiveMinutes()->name('notifications:retry-dispatch');

// Archives a promotion once its own end date has passed — a scheduled
// sweep, never a render-time check, so an expired promotion does not stay
// live in every cache that has not yet turned over.
Schedule::job(new PromotionArchivalJob)->daily()->name('content:archive-promotions');

// Withdraws a news item from feeds once its own end date has passed —
// distinct from the promotion sweep above: the item's own detail page
// stays reachable, only its feed presence ends.
Schedule::job(new NewsItemWithdrawalJob)->daily()->name('content:withdraw-news');

// Regenerates the sitemap index and its per-language, per-entity-type
// children — a periodic schedule rather than a hook on every publish/
// unpublish/edit across five separate content-lifecycle services, since
// this artefact tolerates being briefly stale far better than it tolerates
// the coupling five separate event listeners would add. Hourly keeps that
// staleness window small without regenerating on every request.
Schedule::job(new GenerateSitemapsJob)->hourly()->name('seo:generate-sitemaps');

// Database dump, off-server and off the media bucket (see the `backups`
// disk's own docblock in config/filesystems.php), verified immediately
// after writing. Daily, because the database is the artefact with the
// shortest acceptable recovery point.
Schedule::job(new DatabaseBackupJob)->daily()->name('backup:database');

// Media is bulkier and changes on a slower rhythm than the catalog's own
// writes, so it earns its own, lighter cadence rather than sharing the
// database dump's daily one — see MediaBackupJob's own docblock for why
// this is a plain file mirror rather than Spatie's local-path zipping.
Schedule::job(new MediaBackupJob)->weekly()->name('backup:media');

// Prunes database backup generations beyond the configured retention count
// (App\Services\Backup\GenerationCountCleanupStrategy, configured as
// Spatie's own cleanup strategy in config/backup.php). Runs after the daily
// backup has had time to complete.
Schedule::command('backup:clean')->dailyAt('02:00')->name('backup:cleanup');

// Independent reachability/freshness/size/integrity check against whatever
// is actually sitting on the destination disk — catches a destination that
// silently stopped receiving artefacts, or received a corrupted one, rather
// than relying solely on the producing job's own exit status.
Schedule::command('backup:monitor')
    ->dailyAt('03:00')
    ->name('backup:monitor')
    // The administration screen reads a 15-minute-cached snapshot of the
    // destination-disk listing rather than enumerating it live on every
    // render; this daily run has just touched the same destination, so
    // bust that cache so the screen reflects it on the next visit.
    ->after(fn () => app(BackupAdministrationService::class)->forgetViewSnapshot());

// Records the point-in-time job/queue throughput sample Horizon's own
// metrics graphs read — without this scheduled, the dashboard's metrics
// tab stays permanently blank regardless of how much real queue activity
// happens, since Horizon never takes a snapshot on its own.
Schedule::command('horizon:snapshot')->everyFiveMinutes()->name('horizon:snapshot');
