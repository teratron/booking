<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Promotion
 */
final class PromotionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'summary' => $this->summary,
            'body' => $this->body,
            'object_id' => $this->object_id,
            'territory_id' => $this->territory_id,
            'image_url' => $this->getFirstMediaUrl('image') ?: null,
            'starts_at' => $this->starts_at->toDateString(),
            'ends_at' => $this->ends_at->toDateString(),
        ];
    }
}
