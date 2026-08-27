<?php

declare(strict_types=1);

use App\Models\Object_;
use App\Models\ObjectType;
use App\Models\Territory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Realistic-Volume Demo Seeder
|--------------------------------------------------------------------------
|
| Tagged `slow` and excluded from the default `composer test`/`quality`
| run — this seeds over 175,000 rows for real, which is the whole point
| (a dozen fixtures measure nothing), but paying that cost on every
| quality-gate invocation would slow down every later task's loop for the
| rest of the project. Run explicitly via `composer test:slow`.
|
*/

test('the demo volume seeder produces catalog-ranking benchmark fixtures at realistic volume', function (): void {
    $this->seed();

    $previousLimit = ini_set('memory_limit', '128M');

    try {
        $exitCode = Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\DemoVolumeSeeder',
            '--force' => true,
        ]);
    } finally {
        ini_set('memory_limit', $previousLimit);
    }

    // A real, enforced constraint, not a documented one: if chunking
    // regresses back to accumulating the whole 175,000-row insert in PHP
    // memory before flushing, this fails with a fatal "Allowed memory size
    // exhausted" before reaching the assertions below.
    expect($exitCode)->toBe(0);

    $objectCount = DB::table('objects')->count();
    $leafTerritoryCount = DB::table('territories')
        ->whereIn('level_id', DB::table('territory_levels')->where('depth_rank', 4)->pluck('id'))
        ->count();

    expect($objectCount)->toBeGreaterThanOrEqual(50_000);
    expect($leafTerritoryCount)->toBeGreaterThanOrEqual(3_000);

    // Every object has translations in both active languages and a
    // populated geom — not merely a nonzero count.
    $activeLanguageCount = DB::table('languages')->where('is_active', true)->count();

    $objectsWithFullTranslations = DB::table('object_translations')
        ->select('object_id')
        ->groupBy('object_id')
        ->havingRaw('count(*) = ?', [$activeLanguageCount])
        ->count();

    $objectsWithGeom = DB::table('objects')->whereNotNull('geom')->count();

    expect($objectsWithFullTranslations)->toBe($objectCount);
    expect($objectsWithGeom)->toBe($objectCount);
})->group('slow');

test('the demo volume seeder produces contact channels, banners, editorial content, reviews, and an audit trail', function (): void {
    $this->seed();

    $previousLimit = ini_set('memory_limit', '256M');

    try {
        $exitCode = Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\DemoVolumeSeeder',
            '--force' => true,
        ]);
    } finally {
        ini_set('memory_limit', $previousLimit);
    }

    expect($exitCode)->toBe(0);

    $objectCount = DB::table('objects')->count();

    // Contact channels: a representative majority of objects get 1-3
    // channels each, so the total sits well above the object count itself
    // — never zero, never a token handful.
    $contactChannelCount = DB::table('contact_channels')->count();
    expect($contactChannelCount)->toBeGreaterThan((int) ($objectCount * 0.9))
        ->and($contactChannelCount)->toBeLessThan($objectCount * 3);
    expect(DB::table('contact_channels')->where('is_active', true)->count())->toBeGreaterThan(0);
    expect(DB::table('contact_channels')->where('is_active', false)->count())->toBeGreaterThan(0);

    // Banners: a modest inventory, spread across every seeded slot, with
    // all three targeting shapes represented (untargeted rows carry no
    // `banner_targets` entry at all, so only the other two show up here).
    $bannerCount = DB::table('banners')->count();
    expect($bannerCount)->toBeGreaterThanOrEqual(20)->and($bannerCount)->toBeLessThan(200);
    expect(DB::table('banner_translations')->count())->toBeGreaterThan(0);
    expect(DB::table('banner_targets')->count())->toBeGreaterThan(0);
    expect(DB::table('banner_targets')->distinct()->pluck('target_type')->all())
        ->toEqualCanonicalizing([Territory::class, ObjectType::class]);

    // Editorial content: modest, curated-taxonomy volume — dozens, never
    // thousands, unlike the object/territory volume story above.
    expect(DB::table('article_categories')->count())->toBeGreaterThan(0);
    expect(DB::table('article_tags')->count())->toBeGreaterThan(0);

    $articleCount = DB::table('articles')->count();
    expect($articleCount)->toBeGreaterThanOrEqual(20)->and($articleCount)->toBeLessThan(500);
    expect(DB::table('article_translations')->count())->toBeGreaterThan(0);

    $newsCount = DB::table('news_items')->count();
    expect($newsCount)->toBeGreaterThanOrEqual(20)->and($newsCount)->toBeLessThan(500);
    expect(DB::table('news_translations')->count())->toBeGreaterThan(0);

    $promotionCount = DB::table('promotions')->count();
    expect($promotionCount)->toBeGreaterThanOrEqual(10)->and($promotionCount)->toBeLessThan(500);
    expect(DB::table('promotion_translations')->count())->toBeGreaterThan(0);

    // Reviews: a sample of objects, with every moderation status present.
    $reviewCount = DB::table('reviews')->count();
    expect($reviewCount)->toBeGreaterThan(0)->and($reviewCount)->toBeLessThan($objectCount);
    expect(DB::table('reviews')->where('status', 'published')->count())->toBeGreaterThan(0);
    expect(DB::table('reviews')->where('status', 'pending')->count())->toBeGreaterThan(0);
    expect(DB::table('reviews')->where('status', 'rejected')->count())->toBeGreaterThan(0);

    // Audit trail: populated through the Eloquent model layer specifically
    // — a handful of real, package-shaped rows, not a bulk-inserted count.
    expect(DB::table('audits')->count())->toBeGreaterThan(0);
    expect(
        DB::table('audits')->where('auditable_type', Object_::class)->where('event', 'updated')->count()
    )->toBeGreaterThan(0);
})->group('slow');
