<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TranslatableDefaults;
use App\Policies\PlacementTierPolicy;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * One of the four structural placement ranks. `rank` (1 = highest) is the
 * primary term of the catalog ordering contract — a lower-ranked object must
 * never appear above a higher-ranked one. Only the rank's presentation
 * (label, badge colour and text, icon, active flag) is administrator-data;
 * the rank count and order are fixed at four by the schema and the
 * application layer, never by a row an administrator could add or remove.
 *
 * @property int $id
 * @property int $rank
 * @property string $border_colour
 * @property string $badge_colour
 * @property ?string $badge_icon
 * @property bool $is_active
 * @property-read ?string $label virtual, proxied through the active translation
 * @property-read ?string $badge_text virtual, proxied through the active translation
 */
#[UsePolicy(PlacementTierPolicy::class)]
class PlacementTier extends Model implements TranslatableContract
{
    use Translatable;
    use TranslatableDefaults;

    public ?string $translationModel = null;

    /** @var list<string> */
    public array $translatedAttributes = ['label', 'badge_text'];

    protected $guarded = ['id'];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'rank' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<PlacementPackage, $this> */
    public function packages(): HasMany
    {
        return $this->hasMany(PlacementPackage::class);
    }
}
