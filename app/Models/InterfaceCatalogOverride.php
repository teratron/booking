<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An administrator's override of a single interface catalog entry. A row
 * here always wins over the shipped value in `resources/lang/{locale}/{group}.php`
 * — the loader that applies this overlay is
 * `App\Services\Localization\DatabaseOverlayLoader`. Deleting the row
 * reverts the key to its file-shipped default; it is never blanked in place.
 *
 * @property string $locale
 * @property string $group
 * @property string $key
 * @property string $value
 */
final class InterfaceCatalogOverride extends Model
{
    protected $guarded = ['id'];

    /**
     * The single cache key `DatabaseOverlayLoader` and
     * `InterfaceCatalogRepository` both use — the whole table is cached as
     * one entry, so a write always invalidates exactly the entry a later
     * read would hit.
     */
    public static function cacheKey(): string
    {
        return 'interface_catalog_overrides:all';
    }
}
