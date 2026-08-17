<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AmenityResource;
use App\Models\Amenity;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * A global facility registry — not owned by a country or a category, so no
 * token scope narrows it. Any token holding the `amenities` ability sees the
 * whole active list, the same one every object type's own filter widget
 * draws from.
 */
final class AmenityController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return AmenityResource::collection(
            Amenity::query()->where('is_active', true)->orderBy('id')->get(),
        );
    }
}
