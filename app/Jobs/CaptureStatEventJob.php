<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\StatEvent;
use App\Services\Analytics\EventCaptureService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Writes one raw `stat_events` row — the only writer for that table.
 * Dispatched exclusively from {@see EventCaptureService::capture()},
 * which computes `occurred_at` and `dedup_token` at the moment of the
 * interaction, before this job ever runs; a delayed or retried job must
 * still record the time the visitor actually acted, not the time a worker
 * picked it up.
 *
 * Deduplication is scoped to (`dedup_token`, `kind`, `subject_type`,
 * `subject_id`, `contact_channel_type_id`) rather than the token alone —
 * the token by itself only proves "same visitor, same fifteen-minute
 * window"; two genuinely different interactions in that window (viewing a
 * page, then clicking a phone number) must both count.
 */
final class CaptureStatEventJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly string $kind,
        private readonly string $subjectType,
        private readonly int $subjectId,
        private readonly string $occurredAt,
        private readonly ?string $dedupToken,
        private readonly ?int $contactChannelTypeId = null,
        private readonly ?int $territoryId = null,
        private readonly ?int $countryId = null,
        private readonly ?string $locale = null,
        private readonly ?string $sourceChannel = null,
        private readonly ?string $sourceDomain = null,
        private readonly ?string $sourceCampaign = null,
        private readonly ?string $endpoint = null,
    ) {}

    public function handle(): void
    {
        if ($this->dedupToken !== null && $this->isDuplicate()) {
            return;
        }

        StatEvent::query()->create([
            'kind' => $this->kind,
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'contact_channel_type_id' => $this->contactChannelTypeId,
            'territory_id' => $this->territoryId,
            'country_id' => $this->countryId,
            'locale' => $this->locale,
            'occurred_at' => $this->occurredAt,
            'dedup_token' => $this->dedupToken,
            'source_channel' => $this->sourceChannel,
            'source_domain' => $this->sourceDomain,
            'source_campaign' => $this->sourceCampaign,
            'endpoint' => $this->endpoint,
        ]);
    }

    /**
     * `where($column, null)` degrades to `whereNull($column)` in Laravel's
     * query builder, so this reads correctly whether
     * `contact_channel_type_id` is set or absent — no separate branch needed
     * for the nullable case.
     */
    private function isDuplicate(): bool
    {
        return StatEvent::query()
            ->where('dedup_token', $this->dedupToken)
            ->where('kind', $this->kind)
            ->where('subject_type', $this->subjectType)
            ->where('subject_id', $this->subjectId)
            ->where('contact_channel_type_id', $this->contactChannelTypeId)
            ->exists();
    }
}
