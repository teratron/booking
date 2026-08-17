<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\DocumentsQueryParameters;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ArticleResource;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * No token-scope narrowing here: an article's own geographic reach
 * (`territories()`) is a many-to-many association, not a single owning
 * country the way an object's `country_id` is, and the object-category
 * axis has no equivalent on editorial content at all. Visibility is still
 * fully enforced — {@see Article::scopePublished()} is the one gate this
 * model has, since it carries no moderation scope of its own.
 */
final class ArticleController extends Controller implements DocumentsQueryParameters
{
    /** @return array<string, list<string>> */
    public static function queryParameterRules(): array
    {
        return [
            'category' => ['nullable', 'integer', 'exists:article_categories,id'],
            'tag' => ['nullable', 'integer', 'exists:article_tags,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate(self::queryParameterRules());

        $query = Article::published()->with('translations')->latest('publish_at');

        if (isset($validated['category'])) {
            $query->where('article_category_id', $validated['category']);
        }

        if (isset($validated['tag'])) {
            $tagId = $validated['tag'];
            $query->whereHas('tags', fn ($tags) => $tags->where('article_tags.id', $tagId));
        }

        return ArticleResource::collection($query->paginate((int) ($validated['per_page'] ?? 24)));
    }
}
