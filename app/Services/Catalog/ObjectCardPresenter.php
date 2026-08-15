<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\ContactChannel;
use App\Models\ContactChannelType;
use App\Models\Object_;
use App\Models\ObjectType;
use App\Models\PlacementTier;
use App\Models\Room;
use App\Models\StatDaily;
use App\Services\Contact\ContactChannelLinkResolver;
use App\Support\Analytics\StatEventKind;
use App\Support\Catalog\ObjectCardContactAction;
use App\Support\Catalog\ObjectCardViewModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Resolves one {@see Object_} into the read model the card component
 * renders. Every field here reads an existing relation or aggregate;
 * nothing is computed by re-deriving state another service already owns
 * (placement tier, contact deep links, review aggregation).
 *
 * A handful of card fields are genuinely absent from most objects at this
 * point in the codebase's own life (no visitor-facing route to the object
 * profile page exists until a later task, no price row until an owner
 * enters one) — every one of those degrades to null rather than a
 * fabricated placeholder, and the card component itself is responsible for
 * omitting what it is given nothing for.
 */
final class ObjectCardPresenter
{
    /** Capped for a compact card row, not the object's full amenity list. */
    private const int MAX_KEY_SERVICES = 3;

    /** Capped for a compact card row, not the object's full contact list. */
    private const int MAX_CONTACT_ACTIONS = 3;

    public function __construct(
        private readonly ContactChannelLinkResolver $contactLinks,
    ) {}

    public function present(Object_ $object): ObjectCardViewModel
    {
        $attributes = $object->getAttributes();
        $hasBatchedTier = array_key_exists(CatalogQueryService::CARD_TIER_BADGE_TEXT_KEY, $attributes);

        $relations = ['translations', 'objectType', 'territory.translations', 'amenities.translations', 'contactChannels.contactChannelType.translations'];

        if (! $hasBatchedTier) {
            $relations[] = 'placement.package.tier';
        }

        $object->loadMissing($relations);

        [$ratingAverage, $reviewCount] = $this->reviewSummary($object);
        [$priceAmount, $priceCurrency] = $this->priceFrom($object);
        [$tierBadgeText, $tierBadgeColour, $tierBorderColour] = $this->tierBadge($object, $hasBatchedTier, $attributes);

        return new ObjectCardViewModel(
            objectId: $object->id,
            name: (string) ($object->name ?? ''),
            coverPhotoUrl: $this->coverPhotoUrl($object),
            settlement: (string) ($object->territory->name ?? ''),
            shortDescription: $object->short_description,
            keyServices: $this->keyServices($object),
            ratingAverage: $ratingAverage,
            reviewCount: $reviewCount,
            viewCount: $this->viewCount($object),
            tierBadgeText: $tierBadgeText,
            tierBadgeColour: $tierBadgeColour,
            tierBorderColour: $tierBorderColour,
            availabilityStatus: $this->availabilityStatus($object),
            priceFromAmount: $priceAmount,
            priceCurrency: $priceCurrency,
            detailsUrl: $this->detailsUrl($object),
            contactActions: $this->contactActions($object),
        );
    }

    /**
     * Reads {@see CatalogQueryService::attachCardAggregates()}'s own
     * page-wide tier batch when present, the same fallback shape
     * {@see reviewSummary()}, {@see viewCount()}, and {@see priceFrom()}
     * use — falling back to the live `placement.package.tier` relation
     * chain otherwise.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{0: ?string, 1: ?string, 2: ?string}
     */
    private function tierBadge(Object_ $object, bool $hasBatchedTier, array $attributes): array
    {
        if ($hasBatchedTier) {
            return [
                $attributes[CatalogQueryService::CARD_TIER_BADGE_TEXT_KEY],
                $attributes[CatalogQueryService::CARD_TIER_BADGE_COLOUR_KEY],
                $attributes[CatalogQueryService::CARD_TIER_BORDER_COLOUR_KEY],
            ];
        }

        $tier = $object->placement?->package?->tier;

        return [
            $tier instanceof PlacementTier ? $tier->badge_text : null,
            $tier instanceof PlacementTier ? $tier->badge_colour : null,
            $tier instanceof PlacementTier ? $tier->border_colour : null,
        ];
    }

    private function coverPhotoUrl(Object_ $object): ?string
    {
        $primary = $object->getMedia('photos')
            ->first(fn ($media): bool => (bool) $media->getCustomProperty('is_primary', false));

        $photo = $primary ?? $object->getFirstMedia('photos');

        return $photo?->getUrl('card');
    }

    /** @return list<array{iconPath: ?string, label: string}> */
    private function keyServices(Object_ $object): array
    {
        return array_values($object->amenities
            ->take(self::MAX_KEY_SERVICES)
            ->map(fn ($amenity): array => ['iconPath' => $amenity->icon_path, 'label' => (string) ($amenity->name ?? '')])
            ->all());
    }

    /**
     * Reads {@see CatalogQueryService::attachCardAggregates()}'s own
     * page-wide batch when the object came from that call — every real
     * caller does — falling back to a live per-object query otherwise, so
     * this stays correct for a caller that hands the presenter a bare
     * object outside that path.
     *
     * @return array{0: ?float, 1: int}
     */
    private function reviewSummary(Object_ $object): array
    {
        $attributes = $object->getAttributes();

        if (array_key_exists(CatalogQueryService::CARD_REVIEW_AVERAGE_KEY, $attributes)) {
            return [$attributes[CatalogQueryService::CARD_REVIEW_AVERAGE_KEY], (int) $attributes[CatalogQueryService::CARD_REVIEW_COUNT_KEY]];
        }

        $row = DB::table('reviews')
            ->where('object_id', $object->id)
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->selectRaw('avg(rating) as average, count(*) as total')
            ->first();

        $average = $row?->average !== null ? round((float) $row->average, 1) : null;

        return [$average, (int) ($row->total ?? 0)];
    }

    private function viewCount(Object_ $object): int
    {
        $attributes = $object->getAttributes();

        if (array_key_exists(CatalogQueryService::CARD_VIEW_COUNT_KEY, $attributes)) {
            return (int) $attributes[CatalogQueryService::CARD_VIEW_COUNT_KEY];
        }

        return (int) StatDaily::query()
            ->where('subject_type', Object_::class)
            ->where('subject_id', $object->id)
            ->where('kind', StatEventKind::ObjectPageView->value)
            ->sum('count');
    }

    /**
     * Absent for any type that has not declared availability tracking —
     * asserting "available"/"unavailable" for a dining or attraction object
     * would claim a distinction that type never makes.
     */
    private function availabilityStatus(Object_ $object): ?string
    {
        $type = $object->objectType;

        if (! $type instanceof ObjectType || ! $type->has_availability_status) {
            return null;
        }

        return (string) $object->availability_status;
    }

    /**
     * The lowest price among every row attached directly to the object or
     * to one of its rooms — the same "either kind of price row" reading
     * {@see CatalogQueryService}'s own price filter uses, here fetching a
     * value instead of testing a range. Reads
     * {@see CatalogQueryService::attachCardAggregates()}'s own page-wide
     * batch first, the same fallback shape {@see reviewSummary()} and
     * {@see viewCount()} use.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function priceFrom(Object_ $object): array
    {
        $attributes = $object->getAttributes();

        if (array_key_exists(CatalogQueryService::CARD_PRICE_AMOUNT_KEY, $attributes)) {
            return [$attributes[CatalogQueryService::CARD_PRICE_AMOUNT_KEY], $attributes[CatalogQueryService::CARD_PRICE_CURRENCY_KEY]];
        }

        $row = DB::table('prices')
            ->where(function ($query) use ($object): void {
                $query->where(function ($direct) use ($object): void {
                    $direct->where('priceable_type', Object_::class)->where('priceable_id', $object->id);
                })->orWhere(function ($viaRoom) use ($object): void {
                    $viaRoom->where('priceable_type', Room::class)
                        ->whereIn('priceable_id', DB::table('rooms')->select('id')->where('object_id', $object->id));
                });
            })
            ->orderBy('amount')
            ->first();

        if ($row === null) {
            return [null, null];
        }

        return [(string) $row->amount, (string) $row->currency];
    }

    private function detailsUrl(Object_ $object): ?string
    {
        return Route::has('public.objects.show')
            ? route('public.objects.show', ['lang' => app()->getLocale(), 'object' => $object])
            : null;
    }

    /**
     * The card's own href routes through the click-capture redirect, not
     * the resolved deep link directly — a quick-contact tap from a listing
     * page is as real a conversion as one from the object page's own rail,
     * and the spec's own contract ("every contact activation is a measured
     * event") does not carve out an exception for it.
     *
     * @return list<ObjectCardContactAction>
     */
    private function contactActions(Object_ $object): array
    {
        return array_values($object->contactChannels
            ->filter(fn (ContactChannel $channel): bool => $channel->is_active)
            ->sortBy('display_order')
            ->take(self::MAX_CONTACT_ACTIONS)
            ->map(function (ContactChannel $channel) use ($object): ?ObjectCardContactAction {
                $type = $channel->contactChannelType;

                if (! $type instanceof ContactChannelType) {
                    return null;
                }

                if ($this->contactLinks->resolve($type, $channel->raw_value) === null) {
                    return null;
                }

                return new ObjectCardContactAction(
                    contactChannelTypeId: $type->id,
                    channelKey: $type->key,
                    label: (string) ($channel->label ?? $type->display_name ?? $type->key),
                    href: route('public.objects.contact.click', [
                        'lang' => app()->getLocale(), 'object' => $object->id, 'channel' => $channel->id,
                    ]),
                );
            })
            ->filter()
            ->all());
    }
}
