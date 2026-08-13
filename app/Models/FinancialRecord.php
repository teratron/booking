<?php

declare(strict_types=1);

namespace App\Models;

use App\Policies\FinancialRecordPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * The portal's unified financial ledger — a record, not a payment system,
 * per §5.5: it records that money changed hands elsewhere. Covers both
 * revenue streams through exactly one of `object_id`/`banner_id` (a
 * database constraint enforces this, mirroring the favorites table's
 * exactly-one-identity pattern): a placement package sold to an object
 * owner, or advertising sold to an advertiser who may own no object at all.
 *
 * @property int $id
 * @property ?int $object_id
 * @property ?int $banner_id
 * @property string $service
 * @property ?int $placement_package_id
 * @property string $amount
 * @property string $currency
 * @property ?Carbon $paid_at
 * @property ?Carbon $valid_from
 * @property ?Carbon $valid_until
 * @property ?string $payment_method
 * @property ?string $document_number
 * @property ?string $comment
 * @property ?int $responsible_staff_id
 * @property string $status one of: awaiting_payment, paid, partially_paid, overdue, cancelled, granted_free
 */
#[UsePolicy(FinancialRecordPolicy::class)]
class FinancialRecord extends Model
{
    protected $guarded = ['id'];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'valid_from' => 'date',
            'valid_until' => 'date',
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
    public function responsibleStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_staff_id');
    }
}
