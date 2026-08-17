<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Object_;
use App\Services\Catalog\ObjectCardPresenter;
use App\Support\Catalog\ObjectCardContactAction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The catalog listing's own read model, shaped by the same
 * {@see ObjectCardPresenter} the public site's own card component renders —
 * an API consumer sees exactly what a visitor would, never a second
 * derivation of "what a card shows".
 *
 * @mixin Object_
 */
final class ObjectCardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Object_ $object */
        $object = $this->resource;
        $card = app(ObjectCardPresenter::class)->present($object);

        return [
            'id' => $card->objectId,
            'name' => $card->name,
            'settlement' => $card->settlement,
            'cover_photo_url' => $card->coverPhotoUrl,
            'short_description' => $card->shortDescription,
            'rating_average' => $card->ratingAverage,
            'review_count' => $card->reviewCount,
            'availability_status' => $card->availabilityStatus,
            'price_from' => $card->priceFromAmount !== null ? [
                'amount' => $card->priceFromAmount,
                'currency' => $card->priceCurrency,
            ] : null,
            'placement' => [
                'tier_badge_text' => $card->tierBadgeText,
                'tier_badge_colour' => $card->tierBadgeColour,
                'tier_border_colour' => $card->tierBorderColour,
            ],
            'url' => $card->detailsUrl,
            'contacts' => array_map(
                static fn (ObjectCardContactAction $action): array => [
                    'channel' => $action->channelKey,
                    'label' => $action->label,
                    'href' => $action->href,
                ],
                $card->contactActions,
            ),
        ];
    }
}
