<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Owners\Pages;

use App\Filament\Admin\Resources\Owners\OwnerResource;
use App\Models\User;
use App\Services\Authorization\ScopeAuthorizer;
use App\Services\Owners\OwnerAccountService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Override;

/**
 * The owner form's edit page — field saves through
 * {@see OwnerAccountService::updateContacts()} plus the three account-level
 * actions beyond an ordinary save: suspending access, lifting a suspension,
 * and sending a fresh password-reset link.
 */
class EditOwner extends EditRecord
{
    protected static string $resource = OwnerResource::class;

    /**
     * Saves through the service rather than a bare `$record->update()`, so
     * contact changes get the same journal entry every other account
     * mutation on this screen does, and only the fields that actually
     * changed are recorded.
     *
     * The resource's own query already keeps a country-scoped administrator
     * from opening a record outside their grant, but the form's own
     * `country_id` field is not itself scope-limited — without this check a
     * scoped administrator could reassign an in-scope owner to a country
     * outside their grant, which is the same field-not-hidden gap
     * {@see CreateOwner::assertWithinScope()} closes on the create side.
     */
    #[Override]
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $record */
        $countryId = isset($data['country_id']) ? (int) $data['country_id'] : null;

        $this->assertWithinScope($countryId);

        $actor = Filament::auth()->user();

        if ($actor instanceof User) {
            app(OwnerAccountService::class)->updateContacts($record, $data, $actor);
        }

        return $record;
    }

    private function assertWithinScope(?int $countryId): void
    {
        $actor = Filament::auth()->user();

        if (! $actor instanceof User) {
            throw ValidationException::withMessages(['data.country_id' => __('panel.owners.form.out_of_scope')]);
        }

        $constraint = app(ScopeAuthorizer::class)->constraintFor($actor, 'user.edit');

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

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            $this->blockAction(),
            $this->restoreAction(),
            $this->sendPasswordResetLinkAction(),
        ];
    }

    private function blockAction(): Action
    {
        return Action::make('block')
            ->label(__('panel.owners.actions.block'))
            ->color('danger')
            ->authorize(fn (User $owner): bool => (bool) auth()->user()?->can('block', $owner))
            ->visible(fn (User $owner): bool => $owner->blocked_at === null)
            ->requiresConfirmation()
            ->modalDescription(__('panel.owners.actions.block_confirm'))
            ->action(function (): void {
                $actor = Filament::auth()->user();

                if ($actor instanceof User) {
                    app(OwnerAccountService::class)->block($this->currentOwner(), $actor);
                }

                Notification::make()->title(__('panel.owners.actions.applied'))->success()->send();
            });
    }

    private function restoreAction(): Action
    {
        return Action::make('restore')
            ->label(__('panel.owners.actions.restore'))
            ->authorize(fn (User $owner): bool => (bool) auth()->user()?->can('restore', $owner))
            ->visible(fn (User $owner): bool => $owner->blocked_at !== null)
            ->requiresConfirmation()
            ->modalDescription(__('panel.owners.actions.restore_confirm'))
            ->action(function (): void {
                $actor = Filament::auth()->user();

                if ($actor instanceof User) {
                    app(OwnerAccountService::class)->restore($this->currentOwner(), $actor);
                }

                Notification::make()->title(__('panel.owners.actions.applied'))->success()->send();
            });
    }

    private function sendPasswordResetLinkAction(): Action
    {
        return Action::make('send_password_reset_link')
            ->label(__('panel.owners.actions.send_password_reset_link'))
            ->authorize(fn (User $owner): bool => (bool) auth()->user()?->can('update', $owner))
            ->action(function (): void {
                $actor = Filament::auth()->user();

                if ($actor instanceof User) {
                    app(OwnerAccountService::class)->sendPasswordResetLink($this->currentOwner(), $actor);
                }

                Notification::make()->title(__('panel.owners.actions.password_reset_link_sent'))->success()->send();
            });
    }

    private function currentOwner(): User
    {
        /** @var User $record */
        $record = $this->getRecord();

        return $record;
    }
}
