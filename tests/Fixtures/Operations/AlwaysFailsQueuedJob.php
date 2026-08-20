<?php

declare(strict_types=1);

namespace Tests\Fixtures\Operations;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

use function Sentry\configureScope;

use Sentry\State\Scope;

/**
 * A deterministic, deliberately-failing job — the stand-in this project's
 * own error-tracking test uses for "one of the real queued jobs (backup,
 * rollup, sweep, import) throws", without depending on any of those actually
 * failing. Every real attempt exhausts on the first try, exactly like the
 * production jobs it stands in for once their own `$tries` is spent.
 *
 * When constructed with an owner phone number it attaches that number as
 * structured Sentry context before throwing — a request/job payload
 * genuinely carrying personal data, the shape the scrubbing hook exists to
 * catch, rather than a synthetic string built only to satisfy a regular
 * expression.
 */
final class AlwaysFailsQueuedJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(private readonly ?string $ownerPhone = null) {}

    public function handle(): void
    {
        if ($this->ownerPhone !== null) {
            configureScope(function (Scope $scope): void {
                $scope->setContext('owner', [
                    'phone' => $this->ownerPhone,
                    'name' => 'Test Owner',
                ]);
            });

            throw new RuntimeException("Failed to notify owner at {$this->ownerPhone}.");
        }

        throw new RuntimeException('Deliberate failure for error-tracking verification.');
    }
}
