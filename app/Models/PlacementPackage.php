<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TranslatableDefaults;
use App\Policies\PlacementPackagePolicy;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * An administrator-defined, purchasable grant of one tier for a period.
 * Binds a tier to a price and a validity window; the object-type scope is
 * optional because some packages sell across every category and others are
 * specific to one (hotels, restaurants, sanatoria, holiday bases).
 *
 * Per the specification's package-parity invariant, a package buys position,
 * card decoration, badge, and bump eligibility — nothing else. Bump
 * availability and frequency (`bump_allowed`, `bump_interval_hours`,
 * `free_bumps_per_period`, `paid_bump_price`) are the single
 * package-varying capability beyond tier; every other entitlement (photo
 * count, contact count, description length) is identical across every
 * package and lives nowhere on this model.
 *
 * @property int $id
 * @property int $placement_tier_id
 * @property ?int $object_type_id
 * @property string $price
 * @property string $currency
 * @property int $validity_days
 * @property bool $bump_allowed
 * @property ?int $bump_interval_hours
 * @property ?int $free_bumps_per_period
 * @property ?string $paid_bump_price
 * @property bool $is_active
 * @property int $display_order
 * @property-read ?string $name virtual, proxied through the active translation
 */
#[UsePolicy(PlacementPackagePolicy::class)]
class PlacementPackage extends Model implements TranslatableContract
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
        return [
            'price' => 'decimal:2',
            'validity_days' => 'integer',
            'bump_allowed' => 'boolean',
            'bump_interval_hours' => 'integer',
            'free_bumps_per_period' => 'integer',
            'paid_bump_price' => 'decimal:2',
            'is_active' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    /** @return BelongsTo<PlacementTier, $this> */
    public function tier(): BelongsTo
    {
        return $this->belongsTo(PlacementTier::class, 'placement_tier_id');
    }

    /** @return BelongsTo<ObjectType, $this> */
    public function objectType(): BelongsTo
    {
        return $this->belongsTo(ObjectType::class);
    }

    /** @return HasMany<ObjectPlacement, $this> */
    public function currentPlacements(): HasMany
    {
        return $this->hasMany(ObjectPlacement::class);
    }

    /** @return HasMany<PlacementHistory, $this> */
    public function history(): HasMany
    {
        return $this->hasMany(PlacementHistory::class);
    }
}
