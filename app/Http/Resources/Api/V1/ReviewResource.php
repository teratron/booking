<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Review;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Review
 */
final class ReviewResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Review $review */
        $review = $this->resource;
        $author = $review->author;

        return [
            'id' => $review->id,
            'rating' => $review->rating,
            'body' => $review->body,
            'author_name' => (string) ($review->author_name ?? ($author instanceof User ? $author->name : null) ?? __('public.object.reviews.anonymous')),
            'date' => $review->created_at?->toDateString(),
            'owner_reply' => $review->owner_reply,
            'owner_reply_date' => $review->owner_replied_at?->toDateString(),
        ];
    }
}
