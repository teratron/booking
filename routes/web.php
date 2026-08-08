<?php

declare(strict_types=1);

use App\Http\Controllers\ExitImpersonationController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'));

Route::get('/support-mode/exit', ExitImpersonationController::class)
    ->middleware('auth')
    ->name('support-mode.exit');
