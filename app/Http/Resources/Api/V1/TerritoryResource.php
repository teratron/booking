<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Territory;
use App\Models\TerritoryLevel;
use App\Services\Seo\PublicUrlGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Territory
 */
final class TerritoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Territory $territory */
        $territory = $this->resource;
        $level = $territory->level;

        return [
            'id' => $territory->id,
            'country_id' => $territory->country_id,
            'parent_id' => $territory->parent_id,
            'level' => $level instanceof TerritoryLevel ? $level->singular_name : null,
            'name' => $territory->name,
            'latitude' => $territory->latitude,
            'longitude' => $territory->longitude,
            'is_featured' => $territory->is_featured,
            'url' => app(PublicUrlGenerator::class)->territoryUrl($territory),
        ];
    }
}
