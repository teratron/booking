<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TranslatableDefaults;
use App\Models\Scopes\ModerationScope;
use App\Policies\RoomPolicy;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Override;

/**
 * One room category an accommodation object offers — "Deluxe Double",
 * "Family Suite" — carrying its own capacity and bed configuration, its own
 * amenity selection, and its own price list. A room's rate can differ by
 * season or by how it is charged, so {@see Price} is a set attached through
 * {@see self::prices()}, never a single column on this table. Name and
 * description are translated, following the same per-locale table pattern
 * as every other translatable entity in this codebase.
 */
#[UsePolicy(RoomPolicy::class)]
final class Room extends Model implements TranslatableContract
{
    use Translatable;
    use TranslatableDefaults;

    public ?string $translationModel = RoomTranslation::class;

    /** @var list<string> */
    public array $translatedAttributes = ['name', 'description'];

    protected $guarded = ['id'];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'room_count' => 'integer',
            'area_sqm' => 'decimal:2',
            'max_guests' => 'integer',
            'has_extra_bed' => 'boolean',
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The tenant-scoping relation Filament's cabinet tenancy looks for on
     * every tenant-scoped child resource's model. `ModerationScope` is
     * stripped for the same reason {@see ObjectPhoto::object()} strips it: a
     * draft or not-yet-approved object is exactly the state an owner spends
     * the most time building a room list for, and leaving the scope on would
     * make this relation — and every policy check built on it — silently
     * fail closed for that owner rather than a genuine outsider.
     *
     * @return BelongsTo<Object_, $this>
     */
    public function object(): BelongsTo
    {
        return $this->belongsTo(Object_::class, 'object_id')->withoutGlobalScope(ModerationScope::class);
    }

    /** @return BelongsToMany<Amenity, $this> */
    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class, 'amenity_room');
    }

    /** @return MorphMany<Price, $this> */
    public function prices(): MorphMany
    {
        return $this->morphMany(Price::class, 'priceable');
    }
}
