<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\DocumentsQueryParameters;
use App\Http\Controllers\Api\V1\Concerns\ResolvesTokenScope;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ObjectCardResource;
use App\Http\Resources\Api\V1\ObjectResource;
use App\Http\Resources\Api\V1\ReviewResource;
use App\Models\Object_;
use App\Models\Review;
use App\Models\Territory;
use App\Services\Authorization\ResourceQueryScoper;
use App\Services\Catalog\CatalogQueryService;
use App\Support\Catalog\CatalogSearchCriteria;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The catalog's own launch-surface endpoints — collection, detail, and
 * reviews. `index()` never builds its own retrieval query: every filter
 * becomes a {@see CatalogSearchCriteria} field and the result comes back
 * from {@see CatalogQueryService::search()}, the identical call the public
 * catalog page makes, so ordering and visibility can never drift between
 * the two surfaces.
 */
final class ObjectController extends Controller implements DocumentsQueryParameters
{
    use ResolvesTokenScope;

    /** @return array<string, list<string>> */
    public static function queryParameterRules(): array
    {
        return [
            'territory' => ['nullable', 'integer', 'exists:territories,id'],
            'type' => ['nullable', 'integer', 'exists:object_types,id'],
            'amenities' => ['nullable', 'string'],
            'price_min' => ['nullable', 'numeric', 'min:0'],
            'price_max' => ['nullable', 'numeric', 'min:0'],
            'rating_min' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'availability' => ['nullable', 'string', 'in:available,unavailable,unspecified'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate(self::queryParameterRules());

        $territory = isset($validated['territory']) ? Territory::query()->find((int) $validated['territory']) : null;

        $amenityIds = isset($validated['amenities']) && $validated['amenities'] !== ''
            ? array_map(intval(...), explode(',', $validated['amenities']))
            : [];

        $criteria = new CatalogSearchCriteria(
            territory: $territory,
            objectTypeId: isset($validated['type']) ? (int) $validated['type'] : null,
            amenityIds: $amenityIds,
            priceMin: isset($validated['price_min']) ? (float) $validated['price_min'] : null,
            priceMax: isset($validated['price_max']) ? (float) $validated['price_max'] : null,
            ratingMin: isset($validated['rating_min']) ? (float) $validated['rating_min'] : null,
            availabilityStatus: $validated['availability'] ?? null,
            page: (int) ($validated['page'] ?? 1),
            perPage: (int) ($validated['per_page'] ?? 24),
        );

        $results = app(CatalogQueryService::class)->search($criteria, $this->scopeConstraint($request));

        return ObjectCardResource::collection($results);
    }

    public function show(Request $request, int $object): ObjectResource
    {
        $query = Object_::query()->where('status', 'published');

        app(ResourceQueryScoper::class)->applyConstraint(
            $query,
            $this->scopeConstraint($request),
            'country_id',
            null,
            'object_type_id',
        );

        return new ObjectResource($query->findOrFail($object));
    }

    public function reviews(Request $request, int $object): AnonymousResourceCollection
    {
        $query = Object_::query()->where('status', 'published');

        app(ResourceQueryScoper::class)->applyConstraint(
            $query,
            $this->scopeConstraint($request),
            'country_id',
            null,
            'object_type_id',
        );

        $model = $query->findOrFail($object);

        $reviews = Review::query()
            ->where('object_id', $model->id)
            ->where('status', 'published')
            ->with('author')
            ->latest()
            ->paginate(24);

        return ReviewResource::collection($reviews);
    }
}
