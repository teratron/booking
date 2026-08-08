<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\Objects\Tables\ObjectsTable;
use App\Models\Amenity;
use App\Models\Object_;
use App\Models\ObjectType;
use App\Models\Territory;
use App\Models\User;
use App\Services\Localization\TranslatableEntityRegistry;
use App\Services\Localization\TranslationCompletenessReport;
use App\Services\Localization\TranslationCopyService;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionMethod;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Translation Management
|--------------------------------------------------------------------------
|
| Every translatable model is discovered by the contract it implements, not
| by a maintained list — the report, the object-list filter, and the copy
| and publish actions all read from that same discovery. A copy-from-primary
| row is deliberately marked needing review: the completeness metric this
| feeds must never count copied-but-unreviewed text as translated.
|
*/

function translationGeography(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('languages')->insert([
        'code' => 'ru', 'short_label' => 'RU', 'is_active' => true, 'is_primary' => false,
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

/** @param  array<string, mixed>  $overrides */
function translationSeedObject(array $geo, array $overrides = []): Object_
{
    $objectId = DB::table('objects')->insertGetId(array_merge([
        'ulid' => (string) Str::ulid(),
        'owner_id' => User::factory()->create()->id,
        'object_type_id' => $geo['typeId'],
        'territory_id' => $geo['territoryId'],
        'country_id' => $geo['countryId'],
        'status' => 'published',
        'availability_status' => 'available',
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));

    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => "Object {$objectId}",
        'slug' => "object-{$objectId}", 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Object_ $object */
    $object = Object_::query()->withUnmoderated()->findOrFail($objectId);

    return $object;
}

it('discovers every translatable model by the contract it implements, not a hardcoded list', function (): void {
    $classes = collect(app(TranslatableEntityRegistry::class)->entries())->pluck('modelClass')->all();

    expect($classes)->toContain(Object_::class)
        ->and($classes)->toContain(Territory::class)
        ->and($classes)->toContain(ObjectType::class)
        ->and($classes)->toContain(Amenity::class)
        ->and(count($classes))->toBeGreaterThanOrEqual(9);
});

it('reports completeness per entity and per language as a number', function (): void {
    $geo = translationGeography();
    translationSeedObject($geo); // en only — missing ru
    $withRu = translationSeedObject($geo);
    DB::table('object_translations')->insert([
        'object_id' => $withRu->id, 'locale' => 'ru', 'name' => 'Объект',
        'slug' => "object-{$withRu->id}-ru", 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $summary = collect(app(TranslationCompletenessReport::class)->summary(['en', 'ru']));

    $enRow = $summary->first(fn (array $row): bool => $row['entity']->modelClass === Object_::class && $row['locale'] === 'en');
    $ruRow = $summary->first(fn (array $row): bool => $row['entity']->modelClass === Object_::class && $row['locale'] === 'ru');

    expect($enRow['total'])->toBe(2)
        ->and($enRow['translated'])->toBe(2)
        ->and($ruRow['total'])->toBe(2)
        ->and($ruRow['translated'])->toBe(1)
        ->and($ruRow['missing'])->toBe(1);
});

it('filters the object list to objects lacking a translation in a chosen language', function (): void {
    $geo = translationGeography();
    $missingRu = translationSeedObject($geo);
    $hasRu = translationSeedObject($geo);
    DB::table('object_translations')->insert([
        'object_id' => $hasRu->id, 'locale' => 'ru', 'name' => 'Объект',
        'slug' => "object-{$hasRu->id}-ru", 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $method = new ReflectionMethod(ObjectsTable::class, 'filters');
    $method->setAccessible(true);
    /** @var list<mixed> $filters */
    $filters = $method->invoke(null);
    $filter = collect($filters)->first(fn ($filter): bool => $filter instanceof SelectFilter && $filter->getName() === 'missing_translation');

    expect($filter)->not->toBeNull();

    $ids = $filter->apply(Object_::query()->withUnmoderated(), ['value' => 'ru'])->pluck('id')->all();

    expect($ids)->toContain($missingRu->id)
        ->and($ids)->not->toContain($hasRu->id);
});

it('copy-from-primary populates a target language and marks it needing review, not translated', function (): void {
    $geo = translationGeography();
    $object = translationSeedObject($geo);
    $actor = User::factory()->create();

    app(TranslationCopyService::class)->copyFromPrimary($object, 'ru', ['name', 'short_description', 'full_description', 'seo_title', 'seo_description', 'slug'], $actor);

    $translation = DB::table('object_translations')->where('object_id', $object->id)->where('locale', 'ru')->first();

    expect($translation)->not->toBeNull()
        ->and($translation->name)->toBe("Object {$object->id}")
        ->and((bool) $translation->needs_review)->toBeTrue()
        ->and($translation->published_at)->toBeNull();

    $summary = collect(app(TranslationCompletenessReport::class)->summary(['ru']));
    $ruRow = $summary->first(fn (array $row): bool => $row['entity']->modelClass === Object_::class && $row['locale'] === 'ru');

    expect($ruRow['translated'])->toBe(0)
        ->and($ruRow['needsReview'])->toBe(1);
});

it('publishes a single language version independently, clearing the review flag', function (): void {
    $geo = translationGeography();
    $object = translationSeedObject($geo);
    $actor = User::factory()->create();

    app(TranslationCopyService::class)->copyFromPrimary($object, 'ru', ['name', 'short_description', 'full_description', 'seo_title', 'seo_description', 'slug'], $actor);
    app(TranslationCopyService::class)->publish($object, 'ru', $actor);

    $translation = DB::table('object_translations')->where('object_id', $object->id)->where('locale', 'ru')->first();

    expect((bool) $translation->needs_review)->toBeFalse()
        ->and($translation->published_at)->not->toBeNull();

    // The English version's own publish state is untouched by publishing Russian.
    $englishTranslation = DB::table('object_translations')->where('object_id', $object->id)->where('locale', 'en')->first();
    expect($englishTranslation->published_at)->not->toBeNull();
});

it('refuses to publish a language with no translation at all', function (): void {
    $geo = translationGeography();
    $object = translationSeedObject($geo);
    $actor = User::factory()->create();

    expect(fn () => app(TranslationCopyService::class)->publish($object, 'ru', $actor))
        ->toThrow(ModelNotFoundException::class);
});

it('includes moderation-pending entities in the missing-translation query, not just published ones', function (): void {
    $geo = translationGeography();
    $pending = translationSeedObject($geo, ['moderation_status' => 'pending']);

    $entity = app(TranslatableEntityRegistry::class)->find(Object_::class);
    $ids = app(TranslationCompletenessReport::class)->missingQuery($entity, 'ru')->pluck('id')->all();

    expect($ids)->toContain($pending->id);
});
