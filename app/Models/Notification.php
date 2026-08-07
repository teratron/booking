<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * One stored notification — what an account was told, independently of
 * whether or how any channel delivered it. `title` and `body` are rendered
 * once, at creation, in the recipient's own locale, and stored as plain
 * text; a message template edited later must never retroactively change
 * what a recipient was already shown.
 *
 * Channel delivery (email, SMS, push) and templated rendering are a
 * separate, later concern layered on top of this table — this model only
 * covers the notification record itself.
 *
 * @property int $recipient_id
 * @property int $notification_type_id
 * @property ?string $related_type
 * @property ?int $related_id
 * @property string $title
 * @property string $body
 * @property string $locale
 * @property ?Carbon $read_at
 * @property ?int $created_by
 */
final class Notification extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'recipient_id',
        'notification_type_id',
        'related_type',
        'related_id',
        'title',
        'body',
        'locale',
        'created_by',
    ];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return MorphTo<Model, $this> */
    public function related(): MorphTo
    {
        return $this->morphTo();
    }
}
