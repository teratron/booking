<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One country or category narrowing on an {@see ApiToken}. The absence of any
 * row for an axis means that axis is unrestricted for the token — there is no
 * `none`-kind sentinel row the way `role_scopes` needs one, since a token's
 * two axes are independent lists rather than alternative single-kind grants.
 *
 * @property int $id
 * @property int $personal_access_token_id
 * @property string $scope_kind
 * @property int $scope_reference_id
 */
class ApiTokenScope extends Model
{
    protected $guarded = ['id'];

    /** @return BelongsTo<ApiToken, $this> */
    public function token(): BelongsTo
    {
        return $this->belongsTo(ApiToken::class, 'personal_access_token_id');
    }
}
