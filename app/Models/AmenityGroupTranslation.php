<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $name
 */
final class AmenityGroupTranslation extends Model
{
    public $timestamps = true;

    protected $guarded = ['id'];
}
