<?php

declare(strict_types=1);

namespace App\Models;

use App\Policies\PlacementHistoryPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * The append-only record of every placement grant an object ever held — a
 * package change never deletes the previous row, it closes it and a new one
 * begins. Doubles as the financial ledger: the payment fields (`amount`,
 * `paid_at`, `document_number`, `status`, …) live on this same row rather
 * than a sibling table, since a
 * placement grant and its payment are the same commercial event viewed from
 * two angles — the schema already carries this shape from its first
 * migration.
 *
 * @property int $id
 * @property int $object_id
 * @property int $placement_package_id
 * @property Carbon $starts_at
 * @property ?Carbon $ends_at
 * @property string $amount
 * @property string $currency
 * @property ?Carbon $paid_at
 * @property ?string $payment_method
 * @property ?string $document_number
 * @property string $status one of: awaiting_payment, paid, partially_paid, overdue, cancelled, granted_free
 * @property ?int $granted_by
 * @property ?string $comment
 */
#[UsePolicy(PlacementHistoryPolicy::class)]
class PlacementHistory extends Model
{
    protected $guarded = ['id'];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Object_, $this> */
    public function object(): BelongsTo
    {
        return $this->belongsTo(Object_::class);
    }

    /** @return BelongsTo<PlacementPackage, $this> */
    public function package(): BelongsTo
    {
        return $this->belongsTo(PlacementPackage::class, 'placement_package_id');
    }

    /** @return BelongsTo<User, $this> */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /**
     * A row is still open — the current grant, not a closed prior one —
     * when it has no end date yet. `ObjectPlacement` holds the single
     * source of truth for "what is current"; this is a read-time
     * convenience for querying history rows the same way.
     */
    public function isOpen(): bool
    {
        return $this->ends_at === null;
    }
}
