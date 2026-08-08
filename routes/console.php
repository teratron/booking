<?php

declare(strict_types=1);

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
