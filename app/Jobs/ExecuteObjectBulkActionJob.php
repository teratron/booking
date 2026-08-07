<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\Objects\ObjectBulkActionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * The queued half of a bulk object operation — dispatched when the selection
 * exceeds the configured threshold, or for the two operations that are
 * always queued regardless of size. A thin dispatcher only: every rule about
 * scope, policy, and per-record journaling lives in
 * `ObjectBulkActionService::execute()`, called here with the identical
 * arguments the inline request path would have used.
 */
final class ExecuteObjectBulkActionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  list<int>  $objectIds
     * @param  array<string, mixed>  $parameters
     */
    public function __construct(
        private readonly string $operation,
        private readonly array $objectIds,
        private readonly array $parameters,
        private readonly int $actorId,
    ) {}

    public function handle(ObjectBulkActionService $service): void
    {
        $actor = User::findOrFail($this->actorId);

        $service->execute($this->operation, $this->objectIds, $this->parameters, $actor);
    }
}
