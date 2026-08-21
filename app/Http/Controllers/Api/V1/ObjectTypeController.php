<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTokenScope;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ObjectTypeResource;
use App\Models\ObjectType;
use App\Services\Authorization\ResourceQueryScoper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ObjectTypeController extends Controller
{
    use ResolvesTokenScope;

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ObjectType::query()->with('translations')->where('is_active', true)->orderBy('id');

        // Category scope only — an object type is not itself owned by a
        // country, so the token's country axis does not narrow this list.
        app(ResourceQueryScoper::class)->applyConstraint($query, $this->scopeConstraint($request), null, null, 'id');

        return ObjectTypeResource::collection($query->get());
    }
}
