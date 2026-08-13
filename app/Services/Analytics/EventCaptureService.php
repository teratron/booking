<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Jobs\CaptureStatEventJob;
use App\Support\Analytics\StatEventKind;
use App\Support\Analytics\TrafficSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The single entry point every tracked interaction funnels through — card
 * view, page view, photo view, each contact-channel click, banner
 * impression, banner click. Capture is fire-and-forget by contract: it
 * queues one {@see CaptureStatEventJob} and returns, and it never lets an
 * unknown `$kind`, a queue outage, or any other failure reach the caller —
 * a page render or a Livewire action must never fail because analytics
 * could not be recorded.
 */
final class EventCaptureService
{
    public function __construct(private readonly TrafficSourceRecorder $trafficSource) {}

    /**
     * Queues capture of one interaction against `$subject`.
     *
     * @param  string  $kind  one of {@see StatEventKind}'s values; an unrecognised
     *                        value is silently dropped rather than thrown — the same posture as
     *                        every other failure this method absorbs
     * @param  array{
     *     contact_channel_type_id?: int,
     *     territory_id?: int,
     *     country_id?: int,
     *     locale?: string,
     *     source?: array{referrer_url?: ?string, campaign?: ?string},
     * }  $context  the `source` key, when present, is resolved through
     *              {@see TrafficSourceRecorder} and written only on the visit's first
     *              event; every other key maps directly onto its `stat_events` column
     */
    public function capture(string $kind, Model $subject, array $context = []): void
    {
        try {
            $eventKind = StatEventKind::tryFrom($kind);

            if (! $eventKind instanceof StatEventKind) {
                return;
            }

            $now = now();
            $source = $this->resolveSource($context);

            CaptureStatEventJob::dispatch(
                kind: $eventKind->value,
                subjectType: $subject->getMorphClass(),
                subjectId: (int) $subject->getKey(),
                occurredAt: $now->toDateTimeString(),
                dedupToken: $this->dedupToken($now),
                contactChannelTypeId: $context['contact_channel_type_id'] ?? null,
                territoryId: $context['territory_id'] ?? null,
                countryId: $context['country_id'] ?? null,
                locale: $context['locale'] ?? null,
                sourceChannel: $source?->channel->value,
                sourceDomain: $source?->domain,
                sourceCampaign: $source?->campaign,
            );
        } catch (Throwable $exception) {
            // Instrumentation must never take the caller down with it — a
            // queue outage or a misconfigured connection degrades to "this
            // interaction went uncounted", never to a failed page render.
            Log::warning('Analytics event capture failed.', [
                'kind' => $kind,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array{source?: array{referrer_url?: ?string, campaign?: ?string}}  $context
     */
    private function resolveSource(array $context): ?TrafficSource
    {
        if (! array_key_exists('source', $context)) {
            return null;
        }

        return $this->trafficSource->firstTouch(
            $context['source']['referrer_url'] ?? null,
            $context['source']['campaign'] ?? null,
        );
    }

    /**
     * A coarse, rotating pseudonym — never a durable visitor identifier.
     * Hashing puts the session id beyond recovery, and bucketing time into
     * fifteen-minute windows means the token itself changes before it could
     * ever be used to link two visits together.
     */
    private function dedupToken(Carbon $now): string
    {
        $window = intdiv((int) $now->timestamp, 900);

        return hash('sha256', $this->visitorSeed().'|'.$window);
    }

    private function visitorSeed(): string
    {
        if (app()->bound('session')) {
            try {
                return (string) session()->getId();
            } catch (Throwable) {
                // No session store resolvable in this context (e.g. a
                // console command) — fall through to the stateless seed.
            }
        }

        return (string) request()->ip();
    }
}
