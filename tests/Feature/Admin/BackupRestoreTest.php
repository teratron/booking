<?php

declare(strict_types=1);

use App\Filament\Admin\Pages\BackupRestore;
use App\Jobs\DatabaseRestoreJob;
use App\Models\User;
use App\Services\Audit\AuditJournal;
use App\Services\Backup\DatabaseRestoreService;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\NotificationChannelSeeder;
use Database\Seeders\NotificationTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use OwenIt\Auditing\Models\Audit;
use PragmaRX\Google2FA\Google2FA;
use ReflectionClass;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Administrator-Triggered Restore Behind Re-Authentication
|--------------------------------------------------------------------------
|
| Selecting an artefact, confirming what it overwrites, and re-authenticating
| are three genuinely separate steps. Restoring never runs inline — the
| screen only ever dispatches DatabaseRestoreJob, which performs and
| journals the actual work in the background.
|
| The job's real success path — a genuine `psql` replay — is deliberately
| NOT exercised here: it drops and recreates the `public` schema of
| whichever database `database.default` names, via a raw subprocess outside
| any Laravel transaction, so running it for real against this suite's own
| shared test database would corrupt every test that runs after it in the
| same process. The real end-to-end mechanics are the next task's own
| purpose (a real artefact restored into a genuinely empty database). What
| this file verifies instead: the screen's three-step gate, the Policy-level
| refusal, and both journalling branches — success via `recordOutcome()`
| directly (the exact method the real success path also calls), failure via
| the real service against a genuinely missing artefact, which fails safely
| before ever invoking `psql`.
|
*/

/** @param  list<string>  $permissions */
function backupRestoreActor(array $permissions, string $roleKey): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate($roleKey, 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user->fresh();
}

function backupRestoreAdministrator(): User
{
    $administrator = backupRestoreActor(['admin_panel_access', 'backup_restore'], 'backup_restore_test_admin');
    $administrator->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    return $administrator->fresh();
}

/** Writes a real, findable zip artefact to the fake `backups` disk. */
function backupRestoreSeedArtefact(): string
{
    Storage::fake('backups');

    $backupName = (string) config('backup.backup.name');
    $timestamp = now()->subHour()->format('Y-m-d-H-i-s');
    $path = "{$backupName}/{$timestamp}.zip";

    Storage::disk('backups')->put($path, 'placeholder-zip-bytes');

    return $path;
}

it('refuses the restore action without a valid re-authentication code', function (): void {
    Queue::fake();
    $path = backupRestoreSeedArtefact();
    $administrator = backupRestoreAdministrator();

    Livewire::actingAs($administrator)
        ->test(BackupRestore::class)
        ->call('selectBackup', $path)
        ->call('confirmSelection')
        ->callAction('restore', data: ['code' => '000000'])
        ->assertHasFormErrors(['code']);

    Queue::assertNotPushed(DatabaseRestoreJob::class);
});

it('dispatches the restore job once a genuinely valid re-authentication code is supplied', function (): void {
    Queue::fake();
    $path = backupRestoreSeedArtefact();
    $administrator = backupRestoreAdministrator();

    $currentCode = app(Google2FA::class)->getCurrentOtp('JBSWY3DPEHPK3PXP');

    Livewire::actingAs($administrator)
        ->test(BackupRestore::class)
        ->call('selectBackup', $path)
        ->call('confirmSelection')
        ->callAction('restore', data: ['code' => $currentCode])
        ->assertHasNoFormErrors();

    Queue::assertPushed(
        DatabaseRestoreJob::class,
        fn (DatabaseRestoreJob $job): bool => (new ReflectionClass($job))->getProperty('backupPath')->getValue($job) === $path
            && (new ReflectionClass($job))->getProperty('actorId')->getValue($job) === $administrator->id,
    );
});

it("names the selected backup's own real timestamp in the confirmation message, not a relative figure alone", function (): void {
    $path = backupRestoreSeedArtefact();
    $administrator = backupRestoreAdministrator();

    $component = Livewire::actingAs($administrator)
        ->test(BackupRestore::class)
        ->call('selectBackup', $path)
        ->call('confirmSelection');

    /** @var BackupRestore $instance */
    $instance = $component->instance();
    $selected = $instance->selectedBackup();

    expect($selected)->toBeInstanceOf(Backup::class);

    $message = $instance->confirmationMessage();

    expect($message)->not->toBeNull()
        ->and($message)->toContain($selected->date()->toDateTimeString());
});

it('denies a non-administrator at the Policy level, not merely by hiding the action', function (): void {
    $staff = backupRestoreActor(['admin_panel_access'], 'backup_restore_test_staff');

    expect(Gate::forUser($staff)->allows('restore', Backup::class))->toBeFalse();

    $administrator = backupRestoreAdministrator();
    expect(Gate::forUser($administrator)->allows('restore', Backup::class))->toBeTrue();
});

it('journals a failed restore against a genuinely missing artefact and notifies every backup_restore holder', function (): void {
    $this->seed([LanguageSeeder::class, NotificationChannelSeeder::class, NotificationTypeSeeder::class]);
    Storage::fake('backups');
    $administrator = backupRestoreAdministrator();

    $job = new DatabaseRestoreJob('nonexistent/does-not-exist.zip', $administrator->id);

    expect(fn () => $job->handle(app(DatabaseRestoreService::class), app(AuditJournal::class)))
        ->toThrow(Exception::class);

    $entry = Audit::query()->where('event', 'backup_restore_failed')->latest('id')->firstOrFail();

    expect($entry->user_id)->toBe($administrator->id)
        ->and($entry->new_values['backup_path'])->toBe('nonexistent/does-not-exist.zip')
        ->and($entry->new_values)->toHaveKey('error');

    $notifications = DB::table('notifications')->where('recipient_id', $administrator->id)->get();
    expect($notifications)->toHaveCount(1);
});

it("journals a successful restore via the same recordOutcome() the real success path calls, naming the actor and the artefact's timestamp", function (): void {
    $administrator = backupRestoreAdministrator();

    $job = new DatabaseRestoreJob('some/artefact.zip', $administrator->id);

    $recordOutcome = (new ReflectionClass($job))->getMethod('recordOutcome');

    $recordOutcome->invoke(
        $job,
        app(AuditJournal::class),
        $administrator,
        'backup_restored',
        '2026-01-01 03:00:00',
        null,
    );

    $entry = Audit::query()->where('event', 'backup_restored')->latest('id')->firstOrFail();

    expect($entry->user_id)->toBe($administrator->id)
        ->and($entry->new_values['backup_path'])->toBe('some/artefact.zip')
        ->and($entry->new_values['backup_timestamp'])->toBe('2026-01-01 03:00:00')
        ->and($entry->new_values)->not->toHaveKey('error');
});
