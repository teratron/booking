<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\DocumentsQueryParameters;
use App\Http\Controllers\Api\V1\Concerns\ResolvesTokenScope;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PromotionResource;
use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class PromotionController extends Controller implements DocumentsQueryParameters
{
    use ResolvesTokenScope;

    /** @return array<string, list<string>> */
    public static function queryParameterRules(): array
    {
        return [
            'object' => ['nullable', 'integer', 'exists:objects,id'],
            'territory' => ['nullable', 'integer', 'exists:territories,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate(self::queryParameterRules());

        $query = Promotion::published()->with('translations')->latest('starts_at');

        if (isset($validated['object'])) {
            $query->where('object_id', $validated['object']);
        }

        if (isset($validated['territory'])) {
            $query->where('territory_id', $validated['territory']);
        }

        $this->applyCountryScopeViaTerritory($query, $this->scopeConstraint($request));

        return PromotionResource::collection($query->paginate((int) ($validated['per_page'] ?? 24)));
    }
}
