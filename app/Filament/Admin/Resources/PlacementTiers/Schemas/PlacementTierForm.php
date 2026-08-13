<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PlacementTiers\Schemas;

use App\Models\Language;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Edit-only: `rank` is not a form field here — the four ranks are structural
 * and this screen never creates or renumbers one, only edits a rank's
 * presentation.
 */
class PlacementTierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            ColorPicker::make('border_colour')->label(__('panel.placement_tiers.form.border_colour'))->required(),
            ColorPicker::make('badge_colour')->label(__('panel.placement_tiers.form.badge_colour'))->required(),
            TextInput::make('badge_icon')->label(__('panel.placement_tiers.form.badge_icon')),
            Toggle::make('is_active')->label(__('panel.placement_tiers.columns.active'))->default(true),

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
                        TextInput::make("translations.{$language->code}.label")
                            ->label(__('panel.placement_tiers.form.label'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make("translations.{$language->code}.badge_text")
                            ->label(__('panel.placement_tiers.form.badge_text'))
                            ->required()
                            ->maxLength(255),
                    ]))
                ->all(),
        );
    }
}
