<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\ObjectType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ObjectType
 */
final class ObjectTypeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'has_rooms' => $this->has_rooms,
            'has_availability_status' => $this->has_availability_status,
        ];
    }
}
