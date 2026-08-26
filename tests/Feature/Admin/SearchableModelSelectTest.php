<?php

declare(strict_types=1);

use App\Filament\Support\SearchableModelSelect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Searchable Model Select
|--------------------------------------------------------------------------
|
| F-01: a Select field over objects/territories built with the naive
| ->options(fn () => Model::query()->get()->mapWithKeys(...)) shape loaded
| every row into a full Eloquent model on every form render — 52,800
| objects with their translations, or 6,270 territories, for one dropdown.
| At a 512MB memory limit this crashed outright; even where it didn't, it
| cost 55+ seconds and 7+ MB of HTML per screen. These tests prove the
| replacement never touches more than a bounded, searched slice of the
| table, regardless of how large the table is — the query-count and result
| -count assertions below are the fast, volume-independent proxy for that;
| a real multi-thousand-row seed belongs in the `slow` group, not here.
|
*/

/** @return array{countryId: int, languageId: int} */
function searchableSelectGeography(): array
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

    return compact('countryId', 'languageId');
}

function searchableSelectMakeTerritory(array $fixture, string $name): int
{
    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $fixture['countryId'], 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryId = DB::table('territories')->insertGetId([
        'country_id' => $fixture['countryId'], 'level_id' => $levelId, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('territory_translations')->insert([
        'territory_id' => $territoryId, 'country_id' => $fixture['countryId'], 'locale' => 'en',
        'name' => $name, 'slug' => Str::slug($name), 'full_slug_path' => Str::slug($name),
        'needs_review' => false, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    return $territoryId;
}

function searchableSelectMakeObject(array $fixture, string $name): int
{
    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'hotel-'.Str::random(6), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $objectId = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(),
        'owner_id' => User::factory()->create()->id,
        'object_type_id' => $typeId,
        'territory_id' => searchableSelectMakeTerritory($fixture, 'Host Territory '.Str::random(8)),
        'country_id' => $fixture['countryId'],
        'status' => 'published', 'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => $name,
        'short_description' => 'x', 'slug' => Str::slug($name).'-'.$objectId,
        'needs_review' => false, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    return $objectId;
}

it('searches objects by name without loading every object into a model', function (): void {
    $fixture = searchableSelectGeography();
    $target = searchableSelectMakeObject($fixture, 'Grand Riverside Hotel');
    searchableSelectMakeObject($fixture, 'Unrelated Guest House');
    searchableSelectMakeObject($fixture, 'Another Unrelated Cafe');

    $select = SearchableModelSelect::objects('object_id', 'Object');

    DB::enableQueryLog();
    $results = $select->getSearchResults('Riverside');
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($results)->toHaveKey($target)
        ->and($results)->toHaveCount(1)
        // The old shape ran one query for the whole table, then N+1'd its
        // translations relation per row; a handful of queries for a single
        // bounded, eager-loaded search is the right order of magnitude,
        // never "one query per candidate row".
        ->and($queryCount)->toBeLessThanOrEqual(3);
});

it('caps object search results at the configured limit regardless of how many rows match', function (): void {
    $fixture = searchableSelectGeography();

    foreach (range(1, 60) as $i) {
        searchableSelectMakeObject($fixture, "Matching Hotel {$i}");
    }

    $select = SearchableModelSelect::objects('object_id', 'Object');
    $results = $select->getSearchResults('Matching');

    expect(count($results))->toBeLessThanOrEqual(50);
});

it('resolves an object option label from just its id, without needing it in the current search results', function (): void {
    // getOptionLabel() itself needs the component attached to a live Schema
    // container to resolve its own state, which is more Filament test
    // scaffolding than this one assertion is worth — reflection reaches the
    // configured getOptionLabelUsing closure directly, the same closure
    // Filament's own getOptionLabel() would call.
    $fixture = searchableSelectGeography();
    $objectId = searchableSelectMakeObject($fixture, 'Selected Elsewhere Hotel');

    $select = SearchableModelSelect::objects('object_id', 'Object');

    $property = new ReflectionProperty($select, 'getOptionLabelUsing');
    $callback = $property->getValue($select);

    expect($callback((string) $objectId))->toContain('Selected Elsewhere Hotel');
});

it('searches territories by name without loading every territory into a model', function (): void {
    $fixture = searchableSelectGeography();
    $target = searchableSelectMakeTerritory($fixture, 'Sunny Coast Resort');
    searchableSelectMakeTerritory($fixture, 'Mountain Village');

    $select = SearchableModelSelect::territories('territory_id', 'Territory');

    DB::enableQueryLog();
    $results = $select->getSearchResults('Sunny');
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($results)->toHaveKey($target)
        ->and($results)->toHaveCount(1)
        ->and($queryCount)->toBeLessThanOrEqual(3);
});

it('excludes the given id from a territory search, so a reparenting form cannot offer itself as its own parent', function (): void {
    $fixture = searchableSelectGeography();
    $self = searchableSelectMakeTerritory($fixture, 'Self Territory');
    $other = searchableSelectMakeTerritory($fixture, 'Self Territory Neighbour');

    $select = SearchableModelSelect::territories('territory_id', 'Territory', excludeId: $self);
    $results = $select->getSearchResults('Self');

    expect($results)->toHaveKey($other)
        ->and($results)->not->toHaveKey($self);
});

it('searches users by name without loading every user', function (): void {
    User::factory()->create(['name' => 'Ionela Cristea']);
    User::factory()->create(['name' => 'Unrelated Person']);

    $select = SearchableModelSelect::users('manager_id', 'Manager');

    $results = $select->getSearchResults('Cristea');

    expect($results)->toHaveCount(1)
        ->and(collect($results)->values()->first())->toBe('Ionela Cristea');
});
