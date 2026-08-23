<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureModuleEnabled;
use App\Http\Middleware\RecordApiConsumption;
use App\Http\Middleware\ResolveApiLocale;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Sentry\Laravel\Integration as SentryIntegration;

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
        // middleware. The anchor must be the *contract* the priority list
        // actually carries — `AuthenticatesRequests` — not the concrete
        // `Authenticate` class: `prependToPriorityList()` requires an exact
        // match against an existing list entry, and the concrete class is
        // not itself in the list, so anchoring on it silently appended this
        // gate to the end instead of before authentication, and every
        // guest/disabled-module probe reached `auth:sanctum` first.
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: EnsureModuleEnabled::class,
        );

        // Laravel's own guest-redirect fallback (Authenticate::redirectTo())
        // only skips the redirect attempt when the request already asks for
        // JSON (Accept-header-driven) — a different check from
        // shouldRenderJsonWhen() below, which decides how a *thrown*
        // exception renders. Left at its default, a token-less API request
        // sent without an explicit JSON Accept header reaches route('login')
        // — a name this app never registers, since the admin and cabinet
        // panels each register their own Filament-prefixed login route
        // instead — producing an unhandled RouteNotFoundException instead of
        // the 401 an unauthenticated API caller should see. api/* never
        // redirects a guest at all; every other guest keeps a real target.
        $middleware->redirectGuestsTo(
            fn (Request $request): ?string => $request->is('api/*')
                ? null
                : route('filament.admin.auth.login'),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Routes every reported exception to the error tracker — web
        // requests, queued jobs (Illuminate\Queue\Worker::runJob() reports
        // through this same handler once a job's own retries are
        // exhausted), and the scheduler (Illuminate\Console\Scheduling\
        // ScheduleRunCommand reports the same way). One wire-up covers all
        // three surfaces because Laravel's own worker and scheduler both
        // report through the container's exception handler already; this
        // is what makes a failed backup, rollup, sweep, or import job
        // visible without a separate integration per surface.
        SentryIntegration::handles($exceptions);
    })->create()
    // Interface catalogs live under resources/, alongside views and assets,
    // rather than at Laravel's default root-level lang/. Keeping every
    // presentation resource under one parent is the project's declared layout.
    ->useLangPath(dirname(__DIR__).'/resources/lang');
