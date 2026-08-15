<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Amenity;
use App\Models\Object_;
use App\Models\ObjectType;
use App\Models\PlacementTier;
use App\Models\Price;
use App\Models\Room;
use App\Support\Catalog\ObjectProfileViewModel;
use Illuminate\Support\Facades\DB;

/**
 * Resolves one {@see Object_} into the read model the object profile page
 * renders. Every field reads an existing relation, aggregate, or the
 * object type's own declared capability — never a branch on the type's
 * `key`, so adding an object type never requires editing this presenter or
 * the page it feeds.
 */
final class ObjectProfilePresenter
{
    public function present(Object_ $object): ObjectProfileViewModel
    {
        $object->loadMissing([
            'translations', 'objectType.translations', 'objectType.parent.translations',
            'territory.translations', 'amenities.translations', 'amenities.group.translations',
            'placement.package.tier',
        ]);

        $type = $object->objectType;
        $hasRooms = $type instanceof ObjectType && $type->has_rooms;
        $parentType = $type instanceof ObjectType ? $type->parent : null;
        [$ratingAverage, $reviewCount] = $this->reviewSummary($object);
        $tier = $object->placement?->package?->tier;

        return new ObjectProfileViewModel(
            objectId: $object->id,
            name: (string) ($object->name ?? ''),
            typeName: (string) ($type->name ?? ''),
            categoryName: $parentType instanceof ObjectType ? (string) ($parentType->name ?? '') : null,
            settlement: (string) ($object->territory->name ?? ''),
            coverPhotoUrl: $this->coverPhotoUrl($object),
            galleryPhotoUrls: $this->galleryPhotoUrls($object),
            ratingAverage: $ratingAverage,
            reviewCount: $reviewCount,
            availabilityStatus: $this->availabilityStatus($object),
            tierBadgeText: $tier instanceof PlacementTier ? $tier->badge_text : null,
            tierBadgeColour: $tier instanceof PlacementTier ? $tier->badge_colour : null,
            tierBorderColour: $tier instanceof PlacementTier ? $tier->border_colour : null,
            shortDescription: $object->short_description,
            fullDescription: $object->full_description,
            hasRooms: $hasRooms,
            rooms: $hasRooms ? $this->rooms($object) : [],
            objectPrices: $hasRooms ? [] : $this->objectPrices($object),
            attributes: $this->attributeEntries($object, $type),
            amenityGroups: $this->amenityGroups($object),
        );
    }

    private function coverPhotoUrl(Object_ $object): ?string
    {
        $primary = $object->getMedia('photos')
            ->first(fn ($media): bool => (bool) $media->getCustomProperty('is_primary', false));

        $photo = $primary ?? $object->getFirstMedia('photos');

        return $photo?->getUrl('card');
    }

    /**
     * Every gallery photo except the cover — the cover is rendered once,
     * large, carrying the placement and availability badges; the gallery
     * strip beneath it is the object's remaining photos, never a repeat of
     * the same image.
     *
     * @return list<string>
     */
    private function galleryPhotoUrls(Object_ $object): array
    {
        $primary = $object->getMedia('photos')
            ->first(fn ($media): bool => (bool) $media->getCustomProperty('is_primary', false));

        $cover = $primary ?? $object->getFirstMedia('photos');

        return array_values($object->getMedia('photos')
            ->filter(fn ($media): bool => $cover === null || $media->id !== $cover->id)
            ->map(fn ($media): string => $media->getUrl('card'))
            ->all());
    }

    /** @return array{0: ?float, 1: int} */
    private function reviewSummary(Object_ $object): array
    {
        $row = DB::table('reviews')
            ->where('object_id', $object->id)
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->selectRaw('avg(rating) as average, count(*) as total')
            ->first();

        $average = $row?->average !== null ? round((float) $row->average, 1) : null;

        return [$average, (int) ($row->total ?? 0)];
    }

    /**
     * Absent for any type that has not declared availability tracking; the
     * caller decides how to render each raw value — this page's own
     * contract (matching the availability-status spec) renders a badge only
     * for the positive state.
     */
    private function availabilityStatus(Object_ $object): ?string
    {
        $type = $object->objectType;

        if (! $type instanceof ObjectType || ! $type->has_availability_status) {
            return null;
        }

        return (string) $object->availability_status;
    }

    /**
     * @return list<array{name: string, description: ?string, capacity: ?int, roomCount: ?int, areaSqm: ?string, bedConfiguration: ?string, maxGuests: ?int, hasExtraBed: bool, amenities: list<string>, prices: list<array{label: ?string, amount: string, currency: string, unit: string}>}>
     */
    private function rooms(Object_ $object): array
    {
        $rooms = Room::query()
            ->where('object_id', $object->id)
            ->where('is_active', true)
            ->with(['translations', 'amenities.translations', 'prices'])
            ->orderBy('display_order')
            ->get();

        return array_values($rooms->map(fn (Room $room): array => [
            'name' => (string) ($room->name ?? ''),
            'description' => $room->description,
            'capacity' => $room->capacity,
            'roomCount' => $room->room_count,
            'areaSqm' => $room->area_sqm !== null ? (string) $room->area_sqm : null,
            'bedConfiguration' => $room->bed_configuration,
            'maxGuests' => $room->max_guests,
            'hasExtraBed' => (bool) $room->has_extra_bed,
            'amenities' => array_values($room->amenities->map(fn (Amenity $amenity): string => (string) ($amenity->name ?? ''))->all()),
            'prices' => array_values($room->prices->map($this->mapPrice(...))->all()),
        ])->all());
    }

    /** @return list<array{label: ?string, amount: string, currency: string, unit: string}> */
    private function objectPrices(Object_ $object): array
    {
        $prices = Price::query()
            ->where('priceable_type', Object_::class)
            ->where('priceable_id', $object->id)
            ->orderBy('amount')
            ->get();

        return array_values($prices->map($this->mapPrice(...))->all());
    }

    /** @return array{label: ?string, amount: string, currency: string, unit: string} */
    private function mapPrice(Price $price): array
    {
        return [
            'label' => $price->type,
            'amount' => (string) $price->amount,
            'currency' => $price->currency,
            'unit' => $price->calculation_unit,
        ];
    }

    /**
     * The generic, type-varying descriptive block — catering and house
     * rules for accommodation, cuisine and opening hours for dining,
     * visiting information for attraction — read uniformly from the type's
     * own declared `attribute_schema`, never a `match ($type->key)` branch.
     *
     * @return list<array{label: string, value: string}>
     */
    private function attributeEntries(Object_ $object, ?ObjectType $type): array
    {
        if (! $type instanceof ObjectType) {
            return [];
        }

        /** @var list<array{key: string, type: string, label?: array<string, string>}> $schema */
        $schema = $type->attribute_schema ?? [];
        $locale = app()->getLocale();
        $values = $object->attributes ?? [];
        $entries = [];

        foreach ($schema as $field) {
            $raw = $values[$field['key']] ?? null;

            if ($raw === null || $raw === '') {
                continue;
            }

            $label = $field['label'][$locale] ?? $field['key'];
            $value = match ($field['type']) {
                'boolean' => $raw ? __('public.object.attributes.yes') : __('public.object.attributes.no'),
                default => (string) $raw,
            };

            $entries[] = ['label' => $label, 'value' => $value];
        }

        return $entries;
    }

    /**
     * @return list<array{groupName: string, amenities: list<array{iconPath: ?string, label: string}>}>
     */
    private function amenityGroups(Object_ $object): array
    {
        $groups = [];

        foreach ($object->amenities as $amenity) {
            $groupName = (string) ($amenity->group->name ?? '');

            $groups[$groupName] ??= [];
            $groups[$groupName][] = ['iconPath' => $amenity->icon_path, 'label' => (string) ($amenity->name ?? '')];
        }

        return array_map(
            fn (string $groupName, array $amenities): array => ['groupName' => $groupName, 'amenities' => $amenities],
            array_keys($groups),
            array_values($groups),
        );
    }
}
