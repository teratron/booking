<?php

declare(strict_types=1);

use App\Http\Controllers\BannerClickController;
use App\Http\Controllers\ExitImpersonationController;
use App\Http\Controllers\Public\CountryPreferenceController;
use App\Http\Controllers\Public\FeedbackSubmissionController;
use App\Http\Middleware\ResolvePublicLocale;
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
        Route::post('/country', CountryPreferenceController::class)->name('country-preference');
        Route::post('/feedback', FeedbackSubmissionController::class)->name('feedback.submit');
    });
