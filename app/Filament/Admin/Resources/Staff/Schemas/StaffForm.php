<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Staff\Schemas;

use App\Models\User;
use App\Services\Staff\StaffAccountService;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

/**
 * The staff account's own fields. The password field only ever appears on
 * creation — a chief administrator hands a colleague their first credential
 * directly, unlike the owner-management screen's reset-link flow, since
 * this is one staff member setting up another's access, not a
 * self-registration handoff. The status toggle only appears once a record
 * exists: a brand-new account has nothing to deactivate yet, and its actual
 * effect is applied through {@see StaffAccountService}
 * from the edit page rather than by this field alone, so the
 * last-remaining-chief-administrator guard is never bypassable by a form
 * submission the service never sees.
 */
class StaffForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('panel.staff.form.name'))
                ->required()
                ->maxLength(255),

            TextInput::make('email')
                ->label(__('panel.staff.form.email'))
                ->email()
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),

            TextInput::make('password')
                ->label(__('panel.staff.form.password'))
                ->password()
                ->revealable()
                ->minLength(12)
                ->visible(fn (?User $record): bool => $record === null)
                ->required(fn (?User $record): bool => $record === null)
                ->dehydrated(fn (?User $record): bool => $record === null),

            Toggle::make('is_active')
                ->label(__('panel.staff.form.is_active'))
                ->default(true)
                ->formatStateUsing(fn (?User $record): bool => $record === null || $record->blocked_at === null)
                ->visible(fn (?User $record): bool => $record !== null)
                ->dehydrated(fn (?User $record): bool => $record !== null),
        ]);
    }
}
