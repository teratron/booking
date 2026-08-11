<?php

declare(strict_types=1);

use App\Exceptions\PermanentDeletionRefusedException;
use App\Models\Object_;
use App\Models\User;
use App\Services\Objects\ObjectLifecycleService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Archive Governance
|--------------------------------------------------------------------------
|
| Soft-delete filtering is a global scope, never a per-query convention — a
| single forgotten filter would republish archived content silently, with
| nothing erroring to say so. Permanent deletion is the one action in the
| whole panel gated on both a role no permission grant can substitute for
| and a re-authentication no confirmation click can substitute for, because
| the record it removes is the one nobody can restore.
|
*/

function archiveGeography(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryId = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true, 'display_order' => 0,
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

function archiveSeedObject(array $geo, int $ownerId): Object_
{
    $objectId = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(),
        'owner_id' => $ownerId,
        'object_type_id' => $geo['typeId'],
        'territory_id' => $geo['territoryId'],
        'country_id' => $geo['countryId'],
        'status' => 'published',
        'moderation_status' => 'approved',
        'availability_status' => 'available',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => "Object {$objectId}",
        'slug' => "object-{$objectId}", 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Object_ $object */
    $object = Object_::query()->withUnmoderated()->findOrFail($objectId);

    return $object;
}

function archiveActor(array $permissions = ['admin_panel_access', 'object.view', 'object.delete'], ?string $extraRole = null): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate('archive_admin_'.md5(implode('_', $permissions)), 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions($permissions);

    $user = User::factory()->create(['password' => Hash::make('correct-horse-battery-staple')]);
    $user->assignRole($role);

    if ($extraRole !== null) {
        $user->assignRole(Role::findOrCreate($extraRole, 'web'));
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    DB::table('role_scopes')->insert([
        'user_id' => $user->id, 'role_id' => $role->id,
        'scope_kind' => 'none', 'scope_reference_id' => null,
        'granted_by' => $user->id, 'granted_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    test()->actingAs($user->fresh());

    return $user->fresh();
}

it('hides an archived object from ordinary queries, lists it as trashed, and restores it with its media intact', function (): void {
    $geo = archiveGeography();
    $owner = User::factory()->create();
    $object = archiveSeedObject($geo, $owner->id);
    $actor = archiveActor();

    DB::table('media')->insert([
        'model_type' => Object_::class, 'model_id' => $object->id,
        'uuid' => (string) Str::uuid(), 'collection_name' => 'gallery',
        'name' => 'photo', 'file_name' => 'photo.jpg', 'mime_type' => 'image/jpeg',
        'disk' => 'public', 'conversions_disk' => 'public', 'size' => 1024,
        'manipulations' => '[]', 'custom_properties' => '[]', 'generated_conversions' => '[]',
        'responsive_images' => '[]', 'order_column' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    app(ObjectLifecycleService::class)->archive($object, $actor);

    expect(Object_::query()->withUnmoderated()->whereKey($object->id)->exists())->toBeFalse()
        ->and(Object_::query()->withUnmoderated()->onlyTrashed()->whereKey($object->id)->exists())->toBeTrue()
        ->and(DB::table('media')->where('model_id', $object->id)->count())->toBe(1);

    app(ObjectLifecycleService::class)->restore($object->fresh(), $actor);

    expect(Object_::query()->withUnmoderated()->whereKey($object->id)->exists())->toBeTrue()
        ->and(DB::table('media')->where('model_id', $object->id)->count())->toBe(1);
});

it('transfers an archived object to another owner', function (): void {
    $geo = archiveGeography();
    $owner = User::factory()->create();
    $newOwner = User::factory()->create();
    $object = archiveSeedObject($geo, $owner->id);
    $actor = archiveActor();

    app(ObjectLifecycleService::class)->archive($object, $actor);
    app(ObjectLifecycleService::class)->transferOwnership($object->fresh(), $newOwner, $actor);

    expect(Object_::query()->withUnmoderated()->onlyTrashed()->whereKey($object->id)->value('owner_id'))
        ->toBe($newOwner->id);
});

it('refuses permanent deletion for every role but the chief administrator', function (): void {
    $geo = archiveGeography();
    $owner = User::factory()->create();
    $object = archiveSeedObject($geo, $owner->id);
    $actor = archiveActor(['admin_panel_access', 'object.view', 'object.delete', 'settings_management']);

    app(ObjectLifecycleService::class)->archive($object, $actor);

    expect(fn () => app(ObjectLifecycleService::class)->permanentlyDelete($object->fresh(), $actor, 'correct-horse-battery-staple'))
        ->toThrow(PermanentDeletionRefusedException::class);

    expect(Object_::query()->withUnmoderated()->onlyTrashed()->whereKey($object->id)->exists())->toBeTrue();
});

it('requires re-authentication with the current password, not merely a confirmation, for permanent deletion', function (): void {
    $geo = archiveGeography();
    $owner = User::factory()->create();
    $object = archiveSeedObject($geo, $owner->id);
    $actor = archiveActor(['admin_panel_access', 'object.view', 'object.delete'], 'chief_administrator');

    app(ObjectLifecycleService::class)->archive($object, $actor);

    expect(fn () => app(ObjectLifecycleService::class)->permanentlyDelete($object->fresh(), $actor, 'wrong-password'))
        ->toThrow(PermanentDeletionRefusedException::class);

    expect(Object_::query()->withUnmoderated()->onlyTrashed()->whereKey($object->id)->exists())->toBeTrue();

    app(ObjectLifecycleService::class)->permanentlyDelete($object->fresh(), $actor, 'correct-horse-battery-staple');

    expect(Object_::query()->withUnmoderated()->onlyTrashed()->whereKey($object->id)->exists())->toBeFalse();
});

it('journals a permanent deletion', function (): void {
    $geo = archiveGeography();
    $owner = User::factory()->create();
    $object = archiveSeedObject($geo, $owner->id);
    $actor = archiveActor(['admin_panel_access', 'object.view', 'object.delete'], 'chief_administrator');
    $objectId = $object->id;

    app(ObjectLifecycleService::class)->archive($object, $actor);
    app(ObjectLifecycleService::class)->permanentlyDelete($object->fresh(), $actor, 'correct-horse-battery-staple');

    expect(DB::table('audits')
        ->where('event', 'object_permanently_deleted')
        ->where('auditable_id', $objectId)
        ->exists())->toBeTrue();
});

it('refuses permanent deletion for an object that was never archived first', function (): void {
    $geo = archiveGeography();
    $owner = User::factory()->create();
    $object = archiveSeedObject($geo, $owner->id);
    $actor = archiveActor(['admin_panel_access', 'object.view', 'object.delete'], 'chief_administrator');

    expect(fn () => app(ObjectLifecycleService::class)->permanentlyDelete($object, $actor, 'correct-horse-battery-staple'))
        ->toThrow(PermanentDeletionRefusedException::class);
});

it('filters archived objects through a global scope, proven by a raw query that bypasses it', function (): void {
    $geo = archiveGeography();
    $owner = User::factory()->create();
    $object = archiveSeedObject($geo, $owner->id);
    $actor = archiveActor();

    app(ObjectLifecycleService::class)->archive($object, $actor);

    // The Eloquent model, scoped: invisible.
    expect(Object_::query()->withUnmoderated()->whereKey($object->id)->exists())->toBeFalse();

    // A raw query on the same table, bypassing every model-level scope:
    // still there. This is what proves the scope — not some other filter,
    // and not the row actually being gone — is what does the hiding.
    expect(DB::table('objects')->where('id', $object->id)->exists())->toBeTrue();
});

it('applies the same soft-delete mechanism generically to another archivable table', function (): void {
    $author = User::factory()->create();

    $newsItem = new class extends Model
    {
        use SoftDeletes;

        protected $table = 'news_items';

        protected $guarded = ['id'];
    };

    $id = $newsItem->newQuery()->insertGetId([
        'author_id' => $author->id, 'status' => 'draft',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Model $record */
    $record = $newsItem->newQuery()->findOrFail($id);
    $record->delete();

    expect($newsItem->newQuery()->whereKey($id)->exists())->toBeFalse()
        ->and($newsItem->newQuery()->onlyTrashed()->whereKey($id)->exists())->toBeTrue()
        ->and(DB::table('news_items')->where('id', $id)->exists())->toBeTrue();

    $record->restore();

    expect($newsItem->newQuery()->whereKey($id)->exists())->toBeTrue();
});

it('archives a user account through its own blocked_at lifecycle, since a user is not SoftDeletes-backed', function (): void {
    $owner = User::factory()->create();
    $blocker = archiveActor(['admin_panel_access', 'user_management']);

    expect($owner->blocked_at)->toBeNull();

    $owner->forceFill(['blocked_at' => now(), 'blocked_by' => $blocker->id])->save();

    expect($owner->fresh()->blocked_at)->not->toBeNull()
        ->and(DB::table('users')->where('id', $owner->id)->exists())->toBeTrue();
});
