<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TranslatableDefaults;
use App\Policies\TerritoryPolicy;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

/**
 * The territory hierarchy — country → region → city → resort, per each
 * country's own level vocabulary. `parent_id` is
 * `staudenmeir/laravel-adjacency-list`'s expected column name, so the
 * recursive subtree/ancestor queries this model exposes need no further
 * configuration.
 *
 * @property-read ?string $name virtual, proxied through the active translation
 * @property-read ?string $slug virtual, proxied through the active translation
 * @property ?int $parent_id
 * @property int<0, max> $country_id
 */
#[UsePolicy(TerritoryPolicy::class)]
class Territory extends Model implements TranslatableContract
{
    use HasRecursiveRelationships;
    use Translatable;
    use TranslatableDefaults;

    public ?string $translationModel = null;

    /** @var list<string> */
    public array $translatedAttributes = [
        'name', 'short_description', 'full_description', 'slug',
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
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return BelongsTo<TerritoryLevel, $this>
     */
    public function level(): BelongsTo
    {
        return $this->belongsTo(TerritoryLevel::class);
    }

    /**
     * @return HasMany<Object_, $this>
     */
    public function objects(): HasMany
    {
        return $this->hasMany(Object_::class);
    }
}
