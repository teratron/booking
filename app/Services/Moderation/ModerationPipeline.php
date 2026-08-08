<?php

declare(strict_types=1);

namespace App\Services\Moderation;

use App\Models\ModerationRequest;
use App\Models\User;
use App\Services\Settings\SettingsRepository;
use Illuminate\Database\Eloquent\Model;

/**
 * Routes a submitted change to publication or to review.
 *
 * This is deliberately not the place that applies a change to a record —
 * "object", "news item", and "promotion" each have their own field set, and
 * teaching one pipeline every entity's shape would grow it without bound.
 * Instead the outcome is the contract: `shouldPublishImmediately` true means
 * the caller applies the change now, and an enqueued request means it must
 * not — the published record stays exactly as it was until a moderator
 * decides.
 *
 * The availability toggle is not a case this class handles. It bypasses
 * moderation by never calling this pipeline at all (`[TZ]` §27.3 requires it
 * to take effect immediately in every mode); routing it through here and
 * special-casing it would reintroduce the coupling the exemption exists to
 * avoid.
 */
final class ModerationPipeline
{
    public function __construct(
        private readonly ModerationModeResolver $modeResolver,
        private readonly SettingsRepository $settings,
    ) {}

    /**
     * @param  string  $section  the change-type key, checked against the
     *                           `moderation.moderated_change_types` setting — not a fixed enum,
     *                           so a type the portal has not opted into moderating publishes
     *                           immediately regardless of any mode configured above it
     * @param  array<string, mixed>|null  $previousData  snapshot of the published state; null when the target has none yet
     * @param  array<string, mixed>  $proposedData  the submitted change
     */
    public function submit(
        Model $target,
        string $section,
        ?array $previousData,
        array $proposedData,
        User $submittedBy,
        ?int $objectId = null,
        ?int $ownerId = null,
        ?int $categoryId = null,
        ?int $countryId = null,
    ): ModerationOutcome {
        /** @var list<string> $moderatedTypes */
        $moderatedTypes = $this->settings->get('moderation.moderated_change_types');

        if (! in_array($section, $moderatedTypes, true)) {
            return ModerationOutcome::publishImmediately();
        }

        $context = new ModerationScopeContext(
            objectId: $objectId,
            ownerId: $ownerId,
            categoryId: $categoryId,
            countryId: $countryId,
        );

        if ($this->modeResolver->resolve($context) === 'immediate') {
            return ModerationOutcome::publishImmediately();
        }

        $request = ModerationRequest::create([
            'target_type' => $target::class,
            'target_id' => $target->getKey(),
            'country_id' => $countryId,
            'owner_id' => $ownerId,
            'section' => $section,
            'previous_data' => $previousData,
            'proposed_data' => $proposedData,
            'submitted_by' => $submittedBy->id,
            'submitted_at' => now(),
            'decision' => 'pending',
        ]);

        return ModerationOutcome::enqueued($request);
    }
}
