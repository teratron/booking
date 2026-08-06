<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Excludes any row whose latest moderation decision is not "approved" from
 * an unqualified query. Pending, rejected, and revision-requested content
 * must never surface on a public-facing page — applied globally rather
 * than per-query, because a single forgotten predicate silently
 * republishes unmoderated content and that failure has no visible symptom.
 */
/**
 * @implements Scope<Model>
 */
final class ModerationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where($model->qualifyColumn('moderation_status'), 'approved');
    }
}
