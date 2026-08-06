<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Scopes\ModerationScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Registers the moderation global scope on a model and provides its escape
 * hatch, mirroring the withTrashed()/withoutGlobalScope() pair Laravel's own
 * SoftDeletes trait offers for soft deletion — a back-office review queue is
 * expected to see pending and rejected rows; a public-facing query is not.
 */
trait FiltersModeration
{
    public static function bootFiltersModeration(): void
    {
        static::addGlobalScope(new ModerationScope);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeWithUnmoderated(Builder $query): Builder
    {
        return $query->withoutGlobalScope(ModerationScope::class);
    }
}
