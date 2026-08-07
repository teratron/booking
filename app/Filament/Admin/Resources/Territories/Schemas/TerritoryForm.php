<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Territories\Schemas;

use App\Models\Country;
use App\Models\Language;
use App\Models\TerritoryLevel;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Every field the geography requirements name for territory administration:
 * names in all languages, level, descriptions, coordinates, SEO fields,
 * display order, and active flag. Parent is deliberately not a plain field
 * here — reparenting is a dedicated edit-page action, because it needs the
 * counted confirmation and cycle guard a plain field save cannot express.
 */
class TerritoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('country_id')
                ->label(__('panel.territories.columns.country'))
                ->options(fn (): array => Country::query()->orderBy('display_order')->pluck('code', 'id')->all())
                ->required()
                ->live(),

            Select::make('level_id')
                ->label(__('panel.territories.columns.level'))
                ->options(function (Get $get): array {
                    $query = TerritoryLevel::query()->with('translations');

                    if ($get('country_id')) {
                        $query->where('country_id', $get('country_id'));
                    }

                    return $query->get()
                        ->mapWithKeys(fn (TerritoryLevel $level): array => [$level->id => $level->singular_name ?? "#{$level->id}"])
                        ->all();
                })
                ->required(),

            TextInput::make('latitude')->numeric(),
            TextInput::make('longitude')->numeric(),

            Toggle::make('is_active')
                ->label(__('panel.territories.columns.active'))
                ->default(true),

            TextInput::make('display_order')
                ->label(__('panel.territories.form.display_order'))
                ->numeric()
                ->default(0),

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
                        TextInput::make("translations.{$language->code}.name")
                            ->label(__('panel.territories.form.name'))
                            ->maxLength(255),
                        TextInput::make("translations.{$language->code}.slug")
                            ->label(__('panel.territories.form.slug')),
                        TextInput::make("translations.{$language->code}.short_description")
                            ->label(__('panel.territories.form.short_description')),
                        TextInput::make("translations.{$language->code}.seo_title")
                            ->label(__('panel.territories.form.seo_title')),
                    ]))
                ->all(),
        );
    }
}
