<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $display_name
 * @property ?string $description
 */
final class ModuleTranslation extends Model
{
    public $timestamps = true;

    protected $guarded = ['id'];
}
