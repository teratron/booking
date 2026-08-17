<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Api\ApiDocumentationGenerator;
use Illuminate\Http\JsonResponse;

/**
 * The published API reference, generated on every request from the route
 * table and each endpoint's own validation rules — never hand-maintained,
 * so it cannot drift from the contract it describes. Auth-free like
 * {@see StatusController}: reading the documentation is not itself a
 * business operation a token needs to be scoped for.
 */
final class DocumentationController extends Controller
{
    public function index(ApiDocumentationGenerator $generator): JsonResponse
    {
        return response()->json([
            'version' => 'v1',
            'endpoints' => $generator->generate(),
        ]);
    }
}
