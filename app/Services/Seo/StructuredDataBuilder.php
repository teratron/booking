<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Models\Object_;
use App\Models\ObjectType;
use App\Models\Promotion;
use App\Models\Territory;
use App\Support\Catalog\ObjectProfileViewModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Type-appropriate Schema.org structured data (JSON-LD) for each public
 * page type the specification names. Every method returns a `list` of
 * ready-to-encode blocks, each carrying its own `@context` — the layout
 * renders each as its own independent `<script type="application/ld+json">`
 * tag, never merged into one document.
 */
final class StructuredDataBuilder
{
    /**
     * Branches on the object type's own declared `structured_data_kind` —
     * never on its `key` — so a new administrator-created type needs only
     * that one declaration, not a code change here. `$bookingActive` gates
     * offer/availability emission: the portal must never claim it can
     * honour a booking the module cannot actually process for this object.
     *
     * @return list<array<string, mixed>>
     */
    public function forObject(Object_ $object, ObjectProfileViewModel $profile, bool $bookingActive): array
    {
        $type = $object->objectType;
        $kind = $type instanceof ObjectType ? $type->structured_data_kind : 'place';

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => match ($kind) {
                'lodging' => 'LodgingBusiness',
                'food' => 'FoodEstablishment',
                default => 'Place',
            },
            'name' => $profile->name,
        ];

        $address = $this->address($object);

        if ($address !== null) {
            $schema['address'] = $address;
        }

        $geo = $this->geo($object);

        if ($geo !== null) {
            $schema['geo'] = $geo;
        }

        if ($kind === 'place') {
            return [$schema];
        }

        if ($profile->ratingAverage !== null && $profile->reviewCount > 0) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $profile->ratingAverage,
                'reviewCount' => $profile->reviewCount,
            ];
        }

        $priceRange = $this->priceRange($profile);

        if ($priceRange !== null) {
            $schema['priceRange'] = $priceRange;
        }

        // Offer availability is the one field this method withholds rather
        // than derives — emitting it while the booking module is inactive
        // for this object would claim a capability the portal cannot
        // actually honour.
        if ($kind === 'lodging' && $bookingActive) {
            $schema['makesOffer'] = [
                '@type' => 'Offer',
                'availability' => $profile->availabilityStatus === 'available'
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
            ];
        }

        return [$schema];
    }

    /**
     * @param  Collection<int, array{name: string, url: string}>  $containedObjects
     * @return list<array<string, mixed>>
     */
    public function forTerritory(Territory $territory, Collection $containedObjects): array
    {
        $place = [
            '@context' => 'https://schema.org',
            '@type' => 'Place',
            'name' => (string) ($territory->name ?? ''),
        ];

        if ($territory->latitude !== null && $territory->longitude !== null) {
            $place['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => (float) $territory->latitude,
                'longitude' => (float) $territory->longitude,
            ];
        }

        $itemList = [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'itemListElement' => $containedObjects->values()->map(fn (array $item, int $index): array => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'url' => $item['url'],
            ])->all(),
        ];

        return [$place, $itemList];
    }

    /**
     * Shared by article and news pages — the specification names an
     * identical shape for both.
     *
     * @return list<array<string, mixed>>
     */
    public function forArticleLike(string $title, ?string $authorName, ?Carbon $date, ?string $imageUrl): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $title,
        ];

        if ($authorName !== null) {
            $schema['author'] = ['@type' => 'Person', 'name' => $authorName];
        }

        if ($date instanceof Carbon) {
            $schema['datePublished'] = $date->toIso8601String();
        }

        if ($imageUrl !== null) {
            $schema['image'] = $imageUrl;
        }

        return [$schema];
    }

    /** @return list<array<string, mixed>> */
    public function forPromotion(Promotion $promotion): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Offer',
            'name' => (string) ($promotion->title ?? ''),
            'validFrom' => $promotion->starts_at->toDateString(),
            'validThrough' => $promotion->ends_at->toDateString(),
        ];

        if ($promotion->summary !== null) {
            $schema['description'] = $promotion->summary;
        }

        return [$schema];
    }

    /** @return ?array{'@type': string, streetAddress: string} */
    private function address(Object_ $object): ?array
    {
        if ($object->address === null) {
            return null;
        }

        return ['@type' => 'PostalAddress', 'streetAddress' => $object->address];
    }

    /** @return ?array{'@type': string, latitude: float, longitude: float} */
    private function geo(Object_ $object): ?array
    {
        if ($object->latitude === null || $object->longitude === null) {
            return null;
        }

        return ['@type' => 'GeoCoordinates', 'latitude' => (float) $object->latitude, 'longitude' => (float) $object->longitude];
    }

    private function priceRange(ObjectProfileViewModel $profile): ?string
    {
        $prices = $profile->hasRooms
            ? collect($profile->rooms)->flatMap(fn (array $room): array => $room['prices'])
            : collect($profile->objectPrices);

        $amounts = $prices->pluck('amount')->map(fn (string $amount): float => (float) $amount)->filter();

        if ($amounts->isEmpty()) {
            return null;
        }

        /** @var string $currency */
        $currency = $prices->first()['currency'] ?? '';

        return sprintf('%s%s-%s%s', $currency, $amounts->min(), $currency, $amounts->max());
    }
}
