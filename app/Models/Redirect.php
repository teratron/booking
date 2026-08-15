<?php

declare(strict_types=1);

namespace App\Models;

use App\Policies\RedirectPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * A permanent (language, from_path) → to_path mapping. Written exclusively
 * by the portal's own redirect-registration service — never directly by a
 * Filament page or a slug-change call site — so every redirect the portal
 * ever creates goes through the same permanence and no-silent-reuse rules.
 *
 * @property string $locale
 * @property string $from_path
 * @property string $to_path
 * @property bool $is_active
 */
#[UsePolicy(RedirectPolicy::class)]
class Redirect extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Language, $this>
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'locale', 'code');
    }
}
