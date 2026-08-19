<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Filament\Admin\Exports\JsonDownloader;
use App\Http\Controllers\Controller;
use Filament\Actions\Exports\Http\Controllers\DownloadExport;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a completed export's JSON artefact. Filament ships an equivalent
 * download route for its own CSV and XLSX formats ({@see DownloadExport});
 * this mirrors its authorization exactly (a signed URL carries the auth
 * guard, an `Export` policy's `view` ability is consulted if one is
 * registered, otherwise only the export's own owner may download it) for
 * the third format that controller cannot resolve.
 */
final class DownloadJsonExportController extends Controller
{
    public function __invoke(Request $request, Export $export): StreamedResponse
    {
        $authGuard = $request->hasValidSignature(absolute: false) ? $request->query('authGuard') : null;

        abort_unless(auth($authGuard)->check(), 401);

        $user = auth($authGuard)->user();

        $exportPolicy = Gate::getPolicyFor($export::class);

        if (filled($exportPolicy) && method_exists($exportPolicy, 'view')) {
            Gate::forUser($user)->authorize('view', Arr::wrap($export));
        } else {
            abort_unless($export->user()->is($user), 403);
        }

        return app(JsonDownloader::class)($export);
    }
}
