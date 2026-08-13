<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TranslatableDefaults;
use App\Policies\BannerSlotPolicy;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * An inventory position banners may be placed into — home, a territory page,
 * a category page, an object page, and so on. A registry, not an enum: an
 * operator adds a new page-kind slot as a data operation, never a
 * deployment.
 *
 * @property-read ?string $name virtual, proxied through the active translation
 * @property list<string> $surfaces page-kind tags this slot may render on
 * @property bool $is_active
 */
#[UsePolicy(BannerSlotPolicy::class)]
class BannerSlot extends Model implements TranslatableContract
{
    use Translatable;
    use TranslatableDefaults;

    public ?string $translationModel = null;

    /** @var list<string> */
    public array $translatedAttributes = ['name'];

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'surfaces' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Banner, $this>
     */
    public function banners(): HasMany
    {
        return $this->hasMany(Banner::class);
    }
}
