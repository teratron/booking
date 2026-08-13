<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Analytics\StatEventKind;
use App\Support\Analytics\TrafficSourceChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * One raw interaction row — the fine-grained tier of the analytics model.
 * The table is partitioned by `occurred_at` at the database level (see its
 * migration); this model needs no special handling for that, since Postgres
 * routes every insert to the right partition transparently.
 *
 * No Eloquent timestamps: `occurred_at` is the table's only temporal column,
 * and it is part of the composite primary key the partitioning scheme
 * requires alongside `id`.
 *
 * @property int $id
 * @property string $kind one of {@see StatEventKind}'s values
 * @property string $subject_type
 * @property int $subject_id
 * @property ?int $contact_channel_type_id
 * @property ?int $territory_id
 * @property ?int $country_id
 * @property ?string $locale
 * @property Carbon $occurred_at
 * @property ?string $dedup_token
 * @property ?string $source_channel one of {@see TrafficSourceChannel}'s values
 * @property ?string $source_domain
 * @property ?string $source_campaign
 */
final class StatEvent extends Model
{
    public $timestamps = false;

    protected $table = 'stat_events';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
