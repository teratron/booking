<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\StatusController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API — v1
|--------------------------------------------------------------------------
|
| Disabled at portal scope by default (the "api" module) — every route
| here 404s, indistinguishable from an unregistered path, until an
| administrator turns the module on. Read-only, tokened, rate-limited: the
| rest of this file grows in later tasks alongside the token model and the
| catalog-backed read endpoints; this task's own scope is the gate and the
| versioned route registration it protects.
|
*/

Route::middleware('module:api')->group(function (): void {
    Route::get('/status', [StatusController::class, 'index'])->name('api.v1.status');
});
