<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\FiltersModeration;
use App\Models\Concerns\TranslatableDefaults;
use App\Policies\NewsItemPolicy;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Override;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Owner- or administrator-authored news — portal-wide when `object_id` is
 * null, otherwise scoped to that one object's page. Unlike `Article`, this
 * carries a real `moderation_status` column and uses `FiltersModeration`:
 * owner-authored input passes moderation, administrator-authored input does
 * not, but both states are expressed through the same column, so an
 * administrator "publish" action must explicitly set `moderation_status =
 * 'approved'` — leaving it null would make even a trusted, administrator-
 * published row invisible under this model's own default query.
 *
 * @property-read ?string $title virtual, proxied through the active translation
 * @property ?int $object_id
 * @property ?int $territory_id
 * @property ?int $article_category_id
 * @property ?Carbon $publish_at
 * @property ?Carbon $end_at
 */
#[UsePolicy(NewsItemPolicy::class)]
class NewsItem extends Model implements HasMedia, TranslatableContract
{
    use FiltersModeration;
    use InteractsWithMedia;
    use SoftDeletes;
    use Translatable;
    use TranslatableDefaults;

    // The translation table is `news_translations`, not the
    // `news_item_translations` Astrotomic's own null-convention would infer
    // from this class's name — explicit here for exactly that reason.
    public ?string $translationModel = NewsTranslation::class;

    /** @var list<string> */
    public array $translatedAttributes = ['title', 'summary', 'body', 'seo_title', 'seo_description', 'slug'];

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'publish_at' => 'datetime',
            'end_at' => 'datetime',
            'is_pinned' => 'boolean',
            'view_count' => 'integer',
        ];
    }

    /**
     * A cover image (singleFile) plus an unbounded gallery — the two
     * collections §3.3's "cover image, gallery" names.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover_image')->singleFile();
        $this->addMediaCollection('gallery');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsTo<Object_, $this>
     */
    public function object(): BelongsTo
    {
        return $this->belongsTo(Object_::class, 'object_id');
    }

    /**
     * @return BelongsTo<Territory, $this>
     */
    public function territory(): BelongsTo
    {
        return $this->belongsTo(Territory::class);
    }

    /**
     * @return BelongsTo<ArticleCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ArticleCategory::class, 'article_category_id');
    }
}
