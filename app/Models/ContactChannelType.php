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
 * A reachable contact method an object may list — phone, WhatsApp, Viber,
 * Telegram, email, website, a social network — modelled as a registry row
 * rather than a fixed column or an enum, so adding a channel ("other social
 * networks") is a data operation, never a schema change.
 *
 * `link_template` is what makes an owner's raw value (a phone number, a
 * handle) into an actionable deep link without the owner ever entering a
 * URI themselves — a dedicated service under `App\Services\Contact` is the
 * one place that substitution happens, kept out of this model to preserve
 * the project's own "thin model" boundary.
 *
 * @property string $key
 * @property ?string $link_template
 */
final class ContactChannelType extends Model implements TranslatableContract
{
    use Translatable;
    use TranslatableDefaults;

    public ?string $translationModel = ContactChannelTypeTranslation::class;

    /** @var list<string> */
    public array $translatedAttributes = ['display_name'];

    protected $guarded = ['id'];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return HasMany<ContactChannel, $this> */
    public function contactChannels(): HasMany
    {
        return $this->hasMany(ContactChannel::class);
    }
}
