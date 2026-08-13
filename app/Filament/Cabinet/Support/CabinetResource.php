<?php

declare(strict_types=1);

namespace App\Filament\Cabinet\Support;

use App\Models\Object_;
use App\Services\Modules\ModuleContext;
use App\Services\Modules\ModuleResolver;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables\Table;

/**
 * The base every cabinet-panel resource extends — the cabinet's counterpart
 * to the admin panel's `ScopedResource`. Query narrowing there is a
 * country/territory/category axis resolved per request by a dedicated
 * scoper service; here it does not need to be hand-written at all, because
 * Filament's own tenancy (wired in `CabinetPanelProvider`) already scopes
 * every tenant-owned resource's queries to the current object via a global
 * scope keyed to the `object` relationship. What this class supplies
 * instead is what tenancy does not: table persistence defaults matching the
 * admin panel's, and the two navigation-visibility axes a cabinet menu
 * needs — the current object's declared type capabilities, and whichever
 * feature modules are active for it.
 */
abstract class CabinetResource extends Resource
{
    /**
     * Table defaults every cabinet resource inherits — the same persistence
     * contract `ScopedResource` gives the admin panel, so a returning owner
     * finds a list exactly as they left it there too.
     */
    public static function applyTableDefaults(Table $table): Table
    {
        return $table
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->persistColumnSearchesInSession()
            ->deferFilters(false);
    }

    /**
     * Whether the currently selected tenant object's type declares the given
     * structural capability (`has_rooms`, `has_availability_status`) — a
     * restaurant owner must never see a Rooms entry a hotel owner does.
     * Resolves to false with no tenant bound rather than throwing, since
     * navigation can render before tenant identification runs.
     */
    protected static function currentObjectHasCapability(string $capability): bool
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Object_) {
            return false;
        }

        return (bool) $tenant->objectType->{$capability};
    }

    /**
     * Whether $moduleKey resolves enabled for the current tenant, through
     * the same resolution ladder (object → owner → category → country →
     * portal) `ModuleResolver` uses everywhere else — the mechanism behind
     * an optional module (e.g. bookings) only showing its menu entries once
     * it is active and the owner has opted in. No module in this project's
     * current configuration is active, so every module-gated entry resolves
     * absent today; that is expected, not a defect this task owns fixing.
     */
    protected static function currentObjectModuleEnabled(string $moduleKey): bool
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Object_) {
            return false;
        }

        return app(ModuleResolver::class)->isEnabled($moduleKey, new ModuleContext(
            objectId: $tenant->id,
            ownerId: $tenant->owner_id,
            categoryId: $tenant->object_type_id,
            countryId: $tenant->country_id,
        ));
    }
}
