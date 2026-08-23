<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\DownloadJsonExportController;
use App\Http\Controllers\BannerClickController;
use App\Http\Controllers\ExitImpersonationController;
use App\Http\Controllers\Public\BlogController;
use App\Http\Controllers\Public\ContactClickController;
use App\Http\Controllers\Public\CountryLandingController;
use App\Http\Controllers\Public\CountryPreferenceController;
use App\Http\Controllers\Public\FeedbackSubmissionController;
use App\Http\Controllers\Public\HomePageController;
use App\Http\Controllers\Public\LegalPageController;
use App\Http\Controllers\Public\MapPinsController;
use App\Http\Controllers\Public\NewsController;
use App\Http\Controllers\Public\ObjectPageController;
use App\Http\Controllers\Public\PromotionController;
use App\Http\Controllers\Public\RobotsController;
use App\Http\Controllers\Public\RootRedirectController;
use App\Http\Controllers\Public\SitemapController;
use App\Http\Controllers\Public\StaticPageController;
use App\Http\Controllers\Public\TerritoryPageController;
use App\Http\Middleware\ResolvePublicLocale;
use App\Http\Middleware\ResolveRedirect;
use App\Livewire\Public\CatalogSearch;
use Illuminate\Support\Facades\Route;

// The bare root carries no language segment, so it has no page of its
// own — it negotiates one and forwards into the `{lang}` group below.
Route::get('/', RootRedirectController::class)->name('public.root');

Route::get('/support-mode/exit', ExitImpersonationController::class)
    ->middleware('auth')
    ->name('support-mode.exit');

Route::get('/banners/{banner}/click', BannerClickController::class)
    ->name('banners.click');

Route::get('/robots.txt', RobotsController::class)->name('robots');

// A third download format alongside Filament's own CSV/XLSX export routes —
// see App\Filament\Admin\Exports\JsonExportFormat for why this lives here
// rather than as a Filament-shipped route.
Route::middleware('web')
    ->prefix(config('filament.system_route_prefix', 'filament'))
    ->group(function (): void {
        Route::get('/exports/{export}/download-json', DownloadJsonExportController::class)
            ->name('exports.download-json');
    });

// Sitemaps are language-agnostic at the index level and read-only static
// artefacts at every level — deliberately outside the `{lang}` group below,
// since ResolvePublicLocale/ResolveRedirect have nothing to resolve for a
// machine-readable XML file.
Route::name('public.')->group(function (): void {
    Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemaps.index');
    Route::get('/sitemaps/{locale}/{filename}', [SitemapController::class, 'child'])
        ->where('locale', '[a-z]{2}')
        ->where('filename', '[a-z]+-[0-9]+\.xml')
        ->name('sitemaps.child');
});

// Public site: every route below lives under a `{lang}` segment resolved
// and validated by ResolvePublicLocale. This group is the layout's own
// route scaffold — later public-site tasks register their pages into it;
// the shell itself only needs the two actions below.
Route::pattern('lang', '[a-z]{2}');

Route::prefix('{lang}')
    ->middleware([ResolvePublicLocale::class, ResolveRedirect::class])
    ->name('public.')
    ->group(function (): void {
        Route::get('/', [HomePageController::class, 'show'])->name('home');
        Route::post('/country', CountryPreferenceController::class)->name('country-preference');
        Route::post('/feedback', FeedbackSubmissionController::class)->name('feedback.submit');
        Route::get('/privacy-policy', [LegalPageController::class, 'privacy'])->name('legal.privacy');
        Route::get('/terms', [LegalPageController::class, 'terms'])->name('legal.terms');
        Route::get('/about', [StaticPageController::class, 'about'])->name('about');
        Route::get('/contacts', [StaticPageController::class, 'contacts'])->name('contacts');
        Route::get('/map/pins', [MapPinsController::class, 'index'])->name('map.pins.index');
        Route::get('/map/pins/{object}', [MapPinsController::class, 'show'])->name('map.pins.show');
        Route::get('/catalog', CatalogSearch::class)->name('catalog.index');
        // Flat object addressing (§5.1): an object may be recategorized or
        // reassigned to a neighbouring territory, and a hierarchical object
        // URL would break a ranked page on every such move.
        Route::get('/o/{slug}', [ObjectPageController::class, 'show'])->name('objects.show');
        Route::get('/objects/{object}/contact/{channel}/click', ContactClickController::class)->name('objects.contact.click');
        Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
        Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');
        Route::get('/news', [NewsController::class, 'index'])->name('news.index');
        Route::get('/news/{slug}', [NewsController::class, 'show'])->name('news.show');
        Route::get('/promotions/{slug}', PromotionController::class)->name('promotions.show');
        // Territory paths mirror the hierarchy (§5.1): `{path}` is the
        // territory's own cached `full_slug_path`, optionally followed by an
        // object-type slug for the typed-catalog-within-a-territory case
        // (`{settlement}/{type}`) — PublicSlugResolver disambiguates the two.
        // Both wildcard routes stay last so every literal segment above
        // (`catalog`, `o`, `blog`, `news`, `promotions`, …) is tried first;
        // none of them can collide with the `{country}` constraint below,
        // since none is exactly two lowercase letters.
        Route::get('/{country}/{path}', [TerritoryPageController::class, 'show'])
            ->where('country', '[a-z]{2}')
            ->where('path', '.*')
            ->name('territories.show');
        Route::get('/{country}', CountryLandingController::class)
            ->where('country', '[a-z]{2}')
            ->name('country.show');
    });
