<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BannerSlots\Schemas;

use App\Models\Language;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * `surfaces` is a fixed, enumerable tag list (page kinds the portal renders),
 * not a foreign-keyed entity — a multi-select over static options is correct
 * even though the column itself stores a JSON array.
 */
class BannerSlotForm
{
    /** @var list<string> */
    private const SURFACES = ['home', 'country', 'region', 'city', 'resort', 'category', 'object', 'news', 'article'];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')
                ->label(__('panel.banner_slots.form.key'))
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),

            Select::make('surfaces')
                ->label(__('panel.banner_slots.form.surfaces'))
                ->options(self::surfaceOptions())
                ->multiple()
                ->required(),

            Toggle::make('is_active')->label(__('panel.banner_slots.columns.active'))->default(true),

            ...self::perLanguageSections(),
        ]);
    }

    /** @return array<string, string> */
    private static function surfaceOptions(): array
    {
        return collect(self::SURFACES)
            ->mapWithKeys(fn (string $surface): array => [$surface => __("panel.banner_slots.form.surface_options.{$surface}")])
            ->all();
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
                            ->label(__('panel.banner_slots.form.name'))
                            ->required()
                            ->maxLength(255),
                    ]))
                ->all(),
        );
    }
}
