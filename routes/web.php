<?php

declare(strict_types=1);

use App\Http\Controllers\BannerClickController;
use App\Http\Controllers\ExitImpersonationController;
use App\Http\Controllers\Public\BlogController;
use App\Http\Controllers\Public\ContactClickController;
use App\Http\Controllers\Public\CountryPreferenceController;
use App\Http\Controllers\Public\FeedbackSubmissionController;
use App\Http\Controllers\Public\HomePageController;
use App\Http\Controllers\Public\LegalPageController;
use App\Http\Controllers\Public\MapPinsController;
use App\Http\Controllers\Public\NewsController;
use App\Http\Controllers\Public\ObjectPageController;
use App\Http\Controllers\Public\PromotionController;
use App\Http\Controllers\Public\TerritoryPageController;
use App\Http\Middleware\ResolvePublicLocale;
use App\Livewire\Public\CatalogSearch;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'));

Route::get('/support-mode/exit', ExitImpersonationController::class)
    ->middleware('auth')
    ->name('support-mode.exit');

Route::get('/banners/{banner}/click', BannerClickController::class)
    ->name('banners.click');

// Public site: every route below lives under a `{lang}` segment resolved
// and validated by ResolvePublicLocale. This group is the layout's own
// route scaffold — later public-site tasks register their pages into it;
// the shell itself only needs the two actions below.
Route::pattern('lang', '[a-z]{2}');

Route::prefix('{lang}')
    ->middleware(ResolvePublicLocale::class)
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
        // ID-addressed rather than the full per-language nested-slug path
        // l1-seo.md §5.1 eventually describes — that URL grammar (and the
        // denormalized ancestor-slug-path caching it needs) is l1-seo's own
        // domain; this route is swappable for it later without touching the
        // page's own composition.
        Route::get('/territory/{territory}', [TerritoryPageController::class, 'show'])->name('territories.show');
        Route::get('/objects/{object}', [ObjectPageController::class, 'show'])->name('objects.show');
        Route::get('/objects/{object}/contact/{channel}/click', ContactClickController::class)->name('objects.contact.click');
        Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
        Route::get('/blog/{article}', [BlogController::class, 'show'])->name('blog.show');
        Route::get('/news', [NewsController::class, 'index'])->name('news.index');
        Route::get('/news/{newsItem}', [NewsController::class, 'show'])->name('news.show');
        Route::get('/promotions/{promotion}', PromotionController::class)->name('promotions.show');
    });
