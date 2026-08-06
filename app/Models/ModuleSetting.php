<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * One module's state at one rung of the scoping ladder. The reference target
 * varies by level — a country, a category, an owner, or an object — so it is
 * held as a soft reference rather than a foreign key.
 *
 * @property string $scope_level
 * @property ?int $scope_reference_id
 * @property string $state
 */
final class ModuleSetting extends Model
{
    protected $guarded = ['id'];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return ['set_at' => 'datetime'];
    }

    /** @return BelongsTo<Module, $this> */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
