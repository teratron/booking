<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * An administrator's decision that one specific catalog filter value is
 * indexable — the allowlist the portal's own indexation policy service
 * consults for the single-filter case. A filter combination of two or
 * more is never promotable, so this table never represents more than one
 * active filter at a time.
 */
class CatalogFilterPromotion extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
