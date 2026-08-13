<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\FiltersModeration;
use App\Policies\Object_Policy;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Override;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The portal's central entity — a hotel, guesthouse, restaurant, or any
 * other admin-managed object type. Named `Object_` rather than `Object`:
 * the latter is a reserved class name in PHP (confirmed by attempting it —
 * `Cannot use "Object" as a class name as it is reserved`), so this is the
 * closest valid name to the table and the domain vocabulary, not a
 * deliberate rename.
 *
 * @property-read ?string $name virtual, proxied through the active translation
 * @property ?int $owner_id null once an owner has been deliberately detached, or
 *                          whenever a user row backing a previous owner is removed
 * @property ?Carbon $availability_changed_at
 * @property ?Carbon $availability_last_confirmed_at
 */
#[UsePolicy(Object_Policy::class)]
class Object_ extends Model implements AuditableContract, HasMedia, HasName, TranslatableContract
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
     * The object's photo gallery — the only media collection this model
     * manages. Deliberately not `singleFile()`: an owner keeps an unbounded
     * (portal-setting-capped, see `ObjectPhotoService`) set of photographs,
     * ordered, one of them optionally flagged primary via a custom property
     * — `spatie/laravel-medialibrary`'s own documented mechanism for that,
     * rather than a second `primary_photo_id` column that could point at a
     * photo from a different collection or a different object entirely.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos');
    }

    /**
     * Two derived sizes generated for every uploaded photograph — a catalog
     * card thumbnail and a larger detail-page size — so an owner is never
     * asked to resize anything before uploading. Queued rather than
     * generated inline (`spatie/laravel-medialibrary`'s own default,
     * `media-library.queue_conversions_by_default`), so an upload request
     * returns immediately and never waits on image processing; named
     * explicitly here anyway, since the guarantee this codebase's own
     * cabinet media task depends on should not rest on a config default
     * nobody at this call site is pinning.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        // `fit()`/`sharpen()` are Spatie's own manipulation methods, proxied
        // through `Conversion::__call()` rather than declared directly — an
        // indirection Larastan cannot see through, so `queued()` and
        // `performOnCollections()` (both real, typed `Conversion` methods)
        // are called first and the magic-proxied manipulation calls last,
        // where nothing chains off their inferred return type.
        $this->addMediaConversion('thumb')
            ->queued()
            ->performOnCollections('photos')
            ->fit(Fit::Crop, 400, 300)
            ->sharpen(5);

        $this->addMediaConversion('card')
            ->queued()
            ->performOnCollections('photos')
            ->fit(Fit::Crop, 800, 600);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * The owner cabinet's tenant switcher resolves a tenant's display name
     * through this contract, not the bare `name` attribute: Filament reads
     * it via `getAttributeValue()`, which bypasses Astrotomic's own
     * `getAttribute()` override — the one place the active-locale
     * translation actually gets resolved. Without this, the switcher would
     * render every object with an empty label.
     */
    public function getFilamentName(): string
    {
        return $this->name ?? '';
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

    /** @return HasMany<AvailabilityHistory, $this> */
    public function availabilityHistories(): HasMany
    {
        return $this->hasMany(AvailabilityHistory::class, 'object_id');
    }

    /**
     * Every promotional-label grant ever recorded for this object, past and
     * present — the decoration service narrows this to the one currently
     * active row itself, rather than this relation carrying that filter.
     *
     * @return HasMany<ObjectPromotion, $this>
     */
    public function objectPromotions(): HasMany
    {
        return $this->hasMany(ObjectPromotion::class, 'object_id');
    }

    /**
     * The current placement grant — one row per object, distinct from the
     * append-only `placement_histories` this table's own sibling keeps.
     *
     * @return HasOne<ObjectPlacement, $this>
     */
    public function placement(): HasOne
    {
        return $this->hasOne(ObjectPlacement::class, 'object_id');
    }
}
