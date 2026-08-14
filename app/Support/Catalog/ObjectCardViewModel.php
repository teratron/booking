<?php

declare(strict_types=1);

namespace App\Support\Catalog;

use App\Services\Catalog\ObjectCardPresenter;

/**
 * Everything the object card component renders, assembled once by
 * {@see ObjectCardPresenter} so the card itself stays
 * a thin presenter over this single read — the same shape every other
 * per-surface summary DTO in this codebase already follows.
 */
final readonly class ObjectCardViewModel
{
    /**
     * @param  list<array{iconPath: ?string, label: string}>  $keyServices
     * @param  list<ObjectCardContactAction>  $contactActions
     */
    public function __construct(
        public int $objectId,
        public string $name,
        public ?string $coverPhotoUrl,
        public string $settlement,
        public ?string $shortDescription,
        public array $keyServices,
        public ?float $ratingAverage,
        public int $reviewCount,
        public int $viewCount,
        public ?string $tierBadgeText,
        public ?string $tierBadgeColour,
        public ?string $tierBorderColour,
        public ?string $availabilityStatus,
        public ?string $priceFromAmount,
        public ?string $priceCurrency,
        public ?string $detailsUrl,
        public array $contactActions,
    ) {}
}
