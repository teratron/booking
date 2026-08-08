<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * The current placement grant — one row per object, unique on `object_id`.
 * The append-only record of every prior grant lives in
 * `placement_histories`, a separate table this model does not touch.
 *
 * @property int $id
 * @property int $object_id
 * @property int $placement_package_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property ?int $pinned_position
 * @property int $internal_priority
 * @property ?string $expiry_action_override
 */
class ObjectPlacement extends Model
{
    protected $guarded = ['id'];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    /** @return BelongsTo<Object_, $this> */
    public function object(): BelongsTo
    {
        return $this->belongsTo(Object_::class);
    }
}
