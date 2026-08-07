<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Owners\Schemas;

use App\Models\Country;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * The owner account's own fields — contact details plus the country axis its
 * grant scoping reads from. No password field: an administrator opening this
 * screen never sees or sets an owner's credential, only triggers a reset
 * link the owner completes themselves — that action lives on the edit page,
 * not this form, since it is not a value this record persists.
 */
class OwnerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('panel.owners.form.name'))
                ->required()
                ->maxLength(255),

            TextInput::make('email')
                ->label(__('panel.owners.form.email'))
                ->email()
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),

            TextInput::make('company')
                ->label(__('panel.owners.form.company'))
                ->maxLength(255),

            TextInput::make('phone')
                ->label(__('panel.owners.form.phone'))
                ->tel()
                ->maxLength(255),

            Select::make('country_id')
                ->label(__('panel.owners.form.country'))
                ->options(fn (): array => Country::query()->orderBy('display_order')->pluck('code', 'id')->all())
                ->searchable(),
        ]);
    }
}
