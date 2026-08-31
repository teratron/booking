<?php

declare(strict_types=1);

use App\Jobs\GenerateSitemapsJob;
use App\Models\ApiClient;
use App\Models\Module;
use App\Models\User;
use App\Services\Modules\ModuleAdministrator;
use App\Services\Seo\SeoHealthReport;
use App\Support\Api\ApiResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Full-funnel regression sweep (2026-08-31)
|--------------------------------------------------------------------------
|
| One assertion per defect an end-to-end walk of the public funnel
| reproduced. Each failed before its fix landed; each proves the specific
| defect stays closed. Every one is written against a *populated* fixture
| — an empty table is what hid several of these on two earlier walks — and
| this file carries its own minimal registry (qaRegistry()) so it runs on
| its own.
|
*/

beforeEach(function (): void {
    // Strict mode is what turns an un-eager-loaded translation read into a
    // hard 500 rather than a silent N+1 — the exact tripwire these guard.
    Model::shouldBeStrict();
});

/**
 * The minimum registry a public page needs to boot: one active primary
 * language and one active country with every not-null column filled.
 * Self-contained rather than shared so this file can run on its own.
 *
 * @return array{languageId: int, countryId: int, levelId: int}
 */
function qaRegistry(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'display_order' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryId = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true, 'display_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('languageId', 'countryId', 'levelId');
}

/** N-01 — the home page renders with a partner banner that carries a translation. */
it('renders the home page when a home-partners banner exists (N-01)', function (): void {
    qaRegistry();

    $slotId = DB::table('banner_slots')->insertGetId([
        'key' => 'home-partners', 'surfaces' => json_encode(['home']),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $bannerId = DB::table('banners')->insertGetId([
        'banner_slot_id' => $slotId, 'name' => 'A partner', 'advertiser' => 'Acme',
        'destination_link' => 'https://example.test', 'display_order' => 1, 'is_active' => true,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addYear(),
        'impressions' => 0, 'clicks' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('banner_translations')->insert([
        'banner_id' => $bannerId, 'locale' => 'en', 'link_text' => 'Visit our partner',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->get('/en')->assertOk();
});

/** N-05 — the public API articles endpoint resolves with a category present. */
it('serves /api/v1/articles when the module is on and an article has a category (N-05)', function (): void {
    qaRegistry();
    $user = User::factory()->create();

    $module = Module::query()->where('key', 'api')->first()
        ?? Module::query()->forceCreate(['key' => 'api', 'default_state' => 'disabled', 'is_active' => true, 'scopable_levels' => ['portal']]);
    app(ModuleAdministrator::class)->setState($module, 'portal', null, true, $user);

    $categoryId = DB::table('article_categories')->insertGetId([
        'slug' => 'guides', 'is_active' => true, 'display_order' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('article_category_translations')->insert([
        'article_category_id' => $categoryId, 'locale' => 'en', 'name' => 'Guides', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $articleId = DB::table('articles')->insertGetId([
        'article_category_id' => $categoryId, 'author_id' => $user->id, 'status' => 'published',
        'publish_at' => now()->subDay(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('article_translations')->insert([
        'article_id' => $articleId, 'locale' => 'en', 'slug' => 'how-to', 'title' => 'How to', 'summary' => 's', 'body' => 'b',
        'needs_review' => false, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    $client = DB::table('api_clients')->insertGetId([
        'name' => 'T', 'is_active' => true, 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $token = ApiClient::query()->find($client)
        ->createToken('t', array_map(fn ($c) => $c->value, ApiResource::cases()))
        ->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/articles')
        ->assertOk();
});

/** N-06 — robots.txt is the dynamic controller, not a static stub. */
it('serves a dynamic robots.txt with the sitemap and panel disallows (N-06)', function (): void {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertSee('Disallow: /'.config('booking.panels.admin.path'), false)
        ->assertSee('Disallow: /'.config('booking.panels.cabinet.path'), false)
        ->assertSee('Sitemap: ', false);

    expect(file_exists(public_path('robots.txt')))->toBeFalse('a static public/robots.txt would shadow the route again');
});

/** N-12 — a missing sitemap artefact yields 503 + a queued regeneration, never an empty 200. */
it('answers 503 and queues regeneration when the sitemap artefact is missing (N-12)', function (): void {
    Queue::fake();
    Storage::disk((string) config('sitemap.disk'))->deleteDirectory('sitemaps');

    $this->get('/sitemap.xml')->assertStatus(503)->assertHeader('Retry-After');

    Queue::assertPushed(GenerateSitemapsJob::class);
});

/** N-03 — the SEO health summary is SQL aggregation: its query count is flat against row volume. */
it('computes the SEO health summary without materialising every translation row (N-03)', function (): void {
    $fixture = qaRegistry();

    // Territory translations with a blank SEO title — each one an "offending
    // row" the summary must count. The earlier implementation loaded every
    // one of them into PHP; this proves it no longer does.
    $seedBlankTitles = function (int $count, int $from) use ($fixture): void {
        for ($i = $from; $i < $from + $count; $i++) {
            $territoryId = DB::table('territories')->insertGetId([
                'country_id' => $fixture['countryId'], 'level_id' => $fixture['levelId'],
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('territory_translations')->insert([
                'territory_id' => $territoryId, 'country_id' => $fixture['countryId'], 'locale' => 'en',
                'name' => "T{$i}", 'slug' => "t{$i}", 'full_slug_path' => "t{$i}",
                'needs_review' => false, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    };

    $measure = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $summary = app(SeoHealthReport::class)->summary();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return [$summary, $queries];
    };

    $seedBlankTitles(15, 0);
    [$small, $smallQueries] = $measure();

    $seedBlankTitles(45, 15); // now 60 offending rows in total
    [$large, $largeQueries] = $measure();

    expect($small['missing_title'])->toBeGreaterThanOrEqual(15)
        ->and($large['missing_title'])->toBeGreaterThanOrEqual(60)
        // The whole point: 4x the offending rows, identical query count.
        ->and($largeQueries)->toBe($smallQueries);
});
