<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One locale's name and description for a {@see Room} — the same
 * per-language translation-table shape every other translatable entity in
 * this codebase follows.
 *
 * @property string $name
 * @property ?string $description
 */
final class RoomTranslation extends Model
{
    public $timestamps = true;

    protected $guarded = ['id'];
}
