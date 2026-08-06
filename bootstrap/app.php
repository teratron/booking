<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureModuleEnabled;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'module' => EnsureModuleEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create()
    // Interface catalogs live under resources/, alongside views and assets,
    // rather than at Laravel's default root-level lang/. Keeping every
    // presentation resource under one parent is the project's declared layout.
    ->useLangPath(dirname(__DIR__).'/resources/lang');
