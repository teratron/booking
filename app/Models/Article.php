<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TranslatableDefaults;
use App\Policies\ArticlePolicy;
use App\Support\Content\ContentSummary;
use App\Support\Content\Summarizable;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Override;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * An administrator-authored editorial piece — a destination guide, a
 * seasonal roundup, a feature on a handful of objects. Unlike `NewsItem` and
 * `Promotion`, this carries no `moderation_status` column at all: an
 * administrator publishing an article is already the trusted act, so it
 * never enters the moderation queue and never uses `FiltersModeration`.
 *
 * @property-read ?string $title virtual, proxied through the active translation
 * @property-read ?string $summary virtual, proxied through the active translation
 * @property-read ?string $slug virtual, proxied through the active translation
 * @property ?int $article_category_id
 * @property ?Carbon $publish_at
 */
#[UsePolicy(ArticlePolicy::class)]
class Article extends Model implements HasMedia, Summarizable, TranslatableContract
{
    use InteractsWithMedia;
    use SoftDeletes;
    use Translatable;
    use TranslatableDefaults;

    public ?string $translationModel = null;

    /** @var list<string> */
    public array $translatedAttributes = ['title', 'summary', 'body', 'seo_title', 'seo_description', 'slug'];

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return ['publish_at' => 'datetime'];
    }

    /**
     * A single cover image — the same "one file, replaced on the next
     * upload" shape `Banner`'s creative collections use, not a gallery.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover_image')->singleFile();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsTo<ArticleCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ArticleCategory::class, 'article_category_id');
    }

    /**
     * Objects this article relates to. Unlike `NewsItem`/`Promotion`, which
     * carry a single optional owner association, an article may relate to
     * several — the source specification's own asymmetry, not invented
     * here.
     *
     * @return BelongsToMany<Object_, $this>
     */
    public function objects(): BelongsToMany
    {
        // Explicit pivot keys: Laravel's own convention would guess the
        // related key from the class name `Object_` as `object__id` (the
        // trailing underscore doubled by the relation helper's own `_id`
        // suffix) — the same pitfall `Object_::amenities()` avoids.
        return $this->belongsToMany(Object_::class, 'article_object', 'article_id', 'object_id');
    }

    /**
     * @return BelongsToMany<Territory, $this>
     */
    public function territories(): BelongsToMany
    {
        return $this->belongsToMany(Territory::class, 'article_territory', 'article_id', 'territory_id');
    }

    /**
     * @return BelongsToMany<ArticleTag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ArticleTag::class, 'article_tag', 'article_id', 'article_tag_id');
    }

    /**
     * Rows a public-facing read may show. Unlike `NewsItem`/`Promotion`,
     * this model carries no moderation scope of its own, so this is the one
     * place "is this article actually visible" is decided: administrator-
     * published, and not embargoed by a `publish_at` still in the future. A
     * `draft` row, and a `scheduled` row whose date has not yet arrived,
     * must never match this scope.
     *
     * @param  Builder<Article>  $query
     * @return Builder<Article>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where(function (Builder $query): void {
                $query->whereNull('publish_at')->orWhere('publish_at', '<=', now());
            });
    }

    #[Override]
    public function toContentSummary(): ContentSummary
    {
        return new ContentSummary(
            contentType: 'article',
            id: (int) $this->id,
            title: $this->title,
            summary: $this->summary,
            coverImageUrl: $this->getFirstMediaUrl('cover_image') ?: null,
            publishedAt: $this->publish_at,
            slug: $this->slug,
        );
    }
}
