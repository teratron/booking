<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Resources\Rooms\Schemas;

use App\Filament\Cabinet\Resources\Rooms\RoomResource;
use App\Models\Amenity;
use App\Models\Language;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The owner cabinet's room-category form: layout and capacity fields,
 * per-language name and description, the room's own amenity selection, and
 * its price list. Every field on it applies immediately on save — see
 * {@see RoomResource}'s own docblock for why none of it is a moderation
 * candidate the way an object's own public-facing fields are.
 *
 * Translated fields are collected as nested form state
 * (`translations.{locale}.*`) and reconciled against the real
 * `room_translations` rows explicitly on the create/edit pages, exactly as
 * the object form's own mechanism does — astrotomic's translation package
 * has no Filament relationship binding of its own to lean on. Amenities and
 * prices, by contrast, bind straight through Filament's own
 * `->relationship()`, since both are genuinely relational (a many-to-many
 * pivot, a polymorphic set of rate rows) and neither needs the moderation
 * exemption reasoned about above — they were never going to be routed
 * through it in the first place.
 */
class RoomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            self::coreSection(),
            self::translationsSection(),
            self::amenitiesSection(),
            self::pricesSection(),
        ]);
    }

    private static function coreSection(): Section
    {
        return Section::make(__('panel.cabinet.rooms.sections.core'))
            ->columns(2)
            ->schema([
                TextInput::make('capacity')
                    ->label(__('panel.cabinet.rooms.form.capacity'))
                    ->numeric()
                    ->minValue(0),

                TextInput::make('room_count')
                    ->label(__('panel.cabinet.rooms.form.room_count'))
                    ->numeric()
                    ->minValue(0),

                TextInput::make('area_sqm')
                    ->label(__('panel.cabinet.rooms.form.area_sqm'))
                    ->numeric()
                    ->minValue(0),

                TextInput::make('bed_configuration')
                    ->label(__('panel.cabinet.rooms.form.bed_configuration')),

                TextInput::make('max_guests')
                    ->label(__('panel.cabinet.rooms.form.max_guests'))
                    ->numeric()
                    ->minValue(0),

                TextInput::make('display_order')
                    ->label(__('panel.cabinet.rooms.form.display_order'))
                    ->numeric()
                    ->default(0),

                Toggle::make('has_extra_bed')
                    ->label(__('panel.cabinet.rooms.form.has_extra_bed')),

                Toggle::make('is_active')
                    ->label(__('panel.cabinet.rooms.form.is_active'))
                    ->default(true),
            ]);
    }

    private static function translationsSection(): Section
    {
        $primaryLocale = Language::query()->where('is_primary', true)->value('code');

        return Section::make(__('panel.cabinet.rooms.sections.translations'))
            ->schema(self::perLanguageSections(fn (string $code): array => [
                TextInput::make("translations.{$code}.name")
                    ->label(__('panel.cabinet.rooms.form.name'))
                    ->maxLength(255)
                    ->required($code === $primaryLocale),

                Textarea::make("translations.{$code}.description")
                    ->label(__('panel.cabinet.rooms.form.description')),
            ]));
    }

    private static function amenitiesSection(): Section
    {
        return Section::make(__('panel.cabinet.rooms.sections.amenities'))
            ->schema([
                Select::make('amenities')
                    ->relationship('amenities', 'id')
                    ->multiple()
                    ->hiddenLabel()
                    ->getOptionLabelFromRecordUsing(fn (Amenity $amenity): string => $amenity->name ?? "#{$amenity->id}")
                    ->options(fn (): array => Amenity::query()->with('translations')->get()
                        ->mapWithKeys(fn (Amenity $amenity): array => [$amenity->id => $amenity->name ?? "#{$amenity->id}"])
                        ->all()),
            ]);
    }

    private static function pricesSection(): Section
    {
        return Section::make(__('panel.cabinet.rooms.sections.prices'))
            ->schema([
                Repeater::make('prices')
                    ->relationship()
                    ->hiddenLabel()
                    ->schema([
                        TextInput::make('type')
                            ->label(__('panel.cabinet.rooms.price_form.type')),

                        Select::make('calculation_unit')
                            ->label(__('panel.cabinet.rooms.price_form.calculation_unit'))
                            ->options(fn (): array => collect(['per_room', 'per_person', 'per_night', 'per_service', 'from'])
                                ->mapWithKeys(fn (string $unit): array => [$unit => __("panel.cabinet.rooms.calculation_unit.{$unit}")])
                                ->all())
                            ->required(),

                        TextInput::make('amount')
                            ->label(__('panel.cabinet.rooms.price_form.amount'))
                            ->numeric()
                            ->minValue(0)
                            ->required(),

                        TextInput::make('currency')
                            ->label(__('panel.cabinet.rooms.price_form.currency'))
                            ->required()
                            ->maxLength(3)
                            ->alpha()
                            ->dehydrateStateUsing(fn (?string $state): string => mb_strtoupper((string) $state)),

                        DatePicker::make('valid_from')
                            ->label(__('panel.cabinet.rooms.price_form.valid_from')),

                        DatePicker::make('valid_until')
                            ->label(__('panel.cabinet.rooms.price_form.valid_until')),

                        Textarea::make('comment')
                            ->label(__('panel.cabinet.rooms.price_form.comment'))
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->addActionLabel(__('panel.cabinet.rooms.price_form.add')),
            ]);
    }

    /**
     * One visually-grouped, collapsible section per active language, rather
     * than a nested `Tabs` component — every active language needs to stay
     * visible and editable at once here, matching the object form's own
     * identical mechanism and reasoning.
     *
     * @return list<Section>
     */
    private static function perLanguageSections(callable $fields): array
    {
        return array_values(
            Language::query()
                ->where('is_active', true)
                ->orderBy('display_order')
                ->get()
                ->map(fn (Language $language): Section => Section::make($language->short_label)
                    ->schema($fields($language->code))
                    ->collapsible())
                ->all(),
        );
    }
}
