<?php

declare(strict_types=1);

namespace App\Services\Cabinet;

use App\Models\Amenity;
use App\Models\AmenityGroup;
use App\Models\Object_;
use App\Models\ObjectType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Resolves which part of the administrator-maintained amenity registry the
 * owner cabinet's service-selection screen may offer for a given object.
 * Free-text entry is never part of this contract — every value this service
 * returns is a real registry row, narrowed to what the object's own declared
 * type allows and what an administrator has left active.
 */
final class ObjectServiceCatalogService
{
    /**
     * Amenity groups applicable to the object's declared type — the axis the
     * cabinet's own selection screen sections its checkboxes by, so a
     * restaurant is never offered room amenities and vice versa. An object
     * with no declared type, or a type with no group carrying at least one
     * active amenity, resolves to an empty collection rather than falling
     * back to the full registry: offering nothing is always safer here than
     * offering everything.
     *
     * @return Collection<int, AmenityGroup>
     */
    public function applicableGroups(Object_ $object): Collection
    {
        $type = $object->objectType;

        if (! $type instanceof ObjectType) {
            return new Collection;
        }

        return $type->amenityGroups()
            ->where('amenity_groups.is_active', true)
            ->whereHas('amenities', fn (Builder $query): Builder => $query->where('is_active', true))
            ->with('translations')
            ->orderBy('amenity_groups.display_order')
            ->get();
    }

    /**
     * The active, administrator-registered amenities within one group — the
     * exact, exhaustive option set the cabinet's own checkbox list for that
     * group renders. Never includes an inactive entry: an administrator
     * deactivating an amenity retires it from every owner's selection screen
     * immediately, without touching any object that already carries it.
     *
     * @return Collection<int, Amenity>
     */
    public function activeAmenities(AmenityGroup $group): Collection
    {
        return $group->amenities()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->with('translations')
            ->get();
    }
}
