<?php

declare(strict_types=1);

namespace App\Services\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Narrows a panel list query to what the actor's grants actually reach.
 *
 * This is the half of scoped authorization a per-record policy cannot do. A
 * policy answers "may this actor open this record"; it runs after the query,
 * which means a country administrator paging a list still learns how many
 * objects every other country holds, and every page they open is a round trip
 * that ends in a refusal. Narrowing happens once, in the query, and the
 * refusal never has to be rendered.
 *
 * Both halves stay: the query narrows the list, the policy guards the record.
 * A record reachable by URL but absent from the list is exactly the gap that
 * appears when only one of them is implemented.
 */
final class ResourceQueryScoper
{
    public function __construct(private readonly ScopeAuthorizer $authorizer) {}

    /**
     * Applies the actor's grants of $permission to $query.
     *
     * A resource declares which of its columns carry the country, territory,
     * and category axes; an axis it does not carry cannot be reached by a
     * grant bounded to that axis. A global registry — languages, modules —
     * therefore declares none, and only an unrestricted grant reaches it.
     * That is the intended reading, not an oversight: "administrator for
     * Georgia" says nothing about who may rename a language.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function narrow(
        Builder $query,
        User $user,
        string $permission,
        ?string $countryColumn = null,
        ?string $territoryColumn = null,
        ?string $categoryColumn = null,
    ): Builder {
        $constraint = $this->authorizer->constraintFor($user, $permission);

        if ($constraint->isUnrestricted) {
            return $query;
        }

        $reachable = array_filter([
            $countryColumn !== null && $constraint->countryIds !== [] ? [$countryColumn, $constraint->countryIds] : null,
            $territoryColumn !== null && $constraint->territoryIds !== [] ? [$territoryColumn, $constraint->territoryIds] : null,
            $categoryColumn !== null && $constraint->categoryIds !== [] ? [$categoryColumn, $constraint->categoryIds] : null,
        ]);

        // Fail closed. An actor whose grants reach no axis this resource
        // carries sees an empty list, never an unfiltered one — the opposite
        // default turns a missing column declaration into a silent disclosure.
        if ($reachable === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $scoped) use ($reachable): void {
            foreach ($reachable as [$column, $ids]) {
                $scoped->orWhereIn($column, $ids);
            }
        });
    }
}
