<?php

declare(strict_types=1);

use App\Filament\Cabinet\Resources\Objects\ObjectResource;
use App\Filament\Cabinet\Resources\Objects\Pages\EditObject;
use App\Models\Object_;
use App\Models\User;
use App\Services\Cabinet\ObjectPublicationEligibilityService;
use Filament\Facades\Filament;
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
| Cabinet Object Editing & Lifecycle
|--------------------------------------------------------------------------
|
| The hard gate for the rest of the object-management track: the field set a
| type declares must render exactly, grouped into Core/Geography/Contacts/
| Translations/SEO; a draft may stay incomplete until it genuinely is not;
| and — the first real exercise `ModerationPipeline` has ever had outside its
| own unit tests — an edit to an *already-published* object must route
| through it, publishing immediately or entering the queue exactly as the
| resolved mode for that object's scope dictates. The immediate-mode and
| review-mode cases below are a falsifying pair on purpose: either one
| passing alone would not prove the branch, only that *some* path was taken.
|
*/

/** @return array{countryId: int, territoryId: int, typeId: int} */
function cabinetObjectEditingGeography(): array
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
        // A single type-declared field this type carries beyond the fixed
        // columns — proves the form renders exactly what the registry
        // declares, not a hard-coded field list.
        'attribute_schema' => json_encode([
            ['key' => 'floor_count', 'type' => 'number', 'label' => ['en' => 'Floor count']],
        ]),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('countryId', 'territoryId', 'typeId');
}

function cabinetObjectEditingOwner(string $roleKey): User
{
    // Deliberately the object_owner role's own real permission set
    // (`object.view`, `object.edit`, `cabinet_access` — no `object.publish`,
    // no `object.delete`), not an inflated one: proving this task's own
    // behaviour holds for the owner this panel is actually built for.
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

/** @param  array<string, mixed>  $overrides */
function cabinetObjectEditingMakeObject(array $fixture, int $ownerId, string $status, array $overrides = []): Object_
{
    $objectId = DB::table('objects')->insertGetId(array_merge([
        'ulid' => (string) Str::ulid(),
        'owner_id' => $ownerId,
        'object_type_id' => $fixture['typeId'],
        'territory_id' => $fixture['territoryId'],
        'country_id' => $fixture['countryId'],
        'status' => $status,
        'moderation_status' => $status === 'published' ? 'approved' : null,
        // Coordinates are required by this form; every fixture starts with
        // some, the same way a staff-provisioned object would arrive in the
        // cabinet with its location already pinned.
        'latitude' => 45.1234500,
        'longitude' => 28.1234500,
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));

    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => 'Original Villa',
        'slug' => 'original-villa-'.$objectId, 'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Object_ $object */
    $object = Object_::query()->withUnmoderated()->findOrFail($objectId);

    return $object;
}

function cabinetObjectEditingSetMode(int $objectId, string $mode): void
{
    DB::table('moderation_settings')->insert([
        'scope_level' => 'object', 'scope_reference_id' => $objectId, 'mode' => $mode,
        'set_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function cabinetObjectEditingMountTenant(Object_ $object): void
{
    Filament::setCurrentPanel(Filament::getPanel('cabinet'));
    Filament::setTenant($object, isQuiet: true);
}

it("renders exactly the declared type's field set, grouped into Core, Geography, Contacts, Translations, and SEO", function (): void {
    $fixture = cabinetObjectEditingGeography();
    $owner = cabinetObjectEditingOwner('object_owner_render_sections');
    $object = cabinetObjectEditingMakeObject($fixture, $owner->id, 'draft');

    $response = $this->actingAs($owner)
        ->followingRedirects()
        ->get(ObjectResource::getUrl('edit', ['record' => $object], panel: 'cabinet', tenant: $object));

    $response->assertSuccessful()
        ->assertSee(__('panel.cabinet.objects.sections.core'))
        ->assertSee(__('panel.cabinet.objects.sections.geography'))
        ->assertSee(__('panel.cabinet.objects.sections.contacts'))
        ->assertSee(__('panel.cabinet.objects.sections.translations'))
        ->assertSee(__('panel.cabinet.objects.sections.seo'))
        // The type's own declared field — proves the schema is read from
        // the registry, not hard-coded.
        ->assertSee('Floor count');
});

it("scopes an owner's edit page to their own object and refuses another owner's, not merely hiding it", function (): void {
    $fixture = cabinetObjectEditingGeography();
    $ownerA = cabinetObjectEditingOwner('object_owner_scope_a');
    $ownerB = cabinetObjectEditingOwner('object_owner_scope_b');
    $objectA = cabinetObjectEditingMakeObject($fixture, $ownerA->id, 'draft');
    $objectB = cabinetObjectEditingMakeObject($fixture, $ownerB->id, 'draft');

    $this->actingAs($ownerA)
        ->get(ObjectResource::getUrl('edit', ['record' => $objectA], panel: 'cabinet', tenant: $objectA))
        ->assertSuccessful();

    // Refused as not-found, not forbidden: the tenant itself is outside
    // ownerA's own reachable set, the same way a category mismatch reads as
    // not-found for a scoped administrator on the admin side — the record
    // genuinely falls outside what this session can resolve, not a policy
    // declining a row it can otherwise see.
    $this->actingAs($ownerA)
        ->get(ObjectResource::getUrl('edit', ['record' => $objectB], panel: 'cabinet', tenant: $objectB))
        ->assertNotFound();
});

it('lets an owner save incomplete descriptive content directly as a draft, with no moderation request created', function (): void {
    $fixture = cabinetObjectEditingGeography();
    $owner = cabinetObjectEditingOwner('object_owner_draft_partial_save');
    $object = cabinetObjectEditingMakeObject($fixture, $owner->id, 'draft');
    // Wipe the name a draft would not yet have — this object is genuinely
    // incomplete, not merely mid-edit.
    DB::table('object_translations')->where('object_id', $object->id)->update(['name' => '']);

    cabinetObjectEditingMountTenant($object);

    Livewire::actingAs($owner)
        ->test(EditObject::class, ['record' => $object->getKey()])
        ->fillForm(['address' => 'Central Street 1'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(DB::table('objects')->where('id', $object->id)->value('address'))->toBe('Central Street 1')
        ->and(DB::table('objects')->where('id', $object->id)->value('status'))->toBe('draft')
        ->and(DB::table('moderation_requests')->count())->toBe(0);
});

it('becomes eligible for publication only once its required fields are complete', function (): void {
    $fixture = cabinetObjectEditingGeography();
    $owner = cabinetObjectEditingOwner('object_owner_eligibility_flow');
    $object = cabinetObjectEditingMakeObject($fixture, $owner->id, 'draft', ['latitude' => null, 'longitude' => null]);
    DB::table('object_translations')->where('object_id', $object->id)->update(['name' => '']);

    $eligibility = app(ObjectPublicationEligibilityService::class);

    expect($eligibility->missingRequirements($object->fresh()))->toBe(['coordinates', 'name']);

    cabinetObjectEditingMountTenant($object);

    // Filling in coordinates alone is not enough yet.
    Livewire::actingAs($owner)
        ->test(EditObject::class, ['record' => $object->getKey()])
        ->fillForm(['latitude' => 45.5, 'longitude' => 28.5])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($eligibility->missingRequirements($object->fresh()))->toBe(['name']);

    // Filling in the name completes it.
    Livewire::actingAs($owner)
        ->test(EditObject::class, ['record' => $object->getKey()])
        ->fillForm(['translations' => ['en' => ['name' => 'Complete Villa']]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($eligibility->isEligibleForPublication($object->fresh()))->toBeTrue();
});

it('requires coordinates as real numeric fields — a blank or non-numeric value refuses the save', function (): void {
    $fixture = cabinetObjectEditingGeography();
    $owner = cabinetObjectEditingOwner('object_owner_coordinates_required');
    $object = cabinetObjectEditingMakeObject($fixture, $owner->id, 'draft');

    cabinetObjectEditingMountTenant($object);

    Livewire::actingAs($owner)
        ->test(EditObject::class, ['record' => $object->getKey()])
        ->fillForm(['latitude' => null, 'longitude' => null])
        ->call('save')
        ->assertHasFormErrors(['latitude', 'longitude']);

    Livewire::actingAs($owner)
        ->test(EditObject::class, ['record' => $object->getKey()])
        ->fillForm(['latitude' => 'north-ish', 'longitude' => 'east-ish'])
        ->call('save')
        ->assertHasFormErrors(['latitude', 'longitude']);

    Livewire::actingAs($owner)
        ->test(EditObject::class, ['record' => $object->getKey()])
        ->fillForm(['latitude' => '46.4', 'longitude' => '30.7'])
        ->call('save')
        ->assertHasNoFormErrors();

    $stored = DB::table('objects')->where('id', $object->id)->first();
    expect((float) $stored->latitude)->toBe(46.4)
        ->and((float) $stored->longitude)->toBe(30.7);
});

it('publishes an edit to an already-published object immediately when the resolved mode is immediate', function (): void {
    $fixture = cabinetObjectEditingGeography();
    $owner = cabinetObjectEditingOwner('object_owner_moderation_immediate');
    $object = cabinetObjectEditingMakeObject($fixture, $owner->id, 'published');
    cabinetObjectEditingSetMode($object->id, 'immediate');

    cabinetObjectEditingMountTenant($object);

    Livewire::actingAs($owner)
        ->test(EditObject::class, ['record' => $object->getKey()])
        ->fillForm(['translations' => ['en' => ['name' => 'Renamed Villa']]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(DB::table('object_translations')->where('object_id', $object->id)->where('locale', 'en')->value('name'))
        ->toBe('Renamed Villa')
        ->and(DB::table('moderation_requests')->count())->toBe(0);
});

it('withholds the same edit and enqueues it instead once the resolved mode is review — the falsifying counterpart', function (): void {
    $fixture = cabinetObjectEditingGeography();
    $owner = cabinetObjectEditingOwner('object_owner_moderation_review');
    $object = cabinetObjectEditingMakeObject($fixture, $owner->id, 'published');
    cabinetObjectEditingSetMode($object->id, 'review');

    cabinetObjectEditingMountTenant($object);

    Livewire::actingAs($owner)
        ->test(EditObject::class, ['record' => $object->getKey()])
        ->fillForm(['translations' => ['en' => ['name' => 'Renamed Villa']]])
        ->call('save')
        ->assertHasNoFormErrors();

    // The live record is untouched — a pending edit never overwrites what
    // is already published.
    expect(DB::table('object_translations')->where('object_id', $object->id)->where('locale', 'en')->value('name'))
        ->toBe('Original Villa');

    $request = DB::table('moderation_requests')->where('target_id', $object->id)->where('target_type', Object_::class)->first();

    expect($request)->not->toBeNull()
        ->and($request->section)->toBe('name')
        ->and($request->decision)->toBe('pending')
        ->and($request->submitted_by)->toBe($owner->id);

    $proposed = json_decode((string) $request->proposed_data, true);
    $previous = json_decode((string) $request->previous_data, true);

    expect($proposed['en']['name'])->toBe('Renamed Villa')
        ->and($previous['en']['name'])->toBe('Original Villa');
});

it('never routes an edit to a still-draft object through moderation, even under review mode', function (): void {
    $fixture = cabinetObjectEditingGeography();
    $owner = cabinetObjectEditingOwner('object_owner_draft_bypasses_moderation');
    $object = cabinetObjectEditingMakeObject($fixture, $owner->id, 'draft');
    cabinetObjectEditingSetMode($object->id, 'review');

    cabinetObjectEditingMountTenant($object);

    Livewire::actingAs($owner)
        ->test(EditObject::class, ['record' => $object->getKey()])
        ->fillForm(['translations' => ['en' => ['name' => 'Draft Renamed']]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(DB::table('object_translations')->where('object_id', $object->id)->where('locale', 'en')->value('name'))
        ->toBe('Draft Renamed')
        ->and(DB::table('moderation_requests')->count())->toBe(0);
});

it('shows a rejected submission with its stated reason, and lets the owner resubmit through the same path', function (): void {
    $fixture = cabinetObjectEditingGeography();
    $owner = cabinetObjectEditingOwner('object_owner_rejected_visible');
    $object = cabinetObjectEditingMakeObject($fixture, $owner->id, 'published');

    $requestId = DB::table('moderation_requests')->insertGetId([
        'target_type' => Object_::class, 'target_id' => $object->id,
        'section' => 'description', 'previous_data' => json_encode(['en' => ['full_description' => 'Old text']]),
        'proposed_data' => json_encode(['en' => ['full_description' => 'Proposed text']]),
        'submitted_by' => $owner->id, 'submitted_at' => now(),
        'decision' => 'rejected', 'decided_at' => now(), 'decided_by' => $owner->id,
        'rejection_reason' => 'The wording overstates the amenities on offer.',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $response = $this->actingAs($owner)
        ->followingRedirects()
        ->get(ObjectResource::getUrl('edit', ['record' => $object], panel: 'cabinet', tenant: $object));

    $response->assertSuccessful()
        ->assertSee('The wording overstates the amenities on offer.');

    // Still editable and resubmittable through the very same save path —
    // proven under review mode so the resubmission genuinely re-enters the
    // queue rather than silently applying.
    cabinetObjectEditingSetMode($object->id, 'review');
    cabinetObjectEditingMountTenant($object);

    Livewire::actingAs($owner)
        ->test(EditObject::class, ['record' => $object->getKey()])
        ->fillForm(['translations' => ['en' => ['full_description' => 'Revised text']]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(DB::table('moderation_requests')->where('id', '!=', $requestId)->where('target_id', $object->id)->count())
        ->toBe(1);
});

it('shows a revision-requested submission with its comment as the stated reason', function (): void {
    $fixture = cabinetObjectEditingGeography();
    $owner = cabinetObjectEditingOwner('object_owner_revision_requested_visible');
    $object = cabinetObjectEditingMakeObject($fixture, $owner->id, 'published');

    DB::table('moderation_requests')->insert([
        'target_type' => Object_::class, 'target_id' => $object->id,
        'section' => 'location', 'previous_data' => null, 'proposed_data' => json_encode(['address' => 'New Address 5']),
        'submitted_by' => $owner->id, 'submitted_at' => now(),
        'decision' => 'revision_requested', 'decided_at' => now(), 'decided_by' => $owner->id,
        'comment' => 'Please confirm the street number against the registry.',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $response = $this->actingAs($owner)
        ->followingRedirects()
        ->get(ObjectResource::getUrl('edit', ['record' => $object], panel: 'cabinet', tenant: $object));

    $response->assertSuccessful()
        ->assertSee('Please confirm the street number against the registry.');
});

it('warns before discarding unsaved changes, inheriting the panel-wide setting rather than opting out', function (): void {
    $fixture = cabinetObjectEditingGeography();
    $owner = cabinetObjectEditingOwner('object_owner_unsaved_alert');
    $object = cabinetObjectEditingMakeObject($fixture, $owner->id, 'draft');

    cabinetObjectEditingMountTenant($object);

    $page = Livewire::actingAs($owner)
        ->test(EditObject::class, ['record' => $object->getKey()])
        ->instance();

    $method = new ReflectionMethod($page, 'hasUnsavedDataChangesAlert');
    $method->setAccessible(true);

    expect(Filament::hasUnsavedChangesAlerts())->toBeTrue()
        ->and($method->invoke($page))->toBeTrue();
});
