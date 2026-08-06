<?php

declare(strict_types=1);

namespace Tests\Fixtures\Filament;

use App\Filament\Admin\Support\ScopedResource;
use App\Models\Object_;
use UnitEnum;

/**
 * A resource declaring no scope axis at all — the shape a global registry
 * takes. Only an unrestricted grant may reach one; a grant bounded to a
 * country says nothing about who may edit a portal-wide registry, so it must
 * reach nothing rather than everything.
 */
final class GlobalProbeResource extends ScopedResource
{
    protected static ?string $model = Object_::class;

    protected static string $permissionPrefix = 'object';

    protected static string|UnitEnum|null $navigationGroup = null;
}
