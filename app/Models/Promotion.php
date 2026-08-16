<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\FiltersModeration;
use App\Models\Concerns\TranslatableDefaults;
use App\Policies\PromotionPolicy;
use App\Support\Content\ContentSummary;
use App\Support\Content\Summarizable;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Override;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * An owner- or administrator-authored, time-bounded promotional content
 * item — NOT the card-decoration entity (`PromotionLabel`/`ObjectPromotion`,
 * a different, unrelated feature that happens to share a word in the
 * source requirements). A promotion always belongs to exactly one object
 * and one territory; unlike `NewsItem`, neither is optional here. Carries a
 * real `moderation_status` column via `FiltersModeration` — the same
 * "administrator publish must set moderation_status explicitly" rule
 * `NewsItem` documents applies here too. Archives automatically once
 * `ends_at` passes, via a scheduled job — never a render-time check.
 *
 * @property-read ?string $title virtual, proxied through the active translation
 * @property-read ?string $summary virtual, proxied through the active translation
 * @property-read ?string $slug virtual, proxied through the active translation
 * @property int $object_id
 * @property int $territory_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 */
#[UsePolicy(PromotionPolicy::class)]
class Promotion extends Model implements HasMedia, Summarizable, TranslatableContract
{
    use FiltersModeration;
    use InteractsWithMedia;
    use SoftDeletes;
    use Translatable;
    use TranslatableDefaults;

    public ?string $translationModel = null;

    /** @var list<string> */
    public array $translatedAttributes = [
        'title', 'summary', 'body', 'slug',
        'seo_title', 'seo_description', 'seo_canonical_url', 'seo_indexable',
        'seo_og_title', 'seo_og_description', 'seo_og_image',
    ];

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    /**
     * A single image — the promotion's own creative, singleFile like
     * `Article`'s cover image.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')->singleFile();
    }

    /**
     * @return BelongsTo<Object_, $this>
     */
    public function object(): BelongsTo
    {
        return $this->belongsTo(Object_::class);
    }

    /**
     * @return BelongsTo<Territory, $this>
     */
    public function territory(): BelongsTo
    {
        return $this->belongsTo(Territory::class);
    }

    /**
     * True once $this has passed its own end date — the condition
     * `PromotionArchivalJob` sweeps for, exposed here so the job and any
     * other caller share one definition rather than each re-deriving it.
     */
    public function hasElapsed(): bool
    {
        return $this->ends_at->isPast();
    }

    /**
     * Rows eligible for a public-facing feed. Combined with
     * `FiltersModeration`'s own global scope, this is the full visibility
     * contract: approved, published, its own window open (`starts_at` has
     * arrived, `ends_at` has not yet passed) — the latter a defensive check
     * alongside `PromotionArchivalJob`, since a row the job has not yet
     * swept should still never appear live past its own end date.
     *
     * @param  Builder<Promotion>  $query
     * @return Builder<Promotion>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where('starts_at', '<=', now()->toDateString())
            ->where('ends_at', '>=', now()->toDateString());
    }

    #[Override]
    public function toContentSummary(): ContentSummary
    {
        return new ContentSummary(
            contentType: 'promotion',
            id: (int) $this->id,
            title: $this->title,
            summary: $this->summary,
            coverImageUrl: $this->getFirstMediaUrl('image') ?: null,
            publishedAt: $this->starts_at,
            slug: $this->slug,
        );
    }
}
