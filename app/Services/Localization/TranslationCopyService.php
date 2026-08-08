<?php

declare(strict_types=1);

namespace App\Services\Localization;

use App\Models\User;
use App\Services\Audit\AuditJournal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Copies the primary-language value of a translatable entity into a target
 * language as an editing starting point, and independently publishes one
 * language version of an entity without touching the others.
 *
 * A copy is deliberately marked `needs_review` — the completeness report
 * this feeds must never count copied-but-unreviewed text as translated,
 * or the first bulk copy would report full coverage of nothing anyone
 * actually translated.
 */
final class TranslationCopyService
{
    public function __construct(
        private readonly LanguageRegistry $languages,
        private readonly AuditJournal $journal,
    ) {}

    /**
     * @param  list<string>  $translatedAttributes
     */
    public function copyFromPrimary(Model $entity, string $targetLocale, array $translatedAttributes, User $actor): void
    {
        /** @phpstan-ignore-next-line method.notFound (provided by astrotomic/laravel-translatable) */
        $source = $entity->translateOrNew($this->languages->primaryLocale());
        /** @phpstan-ignore-next-line method.notFound (provided by astrotomic/laravel-translatable) */
        $target = $entity->translateOrNew($targetLocale);

        foreach ($translatedAttributes as $attribute) {
            $target->{$attribute} = $source->{$attribute};
        }

        $target->needs_review = true;
        $target->published_at = null;

        $entity->save();

        $this->journal->record(
            'translation_copied_from_primary',
            $entity,
            [],
            ['locale' => $targetLocale],
            $actor,
            ['localization'],
        );
    }

    /**
     * @throws ModelNotFoundException when no translation exists yet for that locale
     */
    public function publish(Model $entity, string $locale, User $actor): void
    {
        /** @phpstan-ignore-next-line method.notFound (provided by astrotomic/laravel-translatable) */
        $translation = $entity->translateOrFail($locale);

        $translation->forceFill(['published_at' => now(), 'needs_review' => false])->save();

        $this->journal->record('translation_published', $entity, [], ['locale' => $locale], $actor, ['localization']);
    }
}
