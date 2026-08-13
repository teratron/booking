<?php

declare(strict_types=1);

namespace App\Services\Advertising;

use App\Models\Object_;
use App\Models\ObjectPlacement;
use App\Models\ObjectPromotion;
use App\Models\ObjectType;
use App\Models\PlacementPackage;
use App\Models\PlacementTier;
use App\Models\PromotionLabel;
use App\Services\Placement\PlacementOrderingService;
use App\Support\Advertising\AvailabilityBadge;
use App\Support\Advertising\CardDecorationPayload;
use App\Support\Advertising\CardPosition;
use App\Support\Advertising\PromotionLabelDecoration;
use App\Support\Advertising\TierBadge;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Assembles the bounded decoration payload one catalog card is allowed to
 * render — at most one tier badge, one promotion label, and one
 * availability badge, coordinated from three independent sources
 * (`ObjectPlacement`'s tier chain, the currently-active `ObjectPromotion`
 * grant, and the object's own availability status) into the single closed
 * shape {@see CardDecorationPayload} defines. Nothing here decides catalog
 * *ordering* — {@see PlacementOrderingService} owns
 * that — this service only decides what a card is allowed to show.
 */
final class CardDecorationService
{
    /**
     * Builds the full decoration payload for one persisted object. Every
     * slot resolves independently and defaults to null the moment its own
     * chain is incomplete (no current placement, no package, no tier; no
     * active grant; no availability tracking for the object's category) —
     * an absent decoration renders as nothing, never a placeholder guess.
     *
     * Loads whatever the tier and availability chain still needs — lazy
     * loading is disabled outside production, so a caller that has not
     * already eager-loaded `placement.package.tier` and `objectType` must
     * not make this method throw; the active-grant lookup below runs its
     * own explicit, constrained query instead of reading a relation that
     * would need the same treatment.
     */
    public function forObject(Object_ $object): CardDecorationPayload
    {
        $object->loadMissing(['placement.package.tier', 'objectType']);

        return new CardDecorationPayload(
            tierBadge: $this->resolveTierBadge($object),
            promotionLabel: $this->resolveActivePromotionLabel($object),
            availabilityBadge: $this->resolveAvailabilityBadge($object),
        );
    }

    /**
     * Builds the promotion-label portion of a decoration payload directly
     * from raw attribute values, with no dependency on a persisted model or
     * a database row — this is what powers the admin form's live preview,
     * since a preview that required a saved record first would defeat the
     * "preview before saving" requirement it exists to satisfy.
     *
     * @param  array{border_colour: mixed, text_colour: mixed, background_colour: mixed, icon: mixed, position_on_card: mixed, text: mixed}  $attributes
     *
     * @throws InvalidArgumentException when position_on_card is missing or not one of the permitted positions
     */
    public function previewLabel(array $attributes): PromotionLabelDecoration
    {
        $position = $attributes['position_on_card'] ?? null;

        $position = $position instanceof CardPosition
            ? $position
            : CardPosition::tryFrom((string) $position);

        if (! $position instanceof CardPosition) {
            throw new InvalidArgumentException('A label preview requires a permitted position_on_card value.');
        }

        return new PromotionLabelDecoration(
            borderColour: (string) ($attributes['border_colour'] ?? ''),
            textColour: (string) ($attributes['text_colour'] ?? ''),
            backgroundColour: (string) ($attributes['background_colour'] ?? ''),
            icon: self::nullableString($attributes['icon'] ?? null),
            position: $position,
            text: self::nullableString($attributes['text'] ?? null),
        );
    }

    private function resolveTierBadge(Object_ $object): ?TierBadge
    {
        $placement = $object->placement;

        if (! $placement instanceof ObjectPlacement) {
            return null;
        }

        $package = $placement->package;

        if (! $package instanceof PlacementPackage) {
            return null;
        }

        $tier = $package->tier;

        if (! $tier instanceof PlacementTier) {
            return null;
        }

        return new TierBadge(
            borderColour: $tier->border_colour,
            badgeColour: $tier->badge_colour,
            icon: $tier->badge_icon,
            text: $tier->badge_text,
        );
    }

    /**
     * Reads the one `ObjectPromotion` grant, if any, whose window covers
     * today — "the label an object currently carries" is defined by that
     * window, not by the most recently created grant row.
     */
    private function resolveActivePromotionLabel(Object_ $object): ?PromotionLabelDecoration
    {
        $today = now()->toDateString();

        $grant = $object->objectPromotions()
            ->where('starts_at', '<=', $today)
            ->where('ends_at', '>=', $today)
            ->whereHas('label', fn (Builder $query): Builder => $query->where('is_active', true))
            ->with('label.translations')
            ->latest('starts_at')
            ->first();

        if (! $grant instanceof ObjectPromotion) {
            return null;
        }

        $label = $grant->label;

        if (! $label instanceof PromotionLabel) {
            return null;
        }

        return $this->previewLabel([
            'border_colour' => $label->border_colour,
            'text_colour' => $label->text_colour,
            'background_colour' => $label->background_colour,
            'icon' => $label->icon,
            'position_on_card' => $label->position_on_card,
            'text' => $label->text,
        ]);
    }

    /**
     * Availability is only ever badge-worthy for an object type that
     * declared it tracks availability in the first place — the same flag
     * `AvailabilityAdministrationService`'s own callers key off of.
     */
    private function resolveAvailabilityBadge(Object_ $object): ?AvailabilityBadge
    {
        $objectType = $object->objectType;

        if (! $objectType instanceof ObjectType || ! $objectType->has_availability_status) {
            return null;
        }

        // The column is a non-nullable, defaulted database enum — every row
        // genuinely carries one of the three statuses, never an empty value.
        return new AvailabilityBadge(status: $object->availability_status);
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = (string) $value;

        return $value === '' ? null : $value;
    }
}
