<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\ModerationRequests\Pages\ReviewModerationRequest;
use App\Models\ModerationRequest;
use App\Models\Object_;
use App\Models\User;
use App\Services\Moderation\ModerationDecisionService;
use App\Services\Moderation\ModerationPipeline;
use App\Services\Objects\AvailabilityAdministrationService;
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
| Moderation Invariants
|--------------------------------------------------------------------------
|
| Four claims the whole moderation design rests on, each one a plausible
| implementation can violate without any visible symptom until the exact
| case that breaks it is checked directly — a pending request never
| mutates the published record, approval publishes in one step, the
| availability toggle bypasses moderation even at its strictest setting,
| and the review screen shows what was submitted, not what the record
| has since become.
|
*/

function invariantFixture(): array
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
    $owner = User::factory()->create();

    $objectId = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(), 'owner_id' => $owner->id,
        'object_type_id' => $typeId, 'territory_id' => $territoryId, 'country_id' => $countryId,
        'status' => 'published', 'moderation_status' => 'approved', 'availability_status' => 'available',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => 'Original Villa',
        'slug' => 'original-villa', 'created_at' => now(), 'updated_at' => now(),
    ]);

    return [
        'object' => Object_::query()->withUnmoderated()->findOrFail($objectId),
        'objectId' => $objectId,
        'countryId' => $countryId,
        'owner' => $owner,
    ];
}

function invariantActor(array $permissions): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate('invariant_moderator', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    DB::table('role_scopes')->insert([
        'user_id' => $user->id, 'role_id' => $role->id,
        'scope_kind' => 'none', 'scope_reference_id' => null,
        'granted_by' => $user->id, 'granted_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    Filament::setCurrentPanel('admin');
    test()->actingAs($user->fresh());

    return $user->fresh();
}

/** @return array{objects: array<string, mixed>, translation: array<string, mixed>} */
function invariantSnapshot(int $objectId): array
{
    return [
        'objects' => (array) DB::table('objects')->where('id', $objectId)->first(),
        'translation' => (array) DB::table('object_translations')
            ->where('object_id', $objectId)->where('locale', 'en')->first(),
    ];
}

it('never mutates the published record while a request against it is pending, and rejection leaves it byte-identical', function (): void {
    $fixture = invariantFixture();
    $actor = invariantActor(['admin_panel_access', 'moderation.view', 'moderation.edit']);

    $before = invariantSnapshot($fixture['objectId']);

    $outcome = app(ModerationPipeline::class)->submit(
        target: $fixture['object'],
        section: 'name',
        previousData: ['name' => 'Original Villa'],
        proposedData: ['name' => 'New Name'],
        submittedBy: $fixture['owner'],
        objectId: $fixture['objectId'],
        ownerId: $fixture['owner']->id,
        countryId: $fixture['countryId'],
    );

    expect($outcome->shouldPublishImmediately)->toBeFalse();
    expect(invariantSnapshot($fixture['objectId']))->toEqual($before);

    /** @var ModerationRequest $request */
    $request = $outcome->request;
    app(ModerationDecisionService::class)->reject($request, 'Not needed right now.', $actor);

    expect(invariantSnapshot($fixture['objectId']))->toEqual($before);
});

it('publishes on approval with no second manual step', function (): void {
    $fixture = invariantFixture();
    $actor = invariantActor(['admin_panel_access', 'moderation.view', 'moderation.edit']);

    $outcome = app(ModerationPipeline::class)->submit(
        target: $fixture['object'],
        section: 'name',
        previousData: ['name' => 'Original Villa'],
        proposedData: ['name' => 'New Name'],
        submittedBy: $fixture['owner'],
        objectId: $fixture['objectId'],
        ownerId: $fixture['owner']->id,
        countryId: $fixture['countryId'],
    );

    /** @var ModerationRequest $request */
    $request = $outcome->request;

    // One call. Nothing else touches the record afterward.
    app(ModerationDecisionService::class)->approve($request, $actor);

    expect(DB::table('object_translations')->where('object_id', $fixture['objectId'])->where('locale', 'en')->value('name'))
        ->toBe('New Name');
});

it('bypasses moderation for the availability toggle even under the strictest, object-level mode', function (): void {
    $fixture = invariantFixture();
    $actor = invariantActor(['admin_panel_access', 'object.view', 'object.edit']);

    // The most specific rung on the mode ladder, set to the stricter of the
    // two values — nothing above "always review, and specifically for this
    // object" could plausibly demand more scrutiny than this.
    DB::table('moderation_settings')->insert([
        'scope_level' => 'object', 'scope_reference_id' => $fixture['objectId'], 'mode' => 'review',
        'set_by' => $actor->id, 'set_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    app(AvailabilityAdministrationService::class)->override($fixture['object'], 'unavailable', $actor);

    expect(DB::table('objects')->where('id', $fixture['objectId'])->value('availability_status'))->toBe('unavailable')
        ->and(DB::table('moderation_requests')->count())->toBe(0);
});

it('shows the moderator the snapshot submitted against, unchanged by an administrator editing the record afterward', function (): void {
    $fixture = invariantFixture();
    $actor = invariantActor(['admin_panel_access', 'moderation.view', 'moderation.edit']);

    $outcome = app(ModerationPipeline::class)->submit(
        target: $fixture['object'],
        section: 'name',
        previousData: ['name' => 'Original Villa'],
        proposedData: ['name' => 'New Name'],
        submittedBy: $fixture['owner'],
        objectId: $fixture['objectId'],
        ownerId: $fixture['owner']->id,
        countryId: $fixture['countryId'],
    );

    /** @var ModerationRequest $request */
    $request = $outcome->request;

    // An administrator's own edit publishes immediately, independent of the
    // pending request — the live record moves on while the request's own
    // stored columns must not.
    DB::table('object_translations')
        ->where('object_id', $fixture['objectId'])->where('locale', 'en')
        ->update(['name' => 'Administrator Edited Meanwhile']);

    $fresh = $request->fresh();

    expect($fresh->previous_data)->toBe(['name' => 'Original Villa'])
        ->and($fresh->proposed_data)->toBe(['name' => 'New Name']);

    $diff = Livewire::test(ReviewModerationRequest::class, ['record' => $fresh->id])
        ->instance()
        ->diff();

    $byKey = collect($diff)->keyBy('key');

    expect($byKey['name']['previous'])->toBe('Original Villa')
        ->and($byKey['name']['proposed'])->toBe('New Name');
});
