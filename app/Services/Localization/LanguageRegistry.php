<?php

declare(strict_types=1);

namespace App\Services\Localization;

use App\Models\Language;
use Throwable;

/**
 * Resolves the current primary language — the runtime fallback target every
 * translation lookup degrades to, instead of the static
 * `config('app.fallback_locale')` value. Which language is primary is
 * administrator-editable data, so the fallback target must be looked up, not
 * compiled in. Cached per instance since it is consulted on every translated
 * string in a request; this class is bound as a container singleton so that
 * cache spans the whole request.
 */
final class LanguageRegistry
{
    private ?string $primaryLocale = null;

    /**
     * Injects the primary locale found by a query someone else already ran
     * — `AppServiceProvider` reads the whole `languages` table once per
     * request to sync astrotomic's locale registry, and passes the primary
     * code straight through here so `primaryLocale()` never needs a second,
     * near-identical query of its own.
     */
    public function seed(string $code): void
    {
        $this->primaryLocale = $code;
    }

    /**
     * The same "not ready yet" case `AppServiceProvider::hasReachableTable()`
     * guards its own boot-time callers against — a translation can be
     * resolved before the first migration has ever run (`composer install`'s
     * `package:discover` hook, or a test process whose earliest tests reach
     * a translation lookup before any migration has executed), not only
     * concurrently with one. Degrades to the static fallback locale rather
     * than letting the query exception surface as an unrelated crash.
     */
    public function primaryLocale(): string
    {
        if ($this->primaryLocale !== null) {
            return $this->primaryLocale;
        }

        try {
            $primary = Language::query()->where('is_primary', true)->value('code');
        } catch (Throwable) {
            $primary = null;
        }

        return $this->primaryLocale = $primary ?? config('app.fallback_locale');
    }
}
