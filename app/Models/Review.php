<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\ModerationScope;
use App\Policies\ReviewPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Override;

/**
 * One visitor's rating and write-up of an object — posted either by a
 * registered visitor ({@see self::author()}) or, when `author_id` is null,
 * as a named guest carried entirely in `author_name`. How a review is first
 * submitted is a separate concern this model does not govern; what it does
 * govern is everything that happens to a review afterward from the owner
 * cabinet side: the object's owner may post exactly one reply
 * (`owner_reply`/`owner_replied_at`) and may flag the review for
 * administrator attention (`reported_at`/`reported_by`/`report_reason`).
 * Every other column — the rating, the body, the author, `status`, and
 * `hidden_reason` — is administrator-only territory; see {@see ReviewPolicy}
 * for why an owner (including the object's own owner) can never reach those
 * through this model.
 *
 * @property ?int $author_id
 * @property ?string $author_name
 * @property ?string $owner_reply
 * @property ?Carbon $owner_replied_at
 * @property ?Carbon $reported_at
 * @property ?int $reported_by
 * @property ?string $report_reason
 * @property ?string $hidden_reason
 */
#[UsePolicy(ReviewPolicy::class)]
final class Review extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'owner_replied_at' => 'datetime',
            'reported_at' => 'datetime',
        ];
    }

    /**
     * The tenant-scoping relation Filament's cabinet tenancy looks for on
     * every tenant-scoped child resource's model. `ModerationScope` is
     * stripped for the same reason {@see Room::object()} and
     * {@see ObjectPhoto::object()} strip it: a draft or not-yet-approved
     * object is exactly the state a review can already exist against, and
     * leaving the scope on would make this relation — and every policy check
     * built on it — silently fail closed for the object's own owner rather
     * than a genuine outsider.
     *
     * @return BelongsTo<Object_, $this>
     */
    public function object(): BelongsTo
    {
        return $this->belongsTo(Object_::class, 'object_id')->withoutGlobalScope(ModerationScope::class);
    }

    /**
     * The registered visitor who posted this review, when it was not posted
     * as a named guest.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * The user who reported this review, when it has been reported.
     *
     * @return BelongsTo<User, $this>
     */
    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /**
     * The administrator who soft-deleted this review, when it has been
     * removed.
     *
     * @return BelongsTo<User, $this>
     */
    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
