<?php

declare(strict_types=1);

use App\Exceptions\ReviewAlreadyRepliedException;
use App\Exceptions\ReviewAlreadyReportedException;
use App\Filament\Cabinet\Resources\Reviews\Pages\ListReviews;
use App\Filament\Cabinet\Resources\Reviews\ReviewResource;
use App\Models\Object_;
use App\Models\Review;
use App\Models\User;
use App\Services\Cabinet\ReviewInteractionService;
use Filament\Actions\Exceptions\ActionNotResolvableException;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Cabinet Review Reply & Report
|--------------------------------------------------------------------------
|
| An owner sees every review posted against their own object, whatever its
| moderation status, and may act on it in exactly two ways: post the
| object's single reply, and flag it for administrator attention. Editing
| or deleting a review is never available to an owner — not the review's
| protected fields (rating, body, author info), and not even the review's
| own row wholesale — enforced by a real Policy check, not merely a hidden
| button. Review submission itself is out of scope here: fixtures are
| seeded directly against the table.
|
*/

/** @return array{countryId: int, territoryId: int, typeId: int} */
function cabinetReviewsGeography(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true, 'display_order' => 1,
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

function cabinetReviewsOwner(string $roleKey): User
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

/**
 * @param  array{countryId: int, territoryId: int, typeId: int}  $fixture
 */
function cabinetReviewsMakeObject(array $fixture, int $ownerId, string $status = 'published'): Object_
{
    $objectId = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(),
        'owner_id' => $ownerId,
        'object_type_id' => $fixture['typeId'],
        'territory_id' => $fixture['territoryId'],
        'country_id' => $fixture['countryId'],
        'status' => $status,
        'moderation_status' => $status === 'published' ? 'approved' : null,
        'latitude' => 45.1234500,
        'longitude' => 28.1234500,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => 'Reviewed Guesthouse',
        'slug' => 'reviewed-guesthouse-'.$objectId, 'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Object_ $object */
    $object = Object_::query()->withUnmoderated()->findOrFail($objectId);

    return $object;
}

/** @param  array<string, mixed>  $overrides */
function cabinetReviewsMakeReview(Object_ $object, array $overrides = []): Review
{
    $reviewId = DB::table('reviews')->insertGetId(array_merge([
        'object_id' => $object->id,
        'rating' => 5,
        'body' => 'Wonderful stay, highly recommended.',
        'author_name' => 'Jane Visitor',
        'status' => 'published',
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));

    /** @var Review $review */
    $review = Review::query()->findOrFail($reviewId);

    return $review;
}

function cabinetReviewsMountTenant(Object_ $object): void
{
    Filament::setCurrentPanel(Filament::getPanel('cabinet'));
    Filament::bootCurrentPanel();
    Filament::setTenant($object, isQuiet: true);
}

it('lists every review of the owner\'s own object regardless of its moderation status', function (): void {
    $fixture = cabinetReviewsGeography();
    $owner = cabinetReviewsOwner('reviews_owner_every_status');
    $object = cabinetReviewsMakeObject($fixture, $owner->id);

    $pending = cabinetReviewsMakeReview($object, ['status' => 'pending', 'body' => 'Pending review body.']);
    $published = cabinetReviewsMakeReview($object, ['status' => 'published', 'body' => 'Published review body.']);
    $rejected = cabinetReviewsMakeReview($object, ['status' => 'rejected', 'body' => 'Rejected review body.']);

    cabinetReviewsMountTenant($object);

    Livewire::actingAs($owner)
        ->test(ListReviews::class)
        ->assertCanSeeTableRecords([$pending, $published, $rejected]);
});

it('lets an owner post exactly one reply to a review, refusing a second attempt', function (): void {
    $fixture = cabinetReviewsGeography();
    $owner = cabinetReviewsOwner('reviews_owner_reply_once');
    $object = cabinetReviewsMakeObject($fixture, $owner->id);
    $review = cabinetReviewsMakeReview($object);

    cabinetReviewsMountTenant($object);

    Livewire::actingAs($owner)
        ->test(ListReviews::class)
        ->assertTableActionExists('reply', record: $review)
        ->callTableAction('reply', $review, ['owner_reply' => 'Thank you for staying with us!'])
        ->assertHasNoTableActionErrors();

    $review->refresh();

    expect($review->owner_reply)->toBe('Thank you for staying with us!')
        ->and($review->owner_replied_at)->not->toBeNull();

    // The action itself disappears from the row once a reply already exists —
    // the UI-level half of the "exactly once" guarantee.
    Livewire::actingAs($owner)
        ->test(ListReviews::class)
        ->assertTableActionHidden('reply', record: $review);

    // The service-level half: even a direct second call is refused, not
    // merely a hidden button that a determined caller could route around.
    expect(fn () => app(ReviewInteractionService::class)->reply($review, $owner, 'A second, unwanted reply.'))
        ->toThrow(ReviewAlreadyRepliedException::class);

    expect($review->fresh()->owner_reply)->toBe('Thank you for staying with us!');
});

it('lets an owner report a review, recording the reason against the acting owner', function (): void {
    $fixture = cabinetReviewsGeography();
    $owner = cabinetReviewsOwner('reviews_owner_report');
    $object = cabinetReviewsMakeObject($fixture, $owner->id);
    $review = cabinetReviewsMakeReview($object);

    cabinetReviewsMountTenant($object);

    Livewire::actingAs($owner)
        ->test(ListReviews::class)
        ->assertTableActionExists('report', record: $review)
        ->callTableAction('report', $review, ['report_reason' => 'Contains offensive language.'])
        ->assertHasNoTableActionErrors();

    $review->refresh();

    expect($review->reported_at)->not->toBeNull()
        ->and($review->reported_by)->toBe($owner->id)
        ->and($review->report_reason)->toBe('Contains offensive language.');

    // Hidden once already reported, for the identical reason the reply
    // action hides itself.
    Livewire::actingAs($owner)
        ->test(ListReviews::class)
        ->assertTableActionHidden('report', record: $review);

    expect(fn () => app(ReviewInteractionService::class)->report($review, $owner, 'A second report.'))
        ->toThrow(ReviewAlreadyReportedException::class);
});

it('never exposes an edit or delete control for a review, in the table itself', function (): void {
    $fixture = cabinetReviewsGeography();
    $owner = cabinetReviewsOwner('reviews_owner_no_edit_delete_ui');
    $object = cabinetReviewsMakeObject($fixture, $owner->id);
    $review = cabinetReviewsMakeReview($object);

    cabinetReviewsMountTenant($object);

    Livewire::actingAs($owner)
        ->test(ListReviews::class)
        ->assertTableActionDoesNotExist('edit', record: $review)
        ->assertTableActionDoesNotExist('delete', record: $review);
});

it("refuses the update and delete abilities outright, even for the review's own object owner acting on protected fields", function (): void {
    $fixture = cabinetReviewsGeography();
    $owner = cabinetReviewsOwner('reviews_owner_protected_fields');
    $object = cabinetReviewsMakeObject($fixture, $owner->id);
    $review = cabinetReviewsMakeReview($object, [
        'rating' => 5, 'body' => 'Original, untouched body.', 'author_name' => 'Original Author',
    ]);

    // The owner of the very object this review belongs to — the strongest
    // possible actor — still cannot reach the update or delete ability.
    // Only the dedicated reply/report abilities exist for this actor.
    expect(Gate::forUser($owner)->allows('update', $review))->toBeFalse()
        ->and(Gate::forUser($owner)->allows('delete', $review))->toBeFalse()
        ->and(Gate::forUser($owner)->allows('reply', $review))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('report', $review))->toBeTrue();

    // Falsifying proof that no protected field moved: nothing in this
    // codebase's cabinet surface has a write path for them, and the review
    // row itself proves it stayed exactly as seeded.
    expect($review->fresh()->rating)->toBe(5)
        ->and($review->fresh()->body)->toBe('Original, untouched body.')
        ->and($review->fresh()->author_name)->toBe('Original Author');
});

it("refuses every ability on another owner's review, including update, delete, reply, and report", function (): void {
    $fixture = cabinetReviewsGeography();
    $ownerA = cabinetReviewsOwner('reviews_owner_cross_a');
    $ownerB = cabinetReviewsOwner('reviews_owner_cross_b');
    $objectA = cabinetReviewsMakeObject($fixture, $ownerA->id);
    $objectB = cabinetReviewsMakeObject($fixture, $ownerB->id);
    $reviewOfB = cabinetReviewsMakeReview($objectB);

    expect(Gate::forUser($ownerA)->allows('view', $reviewOfB))->toBeFalse()
        ->and(Gate::forUser($ownerA)->allows('update', $reviewOfB))->toBeFalse()
        ->and(Gate::forUser($ownerA)->allows('delete', $reviewOfB))->toBeFalse()
        ->and(Gate::forUser($ownerA)->allows('reply', $reviewOfB))->toBeFalse()
        ->and(Gate::forUser($ownerA)->allows('report', $reviewOfB))->toBeFalse();
});

it("refuses a table action on another owner's review even while mounted on one's own tenant, treating the record as not found", function (): void {
    $fixture = cabinetReviewsGeography();
    $ownerA = cabinetReviewsOwner('reviews_owner_row_action_a');
    $ownerB = cabinetReviewsOwner('reviews_owner_row_action_b');
    $objectA = cabinetReviewsMakeObject($fixture, $ownerA->id);
    $objectB = cabinetReviewsMakeObject($fixture, $ownerB->id);
    $reviewOfB = cabinetReviewsMakeReview($objectB);

    // A real routed request first: Filament only registers its tenant-scope
    // global scope on the resource's model from inside the `SetUpPanel` HTTP
    // middleware, the first time a request actually reaches the panel.
    $this->actingAs($ownerA)->get(ReviewResource::getUrl('index', panel: 'cabinet', tenant: $objectA))->assertSuccessful();

    cabinetReviewsMountTenant($objectA);

    $attempt = fn () => Livewire::actingAs($ownerA)
        ->test(ListReviews::class)
        ->callTableAction('reply', $reviewOfB, ['owner_reply' => 'Should never be written.']);

    expect($attempt)->toThrow(ActionNotResolvableException::class);

    expect($reviewOfB->fresh()->owner_reply)->toBeNull();
});

it('resolves the owning-object relation correctly even before the object has cleared moderation', function (): void {
    $fixture = cabinetReviewsGeography();
    $owner = cabinetReviewsOwner('reviews_owner_draft_scope');
    $draft = cabinetReviewsMakeObject($fixture, $owner->id, 'draft');
    $review = cabinetReviewsMakeReview($draft);

    expect($draft->moderation_status)->toBeNull()
        ->and($review->object)->not->toBeNull()
        ->and($review->object?->is($draft))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('reply', $review))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('report', $review))->toBeTrue();
});
