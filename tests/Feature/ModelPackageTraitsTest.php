<?php

declare(strict_types=1);

use App\Models\Country;
use App\Models\Object_;
use App\Models\ObjectType;
use App\Models\Territory;
use App\Models\TerritoryLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Model Package Traits
|--------------------------------------------------------------------------
|
| One assertion per package this task wires onto a real model, so a
| misconfigured trait fails loudly here rather than at first use by later
| feature work — astrotomic/laravel-translatable, spatie/laravel-medialibrary,
| owen-it/laravel-auditing, and staudenmeir/laravel-adjacency-list.
|
*/

function seedCoreFixture(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $country = Country::create([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true,
    ]);
    $country->translateOrNew('en')->name = 'Moldova';
    $country->save();

    $level = TerritoryLevel::create(['country_id' => $country->id, 'depth_rank' => 1]);
    $level->translateOrNew('en')->fill(['singular_name' => 'Region', 'plural_name' => 'Regions']);
    $level->save();

    $objectType = ObjectType::create(['key' => 'hotel']);
    $objectType->translateOrNew('en')->fill(['name' => 'Hotel', 'slug' => 'hotel']);
    $objectType->save();

    $owner = User::factory()->create();

    return compact('country', 'level', 'objectType', 'owner');
}

test('a translated territory subtree expands through staudenmeir/laravel-adjacency-list', function (): void {
    ['country' => $country, 'level' => $level] = seedCoreFixture();

    $region = Territory::create(['country_id' => $country->id, 'level_id' => $level->id]);
    $region->translateOrNew('en')->fill(['country_id' => $country->id, 'name' => 'Gagauzia', 'slug' => 'gagauzia']);
    $region->save();

    $city = Territory::create(['country_id' => $country->id, 'level_id' => $level->id, 'parent_id' => $region->id]);
    $city->translateOrNew('en')->fill(['country_id' => $country->id, 'name' => 'Comrat', 'slug' => 'comrat']);
    $city->save();

    $resort = Territory::create(['country_id' => $country->id, 'level_id' => $level->id, 'parent_id' => $city->id]);
    $resort->translateOrNew('en')->fill(['country_id' => $country->id, 'name' => 'Beșalma', 'slug' => 'besalma']);
    $resort->save();

    // astrotomic/laravel-translatable: round-trips through the translation
    // table, not merely stored as an attribute on the base row.
    expect($region->fresh()->translate('en')->name)->toBe('Gagauzia');
    expect(DB::table('territory_translations')->where('territory_id', $region->id)->count())->toBe(1);

    // staudenmeir/laravel-adjacency-list: descendants() walks the recursive
    // CTE two levels down, the exact claim ScopeAuthorizer's territory-scope
    // coverage check relies on.
    $descendantNames = $region->descendants()->pluck('id');
    expect($descendantNames)->toContain($city->id, $resort->id);
    expect($region->descendants()->count())->toBe(2);
});

test('an object round-trips a translation, attaches media, and records an audit entry', function (): void {
    // owen-it/laravel-auditing skips console events by default (audit.console
    // = false) so seeders and artisan commands do not spam the journal —
    // correct in production, but it also means the test runner's own
    // console context would silently produce zero audit rows regardless of
    // whether the trait is wired correctly. Enabled for this one assertion,
    // which is exactly what a real web request needs proven.
    config(['audit.console' => true]);

    ['country' => $country, 'level' => $level, 'objectType' => $objectType, 'owner' => $owner] = seedCoreFixture();

    $territory = Territory::create(['country_id' => $country->id, 'level_id' => $level->id]);
    $territory->translateOrNew('en')->fill(['country_id' => $country->id, 'name' => 'Chișinău', 'slug' => 'chisinau']);
    $territory->save();

    $object = Object_::create([
        'ulid' => (string) Str::ulid(),
        'owner_id' => $owner->id,
        'object_type_id' => $objectType->id,
        'territory_id' => $territory->id,
        'country_id' => $country->id,
        'status' => 'published',
        'moderation_status' => 'approved',
    ]);
    $object->translateOrNew('en')->fill(['name' => 'Grand Hotel', 'slug' => 'grand-hotel']);
    $object->save();

    // astrotomic/laravel-translatable
    expect($object->fresh()->translate('en')->name)->toBe('Grand Hotel');

    // spatie/laravel-medialibrary: a real file attaches to the model's
    // default media collection and is retrievable through the relation.
    $path = tempnam(sys_get_temp_dir(), 'demo').'.jpg';
    file_put_contents($path, base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAMCAgICAgMCAgIDAwMDBAYEBAQEBAgGBgUGCQgKCgkICQkKDA8MCgsOCwkJDRENDg8QEBEQCgwSExIQEw8QEBD/2wBDAQMDAwQDBAgEBAgQCwkLEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBD/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAn/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k='));
    $object->addMedia($path)->preservingOriginal()->toMediaCollection();

    expect($object->fresh()->getMedia()->count())->toBe(1);

    // owen-it/laravel-auditing: the update above is recorded as a real
    // audit row, not merely trusted because the trait is present.
    expect($object->audits()->count())->toBeGreaterThan(0);
    expect(DB::table('audits')->where('auditable_id', $object->id)->where('auditable_type', Object_::class)->exists())->toBeTrue();

    @unlink($path);
});
