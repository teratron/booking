<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Jobs\DatabaseRestoreJob;
use App\Models\User;
use App\Services\Backup\BackupAdministrationService;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;
use Filament\Forms\Components\OneTimeCodeInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use Spatie\Backup\BackupDestination\Backup;
use UnitEnum;

/**
 * The portal's single most destructive administrative action, and its own
 * screen for exactly that reason — selecting an artefact, confirming what
 * it overwrites, and re-authenticating are three genuinely separate steps
 * here, not one confirmation dialog with a password field bolted on.
 *
 * Re-authentication builds on the panel's own native multi-factor system
 * rather than a parallel password check: `chief_administrator`, the only
 * role this screen's own `backup_restore` permission is ever granted to
 * (see `PermissionSeeder`), is also the only role
 * `EnsureSecondFactorForPrivilegedRoles` makes app-based two-factor
 * authentication mandatory for — so every actor who can reach this screen
 * is guaranteed to already have a live authenticator secret to confirm
 * against.
 *
 * The restore itself never runs here: submitting step three only dispatches
 * {@see DatabaseRestoreJob}, which performs and journals the actual work in
 * the background, the same "queue it, do not block the request" posture
 * every other long-running operation in this panel already takes.
 */
final class BackupRestore extends Page
{
    protected string $view = 'filament.admin.pages.backup-restore';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static string|UnitEnum|null $navigationGroup = 'system';

    public ?string $selectedBackupPath = null;

    public bool $isConfirmed = false;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->can('restore', Backup::class);
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.backup_restore.navigation_label');
    }

    public function getTitle(): string
    {
        return __('panel.backup_restore.title');
    }

    /** @return Collection<int, Backup> */
    public function availableBackups(): Collection
    {
        return app(BackupAdministrationService::class)->databaseBackupHistory();
    }

    public function selectedBackup(): ?Backup
    {
        if ($this->selectedBackupPath === null) {
            return null;
        }

        return $this->availableBackups()->first(
            fn (Backup $backup): bool => $backup->path() === $this->selectedBackupPath,
        );
    }

    public function backupSizeLabel(Backup $backup): string
    {
        return Number::fileSize($backup->sizeInBytes());
    }

    /**
     * Step one. Selecting a different artefact resets any confirmation
     * already given for a previous one — the confirmation names a specific
     * timestamp, and that agreement does not carry over to a different row.
     */
    public function selectBackup(string $path): void
    {
        $this->selectedBackupPath = $path;
        $this->isConfirmed = false;
    }

    /** Step two. */
    public function confirmSelection(): void
    {
        if ($this->selectedBackup() instanceof Backup) {
            $this->isConfirmed = true;
        }
    }

    public function resetSelection(): void
    {
        $this->selectedBackupPath = null;
        $this->isConfirmed = false;
    }

    /**
     * The confirmation copy step two renders, naming the selected
     * artefact's own real timestamp — never a relative figure alone, since
     * an operator approving an overwrite needs the exact moment being
     * restored to.
     */
    public function confirmationMessage(): ?string
    {
        $backup = $this->selectedBackup();

        if (! $backup instanceof Backup) {
            return null;
        }

        return __('panel.backup_restore.steps.confirm.description', [
            'timestamp' => $backup->date()->toDateTimeString(),
        ]);
    }

    /** @return list<Action> */
    protected function getHeaderActions(): array
    {
        return [$this->restoreAction()];
    }

    /**
     * Step three. Deliberately visible only once steps one and two are
     * both satisfied, so this action's own modal ever shows nothing but the
     * re-authentication field — the confirmation copy already lives in step
     * two's own section of the page, not repeated here as a bolted-on
     * description.
     */
    private function restoreAction(): Action
    {
        return Action::make('restore')
            ->label(__('panel.backup_restore.actions.restore'))
            ->color('danger')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->visible(fn (): bool => $this->isConfirmed && $this->selectedBackup() instanceof Backup)
            // A usability affordance only — the button hides for an actor
            // who plainly lacks the permission, but the closure below is
            // what actually refuses the restore even if this action were
            // somehow invoked directly.
            ->authorize(fn (): bool => (bool) Filament::auth()->user()?->can('restore', Backup::class))
            ->schema([
                OneTimeCodeInput::make('code')
                    ->label(__('panel.backup_restore.actions.code_label'))
                    ->required()
                    ->rule($this->currentCodeRule()),
            ])
            ->action(function (array $data): void {
                $actor = Filament::auth()->user();

                if (! $actor instanceof User || ! $actor->can('restore', Backup::class)) {
                    abort(403);
                }

                $backup = $this->selectedBackup();

                if (! $backup instanceof Backup) {
                    abort(404);
                }

                DatabaseRestoreJob::dispatch($backup->path(), (int) $actor->getKey());

                $this->resetSelection();

                Notification::make()
                    ->title(__('panel.backup_restore.notifications.queued'))
                    ->success()
                    ->send();
            });
    }

    /**
     * Verifies a currently valid app-authentication code for whoever is
     * signed in, the same {@see AppAuthentication::verifyCode()} primitive
     * the login challenge itself uses — reusing it here is the point: this
     * screen introduces no parallel authentication mechanism of its own.
     * Code reuse is prevented, so replaying an already-accepted code does
     * not count as a fresh re-authentication a second time.
     */
    private function currentCodeRule(): Closure
    {
        return function (): Closure {
            return function (string $attribute, mixed $value, Closure $fail): void {
                $user = Filament::auth()->user();

                if (! $user instanceof User) {
                    $fail(__('panel.backup_restore.actions.code_invalid'));

                    return;
                }

                $provider = app(AppAuthentication::class);

                if (! is_string($value) || ! $provider->isEnabled($user) || ! $provider->verifyCode($value, null, shouldPreventCodeReuse: true)) {
                    $fail(__('panel.backup_restore.actions.code_invalid'));
                }
            };
        };
    }
}
