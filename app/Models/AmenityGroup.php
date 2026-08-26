<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TranslatableDefaults;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * @property-read ?string $name virtual, proxied through the active translation
 */
final class AmenityGroup extends Model implements TranslatableContract
{
    use Translatable;
    use TranslatableDefaults;

    public ?string $translationModel = AmenityGroupTranslation::class;

    /** @var list<string> */
    public array $translatedAttributes = ['name'];

    protected $guarded = ['id'];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return HasMany<Amenity, $this> */
    public function amenities(): HasMany
    {
        return $this->hasMany(Amenity::class);
    }

    /** @return BelongsToMany<ObjectType, $this> */
    public function objectTypes(): BelongsToMany
    {
        return $this->belongsToMany(ObjectType::class, 'amenity_group_object_type');
    }
}
