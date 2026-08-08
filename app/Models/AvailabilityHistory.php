<?php

declare(strict_types=1);

namespace App\Models;

use App\Policies\AvailabilityHistoryPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * One append-only row per availability transition — never updated or
 * deleted through application code, and privilege-restricted at the
 * database level so the record survives even a compromised application
 * role. `from_status` is nullable only for the very first row an object
 * ever receives, before which no prior status existed to name.
 *
 * @property int $id
 * @property int $object_id
 * @property ?string $from_status
 * @property string $to_status
 * @property Carbon $changed_at
 * @property ?int $changed_by
 * @property string $source
 */
#[UsePolicy(AvailabilityHistoryPolicy::class)]
class AvailabilityHistory extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Object_, $this> */
    public function object(): BelongsTo
    {
        return $this->belongsTo(Object_::class);
    }

    /** @return BelongsTo<User, $this> */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
