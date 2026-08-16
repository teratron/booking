<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An administrator-defined template rendering a title or description from
 * entity data when no explicit `seo_title`/`seo_description` is set — the
 * middle rung of the portal's own SEO metadata resolution ladder.
 * Placeholders: `{name}` (the entity's own display name in the template's
 * language) and `{territory}` (its associated territory's name, blank when
 * the entity carries none).
 *
 * `entity_type` and `field` stay plain strings rather than enum casts: this
 * model has no Filament resource of its own yet to benefit from the cast
 * (an administration screen is future work), and the only current reader —
 * the portal's own SEO metadata resolution service — already works
 * entirely in terms of the entity-type and metadata-field enums at its own
 * boundary.
 */
class SeoMetadataTemplate extends Model
{
    protected $guarded = ['id'];
}
