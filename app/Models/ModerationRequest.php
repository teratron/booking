<?php

declare(strict_types=1);

namespace App\Models;

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
 * @property string $section
 * @property array<string, mixed>|null $previous_data
 * @property array<string, mixed> $proposed_data
 * @property string $decision
 */
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
}
