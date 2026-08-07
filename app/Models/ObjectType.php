<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TranslatableDefaults;
use App\Policies\ObjectTypePolicy;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * @property-read ?string $name virtual, proxied through the active translation
 * @property ?int $parent_id
 */
#[UsePolicy(ObjectTypePolicy::class)]
class ObjectType extends Model implements TranslatableContract
{
    use Translatable;
    use TranslatableDefaults;

    public ?string $translationModel = null;

    /** @var list<string> */
    public array $translatedAttributes = ['name', 'seo_title', 'seo_description'];

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'attribute_schema' => 'array',
            'has_rooms' => 'boolean',
            'has_availability_status' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ObjectType, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<ObjectType, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Object_, $this>
     */
    public function objects(): HasMany
    {
        return $this->hasMany(Object_::class);
    }

    /**
     * @return BelongsToMany<AmenityGroup, $this>
     */
    public function amenityGroups(): BelongsToMany
    {
        return $this->belongsToMany(AmenityGroup::class, 'amenity_group_object_type');
    }
}
