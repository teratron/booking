<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\NewsItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NewsItem
 */
final class NewsItemResource extends JsonResource
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
            'cover_image_url' => $this->getFirstMediaUrl('cover_image') ?: null,
            'publish_at' => $this->publish_at?->toIso8601String(),
        ];
    }
}
