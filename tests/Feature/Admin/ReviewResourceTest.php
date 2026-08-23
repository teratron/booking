<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\Reviews\ReviewResource;
use App\Models\Object_;
use App\Models\Review;
use App\Models\User;
use App\Services\Reviews\ReviewModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Admin Review Resource
|--------------------------------------------------------------------------
|
| The moderation screen a submitted review reaches: list, filter, and the
| three decisions (publish, reject, hide) ReviewModerationService makes —
| distinct from the generic ModerationRequest queue, since a review is a
| pure create with no live record to protect from a bad diff.
|
*/

function reviewAdminActor(array $permissions, string $roleKey, string $scopeKind = 'none', ?int $scopeReferenceId = null): User
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
        'scope_kind' => $scopeKind, 'scope_reference_id' => $scopeReferenceId,
        'granted_by' => $user->id, 'granted_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $user->fresh();
}

/** @return array{languageId: int, countryId: int, territoryId: int, typeId: int} */
function reviewAdminRegistry(?string $countryCode = null): array
{
    $languageId = DB::table('languages')->where('code', 'en')->value('id')
        ?? DB::table('languages')->insertGetId([
            'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    $countryId = DB::table('countries')->insertGetId([
        'code' => $countryCode ?? 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true, 'display_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('territory_translations')->insert([
        'territory_id' => $territoryId, 'country_id' => $countryId, 'locale' => 'en', 'name' => 'City '.$territoryId, 'slug' => 'city-'.$territoryId,
        'full_slug_path' => 'city-'.$territoryId,
        'needs_review' => false, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'hotel_'.$territoryId, 'is_active' => true, 'has_availability_status' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_type_translations')->insert([
        'object_type_id' => $typeId, 'locale' => 'en', 'name' => 'Hotel', 'slug' => 'hotel-'.$typeId,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('languageId', 'countryId', 'territoryId', 'typeId');
}

/** @param  array<string, mixed>  $fixture */
function reviewAdminMakeObject(array $fixture, string $name): Object_
{
    $objectId = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(),
        'owner_id' => User::factory()->create()->id,
        'object_type_id' => $fixture['typeId'],
        'territory_id' => $fixture['territoryId'],
        'country_id' => $fixture['countryId'],
        'status' => 'published', 'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => $name,
        'slug' => Str::slug($name).'-'.$objectId, 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Object_ $object */
    $object = Object_::query()->findOrFail($objectId);

    return $object;
}

function reviewAdminMakeReview(Object_ $object, string $status = 'pending'): Review
{
    return Review::query()->create([
        'object_id' => $object->id,
        'country_id' => $object->country_id,
        'territory_id' => $object->territory_id,
        'object_type_id' => $object->object_type_id,
        'rating' => 4,
        'body' => 'A solid stay overall.',
        'author_name' => 'Test Guest',
        'status' => $status,
    ]);
}

it('renders the review list for an actor holding object.view, and refuses one without it', function (): void {
    $permitted = reviewAdminActor(['admin_panel_access', 'object.view'], 'review_admin_permitted');
    $refused = reviewAdminActor(['admin_panel_access'], 'review_admin_refused');

    $this->actingAs($permitted)->get(ReviewResource::getUrl('index', panel: 'admin'))->assertSuccessful();
    $this->actingAs($refused)->get(ReviewResource::getUrl('index', panel: 'admin'))->assertForbidden();
});

it('publishes a pending review and journals the decision', function (): void {
    $fixture = reviewAdminRegistry();
    $object = reviewAdminMakeObject($fixture, 'Publish Target Hotel');
    $review = reviewAdminMakeReview($object);
    $actor = reviewAdminActor(['admin_panel_access', 'object.view', 'moderation.edit'], 'review_publisher');

    app(ReviewModerationService::class)->publish($review, $actor);

    expect($review->fresh()->status)->toBe('published');
    expect(DB::table('audits')->where('event', 'review_published')->exists())->toBeTrue();
});

it('rejects a pending review with a reason and journals the decision', function (): void {
    $fixture = reviewAdminRegistry();
    $object = reviewAdminMakeObject($fixture, 'Reject Target Hotel');
    $review = reviewAdminMakeReview($object);
    $actor = reviewAdminActor(['admin_panel_access', 'object.view', 'moderation.edit'], 'review_rejecter');

    app(ReviewModerationService::class)->reject($review, 'Spam content.', $actor);

    $fresh = $review->fresh();
    expect($fresh->status)->toBe('rejected')
        ->and($fresh->rejection_reason)->toBe('Spam content.');
    expect(DB::table('audits')->where('event', 'review_rejected')->exists())->toBeTrue();
});

it('hides a published review with a reason, soft-deleting it', function (): void {
    $fixture = reviewAdminRegistry();
    $object = reviewAdminMakeObject($fixture, 'Hide Target Hotel');
    $review = reviewAdminMakeReview($object, 'published');
    $actor = reviewAdminActor(['admin_panel_access', 'object.view', 'moderation.edit'], 'review_hider');

    app(ReviewModerationService::class)->hide($review, 'Upheld report: offensive language.', $actor);

    expect(Review::withTrashed()->find($review->id)?->trashed())->toBeTrue();
    $fresh = Review::withTrashed()->find($review->id);
    expect($fresh->hidden_reason)->toBe('Upheld report: offensive language.')
        ->and($fresh->deleted_by)->toBe($actor->id);
    expect(DB::table('audits')->where('event', 'review_hidden')->exists())->toBeTrue();
});

it("refuses a moderation decision from an actor scoped to a different country than the review's object", function (): void {
    $fixture = reviewAdminRegistry('GE');
    $otherFixture = reviewAdminRegistry('UA');
    $object = reviewAdminMakeObject($fixture, 'Foreign Hotel');
    $review = reviewAdminMakeReview($object);

    $wrongCountryActor = reviewAdminActor(
        ['admin_panel_access', 'object.view', 'moderation.edit'],
        'review_wrong_country',
        scopeKind: 'country',
        scopeReferenceId: $otherFixture['countryId'],
    );

    expect($wrongCountryActor->can('publish', $review))->toBeFalse();

    $rightCountryActor = reviewAdminActor(
        ['admin_panel_access', 'object.view', 'moderation.edit'],
        'review_right_country',
        scopeKind: 'country',
        scopeReferenceId: $fixture['countryId'],
    );

    expect($rightCountryActor->can('publish', $review))->toBeTrue();
});

it("scopes the admin review list to the actor's own country", function (): void {
    $ownFixture = reviewAdminRegistry('MD');
    $otherFixture = reviewAdminRegistry('RO');
    $ownObject = reviewAdminMakeObject($ownFixture, 'Own Country Hotel Review Target');
    $otherObject = reviewAdminMakeObject($otherFixture, 'Other Country Hotel Review Target');
    reviewAdminMakeReview($ownObject);
    reviewAdminMakeReview($otherObject);

    $actor = reviewAdminActor(
        ['admin_panel_access', 'object.view'],
        'review_scoped_lister',
        scopeKind: 'country',
        scopeReferenceId: $ownFixture['countryId'],
    );

    $this->actingAs($actor)->get(ReviewResource::getUrl('index', panel: 'admin'))
        ->assertSuccessful()
        ->assertSee('Own Country Hotel Review Target')
        ->assertDontSee('Other Country Hotel Review Target');
});
