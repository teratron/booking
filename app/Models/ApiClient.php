<?php

declare(strict_types=1);

namespace App\Models;

use App\Policies\ApiClientPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\HasApiTokens;
use Override;

/**
 * The outward-facing REST API's token owner — distinct from portal users,
 * staff, and object owners, who authenticate by session. `HasApiTokens`
 * gives it `tokens()`, `createToken()`, and `currentAccessToken()` against
 * {@see ApiToken}, the project's own Sanctum personal-access-token model.
 *
 * @property int $id
 * @property string $name
 * @property ?string $contact
 * @property bool $is_active
 */
#[UsePolicy(ApiClientPolicy::class)]
final class ApiClient extends Model
{
    use HasApiTokens;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
