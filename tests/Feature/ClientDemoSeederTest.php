<?php

declare(strict_types=1);

use App\Models\Article;
use App\Models\Banner;
use App\Models\Object_;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Client Demo Seeder
|--------------------------------------------------------------------------
|
| Tagged `slow` and excluded from the default `composer test`/`quality`
| run for the same reason DemoVolumeSeederTest is, though the concern here
| is network access rather than row volume: this seeder downloads real
| photos from Lorem Picsum, and the ordinary dev/test reset loop must never
| depend on outbound network access. Run explicitly via `composer
| test:slow`.
|
| The assertions below exist because this seeder shipped once with every
| translation and every photo silently missing — a stray
| `use WithoutModelEvents;` copied from DatabaseSeeder suppressed the
| model-event listener astrotomic/laravel-translatable and Spatie Media
| Library both rely on to persist anything, and the seeder still exited 0.
| A row-count check alone would not have caught that; every check here
| reaches into the translation and media tables specifically.
|
*/

test('the client demo seeder produces a small, fully photographed and translated catalog', function (): void {
    $this->seed();

    $exitCode = Artisan::call('db:seed', [
        '--class' => 'Database\\Seeders\\ClientDemoSeeder',
        '--force' => true,
    ]);

    expect($exitCode)->toBe(0);

    $objectIds = DB::table('objects')->pluck('id');
    expect($objectIds)->toHaveCount(18);

    // Every object carries a translation in both launch locales — not
    // merely a nonzero object_translations count, which the
    // WithoutModelEvents regression would still have passed with zero
    // rows undetected by a bare count() alone.
    foreach (['en', 'ru'] as $locale) {
        $translated = DB::table('object_translations')
            ->whereIn('object_id', $objectIds)
            ->where('locale', $locale)
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->count();

        expect($translated)->toBe(18);
    }

    // Every object has its three photos attached, one marked primary.
    foreach ($objectIds as $objectId) {
        $media = DB::table('media')
            ->where('model_type', Object_::class)
            ->where('model_id', $objectId)
            ->where('collection_name', 'photos')
            ->get();

        expect($media)->toHaveCount(3);

        $primaryCount = $media->filter(
            fn (object $row): bool => (bool) (json_decode((string) $row->custom_properties, true)['is_primary'] ?? false)
        )->count();

        expect($primaryCount)->toBe(1);
    }

    // Banners carry both creatives and a translated link_text.
    $bannerIds = DB::table('banners')->pluck('id');
    expect($bannerIds)->toHaveCount(3);

    foreach ($bannerIds as $bannerId) {
        expect(DB::table('media')->where('model_type', Banner::class)->where('model_id', $bannerId)->where('collection_name', 'desktop_creative')->exists())->toBeTrue()
            ->and(DB::table('media')->where('model_type', Banner::class)->where('model_id', $bannerId)->where('collection_name', 'mobile_creative')->exists())->toBeTrue()
            ->and(DB::table('banner_translations')->where('banner_id', $bannerId)->where('locale', 'en')->whereNotNull('link_text')->exists())->toBeTrue();
    }

    // Articles, news, and a promotion each carry a cover image and a
    // published-language title.
    expect(DB::table('articles')->count())->toBe(3)
        ->and(DB::table('news_items')->count())->toBe(2)
        ->and(DB::table('promotions')->count())->toBe(1)
        ->and(DB::table('reviews')->count())->toBe(54);

    foreach (DB::table('articles')->pluck('id') as $articleId) {
        expect(DB::table('media')->where('model_type', Article::class)->where('model_id', $articleId)->where('collection_name', 'cover_image')->exists())->toBeTrue()
            ->and(DB::table('article_translations')->where('article_id', $articleId)->where('locale', 'en')->whereNotNull('title')->exists())->toBeTrue();
    }
})->group('slow');
