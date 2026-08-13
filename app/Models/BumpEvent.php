<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * One scoped reorder — moving an object to the first free position within
 * its own tier, for one territory or object-type list. `scope` is
 * polymorphic (`Territory` or `ObjectType`) rather than two nullable
 * foreign keys, because exactly one of them is ever the scope on a given
 * row, and the catalog's ordering reads the most recent bump *for the scope
 * being rendered* — a single global timestamp cannot express that.
 *
 * @property int $id
 * @property int $object_id
 * @property int $placement_package_id
 * @property string $scope_type
 * @property int $scope_id
 * @property Carbon $occurred_at
 * @property string $type one of: free, paid, automatic, owner, administrator
 * @property ?int $actor_id
 * @property ?int $previous_position
 * @property ?int $new_position
 * @property ?string $price
 * @property ?string $comment
 */
class BumpEvent extends Model
{
    protected $guarded = ['id'];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'price' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Object_, $this> */
    public function object(): BelongsTo
    {
        return $this->belongsTo(Object_::class);
    }

    /** @return BelongsTo<PlacementPackage, $this> */
    public function package(): BelongsTo
    {
        return $this->belongsTo(PlacementPackage::class, 'placement_package_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return MorphTo<Model, $this> */
    public function scope(): MorphTo
    {
        return $this->morphTo();
    }
}
