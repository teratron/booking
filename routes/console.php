<?php

declare(strict_types=1);

use App\Jobs\AnalyticsCompactionJob;
use App\Jobs\AnalyticsRollupJob;
use App\Jobs\ArchiveJournalEntriesJob;
use App\Jobs\AvailabilityConfirmationSweepJob;
use App\Jobs\DispatchRetryJob;
use App\Jobs\PlacementExpirySweepJob;
use App\Jobs\PromotionArchivalJob;
use App\Jobs\StalenessSweepJob;
use App\Jobs\SweepStaleAvailabilityJob;
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
