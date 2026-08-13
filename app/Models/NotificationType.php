<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * One of the ten code-known notification types the specification names —
 * administrators do not invent new types through the panel, unlike every
 * other registry in this project, so there is no translations table here:
 * the back-office label is a Filament translation key, and the actual
 * per-language, per-channel message text lives in `NotificationTemplate`.
 *
 * @property int $id
 * @property string $key
 * @property string $class transactional or optional
 * @property array<int, string> $default_channels channel keys, e.g. ['email', 'inbox']
 * @property bool $is_active
 */
class NotificationType extends Model
{
    protected $guarded = ['id'];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'default_channels' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function isTransactional(): bool
    {
        return $this->class === 'transactional';
    }

    /** @return HasMany<NotificationTemplate, $this> */
    public function templates(): HasMany
    {
        return $this->hasMany(NotificationTemplate::class);
    }

    /** @return HasMany<Notification, $this> */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /** @return HasMany<NotificationPreference, $this> */
    public function preferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }
}
