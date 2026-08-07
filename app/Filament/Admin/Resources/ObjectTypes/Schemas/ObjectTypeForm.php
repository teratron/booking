<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ObjectTypes\Schemas;

use App\Models\AmenityGroup;
use App\Models\Language;
use App\Models\ObjectType;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The registry that makes "a catalog view must render only the fields its
 * type declares" true. `attribute_schema` is what the object form's dynamic
 * tab reads to decide which extra fields an object of this type exposes —
 * declared here as data, so a new type an administrator invents needs no
 * code change to gain its own field set.
 */
class ObjectTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')
                ->label(__('panel.object_types.form.key'))
                ->required()
                ->unique(ignoreRecord: true)
                ->alphaDash(),

            Select::make('parent_id')
                ->label(__('panel.object_types.form.parent'))
                ->options(fn (?ObjectType $record): array => ObjectType::query()
                    ->when($record, fn ($query) => $query->where('id', '!=', $record?->id))
                    ->with('translations')
                    ->get()
                    ->mapWithKeys(fn (ObjectType $type): array => [$type->id => $type->name ?? $type->key])
                    ->all())
                ->placeholder(__('panel.object_types.form.no_parent')),

            TextInput::make('icon_path')->label(__('panel.object_types.form.icon')),

            Toggle::make('has_rooms')->label(__('panel.object_types.form.has_rooms')),
            Toggle::make('has_availability_status')->label(__('panel.object_types.form.has_availability_status')),
            Toggle::make('is_active')->label(__('panel.object_types.columns.active'))->default(true),
            TextInput::make('display_order')->label(__('panel.object_types.form.display_order'))->numeric()->default(0),

            Select::make('amenityGroups')
                ->relationship('amenityGroups', 'id')
                ->multiple()
                ->label(__('panel.object_types.form.amenity_groups'))
                ->getOptionLabelFromRecordUsing(fn (AmenityGroup $group): string => $group->name ?? "#{$group->id}")
                ->options(fn (): array => AmenityGroup::query()->with('translations')->get()
                    ->mapWithKeys(fn (AmenityGroup $group): array => [$group->id => $group->name ?? "#{$group->id}"])
                    ->all()),

            Section::make(__('panel.object_types.form.attribute_schema'))
                ->description(__('panel.object_types.form.attribute_schema_hint'))
                ->schema([
                    Repeater::make('attribute_schema')
                        ->label('')
                        ->schema([
                            TextInput::make('key')
                                ->label(__('panel.object_types.form.attribute_key'))
                                ->required()
                                ->alphaDash(),
                            Select::make('type')
                                ->label(__('panel.object_types.form.attribute_type'))
                                ->options([
                                    'text' => __('panel.object_types.form.attribute_types.text'),
                                    'number' => __('panel.object_types.form.attribute_types.number'),
                                    'boolean' => __('panel.object_types.form.attribute_types.boolean'),
                                ])
                                ->required(),
                            ...self::attributeLabelFields(),
                        ])
                        ->defaultItems(0)
                        ->collapsible(),
                ]),

            ...self::perLanguageSections(),
        ]);
    }

    /** @return list<TextInput> */
    private static function attributeLabelFields(): array
    {
        return array_values(
            Language::query()
                ->where('is_active', true)
                ->orderBy('display_order')
                ->get()
                ->map(fn (Language $language): TextInput => TextInput::make("label.{$language->code}")
                    ->label(__('panel.object_types.form.attribute_label', ['language' => $language->short_label])))
                ->all(),
        );
    }

    /** @return list<Section> */
    private static function perLanguageSections(): array
    {
        return array_values(
            Language::query()
                ->where('is_active', true)
                ->orderBy('display_order')
                ->get()
                ->map(fn (Language $language): Section => Section::make($language->short_label)
                    ->collapsible()
                    ->schema([
                        TextInput::make("translations.{$language->code}.name")
                            ->label(__('panel.object_types.form.name'))
                            ->maxLength(255),
                        TextInput::make("translations.{$language->code}.seo_title")
                            ->label(__('panel.object_types.form.seo_title')),
                        TextInput::make("translations.{$language->code}.seo_description")
                            ->label(__('panel.object_types.form.seo_description')),
                    ]))
                ->all(),
        );
    }
}
