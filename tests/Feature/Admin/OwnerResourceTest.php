<?php

declare(strict_types=1);

use App\Exceptions\OwnerDetachmentException;
use App\Filament\Admin\Resources\Owners\Pages\CreateOwner;
use App\Filament\Admin\Resources\Owners\Pages\EditOwner;
use App\Filament\Admin\Resources\Owners\Pages\ListOwners;
use App\Filament\Admin\Resources\Owners\RelationManagers\ObjectsRelationManager;
use App\Filament\Admin\Resources\Owners\Tables\OwnersTable;
use App\Models\Object_;
use App\Models\User;
use App\Services\Owners\OwnerAccountService;
use Carbon\Carbon;
use Filament\Auth\Pages\Login as PanelLogin;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Owner Resource
|--------------------------------------------------------------------------
|
| The owner-management screen's whole surface: a list carrying every column
| the requirement names, backed by real aggregates rather than hand-set
| flags; account creation, contact edits, object attachment/detachment, and
| suspension/restoration, each mutating the database it claims to and each
| journalled; and the one rule none of that matters without — a blocked
| account loses panel access on its very next request, and can never sign
| in fresh in the first place.
|
*/

function ownerGeography(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryMd = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true, 'display_order' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryUa = DB::table('countries')->insertGetId([
        'code' => 'UA', 'currency' => 'UAH', 'phone_code' => '+380',
        'primary_language_id' => $languageId, 'is_active' => true, 'display_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $levelMd = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryMd, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryMd = DB::table('territories')->insertGetId([
        'country_id' => $countryMd, 'level_id' => $levelMd, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $typeStay = DB::table('object_types')->insertGetId([
        'key' => 'accommodation', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('languageId', 'countryMd', 'countryUa', 'levelMd', 'territoryMd', 'typeStay');
}

/**
 * @param  array<string, mixed>  $geo
 * @param  array<string, mixed>  $overrides
 */
function ownerSeedObject(array $geo, array $overrides = []): int
{
    return DB::table('objects')->insertGetId(array_merge([
        'ulid' => (string) Str::ulid(),
        'owner_id' => null,
        'object_type_id' => $geo['typeStay'],
        'territory_id' => $geo['territoryMd'],
        'country_id' => $geo['countryMd'],
        'status' => 'draft',
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));
}

function ownerSeedOverduePlacement(int $objectId): void
{
    DB::table('placement_tiers')->insert([
        'id' => 1, 'rank' => 1, 'border_colour' => '#000000', 'badge_colour' => '#000000',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('placement_packages')->insert([
        'id' => 1, 'placement_tier_id' => 1, 'validity_days' => 30, 'price' => 10,
        'currency' => 'EUR', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_placements')->insert([
        'object_id' => $objectId, 'placement_package_id' => 1,
        'starts_at' => now()->subDays(40), 'ends_at' => now()->subDays(5),
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** @param  array<string, mixed>  $attributes */
function seedOwnerAccount(array $attributes = []): User
{
    Role::findOrCreate('object_owner', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $owner = User::factory()->create($attributes);
    $owner->assignRole('object_owner');

    return $owner->fresh();
}

/** @param  list<string>  $permissions */
function ownerActor(array $permissions, string $scopeKind, ?int $reference, string $roleKey): User
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
        'scope_kind' => $scopeKind, 'scope_reference_id' => $reference,
        'granted_by' => $user->id, 'granted_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $user->fresh();
}

/** @return list<string> */
function ownerFullPermissions(): array
{
    return ['admin_panel_access', 'user.view', 'user.create', 'user.edit', 'user.delete'];
}

// -----------------------------------------------------------------------
// The list — every required column, backed by real seeded data.
// -----------------------------------------------------------------------

it('declares every column the requirement names', function (): void {
    $method = new ReflectionMethod(OwnersTable::class, 'columns');
    $method->setAccessible(true);

    /** @var list<TextColumn> $columns */
    $columns = $method->invoke(null);
    $names = array_map(fn (TextColumn $column): string => $column->getName(), $columns);

    expect($names)->toEqual([
        'name', 'company', 'phone', 'email', 'country.code',
        'objects_count', 'overdue_placements_count', 'created_at', 'last_sign_in_at', 'status',
    ]);
});

it('carries real seeded data behind every list column, including a genuine overdue-placement aggregate', function (): void {
    $geo = ownerGeography();
    $owner = seedOwnerAccount([
        'name' => 'Marina Draganescu',
        'email' => 'marina.owner@example.test',
        'company' => 'Coastal Retreats',
        'phone' => '+37360011122',
        'country_id' => $geo['countryMd'],
    ]);

    $objectOnTime = ownerSeedObject($geo, ['owner_id' => $owner->id]);
    $objectOverdue = ownerSeedObject($geo, ['owner_id' => $owner->id]);
    ownerSeedOverduePlacement($objectOverdue);
    // A placement that has NOT lapsed must not inflate the overdue count.
    DB::table('placement_tiers')->insert([
        'id' => 2, 'rank' => 2, 'border_colour' => '#111111', 'badge_colour' => '#111111',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('placement_packages')->insert([
        'id' => 2, 'placement_tier_id' => 2, 'validity_days' => 30, 'price' => 10,
        'currency' => 'EUR', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_placements')->insert([
        'object_id' => $objectOnTime, 'placement_package_id' => 2,
        'starts_at' => now(), 'ends_at' => now()->addDays(30),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $signInAt = now()->subDays(2)->startOfSecond();
    DB::table('audits')->insert([
        'user_type' => User::class, 'user_id' => $owner->id,
        'event' => 'sign_in', 'auditable_type' => User::class, 'auditable_id' => $owner->id,
        'created_at' => $signInAt, 'updated_at' => $signInAt,
    ]);

    $actor = ownerActor(ownerFullPermissions(), 'none', null, 'unrestricted_owner_list');
    test()->actingAs($actor);

    $test = Livewire::test(ListOwners::class);

    /** @var User $record */
    $record = $test->instance()->getTableRecord((string) $owner->id);

    expect($record->name)->toBe('Marina Draganescu')
        ->and($record->company)->toBe('Coastal Retreats')
        ->and($record->phone)->toBe('+37360011122')
        ->and($record->email)->toBe('marina.owner@example.test')
        ->and($record->country->code)->toBe('MD')
        ->and((int) $record->objects_count)->toBe(2)
        ->and((int) $record->overdue_placements_count)->toBe(1)
        ->and($record->created_at)->not->toBeNull()
        ->and($record->last_sign_in_at)->not->toBeNull();

    expect(Carbon::parse($record->last_sign_in_at)->equalTo($signInAt))->toBeTrue();

    $test->assertTableColumnStateSet('status', __('panel.owners.status.active'), $owner);
});

// -----------------------------------------------------------------------
// Account creation.
// -----------------------------------------------------------------------

it('creates an owner account, assigns the object_owner role, and dispatches a password reset link', function (): void {
    Notification::fake();
    Role::findOrCreate('object_owner', 'web');

    $geo = ownerGeography();
    $actor = ownerActor(ownerFullPermissions(), 'none', null, 'unrestricted_create');

    Livewire::actingAs($actor)
        ->test(CreateOwner::class)
        ->fillForm([
            'name' => 'Nadia Rusu',
            'email' => 'nadia.rusu@example.test',
            'company' => 'Seaside Stays',
            'phone' => '+373600222333',
            'country_id' => $geo['countryMd'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $owner = User::query()->where('email', 'nadia.rusu@example.test')->firstOrFail();

    expect($owner->hasRole('object_owner'))->toBeTrue()
        ->and(DB::table('audits')->where('event', 'owner_account_created')->where('auditable_id', $owner->id)->count())->toBe(1)
        ->and(DB::table('audits')->where('event', 'owner_password_reset_link_sent')->where('auditable_id', $owner->id)->count())->toBe(1);

    Notification::assertSentTo($owner, ResetPassword::class);
});

// -----------------------------------------------------------------------
// Editing contacts.
// -----------------------------------------------------------------------

it('persists and journals a contact edit across every field', function (): void {
    $geo = ownerGeography();
    $owner = seedOwnerAccount([
        'name' => 'Original Name',
        'email' => 'original@example.test',
        'company' => 'Original Co',
        'phone' => '+373600000000',
        'country_id' => $geo['countryMd'],
    ]);
    $actor = ownerActor(ownerFullPermissions(), 'none', null, 'unrestricted_edit');

    Livewire::actingAs($actor)
        ->test(EditOwner::class, ['record' => $owner->getKey()])
        ->fillForm([
            'name' => 'Updated Name',
            'email' => 'updated@example.test',
            'company' => 'Updated Co',
            'phone' => '+373600111222',
            'country_id' => $geo['countryUa'],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $owner->refresh();

    expect($owner->name)->toBe('Updated Name')
        ->and($owner->email)->toBe('updated@example.test')
        ->and($owner->company)->toBe('Updated Co')
        ->and($owner->phone)->toBe('+373600111222')
        ->and($owner->country_id)->toBe($geo['countryUa'])
        ->and(DB::table('audits')->where('event', 'owner_contacts_updated')->where('auditable_id', $owner->id)->count())->toBe(1);
});

it('refuses a country-scoped administrator reassigning an in-scope owner to an out-of-scope country', function (): void {
    $geo = ownerGeography();
    $owner = seedOwnerAccount(['country_id' => $geo['countryMd']]);
    $actor = ownerActor(['admin_panel_access', 'user.view', 'user.edit'], 'country', $geo['countryMd'], 'md_only_edit');

    Livewire::actingAs($actor)
        ->test(EditOwner::class, ['record' => $owner->getKey()])
        ->fillForm(['country_id' => $geo['countryUa']])
        ->call('save')
        ->assertHasFormErrors(['country_id']);

    expect($owner->fresh()->country_id)->toBe($geo['countryMd'])
        ->and(DB::table('audits')->where('event', 'owner_contacts_updated')->where('auditable_id', $owner->id)->count())->toBe(0);
});

// -----------------------------------------------------------------------
// Attaching / detaching objects.
// -----------------------------------------------------------------------

it('attaches a previously ownerless object, setting owner_id and journalling it', function (): void {
    $geo = ownerGeography();
    $owner = seedOwnerAccount(['country_id' => $geo['countryMd']]);
    $actor = ownerActor(ownerFullPermissions(), 'none', null, 'unrestricted_attach_a');

    $objectId = ownerSeedObject($geo, ['owner_id' => null]);
    $object = Object_::query()->withUnmoderated()->findOrFail($objectId);

    app(OwnerAccountService::class)->attachObject($owner, $object, $actor);

    expect(DB::table('objects')->where('id', $objectId)->value('owner_id'))->toBe($owner->id)
        ->and(DB::table('audits')->where('event', 'owner_object_attached')->where('auditable_id', $objectId)->count())->toBe(1);
});

it('attaches an object that already belonged to someone else, reassigning owner_id and journalling it', function (): void {
    $geo = ownerGeography();
    $owner = seedOwnerAccount(['country_id' => $geo['countryMd']]);
    $previousOwner = User::factory()->create();
    $actor = ownerActor(ownerFullPermissions(), 'none', null, 'unrestricted_attach_b');

    $objectId = ownerSeedObject($geo, ['owner_id' => $previousOwner->id]);
    $object = Object_::query()->withUnmoderated()->findOrFail($objectId);

    app(OwnerAccountService::class)->attachObject($owner, $object, $actor);

    expect(DB::table('objects')->where('id', $objectId)->value('owner_id'))->toBe($owner->id)
        ->and(DB::table('audits')->where('event', 'owner_object_attached')->where('auditable_id', $objectId)->count())->toBe(1);
});

it('detaches an object, clearing its owner and journalling the change, and refuses when already ownerless', function (): void {
    $geo = ownerGeography();
    $owner = seedOwnerAccount(['country_id' => $geo['countryMd']]);
    $actor = ownerActor(ownerFullPermissions(), 'none', null, 'unrestricted_detach');

    $objectId = ownerSeedObject($geo, ['owner_id' => $owner->id]);
    $object = Object_::query()->withUnmoderated()->findOrFail($objectId);

    app(OwnerAccountService::class)->detachObject($object, $actor);

    expect(DB::table('objects')->where('id', $objectId)->value('owner_id'))->toBeNull()
        ->and(DB::table('audits')->where('event', 'owner_object_detached')->where('auditable_id', $objectId)->count())->toBe(1);

    $refetched = Object_::query()->withUnmoderated()->findOrFail($objectId);

    expect(fn () => app(OwnerAccountService::class)->detachObject($refetched, $actor))
        ->toThrow(OwnerDetachmentException::class);
});

it('scopes the attach action\'s object query to the actor\'s own grant, excluding an out-of-scope object entirely', function (): void {
    $geo = ownerGeography();
    $actor = ownerActor(['admin_panel_access', 'user.view', 'user.edit', 'object.edit'], 'country', $geo['countryMd'], 'md_only_attach');

    $inScopeId = ownerSeedObject($geo, ['owner_id' => null]);

    // territory_id is required (not nullable) — a genuine Ukraine-side
    // territory row is needed before the out-of-scope fixture can be
    // inserted at all, not patched in afterward.
    $levelUa = DB::table('territory_levels')->insertGetId([
        'country_id' => $geo['countryUa'], 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryUa = DB::table('territories')->insertGetId([
        'country_id' => $geo['countryUa'], 'level_id' => $levelUa, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $outOfScopeId = ownerSeedObject($geo, [
        'owner_id' => null, 'country_id' => $geo['countryUa'], 'territory_id' => $territoryUa,
    ]);

    // The relation manager's attach action both renders its Select options
    // from this query AND resolves the submitted object_id through it, so a
    // request forging an out-of-scope id is refused by the same mechanism
    // that keeps it off the rendered list in the first place — there is no
    // separate "offer" step to test apart from this one query.
    $method = new ReflectionMethod(
        ObjectsRelationManager::class,
        'attachableObjectsQuery',
    );
    $method->setAccessible(true);

    /** @var Builder<Object_> $query */
    $query = $method->invoke(null, $actor);
    $reachableIds = $query->pluck('id')->all();

    expect($reachableIds)->toContain($inScopeId)
        ->and($reachableIds)->not->toContain($outOfScopeId);
});

// -----------------------------------------------------------------------
// Blocking / restoring.
// -----------------------------------------------------------------------

it('blocks and restores an owner, journalling once each, staying idempotent on a repeated block', function (): void {
    $geo = ownerGeography();
    $owner = seedOwnerAccount(['country_id' => $geo['countryMd']]);
    $actor = ownerActor(ownerFullPermissions(), 'none', null, 'unrestricted_block');

    app(OwnerAccountService::class)->block($owner, $actor);
    $owner->refresh();

    expect($owner->blocked_at)->not->toBeNull()
        ->and($owner->blocked_by)->toBe($actor->id)
        ->and(DB::table('audits')->where('event', 'owner_blocked')->where('auditable_id', $owner->id)->count())->toBe(1);

    // Idempotent: blocking an already-blocked account neither throws nor
    // writes a second journal entry.
    app(OwnerAccountService::class)->block($owner->fresh(), $actor);

    expect(DB::table('audits')->where('event', 'owner_blocked')->where('auditable_id', $owner->id)->count())->toBe(1);

    app(OwnerAccountService::class)->restore($owner->fresh(), $actor);
    $owner->refresh();

    expect($owner->blocked_at)->toBeNull()
        ->and($owner->blocked_by)->toBeNull()
        ->and(DB::table('audits')->where('event', 'owner_restored')->where('auditable_id', $owner->id)->count())->toBe(1);
});

// -----------------------------------------------------------------------
// Panel access, both the next request after being blocked and a fresh
// sign-in attempt against an account that is already blocked.
// -----------------------------------------------------------------------

it('refuses a blocked owner cabinet access on the very next request, without a fresh sign-in', function (): void {
    Permission::findOrCreate('cabinet_access', 'web');
    $role = Role::findOrCreate('object_owner', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions(['cabinet_access']);

    $owner = User::factory()->create();
    $owner->assignRole($role);
    $owner = $owner->fresh();
    $actor = User::factory()->create();

    $cabinetUrl = '/'.config('booking.panels.cabinet.path');

    test()->actingAs($owner)
        ->get($cabinetUrl)
        ->assertSuccessful();

    app(OwnerAccountService::class)->block($owner, $actor);

    test()->get($cabinetUrl)
        ->assertForbidden();
});

it('refuses sign-in for an already-blocked account attempting to authenticate fresh', function (): void {
    Permission::findOrCreate('cabinet_access', 'web');
    $role = Role::findOrCreate('object_owner', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions(['cabinet_access']);

    $owner = User::factory()->create();
    $owner->assignRole($role);
    $owner->forceFill(['blocked_at' => now()])->save();

    Filament::setCurrentPanel('cabinet');

    Livewire::test(PanelLogin::class)
        ->fillForm(['email' => $owner->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    test()->assertGuest();
});

// -----------------------------------------------------------------------
// Password reset link.
// -----------------------------------------------------------------------

it('journals sending a password reset link and dispatches the reset notification', function (): void {
    Notification::fake();

    $geo = ownerGeography();
    $owner = seedOwnerAccount(['country_id' => $geo['countryMd']]);
    $actor = ownerActor(ownerFullPermissions(), 'none', null, 'unrestricted_reset');

    app(OwnerAccountService::class)->sendPasswordResetLink($owner, $actor);

    expect(DB::table('audits')->where('event', 'owner_password_reset_link_sent')->where('auditable_id', $owner->id)->count())->toBe(1);

    Notification::assertSentTo($owner, ResetPassword::class);
});
