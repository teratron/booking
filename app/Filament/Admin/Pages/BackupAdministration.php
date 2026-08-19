<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Jobs\DatabaseBackupJob;
use App\Models\User;
use App\Services\Backup\BackupAdministrationService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use Spatie\Backup\BackupDestination\Backup;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * The staff-facing backup screen the specification names: the real
 * timestamp of the last successful backup, a manual "run now" trigger, the
 * backup log, and a downloadable technical report — plus a staleness
 * warning once the last database backup has aged past the configured
 * threshold.
 *
 * Every figure here is read live through {@see BackupAdministrationService},
 * which in turn reads Spatie's own destination-disk listing — nothing on
 * this screen is a separately tracked date column that could drift from
 * what actually landed on the disk. Restoring an artefact is deliberately
 * not offered here: it is the portal's single most destructive action and
 * earns its own re-authentication gate on its own screen.
 */
class BackupAdministration extends Page
{
    protected string $view = 'filament.admin.pages.backup-administration';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static string|UnitEnum|null $navigationGroup = 'system';

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->can('settings_management');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.backup_administration.navigation_label');
    }

    public function getTitle(): string
    {
        return __('panel.backup_administration.title');
    }

    public function lastDatabaseBackup(): ?Backup
    {
        return $this->service()->lastDatabaseBackup();
    }

    public function lastMediaBackupDate(): ?Carbon
    {
        return $this->service()->lastMediaBackupDate();
    }

    public function isDatabaseBackupStale(): bool
    {
        return $this->service()->isDatabaseBackupStale();
    }

    public function stalenessThresholdHours(): int
    {
        return $this->service()->stalenessThresholdHours();
    }

    /** @return Collection<int, Backup> */
    public function databaseBackupHistory(): Collection
    {
        return $this->service()->databaseBackupHistory();
    }

    /** @return Collection<int, array{path: string, date: Carbon}> */
    public function mediaGenerationHistory(): Collection
    {
        return $this->service()->mediaGenerationHistory();
    }

    /**
     * Queues the database backup — the same job the daily schedule
     * dispatches (see routes/console.php) — rather than running it inline:
     * a spreadsheet-sized dump has no place inside a web request's own
     * time budget, manual trigger or not.
     */
    public function runBackupNow(): void
    {
        DatabaseBackupJob::dispatch();

        Notification::make()
            ->title(__('panel.backup_administration.notifications.queued'))
            ->success()
            ->send();
    }

    public function backupSizeLabel(Backup $backup): string
    {
        return Number::fileSize($backup->sizeInBytes());
    }

    public function downloadTechnicalReport(): StreamedResponse
    {
        $report = $this->service()->technicalReport();
        $fileName = 'backup-technical-report-'.now()->format('Y-m-d_His').'.txt';

        return response()->streamDownload(function () use ($report): void {
            echo $report;
        }, $fileName, ['Content-Type' => 'text/plain']);
    }

    private function service(): BackupAdministrationService
    {
        return app(BackupAdministrationService::class);
    }
}
