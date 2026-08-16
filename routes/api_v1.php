<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\StatusController;
use App\Http\Controllers\Api\V1\TokenController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API — v1
|--------------------------------------------------------------------------
|
| Disabled at portal scope by default (the "api" module) — every route
| here 404s, indistinguishable from an unregistered path, until an
| administrator turns the module on. Read-only, tokened, rate-limited: this
| file grows further in later tasks alongside the catalog-backed read
| endpoints layered over the token model this one already gates.
|
*/

Route::middleware('module:api')->group(function (): void {
    Route::get('/status', [StatusController::class, 'index'])->name('api.v1.status');

    Route::middleware('auth:sanctum')->get('/token', [TokenController::class, 'show'])->name('api.v1.token.show');
});
