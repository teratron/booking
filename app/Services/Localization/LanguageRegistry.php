<?php

declare(strict_types=1);

namespace App\Services\Localization;

use App\Models\Language;

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
     * No `Schema::hasTable()` guard here, unlike the boot-time callers in
     * `AppServiceProvider` — this only runs once a translation is actually
     * being resolved mid-request, by which point the schema is guaranteed
     * to exist; a request rendering translated output is not one running
     * concurrently with the migration that creates the table.
     */
    public function primaryLocale(): string
    {
        if ($this->primaryLocale !== null) {
            return $this->primaryLocale;
        }

        return $this->primaryLocale = Language::query()->where('is_primary', true)->value('code')
            ?? config('app.fallback_locale');
    }
}
