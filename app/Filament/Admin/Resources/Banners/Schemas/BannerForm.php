<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Banners\Schemas;

use App\Models\BannerSlot;
use App\Models\Language;
use App\Models\ObjectType;
use App\Models\Territory;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Territory, category, and language targeting are left empty by default —
 * per the `banner_targets` invariant, an empty selection means "eligible
 * everywhere on this dimension", not "eligible nowhere".
 */
class BannerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('banner_slot_id')
                ->label(__('panel.banners.form.slot'))
                ->required()
                ->options(fn (): array => BannerSlot::query()
                    ->with('translations')
                    ->get()
                    ->mapWithKeys(fn (BannerSlot $slot): array => [$slot->id => $slot->name ?? $slot->key])
                    ->all()),

            TextInput::make('name')
                ->label(__('panel.banners.form.name'))
                ->required()
                ->maxLength(255),

            TextInput::make('advertiser')
                ->label(__('panel.banners.form.advertiser'))
                ->required()
                ->maxLength(255),

            TextInput::make('destination_link')
                ->label(__('panel.banners.form.destination_link'))
                ->required()
                ->url()
                ->maxLength(255),

            DatePicker::make('starts_at')
                ->label(__('panel.banners.form.starts_at'))
                ->required(),

            DatePicker::make('ends_at')
                ->label(__('panel.banners.form.ends_at'))
                ->required(),

            Toggle::make('is_active')->label(__('panel.banners.columns.active'))->default(true),
            TextInput::make('display_order')->label(__('panel.banners.form.display_order'))->numeric()->default(0),

            SpatieMediaLibraryFileUpload::make('desktop_creative')
                ->label(__('panel.banners.form.desktop_creative'))
                ->collection('desktop_creative')
                ->image(),

            SpatieMediaLibraryFileUpload::make('mobile_creative')
                ->label(__('panel.banners.form.mobile_creative'))
                ->collection('mobile_creative')
                ->image(),

            Select::make('territories')
                ->relationship('territories', 'id')
                ->multiple()
                ->label(__('panel.banners.form.territories'))
                ->getOptionLabelFromRecordUsing(fn (Territory $territory): string => $territory->name ?? "#{$territory->id}")
                ->searchable(),

            Select::make('categories')
                ->relationship('categories', 'id')
                ->multiple()
                ->label(__('panel.banners.form.categories'))
                ->getOptionLabelFromRecordUsing(fn (ObjectType $category): string => $category->name ?? $category->key)
                ->searchable(),

            Select::make('targetLanguages')
                ->relationship('targetLanguages', 'id')
                ->multiple()
                ->label(__('panel.banners.form.target_languages'))
                ->getOptionLabelFromRecordUsing(fn (Language $language): string => $language->short_label),

            ...self::perLanguageSections(),
        ]);
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
                        TextInput::make("translations.{$language->code}.link_text")
                            ->label(__('panel.banners.form.link_text'))
                            ->required()
                            ->maxLength(255),
                    ]))
                ->all(),
        );
    }
}
