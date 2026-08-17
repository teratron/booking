<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiClient;
use App\Models\ApiToken;
use App\Services\Analytics\EventCaptureService;
use App\Support\Analytics\StatEventKind;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records one `api_request` event per served response — placed to wrap the
 * resource-ability gate, not to precede it: `$next()` throws before
 * returning when the module gate, token auth, rate limit, or ability check
 * refuses the request, which skips the capture call below entirely. Only a
 * request that actually reached and cleared every prior gate is consumption
 * a token or an endpoint actually used.
 */
final class RecordApiConsumption
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // This middleware sits behind `auth:sanctum`, so the resolved actor
        // is always the token's own ApiClient here, never the portal's own
        // User — the same reasoning TokenController's identical cast
        // documents.
        /** @var ApiClient $client */
        $client = $request->user();
        $token = $client->currentAccessToken();

        if ($token instanceof ApiToken) {
            app(EventCaptureService::class)->capture(
                StatEventKind::ApiRequest->value,
                $token,
                ['endpoint' => (string) $request->route()?->getName()],
            );
        }

        return $response;
    }
}
