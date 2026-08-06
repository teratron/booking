<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TranslatableDefaults;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * A capability an administrator may switch off without the code that
 * implements it being removed.
 *
 * @property int $id
 * @property string $key
 * @property string $default_state
 * @property bool $is_active
 */
final class Module extends Model implements TranslatableContract
{
    use Translatable;
    use TranslatableDefaults;

    public ?string $translationModel = ModuleTranslation::class;

    /** @var list<string> */
    public array $translatedAttributes = ['display_name', 'description'];

    protected $guarded = ['id'];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'scopable_levels' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<ModuleSetting, $this> */
    public function settings(): HasMany
    {
        return $this->hasMany(ModuleSetting::class);
    }

    /** @return BelongsToMany<Module, $this> */
    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'module_dependencies', 'module_id', 'depends_on_module_id');
    }

    /** @return BelongsToMany<Module, $this> */
    public function conflicts(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'module_conflicts', 'module_id', 'conflicts_with_module_id');
    }
}
