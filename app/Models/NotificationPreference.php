<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * One recipient's on/off switch for one notification type. Absence of a row
 * means "not yet configured", not "disabled" — every type defaults to
 * enabled until the recipient explicitly turns it off. Only optional-class
 * types are ever suppressible this way; a transactional type's dispatch
 * never consults this table, per §3's "recipients control optional classes
 * only".
 *
 * @property int $user_id
 * @property int $notification_type_id
 * @property bool $is_enabled
 */
class NotificationPreference extends Model
{
    protected $guarded = ['id'];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<NotificationType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(NotificationType::class, 'notification_type_id');
    }
}
