<?php

declare(strict_types=1);

namespace App\Models;

use App\Policies\ApiTokenPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;
use Override;

/**
 * This project's Sanctum personal-access-token model, registered via
 * `Sanctum::usePersonalAccessTokenModel()` in `AppServiceProvider`. Extends
 * rather than replaces the vendor model so token creation, hashing, and
 * ability checks keep Sanctum's own implementation — this adds only what the
 * token model needs beyond it: immediate revocation and the country/category
 * scope {@see ApiTokenScope} narrows.
 *
 * @property int $id
 * @property string $name
 * @property array<int, string> $abilities
 * @property ?Carbon $expires_at
 * @property ?Carbon $revoked_at
 * @property ?int $revoked_by
 * @property ?int $rate_limit_per_minute
 * @property ?Carbon $last_used_at
 */
#[UsePolicy(ApiTokenPolicy::class)]
class ApiToken extends PersonalAccessToken
{
    protected $table = 'personal_access_tokens';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'abilities' => 'json',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * A revoked token resolves to nothing on its very next request — this is
     * the sole gate Sanctum's guard consults, so no separate check anywhere
     * else can be forgotten.
     *
     * @param  string  $token
     */
    #[Override]
    public static function findToken($token): ?static
    {
        /** @var ?static $instance */
        $instance = parent::findToken($token);

        return $instance !== null && $instance->revoked_at !== null ? null : $instance;
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /** @return BelongsTo<User, $this> */
    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    /** @return HasMany<ApiTokenScope, $this> */
    public function scopes(): HasMany
    {
        return $this->hasMany(ApiTokenScope::class, 'personal_access_token_id');
    }
}
