<?php

declare(strict_types=1);

use App\Exceptions\ModerationDecisionRefusedException;
use App\Filament\Admin\Resources\ModerationRequests\Pages\ReviewModerationRequest;
use App\Models\ModerationRequest;
use App\Models\Object_;
use App\Models\User;
use App\Services\Moderation\ModerationDecisionService;
use App\Services\Settings\SettingsRepository;
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
| Moderation Review
|--------------------------------------------------------------------------
|
| The published record is never touched while a request is pending, and
| rejection leaves it exactly as it was — the practical reason moderation
| models changes rather than records. A rejected or revision-requested edit
| cannot damage a live page it never reached.
|
*/

function moderationReviewGeography(): array
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

function moderationReviewSeedObject(array $geo, int $ownerId): Object_
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
        'object_id' => $objectId, 'locale' => 'en', 'name' => 'Original Name',
        'short_description' => 'Original description',
        'slug' => "object-{$objectId}", 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Object_ $object */
    $object = Object_::query()->withUnmoderated()->findOrFail($objectId);

    return $object;
}

/** @param  array<string, mixed>  $overrides */
function moderationReviewSeedRequest(array $geo, Object_ $object, array $overrides = []): ModerationRequest
{
    return ModerationRequest::create(array_merge([
        'target_type' => Object_::class,
        'target_id' => $object->id,
        'country_id' => $geo['countryId'],
        'owner_id' => $object->owner_id,
        'section' => 'description',
        'previous_data' => ['name' => 'Original Name', 'short_description' => 'Original description'],
        'proposed_data' => ['name' => 'New Name', 'short_description' => 'New description'],
        'submitted_by' => $object->owner_id,
        'submitted_at' => now(),
        'decision' => 'pending',
    ], $overrides));
}

function moderationReviewActor(): User
{
    foreach (['admin_panel_access', 'moderation.view', 'moderation.edit'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate('moderation_review_admin', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions(['admin_panel_access', 'moderation.view', 'moderation.edit']);

    $user = User::factory()->create();
    $user->assignRole($role);

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

it('renders published and proposed values side by side with changed fields marked', function (): void {
    $geo = moderationReviewGeography();
    $owner = User::factory()->create();
    $object = moderationReviewSeedObject($geo, $owner->id);
    $request = moderationReviewSeedRequest($geo, $object);
    moderationReviewActor();

    $diff = Livewire::test(ReviewModerationRequest::class, ['record' => $request->id])
        ->instance()
        ->diff();

    $byKey = collect($diff)->keyBy('key');

    expect($byKey['name']['previous'])->toBe('Original Name')
        ->and($byKey['name']['proposed'])->toBe('New Name')
        ->and($byKey['name']['changed'])->toBeTrue();
});

it('approving applies the proposed data, publishes it immediately, and journals the decision', function (): void {
    $geo = moderationReviewGeography();
    $owner = User::factory()->create();
    $object = moderationReviewSeedObject($geo, $owner->id);
    $request = moderationReviewSeedRequest($geo, $object);
    $actor = moderationReviewActor();

    app(ModerationDecisionService::class)->approve($request, $actor);

    expect($object->fresh()->name)->toBe('New Name')
        ->and($object->fresh()->short_description)->toBe('New description')
        ->and($request->fresh()->decision)->toBe('approved')
        ->and($request->fresh()->decided_by)->toBe($actor->id)
        ->and(DB::table('audits')->where('event', 'moderation_approved')->where('auditable_id', $request->id)->exists())->toBeTrue();
});

it('rejecting leaves the published record byte-identical and stores a reason', function (): void {
    $geo = moderationReviewGeography();
    $owner = User::factory()->create();
    $object = moderationReviewSeedObject($geo, $owner->id);
    $request = moderationReviewSeedRequest($geo, $object);
    $actor = moderationReviewActor();

    app(ModerationDecisionService::class)->reject($request, 'Photos do not match the listing.', $actor);

    expect($object->fresh()->name)->toBe('Original Name')
        ->and($object->fresh()->short_description)->toBe('Original description')
        ->and($request->fresh()->decision)->toBe('rejected')
        ->and($request->fresh()->rejection_reason)->toBe('Photos do not match the listing.');
});

it('requesting revision leaves the published record untouched and attaches the comment for the owner', function (): void {
    $geo = moderationReviewGeography();
    $owner = User::factory()->create();
    $object = moderationReviewSeedObject($geo, $owner->id);
    $request = moderationReviewSeedRequest($geo, $object);
    $actor = moderationReviewActor();

    app(ModerationDecisionService::class)->requestRevision($request, 'Please add a floor plan.', $actor);

    expect($object->fresh()->name)->toBe('Original Name')
        ->and($request->fresh()->decision)->toBe('revision_requested')
        ->and($request->fresh()->comment)->toBe('Please add a floor plan.');
});

it('partially accepts, applying only the selected fields and returning the remainder as a new pending request', function (): void {
    $geo = moderationReviewGeography();
    $owner = User::factory()->create();
    $object = moderationReviewSeedObject($geo, $owner->id);
    $request = moderationReviewSeedRequest($geo, $object);
    $actor = moderationReviewActor();

    app(SettingsRepository::class)->set('moderation.partial_acceptance_enabled', true, $actor);

    app(ModerationDecisionService::class)->partiallyAccept($request, ['name'], $actor);

    expect($object->fresh()->name)->toBe('New Name')
        ->and($object->fresh()->short_description)->toBe('Original description')
        ->and($request->fresh()->decision)->toBe('approved');

    $returned = ModerationRequest::query()->where('id', '!=', $request->id)->where('target_id', $object->id)->first();

    expect($returned)->not->toBeNull()
        ->and($returned->decision)->toBe('pending')
        ->and($returned->proposed_data)->toBe(['short_description' => 'New description']);
});

it('refuses partial acceptance when the portal setting is off', function (): void {
    $geo = moderationReviewGeography();
    $owner = User::factory()->create();
    $object = moderationReviewSeedObject($geo, $owner->id);
    $request = moderationReviewSeedRequest($geo, $object);
    $actor = moderationReviewActor();

    app(SettingsRepository::class)->set('moderation.partial_acceptance_enabled', false, $actor);

    expect(fn () => app(ModerationDecisionService::class)->partiallyAccept($request, ['name'], $actor))
        ->toThrow(ModerationDecisionRefusedException::class);

    expect($object->fresh()->name)->toBe('Original Name');
});

it('refuses to decide a request that has already been decided', function (): void {
    $geo = moderationReviewGeography();
    $owner = User::factory()->create();
    $object = moderationReviewSeedObject($geo, $owner->id);
    $request = moderationReviewSeedRequest($geo, $object, ['decision' => 'approved']);
    $actor = moderationReviewActor();

    expect(fn () => app(ModerationDecisionService::class)->approve($request, $actor))
        ->toThrow(ModerationDecisionRefusedException::class);
});
