<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * @property string $display_name
 * @property ?string $description
 */
final class ModuleTranslation extends Model
{
    public $timestamps = true;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return ['needs_review' => 'boolean', 'published_at' => 'datetime'];
    }
}
