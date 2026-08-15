<?php

declare(strict_types=1);

namespace App\Support\Catalog;

use App\Services\Catalog\ObjectProfilePresenter;

/**
 * Everything the object profile page renders, assembled once by
 * {@see ObjectProfilePresenter} so the page composes this single read
 * rather than re-deriving state the presenter already owns.
 */
final readonly class ObjectProfileViewModel
{
    /**
     * @param  list<string>  $galleryPhotoUrls
     * @param  list<ObjectCardContactAction>  $contactActions
     * @param  list<array{name: string, description: ?string, capacity: ?int, roomCount: ?int, areaSqm: ?string, bedConfiguration: ?string, maxGuests: ?int, hasExtraBed: bool, amenities: list<string>, prices: list<array{label: ?string, amount: string, currency: string, unit: string}>}>  $rooms
     * @param  list<array{label: ?string, amount: string, currency: string, unit: string}>  $objectPrices
     * @param  list<array{label: string, value: string}>  $attributes
     * @param  list<array{groupName: string, amenities: list<array{iconPath: ?string, label: string}>}>  $amenityGroups
     */
    public function __construct(
        public int $objectId,
        public string $name,
        public string $typeName,
        public ?string $categoryName,
        public string $settlement,
        public ?string $coverPhotoUrl,
        public array $galleryPhotoUrls,
        public ?float $ratingAverage,
        public int $reviewCount,
        public ?string $availabilityStatus,
        public ?string $tierBadgeText,
        public ?string $tierBadgeColour,
        public ?string $tierBorderColour,
        public array $contactActions,
        public ?string $shortDescription,
        public ?string $fullDescription,
        public bool $hasRooms,
        public array $rooms,
        public array $objectPrices,
        public array $attributes,
        public array $amenityGroups,
    ) {}
}
