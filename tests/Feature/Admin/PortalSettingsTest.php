<?php

declare(strict_types=1);

use App\Exceptions\CriticalSettingException;
use App\Filament\Admin\Pages\PortalSettings;
use App\Models\User;
use App\Services\Settings\SettingsRegistry;
use App\Services\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Portal Settings
|--------------------------------------------------------------------------
|
| The requirement behind this screen is that monetization, presentation, and
| structural parameters change without a programmer. The measure of that is
| not the screen — it is whether every parameter is declared, typed, and
| defaulted, so the assertions here are about the registry and the writer
| rather than about the form.
|
*/

function settingsActor(array $permissions, ?string $roleKey = null): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate($roleKey ?? 'settings_role', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user->fresh();
}

it('resolves every declared setting from an empty table', function (): void {
    $repository = app(SettingsRepository::class);
    $registry = app(SettingsRegistry::class);

    expect(DB::table('settings')->count())->toBe(0);

    $resolved = $repository->all();

    expect($resolved)->toHaveCount(count($registry->all()));

    foreach ($registry->all() as $key => $definition) {
        expect($resolved[$key])->toBe($definition->default, "setting [{$key}] did not fall back to its declared default");
    }
});

it('refuses to read or write a key nobody declared', function (): void {
    expect(fn () => app(SettingsRepository::class)->get('portal.nmae'))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => app(SettingsRepository::class)->set('portal.nmae', 'x'))
        ->toThrow(InvalidArgumentException::class);
});

it('casts a stored value to its declared type', function (): void {
    $actor = settingsActor(['settings_management'], 'chief_administrator');

    // Written as a string, declared as an integer — the shape a value takes
    // when it arrives through an import or a hand-edited row.
    app(SettingsRepository::class)->set('media.upload_max_kilobytes', '4096', $actor);

    expect(app(SettingsRepository::class)->get('media.upload_max_kilobytes'))->toBe(4096);
});

it('journals a change with its previous value', function (): void {
    $actor = settingsActor(['settings_management'], 'chief_administrator');

    app(SettingsRepository::class)->set('portal.name', 'Riviera Guide', $actor);

    $entry = DB::table('audits')->where('event', 'setting_changed')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->user_id)->toBe($actor->id)
        ->and($entry->old_values)->toContain('Tourism Portal')
        ->and($entry->new_values)->toContain('Riviera Guide')
        ->and($entry->tags)->toBe('settings');
});

it('redacts a critical setting value from the journal', function (): void {
    $actor = settingsActor(['settings_management'], 'chief_administrator');

    app(SettingsRepository::class)->set('integrations.captcha_secret', 'super-secret-value', $actor);

    $entry = DB::table('audits')->where('event', 'setting_changed')->first();

    // The journal is read by more people than may write the setting, and a
    // secret recorded in it has simply moved rather than been protected.
    expect($entry->new_values)->not->toContain('super-secret-value')
        ->and($entry->new_values)->toContain('redacted');
});

it('refuses a critical setting to an administrator who is not the chief', function (): void {
    $actor = settingsActor(['settings_management'], 'technical_support');

    expect(fn () => app(SettingsRepository::class)->set('security.sign_in_max_attempts', 99, $actor))
        ->toThrow(CriticalSettingException::class);

    // Refused at the writer, so the value did not change either.
    expect(app(SettingsRepository::class)->get('security.sign_in_max_attempts'))->toBe(5);
});

it('admits a critical setting to the chief administrator', function (): void {
    $actor = settingsActor(['settings_management'], 'chief_administrator');

    app(SettingsRepository::class)->set('security.sign_in_max_attempts', 9, $actor);

    expect(app(SettingsRepository::class)->get('security.sign_in_max_attempts'))->toBe(9);
});

it('serves a changed setting on the next read rather than after a cache window', function (): void {
    $actor = settingsActor(['settings_management'], 'chief_administrator');

    // Prime the cache, then write through a second instance: a repository that
    // only cleared its own in-memory copy would keep serving the old value.
    expect(app(SettingsRepository::class)->get('portal.name'))->toBe('Tourism Portal');

    app(SettingsRepository::class)->set('portal.name', 'Changed', $actor);

    expect(app(SettingsRepository::class)->get('portal.name'))->toBe('Changed');
});

it('renders for an administrator holding settings management and refuses one without it', function (): void {
    $permitted = settingsActor(['admin_panel_access', 'settings_management'], 'technical_support');
    $refused = settingsActor(['admin_panel_access'], 'content_manager');

    $this->actingAs($permitted)->get(PortalSettings::getUrl(panel: 'admin'))->assertSuccessful();
    $this->actingAs($refused)->get(PortalSettings::getUrl(panel: 'admin'))->assertForbidden();
});

it('covers every runtime-configurable area the requirements name', function (): void {
    $groups = app(SettingsRegistry::class)->groups();

    expect($groups)->toContain('portal', 'presentation', 'media', 'moderation')
        ->and($groups)->toContain('availability', 'placement', 'notifications')
        ->and($groups)->toContain('integrations', 'security', 'journal');
});
