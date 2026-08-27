<?php

declare(strict_types=1);

namespace App\Models;

use App\Policies\RoleScopePolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Override;
use Spatie\Permission\Models\Role;

/**
 * One row of `role_scopes` — the scope a role grant is bounded to, and (once
 * withdrawn) who withdrew it and when. `spatie/laravel-permission` has no
 * concept of a bounded grant; this is this project's own addition, read here
 * so the staff-administration screen can show it. The authorization
 * service that grants and revokes these rows writes them through the query
 * builder rather than this model — its insert-or-update logic keeps a
 * re-granted row's identity stable across a revoke/re-grant cycle in a way
 * a bare Eloquent `create()` would not.
 *
 * @property int $user_id
 * @property int $role_id
 * @property string $scope_kind
 * @property ?int $scope_reference_id
 * @property int $granted_by
 * @property Carbon $granted_at
 * @property ?int $revoked_by
 * @property ?Carbon $revoked_at
 */
#[UsePolicy(RoleScopePolicy::class)]
final class RoleScope extends Model
{
    protected $guarded = ['id'];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** @return BelongsTo<User, $this> */
    public function granter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }
}
