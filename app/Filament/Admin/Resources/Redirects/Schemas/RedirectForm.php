<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Redirects\Schemas;

use App\Models\Language;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class RedirectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('locale')
                ->label(__('panel.redirects.form.locale'))
                ->options(fn (): array => Language::query()->where('is_active', true)->pluck('short_label', 'code')->all())
                ->required(),

            TextInput::make('from_path')
                ->label(__('panel.redirects.form.from_path'))
                ->helperText(__('panel.redirects.form.from_path_hint'))
                ->required()
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule, callable $get) => $rule->where('locale', $get('locale'))),

            TextInput::make('to_path')
                ->label(__('panel.redirects.form.to_path'))
                ->helperText(__('panel.redirects.form.to_path_hint'))
                ->required(),

            Toggle::make('is_active')
                ->label(__('panel.redirects.form.active'))
                ->default(true),
        ]);
    }
}
