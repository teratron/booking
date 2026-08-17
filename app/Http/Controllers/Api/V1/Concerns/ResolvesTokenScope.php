<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\ApiClient;
use App\Models\ApiToken;
use App\Services\Api\ApiTokenService;
use App\Services\Authorization\ResourceQueryScoper;
use App\Services\Authorization\ScopeConstraint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Every read endpoint resolves the bearer token's own country/category
 * narrowing the same way — through {@see ApiTokenService::scopeConstraint()}
 * — so no controller re-derives what "within scope" means.
 */
trait ResolvesTokenScope
{
    private function scopeConstraint(Request $request): ScopeConstraint
    {
        /** @var ApiClient $client */
        $client = $request->user();

        /** @var ApiToken $token */
        $token = $client->currentAccessToken();

        return app(ApiTokenService::class)->scopeConstraint($token);
    }

    /**
     * News and promotions carry no `country_id` column of their own — only
     * a `$territoryRelation`, resolved here through that relation's own
     * `country_id` instead of {@see ResourceQueryScoper::applyConstraint()},
     * which only narrows a query by a column the base table itself carries.
     * $territoryNullable admits a row with no territory at all (portal-wide
     * content) regardless of scope — a country-scoped token still sees
     * content that belongs to no single country.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     */
    private function applyCountryScopeViaTerritory(
        Builder $query,
        ScopeConstraint $constraint,
        string $territoryRelation = 'territory',
        bool $territoryNullable = false,
    ): void {
        if ($constraint->isUnrestricted || $constraint->countryIds === []) {
            return;
        }

        $countryIds = $constraint->countryIds;

        $query->where(function (Builder $scoped) use ($countryIds, $territoryRelation, $territoryNullable): void {
            $scoped->whereHas($territoryRelation, fn (Builder $territory): Builder => $territory->whereIn('country_id', $countryIds));

            if ($territoryNullable) {
                $scoped->orWhereNull("{$territoryRelation}_id");
            }
        });
    }
}
