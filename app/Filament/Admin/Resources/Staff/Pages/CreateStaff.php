<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Staff\Pages;

use App\Filament\Admin\Resources\Staff\StaffResource;
use App\Models\User;
use App\Services\Staff\StaffAccountService;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Override;

/**
 * Delegates account creation to
 * {@see StaffAccountService::createAccount()} rather than a bare Eloquent
 * create — the service owns the journal entry, and mirrors
 * `CreateOwner`'s shape for the reason `StaffAccountService`'s own docblock
 * states.
 */
class CreateStaff extends CreateRecord
{
    protected static string $resource = StaffResource::class;

    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        $actor = Filament::auth()->user();

        if (! $actor instanceof User) {
            throw ValidationException::withMessages(['data.name' => __('panel.staff.form.out_of_scope')]);
        }

        return app(StaffAccountService::class)->createAccount([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ], $actor);
    }
}
