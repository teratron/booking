<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Object_;
use App\Services\Catalog\ObjectProfilePresenter;
use App\Support\Catalog\ObjectCardContactAction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * The full object read model, shaped by the same
 * {@see ObjectProfilePresenter} the public object page renders — contacts,
 * media, rooms, prices, and services all come from that single presenter
 * rather than a second, API-only derivation of the object's own detail page.
 *
 * @mixin Object_
 */
final class ObjectResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Object_ $object */
        $object = $this->resource;
        $profile = app(ObjectProfilePresenter::class)->present($object);

        return [
            'id' => $profile->objectId,
            'name' => $profile->name,
            'type' => $profile->typeName,
            'category' => $profile->categoryName,
            'settlement' => $profile->settlement,
            'cover_photo_url' => $profile->coverPhotoUrl,
            'gallery_photo_urls' => $profile->galleryPhotoUrls,
            'rating_average' => $profile->ratingAverage,
            'review_count' => $profile->reviewCount,
            'availability_status' => $profile->availabilityStatus,
            'placement' => [
                'tier_badge_text' => $profile->tierBadgeText,
                'tier_badge_colour' => $profile->tierBadgeColour,
                'tier_border_colour' => $profile->tierBorderColour,
            ],
            'contacts' => array_map(
                static fn (ObjectCardContactAction $action): array => [
                    'channel' => $action->channelKey,
                    'label' => $action->label,
                    'href' => $action->href,
                ],
                $profile->contactActions,
            ),
            'short_description' => $profile->shortDescription,
            'full_description' => $profile->fullDescription,
            'has_rooms' => $profile->hasRooms,
            'rooms' => $profile->rooms,
            'prices' => $profile->objectPrices,
            'attributes' => $profile->attributes,
            'amenity_groups' => $profile->amenityGroups,
            'promotions' => PromotionResource::collection(new Collection($profile->objectPromotions)),
            'news' => NewsItemResource::collection(new Collection($profile->objectNews)),
            'reviews' => $profile->reviews,
            'nearby_objects' => ObjectCardResource::collection(new Collection($profile->nearbyObjects)),
            'similar_objects' => ObjectCardResource::collection(new Collection($profile->similarObjects)),
        ];
    }
}
