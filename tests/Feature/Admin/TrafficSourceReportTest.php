<?php

declare(strict_types=1);

use App\Filament\Admin\Pages\Exports\StatDailyExporter;
use App\Models\Object_;
use App\Models\StatDaily;
use App\Models\StatEvent;
use App\Services\Analytics\TrafficSourceReportingService;
use App\Support\Analytics\TrafficSourceChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Traffic-Source Reporting — Back Office
|--------------------------------------------------------------------------
|
| `TrafficSourceRecorder` already records a coarse channel on a visit's
| first event; nothing surfaced it portal-wide until now. The report is
| always a channel count, never a per-visitor row — the same coarse shape
| the recorder itself exists to enforce.
|
*/

function trafficSourceEvent(string $channel, ?string $occurredAt = null): void
{
    DB::table('stat_events')->insert([
        'kind' => 'object_page_view',
        'subject_type' => Object_::class,
        'subject_id' => 1,
        'occurred_at' => $occurredAt ?? now(),
        'dedup_token' => (string) Str::uuid(),
        'source_channel' => $channel,
    ]);
}

it('aggregates all six channels correctly, zero-filled where nothing was recorded', function (): void {
    trafficSourceEvent(TrafficSourceChannel::Direct->value);
    trafficSourceEvent(TrafficSourceChannel::Direct->value);
    trafficSourceEvent(TrafficSourceChannel::Search->value);
    trafficSourceEvent(TrafficSourceChannel::Campaign->value);

    $breakdown = app(TrafficSourceReportingService::class)->byChannel();

    expect($breakdown)->toBe([
        'direct' => 2,
        'search' => 1,
        'social' => 0,
        'referral' => 0,
        'internal' => 0,
        'campaign' => 1,
    ]);
});

it('reports the internal channel distinctly from referral', function (): void {
    trafficSourceEvent(TrafficSourceChannel::Internal->value);
    trafficSourceEvent(TrafficSourceChannel::Internal->value);
    trafficSourceEvent(TrafficSourceChannel::Internal->value);
    trafficSourceEvent(TrafficSourceChannel::Referral->value);

    $breakdown = app(TrafficSourceReportingService::class)->byChannel();

    expect($breakdown['internal'])->toBe(3)
        ->and($breakdown['referral'])->toBe(1)
        ->and($breakdown['internal'])->not->toBe($breakdown['referral']);
});

it('respects the period filter, excluding events outside the window', function (): void {
    trafficSourceEvent(TrafficSourceChannel::Social->value, now()->subDays(30)->toDateTimeString());
    trafficSourceEvent(TrafficSourceChannel::Social->value, now()->subDays(2)->toDateTimeString());

    $breakdown = app(TrafficSourceReportingService::class)->byChannel([
        'period_from' => now()->subDays(10)->toDateString(),
        'period_until' => now()->toDateString(),
    ]);

    expect($breakdown['social'])->toBe(1);
});

it('never returns anything but a fixed six-channel count — no per-visitor row reachable', function (): void {
    trafficSourceEvent(TrafficSourceChannel::Direct->value);
    trafficSourceEvent(TrafficSourceChannel::Direct->value);

    DB::enableQueryLog();
    $breakdown = app(TrafficSourceReportingService::class)->byChannel();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    DB::flushQueryLog();

    expect($breakdown)->toHaveCount(6)
        ->and(array_keys($breakdown))->toBe(array_map(fn (TrafficSourceChannel $c): string => $c->value, TrafficSourceChannel::cases()));

    $trafficQuery = collect($queries)->first(fn (array $q): bool => str_contains((string) $q['query'], 'stat_events'));

    expect($trafficQuery)->not->toBeNull()
        ->and($trafficQuery['query'])->toContain('group by');

    // The one back-office export action exports the aggregate StatDaily
    // model only — no export in this codebase is bound to raw StatEvent
    // rows, so no export path can ever return one.
    expect(StatDailyExporter::getModel())->toBe(StatDaily::class)
        ->and(StatDailyExporter::getModel())->not->toBe(StatEvent::class);
});
