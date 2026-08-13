<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TranslatableDefaults;
use App\Policies\PromotionLabelPolicy;
use App\Support\Advertising\CardPosition;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * Administrator-defined card decoration, grantable to an object independently
 * of its placement package (VIP, Recommended, Popular, Verified, …) — an
 * unlimited, back-office-configured set, never a code enum.
 *
 * Which objects currently carry a label, and for what window, lives in the
 * `object_promotions` grant table, not here — this row only holds a label's
 * own card presentation and translated text.
 *
 * @property int $id
 * @property string $border_colour
 * @property string $text_colour
 * @property string $background_colour
 * @property ?string $icon
 * @property CardPosition $position_on_card
 * @property bool $is_active
 * @property-read ?string $text virtual, proxied through the active translation
 */
#[UsePolicy(PromotionLabelPolicy::class)]
class PromotionLabel extends Model implements TranslatableContract
{
    use Translatable;
    use TranslatableDefaults;

    public ?string $translationModel = null;

    /** @var list<string> */
    public array $translatedAttributes = ['text'];

    protected $guarded = ['id'];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'position_on_card' => CardPosition::class,
            'is_active' => 'boolean',
        ];
    }
}
