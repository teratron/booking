<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Analytics\StatEventKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * One aggregated rollup row — the unbounded-history, bounded-cost tier of
 * the analytics model. The scheduled rollup job is the only writer;
 * `count` is the number of raw `stat_events` rows that job's rollup for
 * `date` folded into this tuple.
 *
 * @property int $id
 * @property Carbon $date
 * @property string $subject_type
 * @property int $subject_id
 * @property string $kind one of {@see StatEventKind}'s values
 * @property ?int $contact_channel_type_id
 * @property ?int $territory_id
 * @property ?int $country_id
 * @property ?string $locale
 * @property int $count
 */
final class StatDaily extends Model
{
    protected $table = 'stat_dailies';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return ['date' => 'date', 'count' => 'integer'];
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Territory, $this> */
    public function territory(): BelongsTo
    {
        return $this->belongsTo(Territory::class);
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
