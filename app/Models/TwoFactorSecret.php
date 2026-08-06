<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * A staff account's authenticator-app secret and its single-use recovery
 * codes. Both columns are encrypted at rest: the secret is a bearer credential
 * — anyone holding it can generate valid codes indefinitely — and a recovery
 * code is a password that bypasses the second factor entirely.
 *
 * @property ?string $secret
 * @property ?array<string> $recovery_codes
 * @property-read User $user
 */
#[Fillable(['user_id', 'secret', 'recovery_codes', 'confirmed_at'])]
#[Hidden(['secret', 'recovery_codes'])]
final class TwoFactorSecret extends Model
{
    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'recovery_codes' => 'encrypted:array',
            'confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
