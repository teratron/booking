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
        $object->loadMissing([
            'translations', 'objectType', 'territory.translations', 'amenities.translations',
            'placement.package.tier', 'contactChannels.contactChannelType',
        ]);

        [$ratingAverage, $reviewCount] = $this->reviewSummary($object);
        [$priceAmount, $priceCurrency] = $this->priceFrom($object);
        $tier = $object->placement?->package?->tier;

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
            tierBadgeText: $tier instanceof PlacementTier ? $tier->badge_text : null,
            tierBadgeColour: $tier instanceof PlacementTier ? $tier->badge_colour : null,
            tierBorderColour: $tier instanceof PlacementTier ? $tier->border_colour : null,
            availabilityStatus: $this->availabilityStatus($object),
            priceFromAmount: $priceAmount,
            priceCurrency: $priceCurrency,
            detailsUrl: $this->detailsUrl($object),
            contactActions: $this->contactActions($object),
        );
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

    /** @return array{0: ?float, 1: int} */
    private function reviewSummary(Object_ $object): array
    {
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
     * {@see CatalogQueryService}'s own price filter
     * uses, here fetching a value instead of testing a range.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function priceFrom(Object_ $object): array
    {
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
            ? route('public.objects.show', ['object' => $object])
            : null;
    }

    /** @return list<ObjectCardContactAction> */
    private function contactActions(Object_ $object): array
    {
        return array_values($object->contactChannels
            ->filter(fn (ContactChannel $channel): bool => $channel->is_active)
            ->take(self::MAX_CONTACT_ACTIONS)
            ->map(function (ContactChannel $channel): ?ObjectCardContactAction {
                $type = $channel->contactChannelType;

                if (! $type instanceof ContactChannelType) {
                    return null;
                }

                $href = $this->contactLinks->resolve($type, $channel->raw_value);

                if ($href === null) {
                    return null;
                }

                return new ObjectCardContactAction(
                    contactChannelTypeId: $type->id,
                    channelKey: $type->key,
                    label: (string) ($type->display_name ?? $type->key),
                    href: $href,
                );
            })
            ->filter()
            ->all());
    }
}
