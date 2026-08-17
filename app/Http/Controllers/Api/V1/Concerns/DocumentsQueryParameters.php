<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Services\Api\ApiDocumentationGenerator;

/**
 * A controller implementing this exposes the exact validation rule array it
 * passes to `$request->validate()`, as a single static source both the
 * controller's own validation call and {@see ApiDocumentationGenerator}
 * read — so the published parameter list can never drift from what the
 * endpoint actually enforces.
 */
interface DocumentsQueryParameters
{
    /** @return array<string, list<string>> */
    public static function queryParameterRules(): array;
}
