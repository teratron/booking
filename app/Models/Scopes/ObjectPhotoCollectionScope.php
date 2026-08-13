<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Models\Object_;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Narrows an unqualified `ObjectPhoto` query to rows that actually belong to
 * an `Object_` and sit in its `photos` collection. `ObjectPhoto` reads from
 * the same shared `media` table every `HasMedia` model in this codebase
 * writes to (Article, Banner, NewsItem, Promotion, Object_ itself) — without
 * this, a plain query would surface another model's uploads (an article's
 * cover image, a banner creative) as if they were object photographs.
 */
/**
 * @implements Scope<Model>
 */
final class ObjectPhotoCollectionScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where('model_type', Object_::class)->where('collection_name', 'photos');
    }
}
