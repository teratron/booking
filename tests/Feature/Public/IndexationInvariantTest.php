<?php

declare(strict_types=1);

use App\Models\Object_;
use App\Models\User;
use App\Services\Seo\SitemapBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Indexation Invariant — Nothing Non-Public Is Ever Indexable
|--------------------------------------------------------------------------
|
| A single, route-registry-driven sweep rather than a per-task test: no
| back-office or cabinet route is ever reachable under the public {lang}
| addressing, and no object outside the sitemap's own visibility gate
| (published, moderation-approved, not soft-deleted) ever appears in a
| generated sitemap — proven directly against the artefact a search engine
| actually reads, not only against the page's own noindex meta tag.
|
| Catalog filter-count indexability, territory-level seo_indexable
| exclusion, and the 404-plus-noindex pairing on a non-public object page
| are already covered by IndexationPolicyTest and SitemapGenerationTest;
| this file closes the two gaps neither exercises: cross-checking
| moderation state against the generated sitemap XML itself, and asserting
| the public/panel route sets never overlap.
|
*/

it('never registers a back-office or cabinet route under the public {lang} address space', function (): void {
    $publicNames = [];
    $panelNames = [];

    foreach (Route::getRoutes() as $route) {
        $name = $route->getName();

        if ($name === null) {
            continue;
        }

        if (str_starts_with($name, 'public.')) {
            $publicNames[] = $name;
        }

        if (str_starts_with($name, 'filament.admin.') || str_starts_with($name, 'filament.cabinet.')) {
            $panelNames[] = $name;
        }
    }

    // Both sets must be non-empty for this sweep to mean anything — an
    // empty panel-route set would make the disjointness assertion below
    // vacuously true rather than a real proof.
    expect($publicNames)->not->toBeEmpty()->and($panelNames)->not->toBeEmpty();

    $adminPath = trim((string) config('booking.panels.admin.path'), '/');
    $cabinetPath = trim((string) config('booking.panels.cabinet.path'), '/');

    foreach (Route::getRoutes() as $route) {
        $name = $route->getName();

        if ($name === null || ! str_starts_with($name, 'public.')) {
            continue;
        }

        $uri = $route->uri();

        expect($uri)->not->toContain("/{$adminPath}")
            ->and($uri)->not->toContain("/{$cabinetPath}");
    }
});

/** @return array{languageId: int, countryId: int, cityId: int, typeId: int} */
function indexationInvariantFixture(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryId = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true, 'display_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $cityId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'is_active' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'accommodation', 'is_active' => true, 'attribute_schema' => json_encode([]),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('languageId', 'countryId', 'cityId', 'typeId');
}

/** @param  array{countryId: int, cityId: int, typeId: int}  $fixture */
/** @param  array<string, mixed>  $overrides */
function indexationInvariantMakeObject(array $fixture, string $name, array $overrides = []): Object_
{
    $objectId = DB::table('objects')->insertGetId(array_merge([
        'ulid' => (string) Str::ulid(), 'owner_id' => User::factory()->create()->id,
        'object_type_id' => $fixture['typeId'], 'territory_id' => $fixture['cityId'],
        'country_id' => $fixture['countryId'], 'status' => 'published', 'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));

    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => $name,
        'slug' => Str::slug($name).'-'.$objectId, 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Object_ $object */
    $object = Object_::query()->withUnmoderated()->findOrFail($objectId);

    return $object;
}

it('never includes a pending, rejected, archived, hidden, or soft-deleted object in the generated sitemap', function (): void {
    Storage::fake('public');
    $fixture = indexationInvariantFixture();

    $visible = indexationInvariantMakeObject($fixture, 'Visible Hotel');
    $pending = indexationInvariantMakeObject($fixture, 'Pending Hotel', ['moderation_status' => 'pending']);
    $rejected = indexationInvariantMakeObject($fixture, 'Rejected Hotel', ['moderation_status' => 'rejected']);
    $archived = indexationInvariantMakeObject($fixture, 'Archived Hotel', ['status' => 'archived']);
    $hidden = indexationInvariantMakeObject($fixture, 'Hidden Hotel', ['status' => 'hidden']);
    $softDeleted = indexationInvariantMakeObject($fixture, 'Deleted Hotel');
    $softDeleted->delete();

    app(SitemapBuilder::class)->generate();

    $sitemap = (string) Storage::disk('public')->get('sitemaps/en/object-1.xml');

    expect($sitemap)->toContain($visible->slug)
        ->and($sitemap)->not->toContain($pending->slug)
        ->and($sitemap)->not->toContain($rejected->slug)
        ->and($sitemap)->not->toContain($archived->slug)
        ->and($sitemap)->not->toContain($hidden->slug)
        ->and($sitemap)->not->toContain($softDeleted->slug);
});
