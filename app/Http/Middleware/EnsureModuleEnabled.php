<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Modules\ModuleContext;
use App\Services\Modules\ModuleResolver;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The server-side half of "disabled means inert, not hidden": a route
 * behind a disabled module returns 404, the same response an unknown route
 * would give, not a rendered page with the capability missing. Hiding a
 * link to a gated route is a usability affordance; this is what actually
 * enforces it.
 *
 * Context is read from whatever the route already exposes rather than any
 * per-route wiring — an `object` route-model binding supplies the object
 * and (if the bound model exposes it) owner scope; the authenticated user
 * supplies the owner scope otherwise. Category and country context are
 * supplied by routes that carry them once those routes exist; omitted here,
 * a gate simply resolves down the rest of the ladder.
 */
final class EnsureModuleEnabled
{
    public function __construct(
        private readonly ModuleResolver $resolver,
    ) {}

    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        $context = new ModuleContext(
            objectId: $this->routeParameterId($request, 'object'),
            ownerId: $request->user()?->getAuthIdentifier(),
        );

        if (! $this->resolver->isEnabled($moduleKey, $context)) {
            abort(404);
        }

        return $next($request);
    }

    private function routeParameterId(Request $request, string $name): ?int
    {
        $parameter = $request->route($name);

        return match (true) {
            $parameter instanceof Model => (int) $parameter->getKey(),
            is_numeric($parameter) => (int) $parameter,
            default => null,
        };
    }
}
