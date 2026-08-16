<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Self-introspection for the bearer token presented on the request — the
 * standard way a consumer confirms which client and resource scope a token
 * carries without an administrator having to relay that information out of
 * band. Requires `auth:sanctum`; unlike {@see StatusController} this is the
 * one endpoint that exercises the token model on its own, ahead of the
 * catalog-backed read endpoints layered on top of it.
 */
final class TokenController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var ApiClient $client */
        $client = $request->user();

        return response()->json([
            'client' => $client->name,
            'resources' => $client->currentAccessToken()->abilities,
        ]);
    }
}
