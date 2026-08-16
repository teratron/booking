<?php

declare(strict_types=1);

namespace App\Models;

use App\Policies\SeoMetadataTemplatePolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An administrator-defined template rendering a title or description from
 * entity data when no explicit `seo_title`/`seo_description` is set — the
 * middle rung of the portal's own SEO metadata resolution ladder.
 * Placeholders: `{name}` (the entity's own display name in the template's
 * language) and `{territory}` (its associated territory's name, blank when
 * the entity carries none).
 *
 * `entity_type` and `field` stay plain strings rather than enum casts: the
 * administration screen's own form already constrains both to the closed
 * vocabulary via `Select` options, and the portal's own SEO metadata
 * resolution service already works entirely in terms of the entity-type
 * and metadata-field enums at its own boundary.
 */
#[UsePolicy(SeoMetadataTemplatePolicy::class)]
class SeoMetadataTemplate extends Model
{
    protected $guarded = ['id'];

    /**
     * @return BelongsTo<Language, $this>
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'locale', 'code');
    }
}
