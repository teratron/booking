<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\FiltersModeration;
use App\Policies\Object_Policy;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * The portal's central entity — a hotel, guesthouse, restaurant, or any
 * other admin-managed object type. Named `Object_` rather than `Object`:
 * the latter is a reserved class name in PHP (confirmed by attempting it —
 * `Cannot use "Object" as a class name as it is reserved`), so this is the
 * closest valid name to the table and the domain vocabulary, not a
 * deliberate rename.
 */
#[UsePolicy(Object_Policy::class)]
class Object_ extends Model implements AuditableContract, HasMedia, TranslatableContract
{
    use Auditable;
    use FiltersModeration;
    use InteractsWithMedia;
    use SoftDeletes;
    use Translatable;

    protected $table = 'objects';

    /** @var list<string> */
    public array $translatedAttributes = ['name', 'short_description', 'full_description', 'seo_title', 'seo_description', 'slug'];

    protected $guarded = ['id'];

    /**
     * All three explicit, not the package's own naming convention: `Object`
     * is a reserved PHP class name, so this model is `Object_` — the
     * default convention would look for the nonexistent
     * `App\Models\Object_Translation` and, for the foreign key, the
     * malformed `object__id` (the trailing underscore in the class name
     * doubled by the package's own `_id` suffix). `$localeKey` is declared
     * for the same strict-mode reason `Concerns\TranslatableDefaults` exists
     * for the other Translatable models — not reused here because PHP
     * fatals when a trait and its class declare the same property with
     * different defaults, and this model's `$translationModel` must differ
     * from the trait's.
     */
    public ?string $translationModel = ObjectTranslation::class;

    public ?string $translationForeignKey = 'object_id';

    public ?string $localeKey = null;

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'availability_changed_at' => 'datetime',
            'availability_last_confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<ObjectType, $this>
     */
    public function objectType(): BelongsTo
    {
        return $this->belongsTo(ObjectType::class);
    }

    /**
     * @return BelongsTo<Territory, $this>
     */
    public function territory(): BelongsTo
    {
        return $this->belongsTo(Territory::class);
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return HasMany<ContactChannel, $this>
     */
    public function contactChannels(): HasMany
    {
        // Explicit FK, not the package convention: guessed from the class
        // name `Object_`, Laravel's own convention would look for the
        // malformed `object__id` (the trailing underscore doubled by the
        // relation helper's own `_id` suffix) — the same pitfall the
        // translation relations on this model are already declared against.
        return $this->hasMany(ContactChannel::class, 'object_id');
    }

    /**
     * @return BelongsToMany<Amenity, $this>
     */
    public function amenities(): BelongsToMany
    {
        // Explicit pivot keys for the same reason contactChannels() needs an
        // explicit FK: Laravel would guess this model's own pivot column as
        // `object__id`, not `object_id`.
        return $this->belongsToMany(Amenity::class, 'amenity_object', 'object_id', 'amenity_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'object_user', 'object_id', 'user_id')->withPivot('permissions');
    }
}
