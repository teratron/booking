<?php

declare(strict_types=1);

namespace App\Filament\Admin\Support;

use App\Models\Concerns\FiltersModeration;
use App\Models\Scopes\ModerationScope;
use App\Models\User;
use App\Services\Authorization\ResourceQueryScoper;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Override;

/**
 * The base every staff-panel resource extends.
 *
 * Twenty-odd sections sharing one list/filter/sort/paginate/bulk contract is
 * the difference between a deliverable panel and a permanent one — but the
 * stronger reason this class exists is that three of the behaviours below are
 * the kind an author forgets exactly once, in exactly one resource, and the
 * omission has no visible symptom:
 *
 * - **Scope narrowing.** Applied to the list query here, so a resource cannot
 *   be written without it. A country administrator paging an unnarrowed list
 *   sees other countries' record counts even when every row refuses to open.
 * - **Filter persistence.** Configured once; the requirement is that a
 *   returning administrator finds the list as they left it.
 * - **Counted bulk confirmation.** Supplied as a helper rather than left to
 *   each action, because "are you sure?" and "hide 87 objects in Odesa
 *   region" are different questions and only the second is answerable.
 *
 * Concrete resources declare which columns carry the scope axes. A resource
 * carrying none — a global registry — is reachable only by an unrestricted
 * grant, which is the intended reading of a grant bounded to one country.
 */
abstract class ScopedResource extends Resource
{
    /**
     * Permission prefix this resource's abilities are keyed to, e.g. `object`
     * for `object.view`. Scope narrowing resolves against `{prefix}.view`,
     * since a list is a read.
     */
    protected static string $permissionPrefix = '';

    protected static ?string $countryColumn = null;

    protected static ?string $territoryColumn = null;

    protected static ?string $categoryColumn = null;

    /**
     * Relations any column on the list reads through.
     *
     * Declared rather than lazy-loaded: strict mode turns a lazy load into a
     * thrown exception instead of one extra query per row, and a table plus
     * translations plus media is the exact shape that produces them. Naming
     * them here means the failure is a missing line in one place, not a
     * scattered performance regression nobody measures.
     *
     * @var list<string>
     */
    protected static array $eagerLoad = [];

    /**
     * Table defaults every resource inherits. Call from a resource's own
     * `table()` before adding columns, so persistence is never re-declared and
     * never accidentally omitted.
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

    /** @return Builder<covariant \Illuminate\Database\Eloquent\Model> */
    #[Override]
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(static::$eagerLoad);

        // The moderation global scope exists to keep pending, rejected, and
        // revision-requested rows off public pages. The staff panel is the
        // opposite case: a moderation queue that cannot see unapproved
        // content has nothing to moderate, and an object list that hides a
        // rejected object makes it unreachable by the only people who can fix
        // it. Lifted here rather than per resource, because the resource that
        // forgets is the one where the row silently disappears.
        if (in_array(FiltersModeration::class, class_uses_recursive(static::getModel()), true)) {
            $query->withoutGlobalScope(ModerationScope::class);
        }

        $user = Filament::auth()->user();

        if (! $user instanceof User) {
            // No actor resolves outside a request — a console context, or a
            // guard that has not run yet. Returning the unnarrowed query here
            // would make the fallback the permissive one.
            return $query->whereRaw('1 = 0');
        }

        return app(ResourceQueryScoper::class)->narrow(
            $query,
            $user,
            static::viewPermission(),
            static::$countryColumn,
            static::$territoryColumn,
            static::$categoryColumn,
        );
    }

    public static function viewPermission(): string
    {
        return static::$permissionPrefix.'.view';
    }
}
