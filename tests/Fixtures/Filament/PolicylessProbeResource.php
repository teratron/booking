<?php

declare(strict_types=1);

namespace Tests\Fixtures\Filament;

use App\Filament\Admin\Support\ScopedResource;
use App\Models\ContactChannel;
use UnitEnum;

/**
 * A resource over a model with no policy registered, which is the exact
 * mistake strict authorization exists to catch.
 *
 * Kept pointed at a model that genuinely has none rather than at the object
 * model: the moment a real policy is written for the latter, an assertion
 * built on it starts passing for the wrong reason.
 */
final class PolicylessProbeResource extends ScopedResource
{
    protected static ?string $model = ContactChannel::class;

    protected static string $permissionPrefix = 'object';

    protected static string|UnitEnum|null $navigationGroup = null;
}
