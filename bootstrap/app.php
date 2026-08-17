<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureModuleEnabled;
use App\Http\Middleware\RecordApiConsumption;
use App\Http\Middleware\ResolveApiLocale;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api_v1.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'module' => EnsureModuleEnabled::class,
            'api.locale' => ResolveApiLocale::class,
            'record.consumption' => RecordApiConsumption::class,
            // Sanctum ships these classes but registers no route-middleware
            // alias for them — every consumer is expected to alias its own.
            'abilities' => CheckAbilities::class,
        ]);

        // The public API checks the module gate before token authentication
        // — a disabled module must be inert before any bearer token is ever
        // inspected. Laravel's default priority list would otherwise run
        // `auth:sanctum` first, since a custom alias not in that list keeps
        // its declared route position only relative to other unlisted
        // middleware.
        $middleware->prependToPriorityList(
            before: Authenticate::class,
            prepend: EnsureModuleEnabled::class,
        );
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
