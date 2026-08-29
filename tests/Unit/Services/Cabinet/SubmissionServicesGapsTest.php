<?php

declare(strict_types=1);

use App\Models\ModerationRequest;
use App\Models\NewsItem;
use App\Models\Object_;
use App\Models\Promotion;
use App\Models\User;
use App\Services\Cabinet\NewsItemSubmissionService;
use App\Services\Cabinet\PromotionSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Cabinet Submission Services — applyEdit() Gap
|--------------------------------------------------------------------------
|
| tests/Feature/Cabinet/CabinetModerationGatingTest.php already proves
| submit() end to end for both NewsItemSubmissionService and
| PromotionSubmissionService, through the owner-cabinet create pages. The
| owner-cabinet edit pages (EditNewsItem / EditPromotion) have no test of
| their own, which leaves applyEdit() — and the isLiveAndApproved() and
| currentSnapshot() helpers only it exercises — entirely unreached. This
| file calls both services' applyEdit() directly: once against a
| not-yet-live record, where the edit always applies directly, and once
| against an already-published, already-approved one, where the edit is
| routed back through moderation with a snapshot of the still-live
| translation as `previous_data`.
|
*/

/** @return array{countryId: int, territoryId: int, typeId: int} */
function submissionGapsGeography(): array
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

    return compact('countryId', 'territoryId', 'typeId');
}

/** @param  array{countryId: int, territoryId: int, typeId: int}  $fixture */
function submissionGapsMakeObject(array $fixture, int $ownerId): Object_
{
    $objectId = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(),
        'owner_id' => $ownerId,
        'object_type_id' => $fixture['typeId'],
        'territory_id' => $fixture['territoryId'],
        'country_id' => $fixture['countryId'],
        'status' => 'published', 'moderation_status' => 'approved',
        'availability_status' => 'available',
        'latitude' => 45.1234500, 'longitude' => 28.1234500,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Object_ $object */
    $object = Object_::query()->withUnmoderated()->findOrFail($objectId);

    return $object;
}

/**
 * Updates the object scope's mode in place — `moderation_settings` carries a
 * unique `(scope_level, scope_reference_id)` constraint, and
 * `ModerationModeResolver::resolve()` reads it with no `ORDER BY`.
 */
function submissionGapsSetMode(int $objectId, string $mode): void
{
    DB::table('moderation_settings')->updateOrInsert(
        ['scope_level' => 'object', 'scope_reference_id' => $objectId],
        ['mode' => $mode, 'set_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    );
}

it('applies a news edit directly when the item is not yet live and approved, never touching moderation', function (): void {
    $fixture = submissionGapsGeography();
    $owner = User::factory()->create();
    $object = submissionGapsMakeObject($fixture, $owner->id);

    $newsItem = NewsItem::create([
        'author_id' => $owner->id,
        'object_id' => $object->id,
        'territory_id' => $fixture['territoryId'],
        'status' => 'draft',
    ]);

    $outcome = app(NewsItemSubmissionService::class)->applyEdit($newsItem, $object, [
        'title' => 'Draft Title',
        'summary' => 'Draft summary.',
        'body' => 'Draft body.',
        'publish_at' => null,
    ], $owner);

    expect($outcome->applied)->toBeTrue()
        ->and($outcome->request)->toBeNull();

    $persisted = $newsItem->fresh();
    expect($persisted->status)->toBe('draft')
        ->and($persisted->translate('en', false)->title)->toBe('Draft Title')
        ->and($persisted->translate('en', false)->body)->toBe('Draft body.')
        ->and(ModerationRequest::where('target_id', $newsItem->id)->where('target_type', NewsItem::class)->count())
        ->toBe(0);
});

it('routes a news edit to moderation and snapshots the live translation as previous_data when the item is already published and approved', function (): void {
    $fixture = submissionGapsGeography();
    $owner = User::factory()->create();
    $object = submissionGapsMakeObject($fixture, $owner->id);

    submissionGapsSetMode($object->id, 'immediate');

    $service = app(NewsItemSubmissionService::class);

    $created = $service->submit($object, [
        'title' => 'Original News Title',
        'summary' => 'Original summary.',
        'body' => 'Original body.',
        'publish_at' => '2026-01-15T10:00:00+00:00',
    ], $owner);

    expect($created->applied)->toBeTrue();

    $newsItem = $created->record->fresh();
    expect($newsItem->status)->toBe('published')
        ->and($newsItem->moderation_status)->toBe('approved');

    // Same scope, switched live — the create above proved nothing about
    // this edit unless the scope genuinely gates it too.
    submissionGapsSetMode($object->id, 'review');

    $outcome = $service->applyEdit($newsItem, $object, [
        'title' => 'Updated News Title',
        'summary' => 'Updated summary.',
        'body' => 'Updated body.',
        'publish_at' => '2026-02-20T12:00:00+00:00',
    ], $owner);

    expect($outcome->applied)->toBeFalse()
        ->and($outcome->request)->not->toBeNull();

    // The live record is untouched — the edit lives only inside the pending
    // request's own proposed_data until a moderator approves it.
    $stillLive = $newsItem->fresh();
    expect($stillLive->translate('en', false)->title)->toBe('Original News Title');

    $request = $outcome->request;
    expect($request->section)->toBe('news')
        ->and($request->previous_data)->toBe([
            'publish_at' => '2026-01-15T10:00:00+00:00',
            'en' => [
                'title' => 'Original News Title',
                'summary' => 'Original summary.',
                'body' => 'Original body.',
            ],
        ])
        ->and($request->proposed_data)->toBe([
            'publish_at' => '2026-02-20T12:00:00+00:00',
            'en' => [
                'title' => 'Updated News Title',
                'summary' => 'Updated summary.',
                'body' => 'Updated body.',
                'slug' => Str::slug('Updated News Title').'-'.$newsItem->id,
            ],
        ]);
});

it('applies a promotion edit directly when it is not yet live and approved, never touching moderation', function (): void {
    $fixture = submissionGapsGeography();
    $owner = User::factory()->create();
    $object = submissionGapsMakeObject($fixture, $owner->id);

    $promotion = Promotion::create([
        'object_id' => $object->id,
        'territory_id' => $fixture['territoryId'],
        'starts_at' => '2026-03-01',
        'ends_at' => '2026-03-31',
        'status' => 'draft',
    ]);

    $outcome = app(PromotionSubmissionService::class)->applyEdit($promotion, $object, [
        'title' => 'Draft Offer',
        'description' => 'Draft description.',
        'starts_at' => '2026-04-01',
        'ends_at' => '2026-04-30',
    ], $owner);

    expect($outcome->applied)->toBeTrue()
        ->and($outcome->request)->toBeNull();

    $persisted = $promotion->fresh();
    expect($persisted->starts_at->toDateString())->toBe('2026-04-01')
        ->and($persisted->ends_at->toDateString())->toBe('2026-04-30')
        ->and($persisted->translate('en', false)->title)->toBe('Draft Offer')
        ->and(ModerationRequest::where('target_id', $promotion->id)->where('target_type', Promotion::class)->count())
        ->toBe(0);
});

it('routes a promotion edit to moderation and snapshots the live translation as previous_data when it is already published and approved', function (): void {
    $fixture = submissionGapsGeography();
    $owner = User::factory()->create();
    $object = submissionGapsMakeObject($fixture, $owner->id);

    submissionGapsSetMode($object->id, 'immediate');

    $service = app(PromotionSubmissionService::class);

    $created = $service->submit($object, [
        'title' => 'Original Offer',
        'description' => 'Original description.',
        'starts_at' => '2026-05-01',
        'ends_at' => '2026-05-31',
    ], $owner);

    expect($created->applied)->toBeTrue();

    $promotion = $created->record->fresh();
    expect($promotion->status)->toBe('published')
        ->and($promotion->moderation_status)->toBe('approved');

    submissionGapsSetMode($object->id, 'review');

    $outcome = $service->applyEdit($promotion, $object, [
        'title' => 'Updated Offer',
        'description' => 'Updated description.',
        'starts_at' => '2026-06-01',
        'ends_at' => '2026-06-30',
    ], $owner);

    expect($outcome->applied)->toBeFalse()
        ->and($outcome->request)->not->toBeNull();

    $stillLive = $promotion->fresh();
    expect($stillLive->translate('en', false)->title)->toBe('Original Offer')
        ->and($stillLive->starts_at->toDateString())->toBe('2026-05-01');

    $request = $outcome->request;
    expect($request->section)->toBe('promotions')
        ->and($request->previous_data)->toBe([
            'starts_at' => '2026-05-01',
            'ends_at' => '2026-05-31',
            'en' => [
                'title' => 'Original Offer',
                'summary' => 'Original description.',
            ],
        ])
        ->and($request->proposed_data)->toBe([
            'starts_at' => '2026-06-01',
            'ends_at' => '2026-06-30',
            'en' => [
                'title' => 'Updated Offer',
                'summary' => 'Updated description.',
                'slug' => Str::slug('Updated Offer').'-'.$promotion->id,
            ],
        ]);
});
