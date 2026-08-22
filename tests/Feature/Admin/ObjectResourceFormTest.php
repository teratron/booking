<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\Objects\ObjectResource;
use App\Filament\Admin\Resources\Objects\Pages\CreateObject;
use App\Filament\Admin\Resources\Objects\Pages\EditObject;
use App\Models\Object_;
use App\Models\User;
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
| Object Form — Tabbed Editor & the Full Lifecycle Action Set
|--------------------------------------------------------------------------
|
| Two claims matter more than any individual tab: an administrator's own
| edit through this form is never routed through moderation, and a
| category-scoped administrator is refused a save outside their scope at
| the policy — not merely by a hidden field.
|
*/

function objectFormFixture(): array
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
    $typeDining = DB::table('object_types')->insertGetId([
        'key' => 'dining', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $owner = User::factory()->create();

    $objectId = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(), 'owner_id' => $owner->id,
        'object_type_id' => $typeStay, 'territory_id' => $territoryMd, 'country_id' => $countryMd,
        'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => 'Original Villa',
        'slug' => 'original-villa', 'created_at' => now(), 'updated_at' => now(),
    ]);

    return [
        'object' => Object_::query()->withUnmoderated()->findOrFail($objectId),
        'objectId' => $objectId,
        'owner' => $owner,
        'countryMd' => $countryMd,
        'countryUa' => $countryUa,
        'typeStay' => $typeStay,
        'typeDining' => $typeDining,
    ];
}

function objectFormActor(array $permissions, string $scopeKind, ?int $reference, string $roleKey): User
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

function fullObjectPermissions(): array
{
    return ['admin_panel_access', 'object.view', 'object.create', 'object.edit', 'object.publish', 'object.delete'];
}

it('saves a contact channel with an explicit type, where the prior schema (no type field) threw a QueryException', function (): void {
    $fixture = objectFormFixture();
    $actor = objectFormActor(fullObjectPermissions(), 'none', null, 'unrestricted');
    $typeId = DB::table('contact_channel_types')->insertGetId([
        'key' => 'phone', 'link_template' => 'tel:{value}', 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    Livewire::actingAs($actor)
        ->test(EditObject::class, ['record' => $fixture['objectId']])
        ->fillForm(['contactChannels' => [
            ['contact_channel_type_id' => $typeId, 'raw_value' => '+37360000000', 'label' => 'Front desk'],
        ]])
        ->call('save')
        ->assertHasNoFormErrors();

    $channel = DB::table('contact_channels')->where('object_id', $fixture['objectId'])->first();

    expect($channel)->not->toBeNull()
        ->and($channel->contact_channel_type_id)->toBe($typeId)
        ->and($channel->raw_value)->toBe('+37360000000');
});

it('saves a translated name against the object_translations table', function (): void {
    $fixture = objectFormFixture();
    $actor = objectFormActor(fullObjectPermissions(), 'none', null, 'unrestricted');

    Livewire::actingAs($actor)
        ->test(EditObject::class, ['record' => $fixture['objectId']])
        ->fillForm(['translations' => ['en' => ['name' => 'Renamed Villa']]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(DB::table('object_translations')->where('object_id', $fixture['objectId'])->where('locale', 'en')->value('name'))
        ->toBe('Renamed Villa');
});

it('publishes directly without creating a moderation request — an administrator edit is not owner submission', function (): void {
    $fixture = objectFormFixture();
    $actor = objectFormActor(fullObjectPermissions(), 'none', null, 'unrestricted');

    Livewire::actingAs($actor)
        ->test(EditObject::class, ['record' => $fixture['objectId']])
        ->callAction('publish');

    expect(Object_::query()->withUnmoderated()->find($fixture['objectId'])->status)->toBe('published')
        ->and(DB::table('moderation_requests')->count())->toBe(0)
        ->and(DB::table('audits')->where('event', 'object_published')->count())->toBe(1);
});

it('hides a published object and journals it', function (): void {
    $fixture = objectFormFixture();
    DB::table('objects')->where('id', $fixture['objectId'])->update(['status' => 'published']);
    $actor = objectFormActor(fullObjectPermissions(), 'none', null, 'unrestricted');

    Livewire::actingAs($actor)
        ->test(EditObject::class, ['record' => $fixture['objectId']])
        ->callAction('hide');

    expect(Object_::query()->withUnmoderated()->find($fixture['objectId'])->status)->toBe('hidden')
        ->and(DB::table('audits')->where('event', 'object_hidden')->count())->toBe(1);
});

it('enqueues a decided moderation request carrying the reason when returned for revision', function (): void {
    $fixture = objectFormFixture();
    $actor = objectFormActor(fullObjectPermissions(), 'none', null, 'unrestricted');

    Livewire::actingAs($actor)
        ->test(EditObject::class, ['record' => $fixture['objectId']])
        ->mountAction('return_for_revision')
        ->setActionData(['section' => 'photographs', 'reason' => 'Cover photo is blurry.'])
        ->callMountedAction();

    $request = DB::table('moderation_requests')->where('target_id', $fixture['objectId'])->first();

    expect($request)->not->toBeNull()
        ->and($request->decision)->toBe('revision_requested')
        ->and($request->section)->toBe('photographs')
        ->and($request->comment)->toBe('Cover photo is blurry.')
        ->and(DB::table('audits')->where('event', 'object_returned_for_revision')->count())->toBe(1);
});

it('archives an object as a soft delete and restores it', function (): void {
    $fixture = objectFormFixture();
    $actor = objectFormActor(fullObjectPermissions(), 'none', null, 'unrestricted');

    Livewire::actingAs($actor)
        ->test(EditObject::class, ['record' => $fixture['objectId']])
        ->callAction('archive');

    expect(Object_::query()->withUnmoderated()->find($fixture['objectId']))->toBeNull()
        ->and(Object_::query()->withUnmoderated()->withTrashed()->find($fixture['objectId']))->not->toBeNull();

    Livewire::actingAs($actor)
        ->test(EditObject::class, ['record' => $fixture['objectId']])
        ->callAction('restore');

    expect(Object_::query()->withUnmoderated()->find($fixture['objectId']))->not->toBeNull();
});

it('duplicates without copying placement or statistics, starting the copy as a draft', function (): void {
    $fixture = objectFormFixture();
    $actor = objectFormActor(fullObjectPermissions(), 'none', null, 'unrestricted');

    DB::table('objects')->where('id', $fixture['objectId'])->update(['status' => 'published']);
    DB::table('placement_tiers')->insert([
        'id' => 1, 'rank' => 1, 'border_colour' => '#000000', 'badge_colour' => '#000000',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('placement_packages')->insert([
        'id' => 1, 'placement_tier_id' => 1, 'validity_days' => 30, 'price' => 10,
        'currency' => 'EUR', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_placements')->insert([
        'object_id' => $fixture['objectId'], 'placement_package_id' => 1,
        'starts_at' => now(), 'ends_at' => now()->addDays(30),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    Livewire::actingAs($actor)
        ->test(EditObject::class, ['record' => $fixture['objectId']])
        ->callAction('duplicate');

    $duplicateId = DB::table('objects')->where('id', '!=', $fixture['objectId'])->orderByDesc('id')->value('id');

    expect($duplicateId)->not->toBeNull();
    $duplicate = Object_::query()->withUnmoderated()->findOrFail($duplicateId);

    expect($duplicate->status)->toBe('draft')
        ->and($duplicate->name)->toBe('Original Villa')
        ->and(DB::table('object_placements')->where('object_id', $duplicateId)->count())->toBe(0);
});

it('transfers ownership and removes the outgoing owner from object_user, leaving other staff untouched', function (): void {
    $fixture = objectFormFixture();
    $actor = objectFormActor(fullObjectPermissions(), 'none', null, 'unrestricted');
    $newOwner = User::factory()->create();
    $otherStaff = User::factory()->create();

    DB::table('object_user')->insert([
        ['object_id' => $fixture['objectId'], 'user_id' => $fixture['owner']->id, 'created_at' => now(), 'updated_at' => now()],
        ['object_id' => $fixture['objectId'], 'user_id' => $otherStaff->id, 'created_at' => now(), 'updated_at' => now()],
    ]);

    Livewire::actingAs($actor)
        ->test(EditObject::class, ['record' => $fixture['objectId']])
        ->mountAction('transfer_ownership')
        ->setActionData(['new_owner_id' => $newOwner->id])
        ->callMountedAction();

    expect(Object_::query()->withUnmoderated()->find($fixture['objectId'])->owner_id)->toBe($newOwner->id)
        ->and(DB::table('object_user')->where('object_id', $fixture['objectId'])->where('user_id', $fixture['owner']->id)->exists())->toBeFalse()
        ->and(DB::table('object_user')->where('object_id', $fixture['objectId'])->where('user_id', $otherStaff->id)->exists())->toBeTrue();
});

it('refuses a category-scoped administrator saving an object of another category — refused by the scoped query, not a hidden link', function (): void {
    $fixture = objectFormFixture();
    $actor = objectFormActor(['admin_panel_access', 'object.view', 'object.edit'], 'category', $fixture['typeDining'], 'dining_only');

    // The same scope narrowing that keeps the object off this actor's list
    // also governs the edit page's own record resolution — a category
    // mismatch reads as not found, since the row genuinely falls outside
    // what the grant reaches, not as a policy refusing a record it can see.
    $this->actingAs($actor)
        ->get(ObjectResource::getUrl('edit', ['record' => $fixture['objectId']], panel: 'admin'))
        ->assertNotFound();

    expect($actor->can('update', $fixture['object']))->toBeFalse();
});

it('admits a category-scoped administrator to an object of their own category', function (): void {
    $fixture = objectFormFixture();
    $actor = objectFormActor(['admin_panel_access', 'object.view', 'object.edit'], 'category', $fixture['typeStay'], 'stay_only');

    $this->actingAs($actor)
        ->get(ObjectResource::getUrl('edit', ['record' => $fixture['objectId']], panel: 'admin'))
        ->assertSuccessful();
});

it('refuses creating an object in a country outside the administrator scope', function (): void {
    $fixture = objectFormFixture();
    $actor = objectFormActor(['admin_panel_access', 'object.view', 'object.create'], 'country', $fixture['countryMd'], 'md_only');

    $levelUa = DB::table('territory_levels')->insertGetId([
        'country_id' => $fixture['countryUa'], 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryUa = DB::table('territories')->insertGetId([
        'country_id' => $fixture['countryUa'], 'level_id' => $levelUa, 'created_at' => now(), 'updated_at' => now(),
    ]);

    Livewire::actingAs($actor)
        ->test(CreateObject::class)
        ->fillForm([
            'object_type_id' => $fixture['typeStay'],
            'country_id' => $fixture['countryUa'],
            'territory_id' => $territoryUa,
            'owner_id' => $fixture['owner']->id,
        ])
        ->call('create')
        ->assertHasFormErrors(['country_id']);

    expect(DB::table('objects')->count())->toBe(1);
});
