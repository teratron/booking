<?php

declare(strict_types=1);

use App\Filament\Admin\Pages\BackupAdministration;
use App\Jobs\DatabaseBackupJob;
use App\Models\Notification;
use App\Models\User;
use App\Services\Backup\BackupAdministrationService;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\NotificationChannelSeeder;
use Database\Seeders\NotificationTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Backup\Events\UnhealthyBackupWasFound;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Backup Administration
|--------------------------------------------------------------------------
|
| The screen the specification names: the real last-backup timestamp read
| straight off the destination disk (never a separately tracked date), a
| manual "run now" trigger, the backup log, a downloadable technical
| report, and a staleness warning once the last database backup has aged
| past the configured threshold. A simulated failure must reach the
| platform's own notification model exactly once per administrator.
|
*/

function backupAdminActor(array $permissions = ['admin_panel_access', 'settings_management']): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate('backup_admin_role', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user->fresh();
}

it("renders the last database backup's real timestamp, read from the destination disk", function (): void {
    $actor = backupAdminActor();
    $disk = Storage::fake('backups');
    $backupName = (string) config('backup.backup.name');

    $timestamp = now()->subHours(2);
    $fileName = $timestamp->format('Y-m-d-H-i-s');
    $disk->put("{$backupName}/{$fileName}.zip", 'placeholder-zip-bytes');

    $response = $this->actingAs($actor)->get(BackupAdministration::getUrl(panel: 'admin'));

    $response->assertSuccessful();
    $response->assertSee($timestamp->toDateTimeString());
    // Freshly written, well within the default threshold — no staleness warning.
    $response->assertDontSee(__('panel.backup_administration.staleness_warning', ['hours' => app(BackupAdministrationService::class)->stalenessThresholdHours()]));
});

it('renders the staleness warning once the last database backup exceeds the configured threshold', function (): void {
    config(['booking.backups.staleness_threshold_hours' => 24]);

    $actor = backupAdminActor();
    $disk = Storage::fake('backups');
    $backupName = (string) config('backup.backup.name');

    $old = now()->subHours(48);
    $oldFileName = $old->format('Y-m-d-H-i-s');
    $disk->put("{$backupName}/{$oldFileName}.zip", 'placeholder-zip-bytes');

    $response = $this->actingAs($actor)->get(BackupAdministration::getUrl(panel: 'admin'));

    $response->assertSuccessful();
    $response->assertSee(__('panel.backup_administration.staleness_warning', ['hours' => 24]));

    expect(app(BackupAdministrationService::class)->isDatabaseBackupStale())->toBeTrue();
});

it('refuses the screen to a user without the settings-management permission', function (): void {
    $actor = backupAdminActor(['admin_panel_access']);

    $this->actingAs($actor)->get(BackupAdministration::getUrl(panel: 'admin'))->assertForbidden();
});

it('dispatches the database backup job as a queued job when the manual action runs', function (): void {
    $actor = backupAdminActor();
    Queue::fake();

    Livewire::actingAs($actor)->test(BackupAdministration::class)
        ->call('runBackupNow');

    Queue::assertPushed(DatabaseBackupJob::class);
});

it('raises exactly one notification to the administrator role when an unhealthy backup is found', function (): void {
    $this->seed([LanguageSeeder::class, NotificationChannelSeeder::class, NotificationTypeSeeder::class]);
    $actor = backupAdminActor();

    expect(Notification::query()->count())->toBe(0);

    event(new UnhealthyBackupWasFound(
        diskName: 'backups',
        backupName: (string) config('backup.backup.name'),
        failureMessages: collect([['check' => 'MaximumAgeInDays', 'message' => 'The backup is too old.']]),
    ));

    expect(Notification::query()->count())->toBe(1);

    $notification = Notification::query()->first();
    expect($notification->recipient_id)->toBe($actor->id)
        ->and($notification->body)->toContain('MaximumAgeInDays');
});

it('raises no notification for a simulated failure when no account holds the settings-management permission', function (): void {
    $this->seed([LanguageSeeder::class, NotificationChannelSeeder::class, NotificationTypeSeeder::class]);

    event(new UnhealthyBackupWasFound(
        diskName: 'backups',
        backupName: (string) config('backup.backup.name'),
        failureMessages: collect([['check' => 'MaximumAgeInDays', 'message' => 'The backup is too old.']]),
    ));

    expect(Notification::query()->count())->toBe(0);
});

it('downloads the technical report as a plain-text file naming the destination disk', function (): void {
    $actor = backupAdminActor();
    Storage::fake('backups');

    Livewire::actingAs($actor)->test(BackupAdministration::class)
        ->call('downloadTechnicalReport')
        ->assertFileDownloaded();

    // Content is asserted independently of the download effect, since the
    // downloaded filename embeds the current second and would otherwise be
    // a flaky string to assert against directly.
    $report = app(BackupAdministrationService::class)->technicalReport();

    expect($report)->toContain('Backup Technical Report')
        ->and($report)->toContain('Destination disk: backups')
        ->and($report)->toContain('Health checks');
});
