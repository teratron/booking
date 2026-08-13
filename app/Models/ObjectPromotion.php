<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * The grant of one promotional label to one object for a window of time —
 * card decoration only. `weight` feeds the catalog ordering's own promotion
 * term, a within-tier tie-breaker; granting or revoking a label must never
 * move an object into a different placement tier or past a higher-ranked
 * object, a guarantee this model itself carries no logic to enforce — the
 * ordering query in `PlacementOrderingService` is the single place tier
 * precedence is decided.
 *
 * Distinct from the unrelated `promotions` / `promotion_translations` tables
 * (an owner-authored, article-like content entity): this table's own name
 * happens to share a word with that entity in the source requirements, but
 * the two are structurally and semantically unconnected.
 *
 * @property int $id
 * @property int $object_id
 * @property int $promotion_label_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property ?int $granted_by
 * @property int $weight
 */
class ObjectPromotion extends Model
{
    protected $guarded = ['id'];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'weight' => 'integer',
        ];
    }

    /** @return BelongsTo<Object_, $this> */
    public function object(): BelongsTo
    {
        return $this->belongsTo(Object_::class);
    }

    /** @return BelongsTo<PromotionLabel, $this> */
    public function label(): BelongsTo
    {
        return $this->belongsTo(PromotionLabel::class, 'promotion_label_id');
    }

    /** @return BelongsTo<User, $this> */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
