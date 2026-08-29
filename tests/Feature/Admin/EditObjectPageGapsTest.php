<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\Objects\ObjectResource;
use App\Filament\Admin\Resources\Objects\Pages\EditObject;
use App\Models\Object_;
use App\Models\Territory;
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
| Edit Object Page — Remaining Header Actions
|--------------------------------------------------------------------------
|
| PlacementGrantAdministrationTest already covers grant/pin/unpin, and
| ObjectResourceFormTest already covers the form-field surface and the
| ordinary lifecycle actions (publish, hide, archive, duplicate, transfer,
| return-for-revision). What is left uncovered on this page is: the
| slug-claimed-by-redirect branch of the translation save path, permanent
| deletion (password re-authentication and its refusal), the availability
| override/revert actions, the administrator bump action, and the merge
| action's full action-body wiring (survivor selection either direction,
| the out-of-scope refusal on the *other* record, and a same-record
| refusal) — none of which either existing file exercises.
|
*/

/** @return array{objectId: int, object: Object_, countryMd: int, typeStay: int, typeDining: int, languageId: int} */
function editObjectGapsFixture(): array
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
        'objectId' => $objectId,
        'object' => Object_::query()->withUnmoderated()->findOrFail($objectId),
        'countryMd' => $countryMd,
        'territoryMd' => $territoryMd,
        'typeStay' => $typeStay,
        'typeDining' => $typeDining,
        'languageId' => $languageId,
    ];
}

function editObjectGapsActor(array $permissions, string $scopeKind, ?int $reference, string $roleKey): User
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
function editObjectGapsFullPermissions(): array
{
    return ['admin_panel_access', 'object.view', 'object.create', 'object.edit', 'object.publish', 'object.delete', 'commerce.edit'];
}

/** @return array{tier: int, package: int} */
function editObjectGapsBumpablePackage(bool $bumpAllowed = true): array
{
    $tierId = DB::table('placement_tiers')->insertGetId([
        'rank' => 1, 'border_colour' => '#000000', 'badge_colour' => '#ffffff', 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $packageId = DB::table('placement_packages')->insertGetId([
        'placement_tier_id' => $tierId, 'price' => 10, 'currency' => 'EUR', 'validity_days' => 30,
        'bump_allowed' => $bumpAllowed, 'is_active' => true, 'display_order' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return ['tier' => $tierId, 'package' => $packageId];
}

function editObjectGapsPlace(int $objectId, int $packageId): void
{
    DB::table('object_placements')->insert([
        'object_id' => $objectId, 'placement_package_id' => $packageId,
        'starts_at' => now()->subDays(2)->toDateString(), 'ends_at' => now()->addDays(28)->toDateString(),
        'created_at' => now()->subDays(2), 'updated_at' => now(),
    ]);
}

/*
|--------------------------------------------------------------------------
| Slug claimed by an active redirect
|--------------------------------------------------------------------------
*/

it('skips only the locale whose new slug is claimed by an active redirect, while the rest of the save still applies', function (): void {
    $fixture = editObjectGapsFixture();
    $actor = editObjectGapsActor(editObjectGapsFullPermissions(), 'none', null, 'unrestricted');

    // A redirect already promises that "o/renamed-villa" leads elsewhere —
    // reusing it as this object's own new slug must be refused.
    DB::table('redirects')->insert([
        'locale' => 'en', 'from_path' => 'o/renamed-villa', 'to_path' => 'o/somewhere-else',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    Livewire::actingAs($actor)
        ->test(EditObject::class, ['record' => $fixture['objectId']])
        ->fillForm([
            'translations' => ['en' => ['slug' => 'renamed-villa']],
            'status' => 'published',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified(__('panel.objects.form.slug_claimed_by_redirect', ['path' => 'o/renamed-villa']));

    // The translation write for the claimed locale never happened...
    expect(DB::table('object_translations')->where('object_id', $fixture['objectId'])->where('locale', 'en')->value('slug'))
        ->toBe('original-villa')
        // ...but the rest of the form's changes, on the object row itself, still saved.
        ->and(DB::table('objects')->where('id', $fixture['objectId'])->value('status'))->toBe('published');
});

it('registers a redirect from the old slug to the new one when the new slug is not claimed', function (): void {
    $fixture = editObjectGapsFixture();
    $actor = editObjectGapsActor(editObjectGapsFullPermissions(), 'none', null, 'unrestricted');

    Livewire::actingAs($actor)
        ->test(EditObject::class, ['record' => $fixture['objectId']])
        ->fillForm(['translations' => ['en' => ['slug' => 'free-slug']]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(DB::table('object_translations')->where('object_id', $fixture['objectId'])->where('locale', 'en')->value('slug'))
        ->toBe('free-slug')
        ->and(DB::table('redirects')->where('locale', 'en')->where('from_path', 'o/original-villa')->value('to_path'))
        ->toBe('o/free-slug');
});

/*
|--------------------------------------------------------------------------
| Permanent Deletion — Re-authentication Gate
|--------------------------------------------------------------------------
*/

it('permanently deletes an archived object once the chief administrator confirms their own password', function (): void {
    $fixture = editObjectGapsFixture();
    DB::table('objects')->where('id', $fixture['objectId'])->update(['deleted_at' => now()]);
    $actor = editObjectGapsActor(editObjectGapsFullPermissions(), 'none', null, 'chief_administrator');

    Livewire::actingAs($actor)
        ->test(EditObject::class, ['record' => $fixture['objectId']])
        ->mountAction('permanently_delete')
        ->setActionData(['password' => 'password'])
        ->callMountedAction()
        ->assertRedirect(ObjectResource::getUrl());

    expect(DB::table('objects')->where('id', $fixture['objectId'])->exists())->toBeFalse()
        ->and(DB::table('audits')->where('auditable_id', $fixture['objectId'])->where('event', 'object_permanently_deleted')->count())
        ->toBe(1);
});

it('refuses permanent deletion when the confirmation password does not match, leaving the archived record intact', function (): void {
    $fixture = editObjectGapsFixture();
    DB::table('objects')->where('id', $fixture['objectId'])->update(['deleted_at' => now()]);
    $actor = editObjectGapsActor(editObjectGapsFullPermissions(), 'none', null, 'chief_administrator');

    Livewire::actingAs($actor)
        ->test(EditObject::class, ['record' => $fixture['objectId']])
        ->mountAction('permanently_delete')
        ->setActionData(['password' => 'not-the-right-password'])
        ->callMountedAction()
        ->assertNotified(__('panel.objects.lifecycle.permanently_delete_refused'));

    expect(DB::table('objects')->where('id', $fixture['objectId'])->whereNotNull('deleted_at')->exists())->toBeTrue()
        ->and(DB::table('audits')->where('auditable_id', $fixture['objectId'])->where('event', 'object_permanently_deleted')->count())
        ->toBe(0);
});

it('never offers permanent deletion to an administrator who is not the chief administrator, even with the delete grant', function (): void {
    $fixture = editObjectGapsFixture();
    DB::table('objects')->where('id', $fixture['objectId'])->update(['deleted_at' => now()]);
    $actor = editObjectGapsActor(editObjectGapsFullPermissions(), 'none', null, 'country_administrator');

    Livewire::actingAs($actor)
        ->test(EditObject::class, ['record' => $fixture['objectId']])
        ->assertActionHidden('permanently_delete');
});

/*
|--------------------------------------------------------------------------
| Availability Override & Revert
|--------------------------------------------------------------------------
*/

it('overrides an object\'s availability status, journaling the transition and its administrator comment', function (): void {
    $fixture = editObjectGapsFixture();
    $actor = editObjectGapsActor(editObjectGapsFullPermissions(), 'none', null, 'unrestricted');

    Livewire::actingAs($actor)
        ->test(EditObject::class, ['record' => $fixture['objectId']])
        ->mountAction('override_availability')
        ->setActionData(['availability_status' => 'unavailable', 'comment' => 'Fully booked this week'])
        ->callMountedAction()
        ->assertNotified(__('panel.objects.lifecycle.applied'));

    $fresh = Object_::query()->withUnmoderated()->findOrFail($fixture['objectId']);

    expect($fresh->availability_status)->toBe('unavailable')
        ->and($fresh->availability_previous_status)->toBe('available')
        ->and($fresh->availability_comment)->toBe('Fully booked this week')
        ->and($fresh->availabilityHistories()->where('from_status', 'available')->where('to_status', 'unavailable')->exists())
        ->toBeTrue();
});

it('hides the revert action until an override has left a prior status to revert to, then reverts and journals it again', function (): void {
    $fixture = editObjectGapsFixture();
    $actor = editObjectGapsActor(editObjectGapsFullPermissions(), 'none', null, 'unrestricted');

    $component = Livewire::actingAs($actor)->test(EditObject::class, ['record' => $fixture['objectId']]);
    $component->assertActionHidden('revert_availability');

    $component->mountAction('override_availability')
        ->setActionData(['availability_status' => 'unavailable'])
        ->callMountedAction();

    $component = Livewire::actingAs($actor)->test(EditObject::class, ['record' => $fixture['objectId']]);
    $component->assertActionVisible('revert_availability')
        ->callAction('revert_availability');

    $fresh = Object_::query()->withUnmoderated()->findOrFail($fixture['objectId']);

    expect($fresh->availability_status)->toBe('available')
        ->and($fresh->availabilityHistories()->where('from_status', 'unavailable')->where('to_status', 'available')->exists())
        ->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Administrator Bump
|--------------------------------------------------------------------------
*/

it('bumps an object to the first position within its own tier, scoped to the object\'s own territory', function (): void {
    $fixture = editObjectGapsFixture();
    $actor = editObjectGapsActor(editObjectGapsFullPermissions(), 'none', null, 'unrestricted');
    $package = editObjectGapsBumpablePackage();
    editObjectGapsPlace($fixture['objectId'], $package['package']);

    Livewire::actingAs($actor)
        ->test(EditObject::class, ['record' => $fixture['objectId']])
        ->mountAction('bump')
        ->setActionData(['comment' => 'Administrator bump for a partner'])
        ->callMountedAction()
        ->assertNotified(__('panel.objects.lifecycle.applied'));

    $event = DB::table('bump_events')->where('object_id', $fixture['objectId'])->first();

    expect($event)->not->toBeNull()
        ->and($event->type)->toBe('administrator')
        ->and($event->scope_type)->toBe(Territory::class)
        ->and($event->scope_id)->toBe($fixture['territoryMd'])
        ->and($event->comment)->toBe('Administrator bump for a partner')
        ->and($event->price)->toBeNull()
        ->and(DB::table('audits')->where('auditable_id', $fixture['objectId'])->where('event', 'object_bumped')->count())->toBe(1);
});

it('never offers the bump action when the object\'s current package forbids bumping', function (): void {
    $fixture = editObjectGapsFixture();
    $actor = editObjectGapsActor(editObjectGapsFullPermissions(), 'none', null, 'unrestricted');
    $package = editObjectGapsBumpablePackage(bumpAllowed: false);
    editObjectGapsPlace($fixture['objectId'], $package['package']);

    Livewire::actingAs($actor)
        ->test(EditObject::class, ['record' => $fixture['objectId']])
        ->assertActionHidden('bump');
});

/*
|--------------------------------------------------------------------------
| Merge — Panel Action Wiring
|--------------------------------------------------------------------------
*/

it('merges keeping the currently open record as survivor, archiving the other one picked from the panel', function (): void {
    $fixture = editObjectGapsFixture();
    $actor = editObjectGapsActor(editObjectGapsFullPermissions(), 'none', null, 'unrestricted');

    $otherId = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(), 'owner_id' => $fixture['object']->owner_id,
        'object_type_id' => $fixture['typeStay'], 'territory_id' => $fixture['territoryMd'], 'country_id' => $fixture['countryMd'],
        'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_translations')->insert([
        'object_id' => $otherId, 'locale' => 'en', 'name' => 'Duplicate Villa',
        'slug' => 'duplicate-villa', 'created_at' => now(), 'updated_at' => now(),
    ]);

    Livewire::actingAs($actor)
        ->test(EditObject::class, ['record' => $fixture['objectId']])
        ->mountAction('merge')
        ->setActionData(['other_object_id' => $otherId, 'keep' => 'current'])
        ->callMountedAction()
        ->assertNotified(__('panel.objects.lifecycle.applied'));

    expect(Object_::query()->withUnmoderated()->find($fixture['objectId']))->not->toBeNull()
        ->and(Object_::query()->withUnmoderated()->find($otherId))->toBeNull()
        ->and(Object_::query()->withUnmoderated()->withTrashed()->findOrFail($otherId)->trashed())->toBeTrue();
});

it('merges keeping the other selected record as survivor, redirecting away from the now-archived current one', function (): void {
    $fixture = editObjectGapsFixture();
    $actor = editObjectGapsActor(editObjectGapsFullPermissions(), 'none', null, 'unrestricted');

    $otherId = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(), 'owner_id' => $fixture['object']->owner_id,
        'object_type_id' => $fixture['typeStay'], 'territory_id' => $fixture['territoryMd'], 'country_id' => $fixture['countryMd'],
        'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_translations')->insert([
        'object_id' => $otherId, 'locale' => 'en', 'name' => 'Duplicate Villa',
        'slug' => 'duplicate-villa', 'created_at' => now(), 'updated_at' => now(),
    ]);

    Livewire::actingAs($actor)
        ->test(EditObject::class, ['record' => $fixture['objectId']])
        ->mountAction('merge')
        ->setActionData(['other_object_id' => $otherId, 'keep' => 'other'])
        ->callMountedAction()
        ->assertRedirect(ObjectResource::getUrl('edit', ['record' => $otherId]));

    expect(Object_::query()->withUnmoderated()->find($otherId))->not->toBeNull()
        ->and(Object_::query()->withUnmoderated()->find($fixture['objectId']))->toBeNull()
        ->and(Object_::query()->withUnmoderated()->withTrashed()->findOrFail($fixture['objectId'])->trashed())->toBeTrue();
});

it('refuses the merge when the picked other record falls outside the acting administrator\'s own scope', function (): void {
    $fixture = editObjectGapsFixture();
    // Scoped to the current object's own category (accommodation) only.
    // object.edit is required to open the edit page at all (Object_Policy::update());
    // object.delete alone gates the merge action itself (Object_Policy::merge()) but
    // the actor never reaches it without also being able to view the page.
    $actor = editObjectGapsActor(['admin_panel_access', 'object.view', 'object.edit', 'object.delete'], 'category', $fixture['typeStay'], 'stay_only');

    $otherId = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(), 'owner_id' => $fixture['object']->owner_id,
        'object_type_id' => $fixture['typeDining'], 'territory_id' => $fixture['territoryMd'], 'country_id' => $fixture['countryMd'],
        'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_translations')->insert([
        'object_id' => $otherId, 'locale' => 'en', 'name' => 'Out Of Scope Diner',
        'slug' => 'out-of-scope-diner', 'created_at' => now(), 'updated_at' => now(),
    ]);

    Livewire::actingAs($actor)
        ->test(EditObject::class, ['record' => $fixture['objectId']])
        ->mountAction('merge')
        ->setActionData(['other_object_id' => $otherId, 'keep' => 'current'])
        ->callMountedAction()
        ->assertNotified(__('panel.objects.form.out_of_scope'));

    expect(Object_::query()->withUnmoderated()->find($otherId))->not->toBeNull()
        ->and(Object_::query()->withUnmoderated()->find($fixture['objectId']))->not->toBeNull();
});

it('refuses to merge the open record with itself and surfaces the translated refusal', function (): void {
    $fixture = editObjectGapsFixture();
    $actor = editObjectGapsActor(editObjectGapsFullPermissions(), 'none', null, 'unrestricted');

    Livewire::actingAs($actor)
        ->test(EditObject::class, ['record' => $fixture['objectId']])
        ->mountAction('merge')
        ->setActionData(['other_object_id' => $fixture['objectId'], 'keep' => 'current'])
        ->callMountedAction()
        ->assertNotified(__('panel.objects.merge.refused'));

    expect(Object_::query()->withUnmoderated()->find($fixture['objectId']))->not->toBeNull()
        ->and(Object_::query()->withUnmoderated()->find($fixture['objectId'])->trashed())->toBeFalse();
});
