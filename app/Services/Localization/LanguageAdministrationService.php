<?php

declare(strict_types=1);

namespace App\Services\Localization;

use App\Exceptions\LanguageAdministrationRefusedException;
use App\Models\Language;
use App\Models\User;
use App\Services\Audit\AuditJournal;
use Illuminate\Support\Facades\DB;

/**
 * Administers the language registry: which languages are active, which one
 * is primary, and the order the switcher renders them in. Every write here
 * is what makes activating a language a data operation instead of a
 * deployment — the registry itself is the only moving part.
 */
final class LanguageAdministrationService
{
    public function __construct(private readonly AuditJournal $journal) {}

    public function activate(Language $language, User $actor): void
    {
        $language->forceFill(['is_active' => true])->save();

        $this->journal->record('language_activated', $language, [], ['is_active' => true], $actor, ['language']);
    }

    /**
     * @throws LanguageAdministrationRefusedException when the language is the current primary,
     *                                                or is the last remaining active language
     */
    public function deactivate(Language $language, User $actor): void
    {
        if ($language->is_primary) {
            throw LanguageAdministrationRefusedException::cannotDeactivatePrimary($language->id);
        }

        $activeCount = Language::query()->where('is_active', true)->count();

        if ($activeCount <= 1 && $language->is_active) {
            throw LanguageAdministrationRefusedException::cannotDeactivateLastActive($language->id);
        }

        $language->forceFill(['is_active' => false])->save();

        $this->journal->record('language_deactivated', $language, [], ['is_active' => false], $actor, ['language']);
    }

    /**
     * @throws LanguageAdministrationRefusedException when the language is not active
     */
    public function makePrimary(Language $language, User $actor): void
    {
        if (! $language->is_active) {
            throw LanguageAdministrationRefusedException::mustBeActiveToBecomePrimary($language->id);
        }

        DB::transaction(function () use ($language): void {
            Language::query()->where('is_primary', true)->update(['is_primary' => false]);
            $language->forceFill(['is_primary' => true])->save();
        });

        $this->journal->record('language_primary_changed', $language, [], ['is_primary' => true], $actor, ['language']);
    }

    /**
     * Journals a reorder already persisted by Filament's own reorderable-table
     * handling (`Filament\Tables\Concerns\CanReorderRecords::reorderTable()`),
     * which writes `display_order` directly — this call adds the audit trail only.
     *
     * @param  list<int|string>  $orderedIds  language ids in the display order the switcher now uses
     */
    public function journalReorder(array $orderedIds, User $actor): void
    {
        $first = Language::query()->find($orderedIds[0] ?? null);

        if ($first === null) {
            return;
        }

        $this->journal->record('language_reordered', $first, [], ['order' => $orderedIds], $actor, ['language']);
    }
}
