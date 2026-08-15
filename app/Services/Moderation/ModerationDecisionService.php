<?php

declare(strict_types=1);

namespace App\Services\Moderation;

use App\Exceptions\ModerationDecisionRefusedException;
use App\Models\ModerationRequest;
use App\Models\NotificationType;
use App\Models\Object_;
use App\Models\User;
use App\Services\Audit\AuditJournal;
use App\Services\Localization\LanguageRegistry;
use App\Services\Notifications\NotificationDispatchService;
use App\Services\Seo\PublicUrlGenerator;
use App\Services\Seo\RedirectRegistrar;
use App\Services\Settings\SettingsRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Settles a moderation request. The published record is never touched while
 * a request is pending — approval is the only path that writes to it, and
 * rejection leaves it byte-identical, which is the entire reason moderation
 * models changes rather than records.
 *
 * Every decision writes the request's own outcome and the action journal
 * entry in the same transaction as any target mutation, so a failure never
 * leaves an approved-looking target with no journal trail, or a journal
 * entry for a write that did not happen.
 *
 * A proposed field is applied through `fill()`, which routes translated
 * attribute names to `astrotomic/laravel-translatable`'s current-locale
 * translation automatically — pinned to the portal's primary language
 * before applying, so which language a change lands in never depends on
 * the reviewing moderator's own interface language.
 */
final class ModerationDecisionService
{
    public function __construct(
        private readonly AuditJournal $journal,
        private readonly LanguageRegistry $languages,
        private readonly SettingsRepository $settings,
        private readonly NotificationDispatchService $notifications,
        private readonly RedirectRegistrar $redirects,
    ) {}

    public function approve(ModerationRequest $request, User $actor): void
    {
        $this->assertPending($request);

        DB::transaction(function () use ($request, $actor): void {
            $this->applyData($this->target($request), $request->proposed_data);
            $this->settle($request, 'approved', $actor);
            $this->journal->record('moderation_approved', $request, ['decision' => 'pending'], ['decision' => 'approved', 'section' => $request->section], $actor, ['moderation']);
            $this->notifySubmitter('moderation_approved', $request, $actor);
        });
    }

    public function reject(ModerationRequest $request, string $reason, User $actor): void
    {
        $this->assertPending($request);

        DB::transaction(function () use ($request, $reason, $actor): void {
            $request->forceFill([
                'decision' => 'rejected',
                'decided_at' => now(),
                'decided_by' => $actor->id,
                'rejection_reason' => $reason,
            ])->save();

            $this->journal->record('moderation_rejected', $request, ['decision' => 'pending'], ['decision' => 'rejected', 'reason' => $reason], $actor, ['moderation']);
            // The seeded 'moderation_rejected' template carries no reason
            // placeholder and its own wording already points the owner at
            // the cabinet for the reason, so the dispatched body is the
            // type's default template rather than a hand-assembled string —
            // embedding $reason here would mean literal, untranslated copy
            // in a user-facing notification, which the portal's own
            // localization rule forbids.
            $this->notifySubmitter('moderation_rejected', $request, $actor);
        });
    }

    public function requestRevision(ModerationRequest $request, string $comment, User $actor): void
    {
        $this->assertPending($request);

        DB::transaction(function () use ($request, $comment, $actor): void {
            $request->forceFill([
                'decision' => 'revision_requested',
                'decided_at' => now(),
                'decided_by' => $actor->id,
                'comment' => $comment,
            ])->save();

            $this->journal->record('moderation_revision_requested', $request, ['decision' => 'pending'], ['decision' => 'revision_requested', 'comment' => $comment], $actor, ['moderation']);
            $this->notifySubmitter('revision_requested', $request, $actor);
        });
    }

    /**
     * Applies only the accepted fields; the rest are neither discarded nor
     * silently dropped — they come back as a new, still-pending request so
     * the owner can revise exactly what was not accepted, with no fields
     * the original submission touched simply forgotten.
     *
     * @param  list<string>  $acceptedFields
     *
     * @throws ModerationDecisionRefusedException when the request is already decided, or the portal setting is off
     */
    public function partiallyAccept(ModerationRequest $request, array $acceptedFields, User $actor): void
    {
        $this->assertPending($request);

        if (! $this->settings->get('moderation.partial_acceptance_enabled')) {
            throw ModerationDecisionRefusedException::partialAcceptanceDisabled($request->id);
        }

        DB::transaction(function () use ($request, $acceptedFields, $actor): void {
            $proposed = $request->proposed_data;
            $accepted = array_intersect_key($proposed, array_flip($acceptedFields));
            $remainder = array_diff_key($proposed, array_flip($acceptedFields));

            if ($accepted !== []) {
                $this->applyData($this->target($request), $accepted);
            }

            $this->settle($request, 'approved', $actor);

            if ($remainder !== []) {
                ModerationRequest::create([
                    'target_type' => $request->target_type,
                    'target_id' => $request->target_id,
                    'country_id' => $request->country_id,
                    'owner_id' => $request->owner_id,
                    'section' => $request->section,
                    'previous_data' => $request->previous_data,
                    'proposed_data' => $remainder,
                    'submitted_by' => $request->submitted_by,
                    'submitted_at' => $request->submitted_at,
                    'decision' => 'pending',
                    'comment' => "Returned from partial acceptance of request #{$request->id}.",
                ]);
            }

            $this->journal->record(
                'moderation_partially_accepted',
                $request,
                [],
                ['accepted' => array_keys($accepted), 'returned' => array_keys($remainder)],
                $actor,
                ['moderation'],
            );
        });
    }

    private function target(ModerationRequest $request): Model
    {
        $target = $request->target;

        if ($target === null) {
            throw ModerationDecisionRefusedException::targetMissing($request->id);
        }

        return $target;
    }

    /** @param  array<string, mixed>  $data */
    private function applyData(Model $target, array $data): void
    {
        if (method_exists($target, 'setDefaultLocale')) {
            $target->setDefaultLocale($this->languages->primaryLocale());
        }

        $previousSlugs = $target instanceof Object_ ? $this->currentSlugsByLocale($target, array_keys($data)) : [];

        if ($target instanceof Object_) {
            $data = $this->stripClaimedSlugs($data, $previousSlugs);
        }

        $target->fill($data);
        $target->save();

        if ($target instanceof Object_) {
            $this->registerObjectSlugRedirects($target, $previousSlugs);
        }
    }

    /**
     * Drops a proposed `slug` from `$data` for any locale whose new value
     * would reuse a path an active redirect still claims — approving the
     * rest of the moderated change is not blocked by one field a submitter
     * could not have known was unavailable.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, ?string>  $previousSlugs
     * @return array<string, mixed>
     */
    private function stripClaimedSlugs(array $data, array $previousSlugs): array
    {
        foreach ($previousSlugs as $locale => $previousSlug) {
            $proposedSlug = $data[$locale]['slug'] ?? null;

            if (! is_string($proposedSlug) || $proposedSlug === $previousSlug) {
                continue;
            }

            if ($this->redirects->isClaimed($locale, "o/{$proposedSlug}")) {
                unset($data[$locale]['slug']);
            }
        }

        return $data;
    }

    /**
     * The object's own slug per locale, read before {@see self::applyData()}
     * overwrites it. `$keys` is `$data`'s own top-level key set — astrotomic's
     * `fill()` only routes the ones that name an active locale to a
     * translation row, so a non-locale key here (`address`, `attributes`, …)
     * simply resolves no slug and is dropped by {@see
     * self::registerObjectSlugRedirects()}'s own null guard.
     *
     * @param  list<string>  $keys
     * @return array<string, ?string>
     */
    private function currentSlugsByLocale(Object_ $object, array $keys): array
    {
        $slugs = [];

        foreach ($keys as $key) {
            $slugs[$key] = $object->translations()->where('locale', $key)->value('slug');
        }

        return $slugs;
    }

    /**
     * A moderated object edit is the same kind of address-changing write as
     * an admin-panel one — the flat `/o/{slug}` path this registers against
     * is {@see PublicUrlGenerator::objectUrl()}'s own
     * grammar, restated here rather than injected, since building it needs
     * nothing beyond the slug itself.
     *
     * @param  array<string, ?string>  $previousSlugs
     */
    private function registerObjectSlugRedirects(Object_ $object, array $previousSlugs): void
    {
        foreach ($previousSlugs as $locale => $previousSlug) {
            $newSlug = $object->translations()->where('locale', $locale)->value('slug');

            if ($newSlug === null) {
                continue;
            }

            $this->redirects->registerPathChange(
                $locale,
                $previousSlug !== null ? "o/{$previousSlug}" : null,
                "o/{$newSlug}",
            );
        }
    }

    private function settle(ModerationRequest $request, string $decision, User $actor): void
    {
        $request->forceFill([
            'decision' => $decision,
            'decided_at' => now(),
            'decided_by' => $actor->id,
        ])->save();
    }

    /** @throws ModerationDecisionRefusedException */
    private function assertPending(ModerationRequest $request): void
    {
        if ($request->decision !== 'pending') {
            throw ModerationDecisionRefusedException::alreadyDecided($request->id, $request->decision);
        }
    }

    /**
     * Notifies the owner who submitted $request of a just-made decision.
     * Silently does nothing when $typeKey is not a registered notification
     * type or the request no longer resolves a submitting owner — a
     * decision must never fail on account of a downstream notification gap.
     */
    private function notifySubmitter(string $typeKey, ModerationRequest $request, User $actor): void
    {
        $type = NotificationType::query()->where('key', $typeKey)->first();
        $recipient = $request->submittedBy;

        if (! $type instanceof NotificationType || ! $recipient instanceof User) {
            return;
        }

        $this->notifications->create($type, $recipient, $request, $actor);
    }
}
