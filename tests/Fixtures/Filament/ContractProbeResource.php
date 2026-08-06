<?php

declare(strict_types=1);

namespace Tests\Fixtures\Filament;

use App\Filament\Admin\Support\ScopedResource;
use App\Models\Object_;
use Filament\Tables\Table;
use UnitEnum;

/**
 * A resource that adds nothing of its own, so that what the contract supplies
 * can be observed in isolation.
 *
 * Asserting the contract through a real resource would conflate the two: a
 * passing test would not distinguish "the base class narrows the query" from
 * "the object resource happens to narrow it as well". This declares the scope
 * axes and stops.
 */
final class ContractProbeResource extends ScopedResource
{
    protected static ?string $model = Object_::class;

    protected static string $permissionPrefix = 'object';

    protected static ?string $countryColumn = 'country_id';

    protected static ?string $territoryColumn = 'territory_id';

    protected static ?string $categoryColumn = 'object_type_id';

    protected static string|UnitEnum|null $navigationGroup = null;

    public static function table(Table $table): Table
    {
        return self::applyTableDefaults($table);
    }
}
