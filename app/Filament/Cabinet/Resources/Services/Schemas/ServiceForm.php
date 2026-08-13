<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Resources\Services\Schemas;

use App\Models\Amenity;
use App\Models\AmenityGroup;
use App\Models\Object_;
use App\Services\Cabinet\ObjectServiceCatalogService;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

/**
 * The owner cabinet's service (amenity) selection screen — one section per
 * amenity group applicable to the object's own declared type, each holding a
 * checkbox list of that group's active, administrator-registered amenities.
 * There is deliberately no free-text field anywhere on this form: every
 * selectable value traces back to a real `amenities` row, because a
 * free-text entry here would break the public catalog's own filter facets
 * later.
 *
 * Every checkbox list binds straight through Filament's own `->relationship()`
 * to `Object_::amenities()` — one call per group, each with its own
 * group-scoped query constraint, so a save touches only that group's own
 * pivot rows and leaves every other group's selection untouched. That
 * binding also makes the whole screen structurally immediate: Filament
 * persists a relationship-bound field during the form's own save lifecycle,
 * strictly before the edit page's own record-update hook ever runs, so there
 * is nothing left for that hook to gate behind moderation — a selection here
 * always takes effect right away, regardless of the object's own publication
 * state.
 */
class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(fn (?Object_ $record): array => self::groupSections($record));
    }

    /** @return list<Section> */
    private static function groupSections(?Object_ $record): array
    {
        if (! $record instanceof Object_) {
            return [];
        }

        $catalog = app(ObjectServiceCatalogService::class);
        $groups = $catalog->applicableGroups($record);

        if ($groups->isEmpty()) {
            return [
                Section::make(__('panel.cabinet.services.title'))
                    ->schema([
                        Placeholder::make('services_empty')
                            ->hiddenLabel()
                            ->content(__('panel.cabinet.services.empty')),
                    ]),
            ];
        }

        return array_values(
            $groups->map(fn (AmenityGroup $group): Section => self::groupSection($group, $catalog))->all(),
        );
    }

    private static function groupSection(AmenityGroup $group, ObjectServiceCatalogService $catalog): Section
    {
        return Section::make($group->name ?? "#{$group->id}")
            ->schema([
                CheckboxList::make("amenities_group_{$group->id}")
                    ->relationship(
                        name: 'amenities',
                        titleAttribute: 'id',
                        modifyQueryUsing: fn (Builder $query): Builder => $query
                            ->where('amenity_group_id', $group->id)
                            ->where('is_active', true),
                    )
                    ->getOptionLabelFromRecordUsing(fn (Amenity $amenity): string => $amenity->name ?? "#{$amenity->id}")
                    ->options(fn (): array => $catalog->activeAmenities($group)
                        ->mapWithKeys(fn (Amenity $amenity): array => [$amenity->id => $amenity->name ?? "#{$amenity->id}"])
                        ->all())
                    ->hiddenLabel()
                    ->columns(2),
            ]);
    }
}
