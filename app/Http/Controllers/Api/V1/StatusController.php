<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * The one endpoint under the module gate that never depends on the token
 * model — a consumer can confirm the API is reachable and which contract
 * version they are talking to before ever presenting a bearer token.
 */
final class StatusController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'version' => 'v1',
        ]);
    }
}
