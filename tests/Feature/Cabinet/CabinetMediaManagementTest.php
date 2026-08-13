<?php

declare(strict_types=1);

use App\Exceptions\PhotoLimitExceededException;
use App\Filament\Cabinet\Resources\Photos\Pages\ListPhotos;
use App\Filament\Cabinet\Resources\Photos\PhotoResource;
use App\Models\Object_;
use App\Models\ObjectPhoto;
use App\Models\User;
use App\Services\Cabinet\ObjectPhotoService;
use App\Services\Settings\SettingsRepository;
use Filament\Actions\Exceptions\ActionNotResolvableException;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Cabinet Photo (Media) Management
|--------------------------------------------------------------------------
|
| An owner's gallery for the currently selected object: upload, delete,
| reorder, caption, and primary-photo selection, all against the object's
| own `photos` media collection. Every write is scoped to the tenant object
| the same way the rest of the cabinet is — refused, not merely hidden, for
| a photo belonging to another owner's object — and every upload is queued
| for the portal's own thumbnail/card conversions rather than left for the
| owner to pre-resize. Most objects an owner curates photos for have not yet
| cleared moderation, so several cases here deliberately use a draft object
| rather than a published one.
|
*/

/** @return array{countryId: int, territoryId: int, typeId: int} */
function cabinetMediaManagementGeography(): array
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
        'key' => 'accommodation', 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('countryId', 'territoryId', 'typeId');
}

function cabinetMediaManagementOwner(string $roleKey): User
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

/** @param  array{countryId: int, territoryId: int, typeId: int}  $fixture */
function cabinetMediaManagementMakeObject(array $fixture, int $ownerId, string $status = 'draft'): Object_
{
    $objectId = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(),
        'owner_id' => $ownerId,
        'object_type_id' => $fixture['typeId'],
        'territory_id' => $fixture['territoryId'],
        'country_id' => $fixture['countryId'],
        'status' => $status,
        'moderation_status' => $status === 'published' ? 'approved' : null,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => 'Gallery Villa',
        'slug' => 'gallery-villa-'.$objectId, 'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Object_ $object */
    $object = Object_::query()->withUnmoderated()->findOrFail($objectId);

    return $object;
}

function cabinetMediaManagementMountTenant(Object_ $object): void
{
    Filament::setCurrentPanel(Filament::getPanel('cabinet'));
    Filament::setTenant($object, isQuiet: true);
}

function cabinetMediaManagementAttachPhoto(Object_ $object, string $name = 'photo.jpg'): Media
{
    return $object->addMedia(UploadedFile::fake()->image($name, 1200, 800))->toMediaCollection('photos');
}

it("generates the object's queued thumbnail and card conversions for an uploaded photo, not merely stores the original", function (): void {
    Storage::fake('public');

    $fixture = cabinetMediaManagementGeography();
    $owner = cabinetMediaManagementOwner('media_owner_conversions');
    $object = cabinetMediaManagementMakeObject($fixture, $owner->id);

    $media = cabinetMediaManagementAttachPhoto($object, 'conversions.jpg');
    $media->refresh();

    expect($media->collection_name)->toBe('photos')
        ->and($media->hasGeneratedConversion('thumb'))->toBeTrue()
        ->and($media->hasGeneratedConversion('card'))->toBeTrue()
        ->and(Storage::disk('public')->exists($media->getPathRelativeToRoot('thumb')))->toBeTrue()
        ->and(Storage::disk('public')->exists($media->getPathRelativeToRoot('card')))->toBeTrue();
});

it('reads the maximum photo count from the settings registry, honoring a changed value rather than a hard-coded default', function (): void {
    Storage::fake('public');
    Storage::fake('local');

    $fixture = cabinetMediaManagementGeography();
    $owner = cabinetMediaManagementOwner('media_owner_settings_limit');
    $object = cabinetMediaManagementMakeObject($fixture, $owner->id);

    cabinetMediaManagementAttachPhoto($object, 'existing.jpg');

    $settings = app(SettingsRepository::class);
    $service = app(ObjectPhotoService::class);

    // The object already holds exactly one photo — lowering the registry's
    // own setting to that same count must refuse a further upload, proving
    // the enforced limit is read from the setting rather than some other
    // hard-coded ceiling.
    $settings->set('media.object_photos_max_count', 1);

    expect($service->maxPhotos())->toBe(1)
        ->and($service->remainingSlots($object->fresh()))->toBe(0);

    expect(fn () => $service->uploadFromDisk($object->fresh(), 'irrelevant-path.jpg', 'local'))
        ->toThrow(PhotoLimitExceededException::class);

    // Raising the very same setting must raise the enforced limit in lock
    // step — the falsifying half of this proof.
    $settings->set('media.object_photos_max_count', 3);

    expect($service->maxPhotos())->toBe(3)
        ->and($service->remainingSlots($object->fresh()))->toBe(2);

    $stagedPath = UploadedFile::fake()->image('staged.jpg', 400, 300)
        ->storeAs('object-photo-uploads', 'staged.jpg', 'local');

    $service->uploadFromDisk($object->fresh(), $stagedPath, 'local');

    expect($object->fresh()->getMedia('photos'))->toHaveCount(2);
});

it('sets a caption on a photo, and clears it back to blank', function (): void {
    Storage::fake('public');

    $fixture = cabinetMediaManagementGeography();
    $owner = cabinetMediaManagementOwner('media_owner_caption');
    $object = cabinetMediaManagementMakeObject($fixture, $owner->id);
    $media = cabinetMediaManagementAttachPhoto($object);

    app(ObjectPhotoService::class)->setCaption($media, 'Sea-facing terrace');
    expect($media->fresh()->getCustomProperty('caption'))->toBe('Sea-facing terrace');

    app(ObjectPhotoService::class)->setCaption($media, null);
    expect($media->fresh()->getCustomProperty('caption'))->toBe('');
});

it('marks exactly one photo primary at a time, clearing whichever photo held it before', function (): void {
    Storage::fake('public');

    $fixture = cabinetMediaManagementGeography();
    $owner = cabinetMediaManagementOwner('media_owner_primary');
    $object = cabinetMediaManagementMakeObject($fixture, $owner->id);
    $first = cabinetMediaManagementAttachPhoto($object, 'first.jpg');
    $second = cabinetMediaManagementAttachPhoto($object, 'second.jpg');

    $service = app(ObjectPhotoService::class);

    $service->markPrimary($object, $first);
    expect((bool) $first->fresh()->getCustomProperty('is_primary', false))->toBeTrue()
        ->and((bool) $second->fresh()->getCustomProperty('is_primary', false))->toBeFalse();

    $service->markPrimary($object, $second);
    expect((bool) $first->fresh()->getCustomProperty('is_primary', false))->toBeFalse()
        ->and((bool) $second->fresh()->getCustomProperty('is_primary', false))->toBeTrue();
});

it('deletes a photo, including its stored file, through the base Media class so the cleanup observer runs', function (): void {
    Storage::fake('public');

    $fixture = cabinetMediaManagementGeography();
    $owner = cabinetMediaManagementOwner('media_owner_delete');
    $object = cabinetMediaManagementMakeObject($fixture, $owner->id);
    $media = cabinetMediaManagementAttachPhoto($object);
    $storedPath = $media->getPathRelativeToRoot();

    expect(Storage::disk('public')->exists($storedPath))->toBeTrue();

    app(ObjectPhotoService::class)->delete($media);

    expect(Media::query()->whereKey($media->id)->exists())->toBeFalse()
        ->and(Storage::disk('public')->exists($storedPath))->toBeFalse();
});

it("scopes an owner's photo screen to their own object and refuses another owner's tenant, not merely hiding it", function (): void {
    $fixture = cabinetMediaManagementGeography();
    $ownerA = cabinetMediaManagementOwner('media_owner_scope_a');
    $ownerB = cabinetMediaManagementOwner('media_owner_scope_b');
    $objectA = cabinetMediaManagementMakeObject($fixture, $ownerA->id);
    $objectB = cabinetMediaManagementMakeObject($fixture, $ownerB->id);

    $this->actingAs($ownerA)
        ->get(PhotoResource::getUrl('index', panel: 'cabinet', tenant: $objectA))
        ->assertSuccessful();

    // Refused as not-found, exactly like the object-edit screen's own
    // cross-owner case: the tenant itself sits outside ownerA's reachable
    // set, so the request never even reaches this resource's own query.
    $this->actingAs($ownerA)
        ->get(PhotoResource::getUrl('index', panel: 'cabinet', tenant: $objectB))
        ->assertNotFound();
});

it("resolves the object's gallery correctly even before the object has cleared moderation, since that is when an owner curates photos most", function (): void {
    $fixture = cabinetMediaManagementGeography();
    $owner = cabinetMediaManagementOwner('media_owner_draft_scope');
    $draft = cabinetMediaManagementMakeObject($fixture, $owner->id, 'draft');

    expect($draft->moderation_status)->toBeNull();

    Storage::fake('public');
    $media = cabinetMediaManagementAttachPhoto($draft);
    /** @var ObjectPhoto $photo */
    $photo = ObjectPhoto::query()->findOrFail($media->id);

    // The relation this whole feature's tenancy and Policy checks lean on
    // must not silently disappear just because the object has not yet been
    // approved — the same escape hatch the cabinet's own tenant resolver
    // already relies on for the identical reason.
    expect($photo->object)->not->toBeNull()
        ->and($photo->object?->is($draft))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('update', $photo))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('delete', $photo))->toBeTrue();
});

it("refuses the bound Policy for an owner acting on another owner's photo, at the record level", function (): void {
    $fixture = cabinetMediaManagementGeography();
    $ownerA = cabinetMediaManagementOwner('media_owner_policy_a');
    $ownerB = cabinetMediaManagementOwner('media_owner_policy_b');
    $objectA = cabinetMediaManagementMakeObject($fixture, $ownerA->id);
    $objectB = cabinetMediaManagementMakeObject($fixture, $ownerB->id);

    Storage::fake('public');
    $mediaA = cabinetMediaManagementAttachPhoto($objectA, 'a.jpg');
    $mediaB = cabinetMediaManagementAttachPhoto($objectB, 'b.jpg');

    /** @var ObjectPhoto $photoA */
    $photoA = ObjectPhoto::query()->findOrFail($mediaA->id);
    /** @var ObjectPhoto $photoB */
    $photoB = ObjectPhoto::query()->findOrFail($mediaB->id);

    expect(Gate::forUser($ownerA)->allows('update', $photoA))->toBeTrue()
        ->and(Gate::forUser($ownerA)->allows('view', $photoB))->toBeFalse()
        ->and(Gate::forUser($ownerA)->allows('update', $photoB))->toBeFalse()
        ->and(Gate::forUser($ownerA)->allows('delete', $photoB))->toBeFalse();
});

it("refuses a table action on another owner's photo even while mounted on one's own tenant, treating the record as not found", function (): void {
    $fixture = cabinetMediaManagementGeography();
    $ownerA = cabinetMediaManagementOwner('media_owner_row_action_a');
    $ownerB = cabinetMediaManagementOwner('media_owner_row_action_b');
    $objectA = cabinetMediaManagementMakeObject($fixture, $ownerA->id);
    $objectB = cabinetMediaManagementMakeObject($fixture, $ownerB->id);

    Storage::fake('public');
    $mediaB = cabinetMediaManagementAttachPhoto($objectB, 'other-owner.jpg');

    // A real routed request first: Filament only registers its tenant-scope
    // global scope on the resource's model from inside the `SetUpPanel` HTTP
    // middleware, the first time a request actually reaches the panel — the
    // manual `Filament::setTenant()` call the rest of this suite otherwise
    // relies on mounts a tenant but never triggers that registration on its
    // own. Without this, the table's own query below would be unscoped.
    $this->actingAs($ownerA)->get(PhotoResource::getUrl('index', panel: 'cabinet', tenant: $objectA))->assertSuccessful();

    cabinetMediaManagementMountTenant($objectA);

    $attempt = fn () => Livewire::actingAs($ownerA)
        ->test(ListPhotos::class)
        ->callTableAction('delete', $mediaB);

    expect($attempt)->toThrow(ActionNotResolvableException::class);

    expect(Media::query()->whereKey($mediaB->id)->exists())->toBeTrue();
});

it('captions, marks primary, and deletes a photo through the real cabinet table row actions', function (): void {
    Storage::fake('public');

    $fixture = cabinetMediaManagementGeography();
    $owner = cabinetMediaManagementOwner('media_owner_row_actions');
    $object = cabinetMediaManagementMakeObject($fixture, $owner->id);
    $first = cabinetMediaManagementAttachPhoto($object, 'row-first.jpg');
    $second = cabinetMediaManagementAttachPhoto($object, 'row-second.jpg');

    cabinetMediaManagementMountTenant($object);

    Livewire::actingAs($owner)
        ->test(ListPhotos::class)
        ->assertTableActionExists('edit_caption', record: $first)
        ->callTableAction('edit_caption', $first, ['caption' => 'Ocean view'])
        ->assertHasNoTableActionErrors()
        ->callTableAction('set_primary', $second)
        ->callTableAction('delete', $first);

    expect(Media::query()->whereKey($first->id)->exists())->toBeFalse()
        ->and((bool) $second->fresh()->getCustomProperty('is_primary', false))->toBeTrue();
});

it("reorders photos through the table's own drag-to-reorder, scoped to the current tenant's query", function (): void {
    Storage::fake('public');

    $fixture = cabinetMediaManagementGeography();
    $owner = cabinetMediaManagementOwner('media_owner_reorder');
    $object = cabinetMediaManagementMakeObject($fixture, $owner->id);
    $first = cabinetMediaManagementAttachPhoto($object, 'reorder-1.jpg');
    $second = cabinetMediaManagementAttachPhoto($object, 'reorder-2.jpg');
    $third = cabinetMediaManagementAttachPhoto($object, 'reorder-3.jpg');

    cabinetMediaManagementMountTenant($object);

    Livewire::actingAs($owner)
        ->test(ListPhotos::class)
        ->call('reorderTable', [(string) $third->id, (string) $first->id, (string) $second->id]);

    expect(Media::query()->whereKey($third->id)->value('order_column'))->toBe(1)
        ->and(Media::query()->whereKey($first->id)->value('order_column'))->toBe(2)
        ->and(Media::query()->whereKey($second->id)->value('order_column'))->toBe(3);
});

it('uploads photos into the tenant object\'s gallery through the real cabinet upload screen', function (): void {
    // Three disks are in play: Livewire stages the picked file on the
    // application's own default disk, Filament's `FileUpload` field then
    // moves it onto its own configured disk/directory once the action's
    // data is read, and `spatie/laravel-medialibrary` persists the
    // permanent copy (plus conversions) on its own configured disk.
    Storage::fake(config('filesystems.default'));
    Storage::fake('local');
    Storage::fake('public');

    $fixture = cabinetMediaManagementGeography();
    $owner = cabinetMediaManagementOwner('media_owner_upload_screen');
    $object = cabinetMediaManagementMakeObject($fixture, $owner->id);

    app(SettingsRepository::class)->set('media.object_photos_max_count', 10);

    cabinetMediaManagementMountTenant($object);

    Livewire::actingAs($owner)
        ->test(ListPhotos::class)
        ->mountTableAction('upload')
        ->setTableActionData(['files' => [UploadedFile::fake()->image('screen-upload.jpg', 1200, 900)]])
        ->callMountedTableAction();

    $gallery = $object->fresh()->getMedia('photos');

    expect($gallery)->toHaveCount(1)
        ->and($gallery->first()?->collection_name)->toBe('photos')
        ->and($gallery->first()?->hasGeneratedConversion('thumb'))->toBeTrue();
});

it('refuses a batch upload that would push the gallery past the settings-registry limit, applying none of it', function (): void {
    Storage::fake(config('filesystems.default'));
    Storage::fake('local');
    Storage::fake('public');

    $fixture = cabinetMediaManagementGeography();
    $owner = cabinetMediaManagementOwner('media_owner_upload_over_limit');
    $object = cabinetMediaManagementMakeObject($fixture, $owner->id);

    app(SettingsRepository::class)->set('media.object_photos_max_count', 1);

    cabinetMediaManagementMountTenant($object);

    Livewire::actingAs($owner)
        ->test(ListPhotos::class)
        ->mountTableAction('upload')
        ->setTableActionData(['files' => [
            UploadedFile::fake()->image('batch-1.jpg', 800, 600),
            UploadedFile::fake()->image('batch-2.jpg', 800, 600),
        ]])
        ->callMountedTableAction();

    expect($object->fresh()->getMedia('photos'))->toHaveCount(0);
});
