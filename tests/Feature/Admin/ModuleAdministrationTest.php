<?php

declare(strict_types=1);

use App\Exceptions\ModuleDependencyException;
use App\Filament\Admin\Resources\Modules\ModuleResource;
use App\Models\Module;
use App\Models\User;
use App\Services\Modules\ModuleAdministrator;
use App\Services\Modules\ModuleContext;
use App\Services\Modules\ModuleResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Module Administration
|--------------------------------------------------------------------------
|
| The dormant booking module is the reason this screen has to be trustworthy:
| the argument for shipping its tables and code gated rather than deleting
| them rests entirely on re-enablement being lossless. That claim is verified
| here rather than assumed.
|
*/

function moduleFixture(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $countryId = DB::table('countries')->insertGetId([
        'code' => 'UA', 'currency' => 'UAH', 'phone_code' => '+380',
        'primary_language_id' => $languageId, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'accommodation', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $owner = User::factory()->create();

    foreach (range(1, 3) as $ignored) {
        DB::table('objects')->insert([
            'ulid' => (string) Str::ulid(), 'owner_id' => $owner->id,
            'object_type_id' => $typeId, 'territory_id' => $territoryId, 'country_id' => $countryId,
            'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $paymentId = DB::table('modules')->insertGetId([
        'key' => 'payment', 'default_state' => 'disabled', 'scopable_levels' => json_encode(['portal']),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $bookingId = DB::table('modules')->insertGetId([
        'key' => 'booking', 'default_state' => 'disabled',
        'scopable_levels' => json_encode(['portal', 'country', 'category', 'owner', 'object']),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('module_dependencies')->insert([
        'module_id' => $bookingId, 'depends_on_module_id' => $paymentId,
    ]);

    return ['country' => $countryId, 'booking' => $bookingId, 'payment' => $paymentId, 'owner' => $owner];
}

function moduleActor(array $permissions, string $roleKey = 'module_admin'): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate($roleKey, 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    DB::table('role_scopes')->insert([
        'user_id' => $user->id, 'role_id' => $role->id,
        'scope_kind' => 'none', 'scope_reference_id' => null,
        'granted_by' => $user->id, 'granted_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $user->fresh();
}

it('counts the blast radius rather than estimating it', function (): void {
    $fixture = moduleFixture();
    $administrator = app(ModuleAdministrator::class);

    expect($administrator->blastRadius('portal', null))->toBe(3)
        ->and($administrator->blastRadius('country', $fixture['country']))->toBe(3)
        ->and($administrator->blastRadius('country', 999_999))->toBe(0);
});

it('refuses to enable a module while its dependency is off', function (): void {
    $fixture = moduleFixture();
    $actor = moduleActor(['settings.view', 'settings_management']);
    $booking = Module::query()->findOrFail($fixture['booking']);

    expect(fn () => app(ModuleAdministrator::class)->setState($booking, 'portal', null, true, $actor))
        ->toThrow(ModuleDependencyException::class);

    // Refused, not silently resolved to disabled — an administrator who
    // toggles a switch that stays off has been told nothing.
    expect(DB::table('module_settings')->where('module_id', $booking->id)->count())->toBe(0);
});

it('enables a module once its dependency is satisfied, and journals the change with its reach', function (): void {
    $fixture = moduleFixture();
    $actor = moduleActor(['settings.view', 'settings_management']);
    $administrator = app(ModuleAdministrator::class);

    $payment = Module::query()->findOrFail($fixture['payment']);
    $booking = Module::query()->findOrFail($fixture['booking']);

    $administrator->setState($payment, 'portal', null, true, $actor);
    $administrator->setState($booking, 'portal', null, true, $actor);

    expect(app(ModuleResolver::class)->isEnabled('booking', new ModuleContext))->toBeTrue();

    $entry = DB::table('audits')->where('event', 'module_toggled')->latest('id')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->new_values)->toContain('"affected_objects":3')
        ->and($entry->tags)->toBe('modules');
});

it('resolves country scope more specifically than portal scope', function (): void {
    $fixture = moduleFixture();
    $actor = moduleActor(['settings.view', 'settings_management']);
    $administrator = app(ModuleAdministrator::class);

    $payment = Module::query()->findOrFail($fixture['payment']);
    $booking = Module::query()->findOrFail($fixture['booking']);

    $administrator->setState($payment, 'portal', null, true, $actor);
    $administrator->setState($booking, 'portal', null, true, $actor);
    $administrator->setState($booking, 'country', $fixture['country'], false, $actor);

    expect(app(ModuleResolver::class)->isEnabled('booking', new ModuleContext))->toBeTrue()
        ->and(app(ModuleResolver::class)->isEnabled('booking', new ModuleContext(countryId: $fixture['country'])))->toBeFalse();
});

it('returns a disabled module to service unchanged when it is re-enabled', function (): void {
    $fixture = moduleFixture();
    $actor = moduleActor(['settings.view', 'settings_management']);
    $administrator = app(ModuleAdministrator::class);

    $payment = Module::query()->findOrFail($fixture['payment']);
    $booking = Module::query()->findOrFail($fixture['booking']);
    $administrator->setState($payment, 'portal', null, true, $actor);
    $administrator->setState($booking, 'portal', null, true, $actor);

    $roomId = DB::table('rooms')->insertGetId([
        'object_id' => DB::table('objects')->value('id'),
        'capacity' => 2, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $reservationId = DB::table('reservations')->insertGetId([
        'room_id' => $roomId, 'guest_id' => $fixture['owner']->id,
        'check_in' => now()->addWeek(), 'check_out' => now()->addWeeks(2),
        'party_size' => 2, 'status' => 'confirmed',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $before = DB::table('reservations')->where('id', $reservationId)->first();

    $administrator->setState($booking, 'portal', null, false, $actor);
    $administrator->setState($booking, 'portal', null, true, $actor);

    // Disabling preserves data, so re-enabling restores records to service
    // unchanged — the guarantee that makes gating the built capability
    // defensible rather than a euphemism for shelving it.
    expect(DB::table('reservations')->where('id', $reservationId)->first())->toEqual($before);
});

it('lists the registry for an unrestricted grant and refuses the registry to a country-scoped one', function (): void {
    moduleFixture();

    $unrestricted = moduleActor(['admin_panel_access', 'settings.view'], 'unrestricted_role');

    $this->actingAs($unrestricted)
        ->get(ModuleResource::getUrl('index', panel: 'admin'))
        ->assertSuccessful();

    $scoped = User::factory()->create();
    $scopedRole = Role::findOrCreate('country_scoped_role', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $scopedRole->syncPermissions(['admin_panel_access', 'settings.view']);
    $scoped->assignRole($scopedRole);
    DB::table('role_scopes')->insert([
        'user_id' => $scoped->id, 'role_id' => $scopedRole->id,
        'scope_kind' => 'country', 'scope_reference_id' => 1,
        'granted_by' => $scoped->id, 'granted_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // Reaches the page — the permission is held — but the registry itself is
    // portal-wide, so a country-bounded grant reaches none of its rows.
    expect(ModuleResource::getEloquentQuery()->count())->toBe(2);

    $this->actingAs($scoped->fresh());
    expect(ModuleResource::getEloquentQuery()->count())->toBe(0);
});
