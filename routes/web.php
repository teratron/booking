<?php

declare(strict_types=1);

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
use App\Http\Controllers\Public\TerritoryPageController;
use App\Http\Middleware\ResolvePublicLocale;
use App\Http\Middleware\ResolveRedirect;
use App\Livewire\Public\CatalogSearch;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'));

Route::get('/support-mode/exit', ExitImpersonationController::class)
    ->middleware('auth')
    ->name('support-mode.exit');

Route::get('/banners/{banner}/click', BannerClickController::class)
    ->name('banners.click');

Route::get('/robots.txt', RobotsController::class)->name('robots');

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
        Route::get('/map/pins', [MapPinsController::class, 'index'])->name('map.pins.index');
        Route::get('/map/pins/{object}', [MapPinsController::class, 'show'])->name('map.pins.show');
        Route::get('/catalog', CatalogSearch::class)->name('catalog.index');
        // Flat object addressing (§5.1): an object may be recategorized or
        // reassigned to a neighbouring territory, and a hierarchical object
        // URL would break a ranked page on every such move.
        Route::get('/o/{slug}', [ObjectPageController::class, 'show'])->name('objects.show');
        Route::get('/objects/{object}/contact/{channel}/click', ContactClickController::class)->name('objects.contact.click');
        Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
        Route::get('/blog/{article}', [BlogController::class, 'show'])->name('blog.show');
        Route::get('/news', [NewsController::class, 'index'])->name('news.index');
        Route::get('/news/{newsItem}', [NewsController::class, 'show'])->name('news.show');
        Route::get('/promotions/{promotion}', PromotionController::class)->name('promotions.show');
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
