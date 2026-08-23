<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\DocumentsQueryParameters;
use App\Http\Controllers\Api\V1\Concerns\ResolvesTokenScope;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TerritoryResource;
use App\Models\Territory;
use App\Services\Authorization\ResourceQueryScoper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class TerritoryController extends Controller implements DocumentsQueryParameters
{
    use ResolvesTokenScope;

    /** @return array<string, list<string>> */
    public static function queryParameterRules(): array
    {
        return [
            'country' => ['nullable', 'integer', 'exists:countries,id'],
            'parent' => ['nullable', 'integer', 'exists:territories,id'],
            'level' => ['nullable', 'integer', 'exists:territory_levels,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate(self::queryParameterRules());

        $query = Territory::query()
            ->with(['translations', 'level.translations', 'country'])
            ->where('is_active', true)
            ->orderBy('display_order');

        if (isset($validated['country'])) {
            $query->where('country_id', $validated['country']);
        }

        if (isset($validated['parent'])) {
            $query->where('parent_id', $validated['parent']);
        }

        if (isset($validated['level'])) {
            $query->where('level_id', $validated['level']);
        }

        app(ResourceQueryScoper::class)->applyConstraint($query, $this->scopeConstraint($request), 'country_id');

        return TerritoryResource::collection($query->paginate((int) ($validated['per_page'] ?? 24)));
    }

    public function show(Request $request, int $territory): TerritoryResource
    {
        $query = Territory::query()
            ->with(['translations', 'level.translations', 'country'])
            ->where('is_active', true);

        app(ResourceQueryScoper::class)->applyConstraint($query, $this->scopeConstraint($request), 'country_id');

        return new TerritoryResource($query->findOrFail($territory));
    }
}
