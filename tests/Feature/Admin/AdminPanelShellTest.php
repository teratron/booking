<?php

declare(strict_types=1);

use App\Filament\Admin\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Admin Panel Shell
|--------------------------------------------------------------------------
|
| Covers the three things the shell is responsible for and that nothing else
| in the phase can compensate for later: the panel answers at a protected
| address, admission is a permission rather than a role name, and every
| sign-in outcome — success, failure, lockout, sign-out — reaches the action
| journal. The journal is the one of the three that cannot be backfilled:
| the events it records will already have happened.
|
*/

function staffUserWith(array $permissions, string $roleKey = 'staff'): User
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

function adminPanelUrl(string $path = ''): string
{
    return '/'.config('booking.panels.admin.path').$path;
}

it('answers at the configured protected address and not at the conventional one', function (): void {
    expect(config('booking.panels.admin.path'))->not->toBe('admin');

    $this->get(adminPanelUrl('/login'))->assertOk();
    $this->get('/admin')->assertNotFound();
    $this->get('/admin/login')->assertNotFound();
});

it('refuses an account that holds no panel permission', function (): void {
    $user = staffUserWith(['object.view'], 'no_panel_access');

    $this->actingAs($user)
        ->get(adminPanelUrl())
        ->assertForbidden();
});

it('admits an account holding the panel permission', function (): void {
    $user = staffUserWith(['admin_panel_access'], 'panel_access');

    $this->actingAs($user)
        ->get(adminPanelUrl())
        ->assertSuccessful();
});

it('journals a successful sign-in with its address and device', function (): void {
    $user = staffUserWith(['admin_panel_access'], 'panel_access');

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate');

    $entry = DB::table('audits')->where('event', 'sign_in')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->auditable_id)->toBe($user->id)
        ->and($entry->user_id)->toBe($user->id)
        ->and($entry->ip_address)->not->toBeNull()
        ->and($entry->tags)->toBe('authentication');
});

it('journals a failed sign-in against a real account', function (): void {
    $user = staffUserWith(['admin_panel_access'], 'panel_access');

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'not-the-password'])
        ->call('authenticate');

    expect(DB::table('audits')->where('event', 'sign_in_failed')->where('auditable_id', $user->id)->count())->toBe(1);
});

it('leaves no journal row for an attempt against an unrecognised address', function (): void {
    Livewire::test(Login::class)
        ->fillForm(['email' => 'nobody@example.test', 'password' => 'whatever'])
        ->call('authenticate');

    expect(DB::table('audits')->count())->toBe(0);
});

it('journals a sign-out', function (): void {
    $user = staffUserWith(['admin_panel_access'], 'panel_access');

    $this->actingAs($user);
    auth()->logout();

    expect(DB::table('audits')->where('event', 'sign_out')->where('auditable_id', $user->id)->count())->toBe(1);
});

it('locks the account out after the configured attempt budget and journals it', function (): void {
    $user = staffUserWith(['admin_panel_access'], 'panel_access');
    $budget = config('booking.sign_in.max_attempts');

    $component = Livewire::test(Login::class)->fillForm([
        'email' => $user->email,
        'password' => 'not-the-password',
    ]);

    for ($attempt = 1; $attempt <= $budget; $attempt++) {
        $component->call('authenticate');
    }

    expect(DB::table('audits')->where('event', 'sign_in_locked_out')->count())->toBe(0);

    // One past the budget: the throttle refuses before credentials are read,
    // which is the moment no framework event announces and the custom login
    // page exists to record.
    $component->call('authenticate');

    $entry = DB::table('audits')->where('event', 'sign_in_locked_out')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->auditable_id)->toBe($user->id);
});

it('round-trips the second-factor secret and recovery codes, encrypted at rest', function (): void {
    $user = staffUserWith(['admin_panel_access'], 'panel_access');

    $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $user->saveAppAuthenticationRecoveryCodes(['code-one', 'code-two']);

    expect($user->getAppAuthenticationSecret())->toBe('JBSWY3DPEHPK3PXP')
        ->and($user->getAppAuthenticationRecoveryCodes())->toBe(['code-one', 'code-two'])
        ->and($user->getAppAuthenticationHolderName())->toBe($user->email);

    // Both columns are bearer credentials: the secret generates valid codes
    // indefinitely, and a recovery code bypasses the factor outright. Neither
    // may be readable from a database dump.
    $stored = DB::table('two_factor_secrets')->where('user_id', $user->id)->first();
    expect($stored->secret)->not->toContain('JBSWY3DPEHPK3PXP')
        ->and($stored->recovery_codes)->not->toContain('code-one');

    $user->saveAppAuthenticationSecret(null);
    expect($user->getAppAuthenticationSecret())->toBeNull()
        ->and(DB::table('two_factor_secrets')->where('user_id', $user->id)->count())->toBe(0);
});

it('requires a second factor of the chief administrator and not of other roles', function (): void {
    $chief = staffUserWith(['admin_panel_access'], 'chief_administrator');
    $manager = staffUserWith(['admin_panel_access'], 'content_manager');

    $this->actingAs($chief)
        ->get(adminPanelUrl())
        ->assertRedirect();

    $this->actingAs($manager)
        ->get(adminPanelUrl())
        ->assertSuccessful();
});
