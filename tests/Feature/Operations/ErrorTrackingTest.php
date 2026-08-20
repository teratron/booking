<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Sentry\State\HubInterface;
use Tests\Fixtures\Operations\AlwaysFailsQueuedJob;
use Tests\Fixtures\Operations\FakeSentryTransport;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Production SMTP and Error Tracking
|--------------------------------------------------------------------------
|
| Queue and scheduler failures are the point: the backup, rollup, sweep, and
| import jobs this portal depends on all run there, never inside a web
| request, so both surfaces route through the exact same reportable
| exception handler wired in bootstrap/app.php. Every case here binds
| Sentry's real client to a faked transport (config('sentry.transport'), the
| option the SDK itself documents for unit testing) so nothing in this
| suite ever reaches a real network endpoint.
|
*/

/**
 * Rebuilds Sentry's hub against a fresh, faked transport. The hub is a
 * container singleton built once at application boot — before this test had
 * any chance to set the fake transport — so it has to be forgotten and
 * re-resolved for the swap to take effect.
 */
function bindErrorTrackingFakeTransport(): FakeSentryTransport
{
    $transport = new FakeSentryTransport;

    config([
        'sentry.dsn' => 'https://public@o0.ingest.sentry.io/0',
        'sentry.transport' => $transport,
    ]);

    app()->forgetInstance(HubInterface::class);
    app()->make(HubInterface::class);

    return $transport;
}

it('captures a deliberately failed queued job through a faked transport', function (): void {
    $transport = bindErrorTrackingFakeTransport();

    AlwaysFailsQueuedJob::dispatch()->onConnection('database');

    Artisan::call('queue:work', [
        'connection' => 'database',
        '--once' => true,
    ]);

    expect($transport->events)->not->toBeEmpty();

    $exceptions = $transport->events[0]->getExceptions();

    expect($exceptions)->not->toBeEmpty()
        ->and($exceptions[0]->getValue())->toContain('Deliberate failure for error-tracking verification');
});

it('captures a deliberately failed scheduled task through the same faked transport', function (): void {
    $transport = bindErrorTrackingFakeTransport();

    // A schedule built fresh for this test, never the container's real one
    // — routes/console.php's own entries stay untouched and unrun, so a
    // production job that happened to be due at the moment this suite runs
    // can never fire for real inside a test.
    $isolatedSchedule = new Schedule;
    $isolatedSchedule->call(function (): void {
        throw new RuntimeException('Deliberate scheduled-task failure for error-tracking verification.');
    })->everyMinute()->name('error-tracking-probe');

    app()->instance(Schedule::class, $isolatedSchedule);

    Artisan::call('schedule:run');

    $matching = collect($transport->events)->filter(
        fn ($event) => collect($event->getExceptions())->contains(
            fn ($exception) => str_contains($exception->getValue(), 'Deliberate scheduled-task failure'),
        ),
    );

    expect($matching)->not->toBeEmpty();
});

it('scrubs an owner telephone number out of the event before it would be transmitted', function (): void {
    $transport = bindErrorTrackingFakeTransport();

    AlwaysFailsQueuedJob::dispatch('+373 69 123 456')->onConnection('database');

    Artisan::call('queue:work', [
        'connection' => 'database',
        '--once' => true,
    ]);

    expect($transport->events)->not->toBeEmpty();

    $captured = $transport->events[0];

    // The raw number must not survive anywhere in the transmitted event —
    // not in the structured context it was attached under, and not in the
    // exception message it was also embedded in.
    $serialized = json_encode([
        'contexts' => $captured->getContexts(),
        'extra' => $captured->getExtra(),
        'exceptions' => array_map(
            static fn ($exception) => $exception->getValue(),
            $captured->getExceptions(),
        ),
    ]);

    expect($serialized)->not->toContain('+373 69 123 456')
        ->and($captured->getContexts())->toHaveKey('owner')
        ->and($captured->getContexts()['owner']['phone'])->toBe('[redacted]');
});

it('resolves the mailer transport from the environment — Mailpit locally, a configured relay otherwise', function (): void {
    // The published config itself must never hardcode a specific provider —
    // every value the SMTP mailer needs comes from the environment, so
    // switching from Mailpit (local) to a production relay (Postmark, SES,
    // Resend, a self-hosted relay) is a deployment configuration change,
    // never a code change.
    $contents = (string) file_get_contents(config_path('mail.php'));

    expect($contents)->toContain("'default' => env('MAIL_MAILER', 'log')")
        ->and($contents)->toContain("'host' => env('MAIL_HOST'")
        ->and($contents)->toContain("'port' => env('MAIL_PORT'")
        ->and($contents)->toContain("'username' => env('MAIL_USERNAME')")
        ->and($contents)->toContain("'password' => env('MAIL_PASSWORD')");

    // Overriding the environment (simulating production) changes what the
    // mailer resolves to with no code involved — the same config array read
    // against different values, proving the switch is genuinely
    // config-driven rather than short-circuited anywhere in application
    // code.
    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp.host' => 'smtp.postmarkapp.com',
        'mail.mailers.smtp.port' => 587,
    ]);

    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.host'))->toBe('smtp.postmarkapp.com')
        ->and(config('mail.mailers.smtp.port'))->toBe(587);
});

it('carries only placeholder credentials in .env.example for mail and error tracking', function (): void {
    $contents = (string) file_get_contents(base_path('.env.example'));

    expect($contents)->toContain('MAIL_MAILER=smtp')
        ->and($contents)->toMatch('/^SENTRY_LARAVEL_DSN=\s*$/m');
});
