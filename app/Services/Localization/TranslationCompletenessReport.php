<?php

declare(strict_types=1);

namespace App\Services\Localization;

use App\Models\Concerns\FiltersModeration;
use App\Models\Scopes\ModerationScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Completeness per translatable entity class and per active language — the
 * number the SEO "translation missing" warning reads. A row counts as
 * translated only when it exists and is not `needs_review`; a copied,
 * unreviewed placeholder counts as still missing.
 */
final class TranslationCompletenessReport
{
    public function __construct(private readonly TranslatableEntityRegistry $registry) {}

    /**
     * @param  list<string>  $activeLocales
     * @return list<array{entity: TranslatableEntity, locale: string, total: int, translated: int, needsReview: int, missing: int}>
     */
    public function summary(array $activeLocales): array
    {
        $rows = [];

        foreach ($this->registry->entries() as $entity) {
            $total = DB::table($entity->table)->count();

            $counts = DB::table($entity->translationTable)
                ->select('locale', 'needs_review', DB::raw('count(*) as aggregate'))
                ->groupBy('locale', 'needs_review')
                ->get()
                ->groupBy('locale');

            foreach ($activeLocales as $locale) {
                $forLocale = $counts->get($locale, collect());
                $translatedRow = $forLocale->firstWhere('needs_review', false);
                $needsReviewRow = $forLocale->firstWhere('needs_review', true);
                $translated = $translatedRow === null ? 0 : (int) $translatedRow->aggregate;
                $needsReview = $needsReviewRow === null ? 0 : (int) $needsReviewRow->aggregate;

                $rows[] = [
                    'entity' => $entity,
                    'locale' => $locale,
                    'total' => $total,
                    'translated' => $translated,
                    'needsReview' => $needsReview,
                    'missing' => max(0, $total - $translated - $needsReview),
                ];
            }
        }

        return $rows;
    }

    /**
     * Entities of one class missing — or only holding an unreviewed copy
     * of — a translation in the given locale. A real Eloquent query, so
     * moderation-pending entities are not silently dropped: the staff
     * panel is the one place that must see them.
     *
     * Eager-loads `translations`: the only caller (the translation report
     * page) calls astrotomic's `hasTranslation()`/`translate()` on every
     * row it renders — per-row column state and the publish action's own
     * visibility both go through them — which lazy-load that relation
     * without this, throwing under `Model::shouldBeStrict()`.
     *
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    public function missingQuery(TranslatableEntity $entity, string $locale): Builder
    {
        $modelClass = $entity->modelClass;

        /** @var Builder<covariant \Illuminate\Database\Eloquent\Model> $query */
        $query = $modelClass::query()->with('translations');

        if (in_array(FiltersModeration::class, class_uses_recursive($modelClass), true)) {
            $query->withoutGlobalScope(ModerationScope::class);
        }

        return $query->whereDoesntHave('translations', function (Builder $translations) use ($locale): void {
            $translations->where('locale', $locale)->where('needs_review', false);
        });
    }
}
