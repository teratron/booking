<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TranslatableDefaults;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * A delivery channel — launch set is `inbox` and `email`; Telegram and
 * Viber are seeded inactive, ready for an adapter without a schema change.
 * Adding a channel is this registry entry plus a class implementing
 * `NotificationChannelAdapter`, never a change to `Notification` itself.
 *
 * @property int $id
 * @property string $key
 * @property bool $is_active
 * @property-read ?string $name virtual, proxied through the active translation
 */
class NotificationChannel extends Model implements TranslatableContract
{
    use Translatable;
    use TranslatableDefaults;

    public ?string $translationModel = null;

    /** @var list<string> */
    public array $translatedAttributes = ['name'];

    protected $guarded = ['id'];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return HasMany<NotificationTemplate, $this> */
    public function templates(): HasMany
    {
        return $this->hasMany(NotificationTemplate::class);
    }

    /** @return HasMany<NotificationDispatch, $this> */
    public function dispatches(): HasMany
    {
        return $this->hasMany(NotificationDispatch::class);
    }
}
