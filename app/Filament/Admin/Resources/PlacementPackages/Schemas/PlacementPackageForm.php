<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PlacementPackages\Schemas;

use App\Models\Language;
use App\Models\ObjectType;
use App\Models\PlacementTier;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Per the package-parity invariant, nothing here varies photo count, contact
 * count, or description length — the only package-varying capability beyond
 * tier is bumping, so the bump fields are the only ones this form makes
 * conditional on a toggle.
 */
class PlacementPackageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('placement_tier_id')
                ->label(__('panel.placement_packages.form.tier'))
                ->required()
                ->options(fn (): array => PlacementTier::query()
                    ->with('translations')
                    ->orderBy('rank')
                    ->get()
                    ->mapWithKeys(fn (PlacementTier $tier): array => [$tier->id => $tier->label ?? "#{$tier->rank}"])
                    ->all()),

            Select::make('object_type_id')
                ->label(__('panel.placement_packages.form.object_type'))
                ->placeholder(__('panel.placement_packages.form.any_object_type'))
                ->options(fn (): array => ObjectType::query()
                    ->with('translations')
                    ->get()
                    ->mapWithKeys(fn (ObjectType $type): array => [$type->id => $type->name ?? $type->key])
                    ->all()),

            TextInput::make('price')
                ->label(__('panel.placement_packages.form.price'))
                ->required()
                ->numeric()
                ->minValue(0),

            TextInput::make('currency')
                ->label(__('panel.placement_packages.form.currency'))
                ->required()
                ->maxLength(3)
                ->alpha()
                ->dehydrateStateUsing(fn (?string $state): string => mb_strtoupper((string) $state)),

            TextInput::make('validity_days')
                ->label(__('panel.placement_packages.form.validity_days'))
                ->required()
                ->numeric()
                ->minValue(1),

            Toggle::make('bump_allowed')
                ->label(__('panel.placement_packages.form.bump_allowed'))
                ->live()
                ->default(false),

            TextInput::make('bump_interval_hours')
                ->label(__('panel.placement_packages.form.bump_interval_hours'))
                ->numeric()
                ->minValue(1)
                ->visible(fn (Get $get): bool => (bool) $get('bump_allowed')),

            TextInput::make('free_bumps_per_period')
                ->label(__('panel.placement_packages.form.free_bumps_per_period'))
                ->numeric()
                ->minValue(0)
                ->visible(fn (Get $get): bool => (bool) $get('bump_allowed')),

            TextInput::make('paid_bump_price')
                ->label(__('panel.placement_packages.form.paid_bump_price'))
                ->numeric()
                ->minValue(0)
                ->visible(fn (Get $get): bool => (bool) $get('bump_allowed')),

            Toggle::make('is_active')->label(__('panel.placement_packages.columns.active'))->default(true),
            TextInput::make('display_order')->label(__('panel.placement_packages.form.display_order'))->numeric()->default(0),

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
                            ->label(__('panel.placement_packages.form.name'))
                            ->required()
                            ->maxLength(255),
                    ]))
                ->all(),
        );
    }
}
