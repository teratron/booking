<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Seo\SitemapBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Sitemap Index — Paginated Per-Language Artefacts
|--------------------------------------------------------------------------
|
| The index references one child sitemap per (active language, populated
| entity type). A child sitemap paginates once its own URL count passes the
| configured per-file limit. A page an administrator has explicitly marked
| not indexable never appears in any child, generated as a static artefact
| a route then reads back unchanged.
|
*/

/** @return array{languageId: int, countryId: int} */
function sitemapRegistry(): array
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

    return compact('languageId', 'countryId');
}

/** @param  array<string, mixed>  $overrides */
function sitemapMakeTerritory(int $countryId, string $name, array $overrides = []): int
{
    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'is_active' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $slug = Str::slug($name);

    DB::table('territory_translations')->insert(array_merge([
        'territory_id' => $territoryId, 'country_id' => $countryId, 'locale' => 'en', 'name' => $name,
        'slug' => $slug, 'full_slug_path' => $slug, 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));

    return $territoryId;
}

it('references one child sitemap per active language and populated entity type', function (): void {
    Storage::fake('public');
    $fixture = sitemapRegistry();
    sitemapMakeTerritory($fixture['countryId'], 'Indexed Town');

    app(SitemapBuilder::class)->generate();

    $index = Storage::disk('public')->get('sitemaps/sitemap.xml');

    expect($index)->not->toBeNull()
        ->and($index)->toContain('territory-1.xml')
        ->and(Storage::disk('public')->exists('sitemaps/en/territory-1.xml'))->toBeTrue();
});

it('paginates a child sitemap once its own URL count passes the configured per-file limit', function (): void {
    Storage::fake('public');
    config(['sitemap.urls_per_file' => 3]);
    $fixture = sitemapRegistry();

    foreach (range(1, 5) as $i) {
        sitemapMakeTerritory($fixture['countryId'], "Paginated Town {$i}");
    }

    app(SitemapBuilder::class)->generate();

    expect(Storage::disk('public')->exists('sitemaps/en/territory-1.xml'))->toBeTrue()
        ->and(Storage::disk('public')->exists('sitemaps/en/territory-2.xml'))->toBeTrue()
        ->and(Storage::disk('public')->exists('sitemaps/en/territory-3.xml'))->toBeFalse();

    $firstPage = (string) Storage::disk('public')->get('sitemaps/en/territory-1.xml');
    $secondPage = (string) Storage::disk('public')->get('sitemaps/en/territory-2.xml');

    expect(substr_count($firstPage, '<url>'))->toBe(3)
        ->and(substr_count($secondPage, '<url>'))->toBe(2);
});

it('never includes a page an administrator has explicitly marked not indexable', function (): void {
    Storage::fake('public');
    $fixture = sitemapRegistry();
    sitemapMakeTerritory($fixture['countryId'], 'Indexed Territory');
    sitemapMakeTerritory($fixture['countryId'], 'Hidden Territory', ['seo_indexable' => false]);

    app(SitemapBuilder::class)->generate();

    $sitemap = (string) Storage::disk('public')->get('sitemaps/en/territory-1.xml');

    expect($sitemap)->toContain('indexed-territory')
        ->and($sitemap)->not->toContain('hidden-territory');
});

it('serves the generated index and its children through the static-read routes', function (): void {
    Storage::fake('public');
    $fixture = sitemapRegistry();
    sitemapMakeTerritory($fixture['countryId'], 'Served Town');

    app(SitemapBuilder::class)->generate();

    $this->get('/sitemap.xml')->assertOk()->assertSee('territory-1.xml', false);
    $this->get('/sitemaps/en/territory-1.xml')->assertOk()->assertSee('served-town', false);
});

it('404s a sitemap route before the generation job has ever run', function (): void {
    Storage::fake('public');

    $this->get('/sitemap.xml')->assertNotFound();
    $this->get('/sitemaps/en/territory-1.xml')->assertNotFound();
});

it('lists a news item at its canonical slug URL, not the raw numeric id', function (): void {
    // F-03's route fix (numeric-id binding replaced by slug binding)
    // left the sitemap generator building the old id-based URL directly —
    // listing a URL that now 301-redirects defeats the sitemap's own
    // purpose of naming the canonical address.
    Storage::fake('public');
    sitemapRegistry();

    $newsId = DB::table('news_items')->insertGetId([
        'author_id' => User::factory()->create()->id,
        'status' => 'published', 'moderation_status' => 'approved', 'publish_at' => now()->subDay(),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('news_translations')->insert([
        'news_item_id' => $newsId, 'locale' => 'en', 'title' => 'Sitemap News',
        'body' => 'Body.', 'slug' => 'sitemap-news',
        'needs_review' => false, 'created_at' => now(), 'updated_at' => now(),
    ]);

    app(SitemapBuilder::class)->generate();

    $sitemap = (string) Storage::disk('public')->get('sitemaps/en/news-1.xml');

    expect($sitemap)->toContain('/en/news/sitemap-news')
        ->and($sitemap)->not->toContain("/en/news/{$newsId}<");
});
