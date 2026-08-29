<?php

declare(strict_types=1);

use App\Services\Analytics\TrafficSourceRecorder;
use App\Services\Localization\LanguageRegistry;
use App\Support\Analytics\TrafficSourceChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Traffic Source Recorder — Edge Branches
|--------------------------------------------------------------------------
|
| tests/Feature/Admin/TrafficSourceReportTest.php exercises a different
| class (TrafficSourceReportingService) and never touches this recorder.
| These cases fill the gaps its own happy-path coverage leaves: the two
| ways claimFirstTouch() degrades to "always first" (no bound session,
| a session store that throws) and the classify() branches that need a
| specific host to reach (own host, a social host, and the referral
| fallback after both known-host lists miss).
|
*/

test('claims first touch on every call when no session is bound to the container', function (): void {
    // Simulates a console-like context: offsetUnset() clears both the
    // binding and any resolved instance, so bound('session') genuinely
    // reports false rather than merely being unresolved.
    $container = app();
    unset($container['session']);

    $recorder = new TrafficSourceRecorder;

    $first = $recorder->firstTouch(null, null);
    $second = $recorder->firstTouch(null, null);

    // A working session would return null on this second call — proving
    // the unbound-session branch never remembers state across calls.
    expect($first)->not->toBeNull()
        ->and($second)->not->toBeNull()
        ->and($first->channel)->toBe(TrafficSourceChannel::Direct);
});

test('claims first touch on every call when the session store throws', function (): void {
    $throwingSession = new class
    {
        public function get(string $key): mixed
        {
            throw new RuntimeException('Session store misconfigured.');
        }

        public function put(string $key, mixed $value): void
        {
            throw new RuntimeException('Session store misconfigured.');
        }
    };

    app()->instance('session', $throwingSession);

    $recorder = new TrafficSourceRecorder;

    $first = $recorder->firstTouch(null, null);
    $second = $recorder->firstTouch(null, null);

    // The catch(Throwable) branch degrades to "always first", same as an
    // unbound session — a broken store must never suppress attribution.
    expect($first)->not->toBeNull()
        ->and($second)->not->toBeNull();
});

test('classifies a referrer matching the app\'s own host as internal', function (): void {
    config(['app.url' => 'https://booking.test']);

    $result = (new TrafficSourceRecorder)->firstTouch('https://booking.test/some-landing-page', null);

    expect($result)->not->toBeNull()
        ->and($result->channel)->toBe(TrafficSourceChannel::Internal)
        ->and($result->domain)->toBe('booking.test');
});

test('classifies a known social network referrer as social', function (): void {
    config(['app.url' => 'https://booking.test']);

    $result = (new TrafficSourceRecorder)->firstTouch('https://www.facebook.com/some-page', null);

    expect($result)->not->toBeNull()
        ->and($result->channel)->toBe(TrafficSourceChannel::Social)
        ->and($result->domain)->toBe('www.facebook.com');
});

test('falls back to referral for an external host matching neither search nor social lists', function (): void {
    config(['app.url' => 'https://booking.test']);

    $result = (new TrafficSourceRecorder)->firstTouch('https://an-independent-travel-blog.test/article', null);

    expect($result)->not->toBeNull()
        ->and($result->channel)->toBe(TrafficSourceChannel::Referral)
        ->and($result->domain)->toBe('an-independent-travel-blog.test');
});

/*
|--------------------------------------------------------------------------
| Language Registry — Unreachable Table
|--------------------------------------------------------------------------
|
| AppServiceProvider seeds the container-bound singleton during boot, so
| resolving LanguageRegistry from the container would already carry a
| cached primary locale and never reach the query at all. A fresh instance
| is required to exercise primaryLocale()'s own query and its fallback.
|
*/

test('falls back to the static fallback locale when the languages table is unreachable', function (): void {
    config(['app.fallback_locale' => 'en']);

    // Schema::drop() refuses outright — dozens of tables carry a locale FK
    // into this one. CASCADE drops those constraints along with the table;
    // RefreshDatabase's own transaction wrapper (Postgres DDL is
    // transactional) undoes all of it when this test ends regardless.
    DB::statement('DROP TABLE languages CASCADE');

    expect((new LanguageRegistry)->primaryLocale())->toBe('en');
});
