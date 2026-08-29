<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Staff\Pages;

use App\Exceptions\UnrevocableGrantException;
use App\Filament\Admin\Resources\Staff\StaffResource;
use App\Models\User;
use App\Services\Staff\StaffAccountService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Override;

/**
 * The staff form's edit page — contact saves through
 * {@see StaffAccountService::updateContacts()}, and the status toggle
 * translated into a {@see StaffAccountService::deactivate()} or
 * {@see StaffAccountService::restore()} call rather than a bare column
 * write, so the last-remaining-chief-administrator guard on deactivation
 * always runs.
 *
 * A refused deactivation halts the save entirely rather than silently
 * saving the contact changes while leaving the account active — a partial
 * "success" here would tell the administrator the toggle worked when it did
 * not.
 */
class EditStaff extends EditRecord
{
    protected static string $resource = StaffResource::class;

    #[Override]
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $record */
        $actor = Filament::auth()->user();

        if (! $actor instanceof User) {
            return $record;
        }

        $service = app(StaffAccountService::class);
        $wantsActive = (bool) ($data['is_active'] ?? true);

        try {
            // Wrapped explicitly rather than relying on the save action's own
            // transaction: Filament only opens one when a panel calls
            // `->databaseTransactions()`, which neither panel here does. Without
            // this, a refused deactivation still leaves updateContacts()'s write
            // committed — exactly the partial "success" this class's own
            // docblock says can never happen.
            DB::transaction(function () use ($service, $record, $data, $actor, $wantsActive): void {
                $service->updateContacts($record, $data, $actor);

                if ($wantsActive && $record->blocked_at !== null) {
                    $service->restore($record, $actor);
                } elseif (! $wantsActive && $record->blocked_at === null) {
                    $service->deactivate($record, $actor);
                }
            });
        } catch (UnrevocableGrantException) {
            Notification::make()
                ->danger()
                ->title(__('panel.staff.actions.deactivation_refused'))
                ->send();

            throw new Halt;
        }

        return $record;
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            $this->resetTwoFactorAction(),
        ];
    }

    /**
     * Clears a staff account's own two-factor enrolment, forcing it to
     * enrol again on its next sign-in — the "reset" capability the
     * specification names, exposed here since Filament's own multi-factor
     * actions (`DisableAppAuthenticationAction` and friends) are wired
     * exclusively to `Filament::auth()->user()`, the *acting* session, with
     * no built-in way to target another account. This calls the same
     * underlying model contract those actions do
     * ({@see User::saveAppAuthenticationSecret()}) against the record being
     * edited instead — not a second two-factor implementation, the same one
     * administered for someone other than its own holder.
     */
    private function resetTwoFactorAction(): Action
    {
        return Action::make('reset_two_factor')
            ->label(__('panel.staff.actions.reset_two_factor'))
            ->color('warning')
            ->authorize(fn (): bool => (bool) Filament::auth()->user()?->hasRole('chief_administrator'))
            ->visible(fn (User $record): bool => $record->twoFactorSecret()->exists())
            ->requiresConfirmation()
            ->modalDescription(__('panel.staff.actions.reset_two_factor_confirm'))
            ->action(function (): void {
                $this->currentStaff()->saveAppAuthenticationSecret(null);

                Notification::make()->title(__('panel.staff.actions.applied'))->success()->send();
            });
    }

    private function currentStaff(): User
    {
        /** @var User $record */
        $record = $this->getRecord();

        return $record;
    }
}
