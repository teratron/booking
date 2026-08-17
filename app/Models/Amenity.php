<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TranslatableDefaults;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Override;

/**
 * A facility or service an object may offer — pool, parking, Wi-Fi — grouped
 * under an `AmenityGroup` and filterable in the public catalog when marked so.
 *
 * @property-read ?string $name virtual, proxied through the active translation
 */
final class Amenity extends Model implements TranslatableContract
{
    use Translatable;
    use TranslatableDefaults;

    public ?string $translationModel = AmenityTranslation::class;

    /** @var list<string> */
    public array $translatedAttributes = ['name'];

    protected $guarded = ['id'];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'is_filterable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<AmenityGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(AmenityGroup::class, 'amenity_group_id');
    }

    /** @return BelongsToMany<Object_, $this> */
    public function objects(): BelongsToMany
    {
        return $this->belongsToMany(Object_::class, 'amenity_object');
    }
}
