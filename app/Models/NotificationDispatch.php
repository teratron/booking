<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * One channel's attempt to deliver one `Notification`. Separated from the
 * notification itself so a channel can be retried without duplicating the
 * notification, and so a bounced email is discoverable here rather than
 * silently lost. `suppressed` is a real, recorded status — an optional-
 * class notification the recipient disabled is not skipped silently, it is
 * recorded as suppressed, which is what lets an administrator answer "why
 * didn't this owner know".
 *
 * @property int $id
 * @property int $notification_id
 * @property int $notification_channel_id
 * @property string $status queued, sent, failed, or suppressed
 * @property ?Carbon $attempted_at
 * @property ?string $failure_reason
 * @property ?string $provider_reference
 * @property int $retry_count sweep-level retry attempts already spent, distinct from a job's own queue-level `$tries`
 */
class NotificationDispatch extends Model
{
    protected $guarded = ['id'];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return ['attempted_at' => 'datetime'];
    }

    /** @return BelongsTo<Notification, $this> */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }

    /** @return BelongsTo<NotificationChannel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(NotificationChannel::class, 'notification_channel_id');
    }
}
