<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Override;

/**
 * One period-aware rate. `priceable` is polymorphic — a price may attach
 * directly to an {@see Object_} (e.g. a restaurant's average cheque) or to a
 * {@see Room}, since the same rate shape applies to a whole object as
 * readily as it does to one room category. `type` is a free-form label
 * ("standard", "weekend", "high season") an owner sets to tell concurrent
 * rates for the same `calculation_unit` apart; `valid_from`/`valid_until`
 * are an optional seasonal window — a rate with neither set simply always
 * applies.
 */
final class Price extends Model
{
    protected $guarded = ['id'];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function priceable(): MorphTo
    {
        return $this->morphTo();
    }
}
