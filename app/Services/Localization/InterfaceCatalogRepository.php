<?php

declare(strict_types=1);

namespace App\Services\Localization;

use App\Models\InterfaceCatalogOverride;
use App\Models\Language;
use App\Models\User;
use App\Services\Audit\AuditJournal;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Reads and writes administrator overrides of interface catalog entries.
 * An override always wins over the shipped file value; clearing a field back
 * to blank deletes the row and reverts the key to its file default rather
 * than storing an empty override — the DatabaseOverlayLoader that renders
 * this catalog on every request never sees "blank" as a distinct value.
 */
final class InterfaceCatalogRepository
{
    public function __construct(
        private readonly InterfaceCatalog $catalog,
        private readonly AuditJournal $journal,
    ) {}

    /**
     * The value the catalog editor should show: the administrator's override
     * if one exists, otherwise the shipped file value for that locale
     * (blank when that locale has no file entry for the key at all).
     */
    public function currentValue(string $locale, string $group, string $key): string
    {
        $override = InterfaceCatalogOverride::query()
            ->where('locale', $locale)
            ->where('group', $group)
            ->where('key', $key)
            ->value('value');

        if ($override !== null) {
            return $override;
        }

        return $this->catalog->shipped($group, $locale)[$key] ?? '';
    }

    /**
     * The same effective value `currentValue()` computes, for every
     * canonical key in one group at once — one override query total instead
     * of one per key, which is what the catalog editor needs to stay inside
     * the panel's query budget when a group has hundreds of keys.
     *
     * @return array<string, string>
     */
    public function currentValues(string $locale, string $group): array
    {
        $shipped = $this->catalog->shipped($group, $locale);

        $overrides = InterfaceCatalogOverride::query()
            ->where('locale', $locale)
            ->where('group', $group)
            ->pluck('value', 'key');

        $values = [];

        foreach (array_keys($this->catalog->canonicalKeys($group)) as $key) {
            $values[$key] = $overrides[$key] ?? $shipped[$key] ?? '';
        }

        return $values;
    }

    /**
     * @param  array<string, string>  $valuesByKey  submitted values, keyed by dot-path catalog key
     */
    public function save(string $locale, string $group, array $valuesByKey, User $actor): void
    {
        DB::transaction(function () use ($locale, $group, $valuesByKey): void {
            foreach ($valuesByKey as $key => $value) {
                if (trim($value) === '') {
                    InterfaceCatalogOverride::query()
                        ->where('locale', $locale)
                        ->where('group', $group)
                        ->where('key', $key)
                        ->delete();

                    continue;
                }

                InterfaceCatalogOverride::query()->updateOrCreate(
                    ['locale' => $locale, 'group' => $group, 'key' => $key],
                    ['value' => $value],
                );
            }
        });

        Cache::forget(InterfaceCatalogOverride::cacheKey());

        $language = Language::query()->where('code', $locale)->first();

        if ($language === null) {
            return;
        }

        $this->journal->record(
            'interface_catalog_edited',
            $language,
            [],
            ['group' => $group, 'keys' => array_keys($valuesByKey)],
            $actor,
            ['localization'],
        );
    }
}
