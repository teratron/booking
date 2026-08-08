<?php

declare(strict_types=1);

namespace App\Models;

use App\Policies\ModerationRequestPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Override;

/**
 * A change awaiting a decision. The published record it concerns is never
 * touched while this row is `pending` — `previous_data` and `proposed_data`
 * are independent snapshots taken at submission, not references to the live
 * row, so a moderator reviews the comparison that was actually submitted
 * even if the live record changes underneath in the meantime.
 *
 * `country_id` and `owner_id` are denormalized off the polymorphic target at
 * submission time, for scope narrowing and queue filtering — a target type
 * with no owner or country concept simply leaves them null.
 *
 * @property string $section
 * @property array<string, mixed>|null $previous_data
 * @property array<string, mixed> $proposed_data
 * @property string $decision
 * @property ?int $country_id
 * @property ?int $owner_id
 */
#[UsePolicy(ModerationRequestPolicy::class)]
final class ModerationRequest extends Model
{
    protected $guarded = ['id'];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'previous_data' => 'array',
            'proposed_data' => 'array',
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function assignedModerator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_moderator_id');
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
