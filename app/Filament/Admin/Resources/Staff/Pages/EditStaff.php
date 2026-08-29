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
use Override;

/**
 * The staff form's edit page — the whole save (contact fields plus the
 * active/inactive toggle) goes through the single
 * {@see StaffAccountService::saveEdit()} call, atomic there so the
 * last-remaining-chief-administrator guard on deactivation always runs and
 * a refusal can never leave the contact edits committed on their own — a
 * partial "success" here would tell the administrator the toggle worked
 * when it did not.
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

        try {
            app(StaffAccountService::class)->saveEdit($record, $data, $actor);
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
