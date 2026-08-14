<?php

declare(strict_types=1);

use App\Filament\Cabinet\Pages\BumpObject;
use App\Filament\Cabinet\Pages\Dashboard;
use App\Models\Object_;
use App\Models\Territory;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Cabinet Bump
|--------------------------------------------------------------------------
|
| The cabinet's bump screen adds a UI entry point and an owner-presentable
| refusal message over BumpService — no bump logic of its own. This proves
| the call reaches BumpService scoped to the owner's own object only, and
| that a refusal (package forbids it, or the free-bump interval has not
| elapsed) surfaces a translated, owner-facing reason rather than the
| service's raw developer-facing exception message.
|
*/

function cabinetBumpGeography(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryId = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
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

    return compact('countryId', 'territoryId', 'typeId');
}

function cabinetBumpOwner(string $roleKey): User
{
    foreach (['object.view', 'object.edit', 'cabinet_access'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate($roleKey, 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions(['object.view', 'object.edit', 'cabinet_access']);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user->fresh();
}

/** @param  array<string, mixed>  $packageOverrides */
function cabinetBumpMakeObject(array $fixture, int $ownerId, array $packageOverrides = []): Object_
{
    // A fresh tier every call, never cached across calls: RefreshDatabase
    // rolls back each test's transaction independently, so a PHP-level
    // cache of a prior test's row id would outlive the row itself. The
    // counter (not the row) is what is static — it only has to keep
    // `rank` unique within whichever calls share one test's transaction.
    static $rank = 0;
    $rank++;

    $tierId = DB::table('placement_tiers')->insertGetId([
        'rank' => $rank, 'border_colour' => '#000000', 'badge_colour' => '#000000',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $packageId = DB::table('placement_packages')->insertGetId(array_merge([
        'placement_tier_id' => $tierId, 'validity_days' => 30, 'price' => 10, 'currency' => 'EUR',
        'bump_allowed' => true, 'bump_interval_hours' => null, 'free_bumps_per_period' => null,
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ], $packageOverrides));

    $object = new Object_;
    $object->ulid = (string) Str::ulid();
    $object->owner_id = $ownerId;
    $object->object_type_id = $fixture['typeId'];
    $object->territory_id = $fixture['territoryId'];
    $object->country_id = $fixture['countryId'];
    $object->status = 'draft';
    $object->save();

    DB::table('object_placements')->insert([
        'object_id' => $object->id, 'placement_package_id' => $packageId,
        'starts_at' => now()->subDays(5)->toDateString(), 'ends_at' => now()->addDays(25)->toDateString(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $object;
}

it('bumps the owner\'s own object, calling BumpService for real', function (): void {
    $fixture = cabinetBumpGeography();
    $owner = cabinetBumpOwner('bump_owner_success');
    $object = cabinetBumpMakeObject($fixture, $owner->id);

    Filament::setCurrentPanel('cabinet');
    Filament::setTenant($object, isQuiet: true);
    test()->actingAs($owner);

    Livewire::test(BumpObject::class)
        ->callAction('confirm_bump');

    expect(DB::table('bump_events')->where('object_id', $object->id)->where('type', 'free')->count())->toBe(1);
});

it('refuses to reach the bump screen for another owner\'s object', function (): void {
    $fixture = cabinetBumpGeography();
    $ownerA = cabinetBumpOwner('bump_owner_a');
    $ownerB = cabinetBumpOwner('bump_owner_b');
    cabinetBumpMakeObject($fixture, $ownerA->id);
    $objectB = cabinetBumpMakeObject($fixture, $ownerB->id);

    // Owner A's session cannot even resolve owner B's object as a tenant at
    // all — the same not-found convention every cabinet screen in this
    // phase relies on, proven directly against the bump screen's own route
    // rather than assumed from the general tenancy tests elsewhere.
    $response = test()->actingAs($ownerA)->get(
        route('filament.cabinet.pages.bump-object', ['tenant' => $objectB])
    );

    $response->assertNotFound();

    expect(DB::table('bump_events')->where('object_id', $objectB->id)->count())->toBe(0);
});

it('surfaces a translated, owner-facing reason when the package forbids bumping', function (): void {
    $fixture = cabinetBumpGeography();
    $owner = cabinetBumpOwner('bump_owner_forbidden');
    $object = cabinetBumpMakeObject($fixture, $owner->id, ['bump_allowed' => false]);

    Filament::setCurrentPanel('cabinet');
    Filament::setTenant($object, isQuiet: true);
    test()->actingAs($owner);

    Livewire::test(BumpObject::class)
        ->callAction('confirm_bump')
        ->assertNotified(
            FilamentNotification::make()->danger()
                ->title(__('panel.cabinet.bump.refused'))
                ->body(__('panel.cabinet.bump.refused_reasons.not_allowed_by_package'))
        );

    expect(DB::table('bump_events')->where('object_id', $object->id)->count())->toBe(0);
});

it('surfaces a translated, owner-facing reason when the free-bump interval has not elapsed', function (): void {
    $fixture = cabinetBumpGeography();
    $owner = cabinetBumpOwner('bump_owner_interval');
    $object = cabinetBumpMakeObject($fixture, $owner->id, ['bump_interval_hours' => 24]);

    $placement = DB::table('object_placements')->where('object_id', $object->id)->first();

    DB::table('bump_events')->insert([
        'object_id' => $object->id, 'placement_package_id' => $placement->placement_package_id,
        'scope_type' => Territory::class, 'scope_id' => $fixture['territoryId'],
        'occurred_at' => now()->subHours(2), 'type' => 'free', 'actor_id' => $owner->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    Filament::setCurrentPanel('cabinet');
    Filament::setTenant($object, isQuiet: true);
    test()->actingAs($owner);

    Livewire::test(BumpObject::class)
        ->callAction('confirm_bump')
        ->assertNotified(
            FilamentNotification::make()->danger()
                ->title(__('panel.cabinet.bump.refused'))
                ->body(__('panel.cabinet.bump.refused_reasons.interval_not_elapsed', ['hours' => 24]))
        );

    // The pre-seeded fixture bump plus this refused attempt: still exactly
    // one row, proving the refusal genuinely blocked a second write.
    expect(DB::table('bump_events')->where('object_id', $object->id)->count())->toBe(1);
});

it('reaches the bump screen even when the package forbids bumping, refusing only at submission', function (): void {
    // Bumping is documented as the only cabinet capability this phase gates
    // by placement package — and even that gate refuses at submission
    // (proven above), never by hiding the screen itself, matching this
    // phase's own "refused, not merely hidden" convention used everywhere
    // else.
    $fixture = cabinetBumpGeography();
    $owner = cabinetBumpOwner('bump_owner_reachability');
    $object = cabinetBumpMakeObject($fixture, $owner->id, ['bump_allowed' => false]);

    $response = test()->actingAs($owner)->get(
        route('filament.cabinet.pages.bump-object', ['tenant' => $object])
    );

    $response->assertSuccessful();
});

it("wires the dashboard's bump quick action to this screen's real route", function (): void {
    $fixture = cabinetBumpGeography();
    $owner = cabinetBumpOwner('bump_owner_dashboard_link');
    $object = cabinetBumpMakeObject($fixture, $owner->id);

    Filament::setCurrentPanel('cabinet');
    Filament::setTenant($object, isQuiet: true);

    $routeName = Dashboard::quickActionRouteName('bump_object');
    $actions = collect((new Dashboard)->quickActions())->keyBy('key');

    expect($routeName)->toBe('filament.cabinet.pages.bump-object')
        ->and($actions['bump_object']['url'])->toBe(route($routeName, ['tenant' => $object, 'record' => $object]));
});
