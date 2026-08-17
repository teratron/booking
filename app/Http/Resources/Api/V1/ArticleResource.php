<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\ArticleTag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Article
 */
final class ArticleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Article $article */
        $article = $this->resource;
        $category = $article->category;

        return [
            'id' => $article->id,
            'title' => $article->title,
            'summary' => $article->summary,
            'body' => $article->body,
            'category' => $category instanceof ArticleCategory ? $category->name : null,
            'tags' => $article->tags->map(static fn (ArticleTag $tag): string => $tag->name)->all(),
            'cover_image_url' => $article->getFirstMediaUrl('cover_image') ?: null,
            'publish_at' => $article->publish_at?->toIso8601String(),
        ];
    }
}
