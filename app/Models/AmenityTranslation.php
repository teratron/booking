<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $name
 */
final class AmenityTranslation extends Model
{
    public $timestamps = true;

    protected $guarded = ['id'];
}
