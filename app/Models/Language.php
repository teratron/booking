<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * A language the portal may render in. Every field lives directly on this
 * row — unlike most registries in this schema, a language has no separate
 * translation table, since a language's own name is not itself in need of
 * translation for this project's purposes (its short label is language-
 * neutral, e.g. "EN", "RU").
 *
 * @property string $code
 * @property string $short_label
 * @property bool $is_active
 * @property bool $is_primary
 */
final class Language extends Model
{
    protected $guarded = ['id'];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_primary' => 'boolean',
        ];
    }
}
