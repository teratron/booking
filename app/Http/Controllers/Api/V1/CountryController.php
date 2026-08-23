<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTokenScope;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CountryResource;
use App\Models\Country;
use App\Services\Authorization\ResourceQueryScoper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CountryController extends Controller
{
    use ResolvesTokenScope;

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Country::query()->with('translations')->where('is_active', true)->orderBy('display_order');

        app(ResourceQueryScoper::class)->applyConstraint($query, $this->scopeConstraint($request), 'id');

        return CountryResource::collection($query->get());
    }
}
