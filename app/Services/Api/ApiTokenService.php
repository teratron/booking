<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Models\ApiClient;
use App\Models\ApiToken;
use App\Models\User;
use App\Services\Audit\AuditJournal;
use App\Services\Authorization\ScopeConstraint;
use App\Support\Api\ApiResource;
use Carbon\CarbonInterface;
use Laravel\Sanctum\NewAccessToken;

/**
 * Issues, revokes, and re-scopes public-API tokens on an {@see ApiClient}'s
 * behalf, and journals every one of those three events — the only writer
 * this project's token lifecycle goes through.
 *
 * The country/category axes reuse {@see ScopeConstraint} verbatim, including
 * its additive (OR) semantics: a token scoped to Moldova and to the dining
 * category reaches Moldovan objects of any category *and* dining objects in
 * any country, exactly as an administrator's role grant would. This is
 * deliberate — inventing a narrower, intersective reading here would be the
 * second authorization system the specification says not to build.
 */
final class ApiTokenService
{
    public function __construct(private readonly AuditJournal $journal) {}

    /**
     * Issues a new token and returns it with its plain-text value attached —
     * the only moment that value is ever available. The caller must display
     * it to the administrator immediately; it cannot be recovered afterward,
     * since only its hash is stored.
     *
     * @param  list<ApiResource>  $resources
     * @param  list<int>  $countryIds
     * @param  list<int>  $categoryIds
     */
    public function issue(
        ApiClient $client,
        string $name,
        array $resources,
        array $countryIds,
        array $categoryIds,
        ?int $rateLimitPerMinute,
        ?CarbonInterface $expiresAt,
        User $actor,
    ): NewAccessToken {
        $abilities = array_map(static fn (ApiResource $resource): string => $resource->value, $resources);

        $newAccessToken = $client->createToken($name, $abilities, $expiresAt);

        /** @var ApiToken $token */
        $token = $newAccessToken->accessToken;

        if ($rateLimitPerMinute !== null) {
            $token->forceFill(['rate_limit_per_minute' => $rateLimitPerMinute])->save();
        }

        $this->writeScopeRows($token, $countryIds, $categoryIds);

        $this->journal->record(
            event: 'api_token_issued',
            target: $client,
            newValues: [
                'token_id' => $token->id,
                'name' => $name,
                'resources' => $abilities,
                'countries' => $countryIds,
                'categories' => $categoryIds,
                'rate_limit_per_minute' => $rateLimitPerMinute,
                'expires_at' => $expiresAt?->toIso8601String(),
            ],
            actor: $actor,
            tags: ['api'],
        );

        return $newAccessToken;
    }

    /**
     * Revokes $token immediately. Idempotent: revoking an already-revoked
     * token journals nothing further and leaves the original revocation
     * record untouched.
     */
    public function revoke(ApiToken $token, User $actor): void
    {
        if ($token->isRevoked()) {
            return;
        }

        $token->forceFill([
            'revoked_at' => now(),
            'revoked_by' => $actor->id,
        ])->save();

        $token->loadMissing('tokenable');

        /** @var ApiClient $client */
        $client = $token->tokenable;

        $this->journal->record(
            event: 'api_token_revoked',
            target: $client,
            newValues: ['token_id' => $token->id, 'name' => $token->name],
            actor: $actor,
            tags: ['api'],
        );
    }

    /**
     * Replaces $token's resource abilities and country/category scope rows
     * wholesale, journalling the before and after state.
     *
     * @param  list<ApiResource>  $resources
     * @param  list<int>  $countryIds
     * @param  list<int>  $categoryIds
     */
    public function changeScope(
        ApiToken $token,
        array $resources,
        array $countryIds,
        array $categoryIds,
        User $actor,
    ): void {
        $oldValues = [
            'resources' => $token->abilities,
            'countries' => $token->scopes()->where('scope_kind', 'country')->pluck('scope_reference_id')->all(),
            'categories' => $token->scopes()->where('scope_kind', 'category')->pluck('scope_reference_id')->all(),
        ];

        $abilities = array_map(static fn (ApiResource $resource): string => $resource->value, $resources);
        $token->forceFill(['abilities' => $abilities])->save();

        $token->scopes()->delete();
        $this->writeScopeRows($token, $countryIds, $categoryIds);

        $token->loadMissing('tokenable');

        /** @var ApiClient $client */
        $client = $token->tokenable;

        $this->journal->record(
            event: 'api_token_scope_changed',
            target: $client,
            oldValues: $oldValues,
            newValues: ['resources' => $abilities, 'countries' => $countryIds, 'categories' => $categoryIds],
            actor: $actor,
            tags: ['api'],
        );
    }

    /**
     * Resolves $token's country/category narrowing into the same
     * {@see ScopeConstraint} shape a scoped admin query consumes, ready for
     * the read endpoints to apply against `CatalogQueryService` and its
     * siblings. No scope row on an axis means that axis is unrestricted.
     */
    public function scopeConstraint(ApiToken $token): ScopeConstraint
    {
        $rows = $token->scopes()->get(['scope_kind', 'scope_reference_id']);

        if ($rows->isEmpty()) {
            return ScopeConstraint::unrestricted();
        }

        $countryIds = array_values(array_unique(
            $rows->where('scope_kind', 'country')->pluck('scope_reference_id')->all(),
        ));

        $categoryIds = array_values(array_unique(
            $rows->where('scope_kind', 'category')->pluck('scope_reference_id')->all(),
        ));

        return new ScopeConstraint(
            isUnrestricted: false,
            countryIds: $countryIds,
            territoryIds: [],
            categoryIds: $categoryIds,
        );
    }

    /**
     * @param  list<int>  $countryIds
     * @param  list<int>  $categoryIds
     */
    private function writeScopeRows(ApiToken $token, array $countryIds, array $categoryIds): void
    {
        $rows = [
            ...array_map(
                static fn (int $id): array => ['scope_kind' => 'country', 'scope_reference_id' => $id],
                array_values(array_unique($countryIds)),
            ),
            ...array_map(
                static fn (int $id): array => ['scope_kind' => 'category', 'scope_reference_id' => $id],
                array_values(array_unique($categoryIds)),
            ),
        ];

        if ($rows !== []) {
            $token->scopes()->createMany($rows);
        }
    }
}
