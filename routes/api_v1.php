<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AmenityController;
use App\Http\Controllers\Api\V1\ArticleController;
use App\Http\Controllers\Api\V1\CountryController;
use App\Http\Controllers\Api\V1\DocumentationController;
use App\Http\Controllers\Api\V1\NewsController;
use App\Http\Controllers\Api\V1\ObjectController;
use App\Http\Controllers\Api\V1\ObjectTypeController;
use App\Http\Controllers\Api\V1\PromotionController;
use App\Http\Controllers\Api\V1\StatusController;
use App\Http\Controllers\Api\V1\TerritoryController;
use App\Http\Controllers\Api\V1\TokenController;
use App\Support\Api\ApiResource;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API — v1
|--------------------------------------------------------------------------
|
| Disabled at portal scope by default (the "api" module) — every route
| here 404s, indistinguishable from an unregistered path, until an
| administrator turns the module on. The read-only launch surface mirrors
| what an anonymous visitor can already see, each endpoint gated by the
| bearer token's own resource ability and, where the resource carries a
| country or category axis, narrowed to the token's own scope. Every
| authenticated request is rate-limited by `throttle:api-token` before any
| ability check or catalog query runs, and its consumption is recorded by
| `record.consumption` only once it has actually cleared every gate.
|
*/

Route::middleware('module:api')->group(function (): void {
    Route::get('/status', [StatusController::class, 'index'])->name('api.v1.status');
    Route::get('/docs', [DocumentationController::class, 'index'])->name('api.v1.docs');

    Route::middleware(['auth:sanctum', 'throttle:api-token'])->group(function (): void {
        Route::get('/token', [TokenController::class, 'show'])->name('api.v1.token.show');

        Route::middleware(['api.locale', 'record.consumption'])->group(function (): void {
            Route::middleware('abilities:'.ApiResource::Countries->value)
                ->get('/countries', [CountryController::class, 'index'])->name('api.v1.countries.index');

            Route::middleware('abilities:'.ApiResource::Territories->value)->group(function (): void {
                Route::get('/territories', [TerritoryController::class, 'index'])->name('api.v1.territories.index');
                Route::get('/territories/{territory}', [TerritoryController::class, 'show'])->name('api.v1.territories.show');
            });

            Route::middleware('abilities:'.ApiResource::ObjectTypes->value)
                ->get('/object-types', [ObjectTypeController::class, 'index'])->name('api.v1.object-types.index');

            Route::middleware('abilities:'.ApiResource::Amenities->value)
                ->get('/amenities', [AmenityController::class, 'index'])->name('api.v1.amenities.index');

            Route::middleware('abilities:'.ApiResource::Objects->value)->group(function (): void {
                Route::get('/objects', [ObjectController::class, 'index'])->name('api.v1.objects.index');
                Route::get('/objects/{object}', [ObjectController::class, 'show'])->name('api.v1.objects.show');
            });

            Route::middleware('abilities:'.ApiResource::ObjectReviews->value)
                ->get('/objects/{object}/reviews', [ObjectController::class, 'reviews'])->name('api.v1.objects.reviews');

            Route::middleware('abilities:'.ApiResource::News->value)
                ->get('/news', [NewsController::class, 'index'])->name('api.v1.news.index');

            Route::middleware('abilities:'.ApiResource::Promotions->value)
                ->get('/promotions', [PromotionController::class, 'index'])->name('api.v1.promotions.index');

            Route::middleware('abilities:'.ApiResource::Articles->value)
                ->get('/articles', [ArticleController::class, 'index'])->name('api.v1.articles.index');
        });
    });
});
