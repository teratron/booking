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

    /**
     * Memoized rather than resolved fresh per call: the view calls several
     * of this page's methods in sequence during one render, and
     * {@see BackupAdministrationService::destinationUnreachable()}'s own
     * memo only means anything if every one of those calls shares the same
     * service instance.
     */
    private ?BackupAdministrationService $service = null;

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

    /**
     * The whole screen's data, from a 15-minute cache — the live version of
     * this enumerated remote object storage on every render and cost ~7 s.
     * Refreshed by the daily `backup:monitor` schedule and by
     * {@see self::recheckNow()}.
     *
     * @return array{
     *     generated_at: string, unreachable: bool, is_stale: bool,
     *     staleness_threshold_hours: int, last_database_backup_at: ?string,
     *     last_media_backup_at: ?string,
     *     database_history: list<array{date: string, size: string}>,
     *     media_history: list<array{date: string}>,
     * }
     */
    public function snapshot(): array
    {
        return $this->service()->viewSnapshot();
    }

    public function recheckNow(): void
    {
        $this->service()->forgetViewSnapshot();

        Notification::make()
            ->title(__('panel.backup_administration.notifications.rechecked'))
            ->success()
            ->send();
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
        return $this->service ??= app(BackupAdministrationService::class);
    }
}
