<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Owners\Pages;

use App\Filament\Admin\Resources\Owners\OwnerResource;
use App\Models\User;
use App\Services\Authorization\ScopeAuthorizer;
use App\Services\Owners\OwnerAccountService;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Override;

/**
 * Delegates account creation to {@see OwnerAccountService::createAccount()}
 * rather than a bare Eloquent create — the service owns the random
 * password, the `object_owner` role assignment, the reset-link dispatch,
 * and the journal entry, and none of that belongs duplicated here.
 *
 * Creation has no existing record for the policy's `create` ability to
 * scope against, so a country-scoped administrator submitting a country
 * outside their grant would otherwise slip through the field being merely
 * present rather than hidden. Validated here instead, against the same
 * authorizer every list and policy already uses.
 */
class CreateOwner extends CreateRecord
{
    protected static string $resource = OwnerResource::class;

    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        $countryId = isset($data['country_id']) ? (int) $data['country_id'] : null;

        $this->assertWithinScope($countryId);

        $actor = Filament::auth()->user();

        if (! $actor instanceof User) {
            throw ValidationException::withMessages(['data.name' => __('panel.owners.form.out_of_scope')]);
        }

        return app(OwnerAccountService::class)->createAccount([
            'name' => $data['name'],
            'email' => $data['email'],
            'company' => $data['company'] ?? null,
            'phone' => $data['phone'] ?? null,
            'country_id' => $countryId,
        ], $actor);
    }

    private function assertWithinScope(?int $countryId): void
    {
        $actor = Filament::auth()->user();

        if (! $actor instanceof User) {
            throw ValidationException::withMessages(['data.country_id' => __('panel.owners.form.out_of_scope')]);
        }

        $constraint = app(ScopeAuthorizer::class)->constraintFor($actor, 'user.create');

        if ($constraint->isUnrestricted) {
            return;
        }

        if ($constraint->reachesNothing()) {
            throw ValidationException::withMessages(['data.country_id' => __('panel.owners.form.out_of_scope')]);
        }

        if ($constraint->countryIds !== [] && ($countryId === null || ! in_array($countryId, $constraint->countryIds, true))) {
            throw ValidationException::withMessages(['data.country_id' => __('panel.owners.form.out_of_scope')]);
        }
    }
}
