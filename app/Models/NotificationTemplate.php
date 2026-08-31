<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One (type, locale, channel) message variant — administrator-editable
 * seed content, kept as an audit trail: editing a template must never
 * retroactively change what a recipient was already shown, so
 * `NotificationDispatchService` renders and stores the message at creation
 * time rather than resolving this row at delivery time.
 *
 * @property int $id
 * @property int $notification_type_id
 * @property string $locale
 * @property int $notification_channel_id
 * @property ?string $subject nullable — an inbox entry has no subject line
 * @property string $body
 */
class NotificationTemplate extends Model
{
    protected $guarded = ['id'];

    /** @return BelongsTo<NotificationType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(NotificationType::class, 'notification_type_id');
    }

    /** @return BelongsTo<NotificationChannel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(NotificationChannel::class, 'notification_channel_id');
    }
}
