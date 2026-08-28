<?php

declare(strict_types=1);

use App\Models\Object_;
use App\Models\PlacementHistory;
use App\Models\Review;
use App\Models\RoleScope;
use App\Models\StatDaily;
use App\Models\StatEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Placement History / Review / Role Scope / Stat Daily / Stat Event
|--------------------------------------------------------------------------
|
| Each of these five models already has partial coverage elsewhere:
| PlacementHistoryRelationManagerTest exercises `package()` and
| `grantedBy()` through the relation manager's own table columns;
| RoleGrantPresenterTest eager-loads `role()`; CabinetReviewsTest proves
| `object()` strips ModerationScope for a draft object. None of those touch
| what remains — PlacementHistory's own `object()` relation and
| `isOpen()` business rule; Review's `author()`, `reportedBy()`, and
| `deletedBy()` relations plus its numeric/datetime casts; RoleScope's
| `user()`, `granter()`, and `revoker()` relations; and StatDaily/
| StatEvent's polymorphic `subject()` resolution, geography relations, and
| (for StatEvent) the disabled-timestamps table shape. That is the gap this
| file closes.
|
*/

/** @return array{countryId: int, territoryId: int, objectTypeId: int} */
function pasmGeoFixture(): array
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
    $objectTypeId = DB::table('object_types')->insertGetId([
        'key' => 'accommodation', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return ['countryId' => $countryId, 'territoryId' => $territoryId, 'objectTypeId' => $objectTypeId];
}

/** A published, already-approved object — resolvable through a plain belongsTo()/morphTo() without withUnmoderated(). */
function pasmObject(int $countryId, int $territoryId, int $objectTypeId, ?int $ownerId = null): int
{
    return DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(),
        'owner_id' => $ownerId,
        'object_type_id' => $objectTypeId,
        'territory_id' => $territoryId,
        'country_id' => $countryId,
        'status' => 'published',
        'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function pasmPlacementPackage(int $rank = 1): int
{
    $tierId = DB::table('placement_tiers')->insertGetId([
        'rank' => $rank, 'border_colour' => '#000000', 'badge_colour' => '#111111',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return DB::table('placement_packages')->insertGetId([
        'placement_tier_id' => $tierId, 'price' => 10, 'currency' => 'EUR', 'validity_days' => 30,
        'bump_allowed' => true, 'is_active' => true, 'display_order' => $rank,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

// -----------------------------------------------------------------------
// PlacementHistory
// -----------------------------------------------------------------------

it('casts PlacementHistory date, decimal, and datetime fields, not merely echoing the assigned string', function (): void {
    $fixture = pasmGeoFixture();
    $objectId = pasmObject($fixture['countryId'], $fixture['territoryId'], $fixture['objectTypeId']);
    $packageId = pasmPlacementPackage();

    $history = PlacementHistory::create([
        'object_id' => $objectId, 'placement_package_id' => $packageId,
        'starts_at' => '2026-01-10', 'ends_at' => '2026-02-10',
        'amount' => '199.9', 'currency' => 'EUR', 'paid_at' => '2026-01-11 09:30:00',
        'status' => 'paid', 'granted_by' => null, 'comment' => null,
    ]);

    $reloaded = PlacementHistory::query()->findOrFail($history->id);

    expect($reloaded->starts_at)->toBeInstanceOf(Carbon::class)
        ->and($reloaded->starts_at->toDateString())->toBe('2026-01-10')
        ->and($reloaded->ends_at)->toBeInstanceOf(Carbon::class)
        ->and($reloaded->ends_at->toDateString())->toBe('2026-02-10')
        ->and((string) $reloaded->amount)->toBe('199.90')
        ->and($reloaded->paid_at)->toBeInstanceOf(Carbon::class)
        ->and($reloaded->paid_at->format('Y-m-d H:i:s'))->toBe('2026-01-11 09:30:00');
});

it('resolves PlacementHistory.object to the correct record', function (): void {
    $fixture = pasmGeoFixture();
    $objectId = pasmObject($fixture['countryId'], $fixture['territoryId'], $fixture['objectTypeId']);
    $packageId = pasmPlacementPackage();

    $history = PlacementHistory::create([
        'object_id' => $objectId, 'placement_package_id' => $packageId,
        'starts_at' => now()->toDateString(), 'ends_at' => null,
        'amount' => 50, 'currency' => 'EUR', 'status' => 'granted_free',
    ]);

    expect($history->object())->toBeInstanceOf(BelongsTo::class)
        ->and($history->object)->toBeInstanceOf(Object_::class)
        ->and($history->object->id)->toBe($objectId);
});

it('reports isOpen() true only for a grant that has no end date yet', function (): void {
    $fixture = pasmGeoFixture();
    $objectId = pasmObject($fixture['countryId'], $fixture['territoryId'], $fixture['objectTypeId']);
    $packageId = pasmPlacementPackage();

    $open = PlacementHistory::create([
        'object_id' => $objectId, 'placement_package_id' => $packageId,
        'starts_at' => now()->toDateString(), 'ends_at' => null,
        'amount' => 50, 'currency' => 'EUR', 'status' => 'paid',
    ]);
    $closed = PlacementHistory::create([
        'object_id' => $objectId, 'placement_package_id' => $packageId,
        'starts_at' => now()->subDays(30)->toDateString(), 'ends_at' => now()->subDay()->toDateString(),
        'amount' => 50, 'currency' => 'EUR', 'status' => 'paid',
    ]);

    expect($open->isOpen())->toBeTrue()
        ->and($closed->isOpen())->toBeFalse();
});

// -----------------------------------------------------------------------
// Review
// -----------------------------------------------------------------------

/** @param  array<string, mixed>  $overrides */
function pasmReview(int $objectId, array $overrides = []): Review
{
    $reviewId = DB::table('reviews')->insertGetId(array_merge([
        'object_id' => $objectId, 'rating' => 4, 'body' => 'A pleasant, unremarkable stay.',
        'author_name' => 'Guest Visitor', 'status' => 'published',
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));

    return Review::query()->findOrFail($reviewId);
}

it('casts Review.rating to an integer and its reported/replied timestamps to Carbon instances', function (): void {
    $fixture = pasmGeoFixture();
    $objectId = pasmObject($fixture['countryId'], $fixture['territoryId'], $fixture['objectTypeId']);

    $review = pasmReview($objectId, [
        'rating' => '5', 'owner_replied_at' => '2026-01-20 08:00:00', 'reported_at' => '2026-01-21 12:00:00',
    ]);

    expect($review->rating)->toBeInt()->toBe(5)
        ->and($review->owner_replied_at)->toBeInstanceOf(Carbon::class)
        ->and($review->owner_replied_at->format('Y-m-d H:i:s'))->toBe('2026-01-20 08:00:00')
        ->and($review->reported_at)->toBeInstanceOf(Carbon::class)
        ->and($review->reported_at->format('Y-m-d H:i:s'))->toBe('2026-01-21 12:00:00');
});

it('resolves Review.author for a registered visitor, leaving it null for a guest-only review', function (): void {
    $fixture = pasmGeoFixture();
    $objectId = pasmObject($fixture['countryId'], $fixture['territoryId'], $fixture['objectTypeId']);
    $visitor = User::factory()->create();

    $byVisitor = pasmReview($objectId, ['author_id' => $visitor->id, 'author_name' => null]);
    $byGuest = pasmReview($objectId, ['author_id' => null, 'author_name' => 'Anonymous Guest']);

    expect($byVisitor->author())->toBeInstanceOf(BelongsTo::class)
        ->and($byVisitor->author)->toBeInstanceOf(User::class)
        ->and($byVisitor->author->id)->toBe($visitor->id)
        ->and($byGuest->author)->toBeNull();
});

it('resolves Review.reportedBy to the staff member who flagged the review', function (): void {
    $fixture = pasmGeoFixture();
    $objectId = pasmObject($fixture['countryId'], $fixture['territoryId'], $fixture['objectTypeId']);
    $reporter = User::factory()->create();

    $review = pasmReview($objectId, ['reported_by' => $reporter->id, 'reported_at' => now(), 'report_reason' => 'Spam.']);

    expect($review->reportedBy())->toBeInstanceOf(BelongsTo::class)
        ->and($review->reportedBy)->toBeInstanceOf(User::class)
        ->and($review->reportedBy->id)->toBe($reporter->id);
});

it('resolves Review.deletedBy to the administrator who soft-deleted the review', function (): void {
    $fixture = pasmGeoFixture();
    $objectId = pasmObject($fixture['countryId'], $fixture['territoryId'], $fixture['objectTypeId']);
    $administrator = User::factory()->create();

    $review = pasmReview($objectId);
    $review->delete();
    DB::table('reviews')->where('id', $review->id)->update([
        'deleted_by' => $administrator->id, 'hidden_reason' => 'Violates community guidelines.',
    ]);

    $trashed = Review::withTrashed()->findOrFail($review->id);

    expect($trashed->trashed())->toBeTrue()
        ->and($trashed->deletedBy())->toBeInstanceOf(BelongsTo::class)
        ->and($trashed->deletedBy)->toBeInstanceOf(User::class)
        ->and($trashed->deletedBy->id)->toBe($administrator->id);
});

// -----------------------------------------------------------------------
// RoleScope
// -----------------------------------------------------------------------

it('resolves RoleScope.user, RoleScope.granter, and RoleScope.revoker to their respective users', function (): void {
    $role = Role::create(['name' => 'territory_moderator', 'guard_name' => 'web']);
    $grantee = User::factory()->create();
    $grantor = User::factory()->create();
    $revoker = User::factory()->create();

    $scope = RoleScope::create([
        'user_id' => $grantee->id, 'role_id' => $role->id,
        'scope_kind' => 'none', 'scope_reference_id' => null,
        'granted_by' => $grantor->id, 'granted_at' => now(),
        'revoked_by' => $revoker->id, 'revoked_at' => now(),
    ]);

    expect($scope->user())->toBeInstanceOf(BelongsTo::class)
        ->and($scope->user->id)->toBe($grantee->id)
        ->and($scope->granter())->toBeInstanceOf(BelongsTo::class)
        ->and($scope->granter->id)->toBe($grantor->id)
        ->and($scope->revoker())->toBeInstanceOf(BelongsTo::class)
        ->and($scope->revoker->id)->toBe($revoker->id);
});

it('leaves RoleScope.revoker null for a grant that has never been revoked', function (): void {
    $role = Role::create(['name' => 'never_revoked_role', 'guard_name' => 'web']);
    $grantee = User::factory()->create();
    $grantor = User::factory()->create();

    $scope = RoleScope::create([
        'user_id' => $grantee->id, 'role_id' => $role->id,
        'scope_kind' => 'none', 'scope_reference_id' => null,
        'granted_by' => $grantor->id, 'granted_at' => now(),
    ]);

    expect($scope->revoker)->toBeNull()
        ->and($scope->revoked_at)->toBeNull();
});

// -----------------------------------------------------------------------
// StatDaily
// -----------------------------------------------------------------------

it('resolves StatDaily.subject polymorphically depending on the stored subject_type, and its territory/country relations', function (): void {
    $fixture = pasmGeoFixture();
    $objectId = pasmObject($fixture['countryId'], $fixture['territoryId'], $fixture['objectTypeId']);
    $user = User::factory()->create();

    $objectRollupId = DB::table('stat_dailies')->insertGetId([
        'date' => '2026-01-15', 'subject_type' => Object_::class, 'subject_id' => $objectId,
        'kind' => 'object_card_view', 'territory_id' => $fixture['territoryId'], 'country_id' => $fixture['countryId'],
        'count' => 3, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $userRollupId = DB::table('stat_dailies')->insertGetId([
        'date' => '2026-01-15', 'subject_type' => User::class, 'subject_id' => $user->id,
        'kind' => 'api_request', 'endpoint' => 'api.v1.objects.index',
        'count' => 7, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $objectRollup = StatDaily::query()->findOrFail($objectRollupId);
    $userRollup = StatDaily::query()->findOrFail($userRollupId);

    expect($objectRollup->subject())->toBeInstanceOf(MorphTo::class)
        ->and($objectRollup->subject)->toBeInstanceOf(Object_::class)
        ->and($objectRollup->subject->id)->toBe($objectId)
        ->and($userRollup->subject)->toBeInstanceOf(User::class)
        ->and($userRollup->subject->id)->toBe($user->id)
        ->and($objectRollup->territory())->toBeInstanceOf(BelongsTo::class)
        ->and($objectRollup->territory->id)->toBe($fixture['territoryId'])
        ->and($objectRollup->country())->toBeInstanceOf(BelongsTo::class)
        ->and($objectRollup->country->id)->toBe($fixture['countryId'])
        ->and($userRollup->territory)->toBeNull()
        ->and($userRollup->country)->toBeNull();
});

it('casts StatDaily.date to a Carbon date and count to an integer', function (): void {
    $fixture = pasmGeoFixture();
    $objectId = pasmObject($fixture['countryId'], $fixture['territoryId'], $fixture['objectTypeId']);

    $id = DB::table('stat_dailies')->insertGetId([
        'date' => '2026-02-01', 'subject_type' => Object_::class, 'subject_id' => $objectId,
        'kind' => 'photo_view', 'count' => '42', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $rollup = StatDaily::query()->findOrFail($id);

    expect($rollup->date)->toBeInstanceOf(Carbon::class)
        ->and($rollup->date->toDateString())->toBe('2026-02-01')
        ->and($rollup->count)->toBeInt()->toBe(42);
});

// -----------------------------------------------------------------------
// StatEvent
// -----------------------------------------------------------------------

it('resolves StatEvent.subject polymorphically depending on the stored subject_type', function (): void {
    $fixture = pasmGeoFixture();
    $objectId = pasmObject($fixture['countryId'], $fixture['territoryId'], $fixture['objectTypeId']);
    $user = User::factory()->create();

    $objectEventId = DB::table('stat_events')->insertGetId([
        'kind' => 'object_page_view', 'subject_type' => Object_::class, 'subject_id' => $objectId,
        'occurred_at' => now(),
    ]);
    $userEventId = DB::table('stat_events')->insertGetId([
        'kind' => 'api_request', 'subject_type' => User::class, 'subject_id' => $user->id,
        'occurred_at' => now(), 'endpoint' => 'api.v1.objects.show',
    ]);

    $objectEvent = StatEvent::query()->findOrFail($objectEventId);
    $userEvent = StatEvent::query()->findOrFail($userEventId);

    expect($objectEvent->subject())->toBeInstanceOf(MorphTo::class)
        ->and($objectEvent->subject)->toBeInstanceOf(Object_::class)
        ->and($objectEvent->subject->id)->toBe($objectId)
        ->and($userEvent->subject)->toBeInstanceOf(User::class)
        ->and($userEvent->subject->id)->toBe($user->id);
});

it('persists StatEvent with $timestamps disabled, matching a table with no created_at/updated_at columns', function (): void {
    $fixture = pasmGeoFixture();
    $objectId = pasmObject($fixture['countryId'], $fixture['territoryId'], $fixture['objectTypeId']);

    $event = StatEvent::create([
        'kind' => 'contact_click', 'subject_type' => Object_::class, 'subject_id' => $objectId,
        'occurred_at' => '2026-03-01 10:00:00',
    ]);

    // If $timestamps were true, Eloquent would try to write created_at/
    // updated_at into a table that declares neither column, and the insert
    // above would have failed outright rather than reaching this line.
    expect($event->exists)->toBeTrue()
        ->and($event->getAttributes())->not->toHaveKey('created_at')
        ->and($event->getAttributes())->not->toHaveKey('updated_at')
        ->and($event->occurred_at)->toBeInstanceOf(Carbon::class)
        ->and($event->occurred_at->format('Y-m-d H:i:s'))->toBe('2026-03-01 10:00:00');
});
