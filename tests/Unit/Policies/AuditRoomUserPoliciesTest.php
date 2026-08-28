<?php

declare(strict_types=1);

use App\Models\Object_;
use App\Models\Room;
use App\Models\User;
use App\Policies\AuditPolicy;
use App\Policies\RoomPolicy;
use App\Policies\StaffPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OwenIt\Auditing\Models\Audit;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| AuditPolicy / RoomPolicy / UserPolicy
|--------------------------------------------------------------------------
|
| AuditPolicy never calls ScopeAuthorizer at all — audit.view is a flat,
| portal-wide grant per its own docblock — so its coverage proves the grant
| decides the outcome regardless of the scope kind attached to it.
|
| RoomPolicy delegates every record-level decision to its owning object,
| exercising the same two independent axes Object_Policy checks: a staff
| account's country/territory/category scope, or an owner/staff-member's
| direct relationship to the object via CabinetAccessResolver. Both axes are
| proven, including the branch where the owning object cannot be resolved
| at all (a soft-deleted object leaves Room::object() — which strips only
| the moderation scope, not the soft-delete one — returning null).
|
| UserPolicy carries only a country axis (an owner account has no territory
| or category of its own), and deliberately does not gate the staff-
| administration screen: StaffResource uses StaffPolicy instead, precisely
| so a country administrator's user.edit grant can never reach it. One test
| below proves that boundary directly rather than leaving it as prose.
|
*/

/** @param  list<string>  $permissions */
function auditRoomUserPolicyActor(
    array $permissions,
    string $roleKey,
    string $scopeKind = 'none',
    ?int $scopeReferenceId = null,
): User {
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
        'scope_kind' => $scopeKind, 'scope_reference_id' => $scopeReferenceId,
        'granted_by' => $user->id, 'granted_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $user->fresh();
}

/** @return array{countryId: int, territoryId: int, typeId: int} */
function auditRoomUserPolicyGeography(): array
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

/** @param  array{countryId: int, territoryId: int, typeId: int}  $geography */
function auditRoomUserPolicyObject(array $geography, ?int $ownerId): Object_
{
    $object = new Object_;
    $object->ulid = (string) Str::ulid();
    $object->owner_id = $ownerId;
    $object->object_type_id = $geography['typeId'];
    $object->territory_id = $geography['territoryId'];
    $object->country_id = $geography['countryId'];
    $object->status = 'draft';
    $object->save();

    return $object;
}

function auditRoomUserPolicyRoom(Object_ $object): Room
{
    /** @var Room $room */
    $room = Room::query()->create(['object_id' => $object->id]);

    return $room;
}

// --- AuditPolicy ---------------------------------------------------------

it('resolves AuditPolicy viewAny and view from audit.view alone', function (): void {
    $permitted = auditRoomUserPolicyActor(['admin_panel_access', 'audit.view'], 'audit_policy_permitted');
    $refused = auditRoomUserPolicyActor(['admin_panel_access'], 'audit_policy_refused');

    $policy = app(AuditPolicy::class);
    $audit = new Audit;

    expect($policy->viewAny($permitted))->toBeTrue()
        ->and($policy->view($permitted, $audit))->toBeTrue();

    expect($policy->viewAny($refused))->toBeFalse()
        ->and($policy->view($refused, $audit))->toBeFalse();
});

it('denies AuditPolicy outright for a bystander holding no audit.view grant at all', function (): void {
    $bystander = User::factory()->create();

    $policy = app(AuditPolicy::class);
    $audit = new Audit;

    expect($policy->viewAny($bystander))->toBeFalse()
        ->and($policy->view($bystander, $audit))->toBeFalse();
});

it('authorizes AuditPolicy regardless of the grant\'s scope kind, since the action journal is portal-wide and unscoped', function (): void {
    // A country-scoped grant would fail ScopeAuthorizer's own country check
    // for any real country id, yet AuditPolicy never calls authorize() at
    // all — it reads $user->can() directly — so the scope kind attached to
    // the grant must never change the outcome.
    $countryScoped = auditRoomUserPolicyActor(
        ['admin_panel_access', 'audit.view'],
        'audit_policy_country_scoped',
        scopeKind: 'country',
        scopeReferenceId: 1,
    );

    $policy = app(AuditPolicy::class);

    expect($policy->viewAny($countryScoped))->toBeTrue()
        ->and($policy->view($countryScoped, new Audit))->toBeTrue();
});

// --- RoomPolicy ------------------------------------------------------------

it('resolves RoomPolicy viewAny/create from the flat object.view / object.edit grants, independent of any record', function (): void {
    $permitted = auditRoomUserPolicyActor(['admin_panel_access', 'object.view', 'object.edit'], 'room_policy_permitted');
    $refused = User::factory()->create();

    $policy = app(RoomPolicy::class);

    expect($policy->viewAny($permitted))->toBeTrue()
        ->and($policy->create($permitted))->toBeTrue();

    expect($policy->viewAny($refused))->toBeFalse()
        ->and($policy->create($refused))->toBeFalse();
});

it('authorizes RoomPolicy view/update/delete through a country-scoped grant matching the room\'s owning object, and denies a mismatched country', function (): void {
    $geography = auditRoomUserPolicyGeography();
    $owner = User::factory()->create();
    $object = auditRoomUserPolicyObject($geography, $owner->id);
    $room = auditRoomUserPolicyRoom($object);

    $matching = auditRoomUserPolicyActor(
        ['admin_panel_access', 'object.view', 'object.edit'],
        'room_policy_country_matching',
        scopeKind: 'country',
        scopeReferenceId: $geography['countryId'],
    );
    $mismatched = auditRoomUserPolicyActor(
        ['admin_panel_access', 'object.view', 'object.edit'],
        'room_policy_country_mismatched',
        scopeKind: 'country',
        scopeReferenceId: $geography['countryId'] + 1000,
    );

    $policy = app(RoomPolicy::class);

    expect($policy->view($matching, $room))->toBeTrue()
        ->and($policy->update($matching, $room))->toBeTrue()
        ->and($policy->delete($matching, $room))->toBeTrue();

    // Not the object's owner, and no object_user row either, so the cabinet
    // fallback has nothing to grant — the country mismatch must actually deny.
    expect($policy->view($mismatched, $room))->toBeFalse()
        ->and($policy->update($mismatched, $room))->toBeFalse()
        ->and($policy->delete($mismatched, $room))->toBeFalse();
});

it('authorizes RoomPolicy through a category-scoped grant matching the room\'s own object_type_id, proving the third scope axis is forwarded', function (): void {
    $geography = auditRoomUserPolicyGeography();
    $owner = User::factory()->create();
    $object = auditRoomUserPolicyObject($geography, $owner->id);
    $room = auditRoomUserPolicyRoom($object);

    $categoryScoped = auditRoomUserPolicyActor(
        ['admin_panel_access', 'object.view'],
        'room_policy_category_scoped',
        scopeKind: 'category',
        scopeReferenceId: $geography['typeId'],
    );

    expect(app(RoomPolicy::class)->view($categoryScoped, $room))->toBeTrue();
});

it('falls back to CabinetAccessResolver and authorizes the object\'s own owner even when their role scope does not cover it', function (): void {
    $geography = auditRoomUserPolicyGeography();
    $owner = auditRoomUserPolicyActor(
        ['admin_panel_access', 'object.view', 'object.edit'],
        'room_policy_owner_out_of_scope',
        scopeKind: 'country',
        scopeReferenceId: $geography['countryId'] + 1000, // deliberately not this object's country
    );
    $object = auditRoomUserPolicyObject($geography, $owner->id);
    $room = auditRoomUserPolicyRoom($object);

    $policy = app(RoomPolicy::class);

    // ScopeAuthorizer alone would deny this — the owner's own grant is
    // scoped to a different country — so a true result here can only come
    // from CabinetAccessResolver's ownership branch.
    expect($policy->view($owner, $room))->toBeTrue()
        ->and($policy->update($owner, $room))->toBeTrue()
        ->and($policy->delete($owner, $room))->toBeTrue();
});

it('falls back to CabinetAccessResolver and authorizes a staff member from their object_user grant alone, independent of any portal-wide permission', function (): void {
    $geography = auditRoomUserPolicyGeography();
    $owner = User::factory()->create();
    $object = auditRoomUserPolicyObject($geography, $owner->id);
    $room = auditRoomUserPolicyRoom($object);

    // Deliberately holds object.view only at the role level — object.edit
    // must come entirely from the object_user row, never from the
    // portal-wide permission system, mirroring the migration's own design
    // intent for staff members attached to a single object.
    $staffMember = auditRoomUserPolicyActor(['admin_panel_access', 'object.view'], 'room_policy_staff_grant');

    DB::table('object_user')->insert([
        'object_id' => $object->id,
        'user_id' => $staffMember->id,
        'permissions' => json_encode(['object.view' => true, 'object.edit' => true]),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $policy = app(RoomPolicy::class);

    expect($policy->view($staffMember, $room))->toBeTrue()
        ->and($policy->update($staffMember, $room))->toBeTrue();
});

it('denies RoomPolicy through CabinetAccessResolver when the object_user grant does not include the requested permission', function (): void {
    $geography = auditRoomUserPolicyGeography();
    $owner = User::factory()->create();
    $object = auditRoomUserPolicyObject($geography, $owner->id);
    $room = auditRoomUserPolicyRoom($object);

    $staffMember = auditRoomUserPolicyActor(['admin_panel_access', 'object.view'], 'room_policy_staff_insufficient_grant');

    DB::table('object_user')->insert([
        'object_id' => $object->id,
        'user_id' => $staffMember->id,
        'permissions' => json_encode(['object.view' => true]),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $policy = app(RoomPolicy::class);

    expect($policy->view($staffMember, $room))->toBeTrue()
        ->and($policy->update($staffMember, $room))->toBeFalse();
});

it('denies every RoomPolicy record ability once the owning object can no longer be resolved', function (): void {
    $geography = auditRoomUserPolicyGeography();
    $owner = User::factory()->create();
    $object = auditRoomUserPolicyObject($geography, $owner->id);
    $room = auditRoomUserPolicyRoom($object);

    $staff = auditRoomUserPolicyActor(
        ['admin_panel_access', 'object.view', 'object.edit'],
        'room_policy_dangling_object',
        scopeKind: 'country',
        scopeReferenceId: $geography['countryId'],
    );

    // Soft-deleting the object leaves Room::object() — which strips only the
    // moderation scope, not the soft-delete one — resolving to null, exactly
    // the "the record vanished under it" case this branch exists to refuse.
    $object->delete();

    $policy = app(RoomPolicy::class);

    expect($policy->view($staff, $room))->toBeFalse()
        ->and($policy->update($staff, $room))->toBeFalse()
        ->and($policy->delete($staff, $room))->toBeFalse();
});

it('denies every RoomPolicy ability for a bystander holding no object.* grant and no relationship to the object at all', function (): void {
    $geography = auditRoomUserPolicyGeography();
    $owner = User::factory()->create();
    $object = auditRoomUserPolicyObject($geography, $owner->id);
    $room = auditRoomUserPolicyRoom($object);

    $bystander = User::factory()->create();

    $policy = app(RoomPolicy::class);

    expect($policy->viewAny($bystander))->toBeFalse()
        ->and($policy->view($bystander, $room))->toBeFalse()
        ->and($policy->create($bystander))->toBeFalse()
        ->and($policy->update($bystander, $room))->toBeFalse()
        ->and($policy->delete($bystander, $room))->toBeFalse();
});

// --- UserPolicy ------------------------------------------------------------

it('resolves UserPolicy viewAny/create from the flat user.view / user.create grants, independent of any target', function (): void {
    $permitted = auditRoomUserPolicyActor(['admin_panel_access', 'user.view', 'user.create'], 'user_policy_permitted');
    $refused = User::factory()->create();

    $policy = app(UserPolicy::class);

    expect($policy->viewAny($permitted))->toBeTrue()
        ->and($policy->create($permitted))->toBeTrue();

    expect($policy->viewAny($refused))->toBeFalse()
        ->and($policy->create($refused))->toBeFalse();
});

it('authorizes UserPolicy view/update through a country-scoped grant matching the target\'s own country, and denies a mismatched country', function (): void {
    $countryId = auditRoomUserPolicyGeography()['countryId'];
    $target = User::factory()->create(['country_id' => $countryId]);

    $matching = auditRoomUserPolicyActor(
        ['admin_panel_access', 'user.view', 'user.edit'],
        'user_policy_country_matching',
        scopeKind: 'country',
        scopeReferenceId: $countryId,
    );
    $mismatched = auditRoomUserPolicyActor(
        ['admin_panel_access', 'user.view', 'user.edit'],
        'user_policy_country_mismatched',
        scopeKind: 'country',
        scopeReferenceId: $countryId + 1000,
    );

    $policy = app(UserPolicy::class);

    expect($policy->view($matching, $target))->toBeTrue()
        ->and($policy->update($matching, $target))->toBeTrue();

    expect($policy->view($mismatched, $target))->toBeFalse()
        ->and($policy->update($mismatched, $target))->toBeFalse();
});

it('denies UserPolicy view/update under a country-scoped grant when the target carries no country at all', function (): void {
    $countryId = auditRoomUserPolicyGeography()['countryId'];
    $target = User::factory()->create(['country_id' => null]);

    $scoped = auditRoomUserPolicyActor(
        ['admin_panel_access', 'user.view', 'user.edit'],
        'user_policy_country_null_target',
        scopeKind: 'country',
        scopeReferenceId: $countryId,
    );

    $policy = app(UserPolicy::class);

    expect($policy->view($scoped, $target))->toBeFalse()
        ->and($policy->update($scoped, $target))->toBeFalse();
});

it('authorizes UserPolicy view/update for any target through an unrestricted (none-scoped) grant', function (): void {
    $countryId = auditRoomUserPolicyGeography()['countryId'];
    $target = User::factory()->create(['country_id' => $countryId]);

    $unrestricted = auditRoomUserPolicyActor(['admin_panel_access', 'user.view', 'user.edit'], 'user_policy_unrestricted');

    $policy = app(UserPolicy::class);

    expect($policy->view($unrestricted, $target))->toBeTrue()
        ->and($policy->update($unrestricted, $target))->toBeTrue();
});

it('gates UserPolicy block and restore against the user.delete permission, scoped the same way as view/update', function (): void {
    $countryId = auditRoomUserPolicyGeography()['countryId'];
    $target = User::factory()->create(['country_id' => $countryId]);

    $matching = auditRoomUserPolicyActor(
        ['admin_panel_access', 'user.delete'],
        'user_policy_delete_matching',
        scopeKind: 'country',
        scopeReferenceId: $countryId,
    );
    $mismatched = auditRoomUserPolicyActor(
        ['admin_panel_access', 'user.delete'],
        'user_policy_delete_mismatched',
        scopeKind: 'country',
        scopeReferenceId: $countryId + 1000,
    );

    $policy = app(UserPolicy::class);

    expect($policy->block($matching, $target))->toBeTrue()
        ->and($policy->restore($matching, $target))->toBeTrue();

    expect($policy->block($mismatched, $target))->toBeFalse()
        ->and($policy->restore($mismatched, $target))->toBeFalse();
});

it('denies UserPolicy block/restore for an actor holding only user.edit, since suspension is gated by the delete verb, not edit', function (): void {
    $countryId = auditRoomUserPolicyGeography()['countryId'];
    $target = User::factory()->create(['country_id' => $countryId]);

    $editOnly = auditRoomUserPolicyActor(
        ['admin_panel_access', 'user.edit'],
        'user_policy_edit_only',
        scopeKind: 'country',
        scopeReferenceId: $countryId,
    );

    $policy = app(UserPolicy::class);

    expect($policy->update($editOnly, $target))->toBeTrue()
        ->and($policy->block($editOnly, $target))->toBeFalse()
        ->and($policy->restore($editOnly, $target))->toBeFalse();
});

it('keeps a country administrator\'s UserPolicy user.edit grant from reaching StaffPolicy\'s chief-administrator-gated staff screen', function (): void {
    $countryId = auditRoomUserPolicyGeography()['countryId'];

    $countryAdmin = auditRoomUserPolicyActor(
        ['admin_panel_access', 'user.edit'],
        'user_policy_vs_staff_policy',
        scopeKind: 'country',
        scopeReferenceId: $countryId,
    );
    $staffTarget = User::factory()->create(['country_id' => $countryId]);

    // The same actor passes UserPolicy on this exact target ...
    expect(app(UserPolicy::class)->update($countryAdmin, $staffTarget))->toBeTrue();

    // ... yet StaffPolicy — which StaffResource uses instead of UserPolicy for
    // precisely this reason — never even looks at the permission grant.
    expect(app(StaffPolicy::class)->update($countryAdmin, $staffTarget))->toBeFalse();
});

it('denies every UserPolicy ability for a bystander holding no user.* grant at all', function (): void {
    $countryId = auditRoomUserPolicyGeography()['countryId'];
    $target = User::factory()->create(['country_id' => $countryId]);
    $bystander = User::factory()->create();

    $policy = app(UserPolicy::class);

    expect($policy->viewAny($bystander))->toBeFalse()
        ->and($policy->view($bystander, $target))->toBeFalse()
        ->and($policy->create($bystander))->toBeFalse()
        ->and($policy->update($bystander, $target))->toBeFalse()
        ->and($policy->block($bystander, $target))->toBeFalse()
        ->and($policy->restore($bystander, $target))->toBeFalse();
});
