<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Amenity;
use App\Models\AmenityGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Amenity
 */
final class AmenityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Amenity $amenity */
        $amenity = $this->resource;
        $group = $amenity->group;

        return [
            'id' => $amenity->id,
            'name' => $amenity->name,
            'group' => $group instanceof AmenityGroup ? $group->name : null,
            'is_filterable' => $amenity->is_filterable,
        ];
    }
}
