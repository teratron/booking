<?php

declare(strict_types=1);

use App\Models\Object_;
use App\Models\User;
use App\Services\Moderation\ModerationModeResolver;
use App\Services\Moderation\ModerationPipeline;
use App\Services\Moderation\ModerationScopeContext;
use App\Services\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Moderation Mode Resolution & the Change-Request Pipeline
|--------------------------------------------------------------------------
|
| Moderation mode resolution is a high-cascade-risk piece: the queue, the
| review screen, and the object form's return-for-revision action all
| consume its output, and its snapshot semantics cannot be retrofitted once
| requests exist in the table. Every rung of the ladder is exercised by its
| own case rather than trusted from the shape of the code.
|
*/

function moderationFixtureObject(): array
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
        'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
    ]);

    return [
        'object' => Object_::query()->withUnmoderated()->findOrFail($objectId),
        'owner' => $owner,
        'country' => $countryId,
        'type' => $typeId,
        'objectId' => $objectId,
    ];
}

function setModerationMode(string $level, int $reference, string $mode): void
{
    DB::table('moderation_settings')->insert([
        'scope_level' => $level, 'scope_reference_id' => $reference, 'mode' => $mode,
        'set_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('falls back to the portal default when no rung is configured', function (): void {
    app(SettingsRepository::class)->set('moderation.default_mode', 'immediate');

    $mode = app(ModerationModeResolver::class)->resolve(new ModerationScopeContext(
        objectId: 999, ownerId: 999, categoryId: 999, countryId: 999,
    ));

    expect($mode)->toBe('immediate');
});

it('resolves each rung independently, most specific winning', function (): void {
    app(SettingsRepository::class)->set('moderation.default_mode', 'review');

    setModerationMode('country', 1, 'immediate');
    expect(app(ModerationModeResolver::class)->resolve(new ModerationScopeContext(countryId: 1)))
        ->toBe('immediate');

    setModerationMode('category', 2, 'immediate');
    expect(app(ModerationModeResolver::class)->resolve(new ModerationScopeContext(categoryId: 2, countryId: 1)))
        ->toBe('immediate');

    // Category 2 has no override yet at this point in the sequence — proves
    // country is only consulted when nothing more specific answers.
    setModerationMode('owner', 3, 'review');
    expect(app(ModerationModeResolver::class)->resolve(new ModerationScopeContext(ownerId: 3, categoryId: 2, countryId: 1)))
        ->toBe('review');

    setModerationMode('object', 4, 'immediate');
    expect(app(ModerationModeResolver::class)->resolve(new ModerationScopeContext(objectId: 4, ownerId: 3, categoryId: 2, countryId: 1)))
        ->toBe('immediate');
});

it('publishes immediately when the change type is not on the moderated list, regardless of mode', function (): void {
    $fixture = moderationFixtureObject();
    setModerationMode('object', $fixture['objectId'], 'review');

    // 'availability' is deliberately absent from the default moderated-types
    // list — the same fact that makes the owner-facing toggle exempt
    // elsewhere makes it exempt here, with no special case in the pipeline.
    $outcome = app(ModerationPipeline::class)->submit(
        target: $fixture['object'],
        section: 'availability',
        previousData: ['availability_status' => 'available'],
        proposedData: ['availability_status' => 'unavailable'],
        submittedBy: $fixture['owner'],
        objectId: $fixture['objectId'],
    );

    expect($outcome->shouldPublishImmediately)->toBeTrue()
        ->and($outcome->request)->toBeNull()
        ->and(DB::table('moderation_requests')->count())->toBe(0);
});

it('publishes immediately when the resolved mode is immediate', function (): void {
    $fixture = moderationFixtureObject();
    setModerationMode('object', $fixture['objectId'], 'immediate');

    $outcome = app(ModerationPipeline::class)->submit(
        target: $fixture['object'],
        section: 'name',
        previousData: ['name' => 'Old Name'],
        proposedData: ['name' => 'New Name'],
        submittedBy: $fixture['owner'],
        objectId: $fixture['objectId'],
    );

    expect($outcome->shouldPublishImmediately)->toBeTrue()
        ->and($outcome->request)->toBeNull()
        ->and(DB::table('moderation_requests')->count())->toBe(0);
});

it('enqueues a request storing previous and proposed data as independent snapshots', function (): void {
    $fixture = moderationFixtureObject();
    setModerationMode('object', $fixture['objectId'], 'review');

    $outcome = app(ModerationPipeline::class)->submit(
        target: $fixture['object'],
        section: 'prices',
        previousData: ['nightly_rate' => 100],
        proposedData: ['nightly_rate' => 150],
        submittedBy: $fixture['owner'],
        objectId: $fixture['objectId'],
    );

    expect($outcome->shouldPublishImmediately)->toBeFalse()
        ->and($outcome->request)->not->toBeNull();

    $request = $outcome->request;
    expect($request->target_type)->toBe(Object_::class)
        ->and($request->target_id)->toBe($fixture['objectId'])
        ->and($request->section)->toBe('prices')
        ->and($request->previous_data)->toBe(['nightly_rate' => 100])
        ->and($request->proposed_data)->toBe(['nightly_rate' => 150])
        ->and($request->decision)->toBe('pending')
        ->and($request->submitted_by)->toBe($fixture['owner']->id);

    // The published object itself is untouched — the snapshot lives on the
    // request, never applied to the live row.
    expect(Object_::query()->withUnmoderated()->find($fixture['objectId'])->name)->toBeNull();
});

it('keeps the previous snapshot independent of the live record if it changes after submission', function (): void {
    $fixture = moderationFixtureObject();
    setModerationMode('object', $fixture['objectId'], 'review');

    $outcome = app(ModerationPipeline::class)->submit(
        target: $fixture['object'],
        section: 'description',
        previousData: ['description' => 'Original text'],
        proposedData: ['description' => 'Proposed text'],
        submittedBy: $fixture['owner'],
        objectId: $fixture['objectId'],
    );

    DB::table('objects')->where('id', $fixture['objectId'])->update(['status' => 'archived']);

    // A reference-based diff would now show the moderator whatever the live
    // record has become; the snapshot must still show what was actually
    // submitted against.
    expect($outcome->request->fresh()->previous_data)->toBe(['description' => 'Original text']);
});

it('reads the moderated-change-type list at call time rather than trusting a fixed set', function (): void {
    $fixture = moderationFixtureObject();
    app(SettingsRepository::class)->set('moderation.default_mode', 'immediate');

    $before = app(ModerationPipeline::class)->submit(
        target: $fixture['object'],
        section: 'custom_experimental_field',
        previousData: null,
        proposedData: ['value' => 'x'],
        submittedBy: $fixture['owner'],
        objectId: $fixture['objectId'],
    );

    expect($before->shouldPublishImmediately)->toBeTrue();

    app(SettingsRepository::class)->set(
        'moderation.moderated_change_types',
        [...app(SettingsRepository::class)->get('moderation.moderated_change_types'), 'custom_experimental_field'],
    );
    setModerationMode('object', $fixture['objectId'], 'review');

    $after = app(ModerationPipeline::class)->submit(
        target: $fixture['object'],
        section: 'custom_experimental_field',
        previousData: null,
        proposedData: ['value' => 'y'],
        submittedBy: $fixture['owner'],
        objectId: $fixture['objectId'],
    );

    expect($after->shouldPublishImmediately)->toBeFalse();
});
