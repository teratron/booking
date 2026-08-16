<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One locale's title and body for an {@see ErrorPage} — the same
 * per-language translation-table shape every other translatable entity in
 * this codebase follows.
 *
 * @property string $title
 * @property string $body
 */
final class ErrorPageTranslation extends Model
{
    public $timestamps = true;

    protected $guarded = ['id'];
}
