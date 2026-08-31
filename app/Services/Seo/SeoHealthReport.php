<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Models\Article;
use App\Models\Language;
use App\Models\NewsItem;
use App\Models\Object_;
use App\Models\ObjectType;
use App\Models\Promotion;
use App\Models\Territory;
use App\Services\Localization\TranslationCompletenessReport;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * The six health checks that make a catalog this size maintainable: at
 * portal scale nobody finds a duplicate slug by browsing.
 *
 * Every check has two shapes. {@see self::summary()} is a pure SQL
 * `count(*)` per check — accurate, one aggregate query per entity kind,
 * and the only thing the dashboard's headline needs. {@see self::warnings()}
 * returns a **bounded sample** ({@see self::SAMPLE_LIMIT} rows per check per
 * entity kind) for the drill-down table, with the check predicate pushed
 * into the `WHERE` clause rather than filtered in PHP. Enumerating every
 * offending translation row into one response — which an earlier version
 * did, producing a 66 MB page against seeded volume — breaches the
 * response-size budget, not the time budget: it fails the moment the
 * catalog grows, regardless of how fast the query itself runs.
 */
final class SeoHealthReport
{
    private const int TITLE_MAX_LENGTH = 60;

    /** Rows shown per (check, entity kind) in the drill-down — the true total comes from {@see self::summary()}. */
    private const int SAMPLE_LIMIT = 25;

    /**
     * @var array<string, array{table: string, translations: string, fk: string, nameColumn: string}>
     */
    private const array ENTITIES = [
        'territory' => ['table' => 'territories', 'translations' => 'territory_translations', 'fk' => 'territory_id', 'nameColumn' => 'name'],
        'object_type' => ['table' => 'object_types', 'translations' => 'object_type_translations', 'fk' => 'object_type_id', 'nameColumn' => 'name'],
        'object' => ['table' => 'objects', 'translations' => 'object_translations', 'fk' => 'object_id', 'nameColumn' => 'name'],
        'promotion' => ['table' => 'promotions', 'translations' => 'promotion_translations', 'fk' => 'promotion_id', 'nameColumn' => 'title'],
        'news_item' => ['table' => 'news_items', 'translations' => 'news_translations', 'fk' => 'news_item_id', 'nameColumn' => 'title'],
        'article' => ['table' => 'articles', 'translations' => 'article_translations', 'fk' => 'article_id', 'nameColumn' => 'title'],
    ];

    /** @var array<string, class-string> entity kind => translatable model class, for the missing-translation check only */
    private const array ENTITY_MODELS = [
        'territory' => Territory::class,
        'object_type' => ObjectType::class,
        'object' => Object_::class,
        'promotion' => Promotion::class,
        'news_item' => NewsItem::class,
        'article' => Article::class,
    ];

    /**
     * The check keys, in the specification's own order, paired with the
     * `WHERE` predicate that defines an offending row. `null` marks a check
     * whose shape is not a single-row predicate (handled separately below).
     *
     * @return array<string, (callable(Builder): Builder)|null>
     */
    private function fieldChecks(): array
    {
        return [
            'missing_title' => static fn (Builder $q): Builder => $q->where(fn (Builder $w) => $w->whereNull('tr.seo_title')->orWhereRaw("trim(tr.seo_title) = ''")),
            'missing_description' => static fn (Builder $q): Builder => $q->where(fn (Builder $w) => $w->whereNull('tr.seo_description')->orWhereRaw("trim(tr.seo_description) = ''")),
            'over_length_title' => static fn (Builder $q): Builder => $q->whereRaw('char_length(tr.seo_title) > ?', [self::TITLE_MAX_LENGTH]),
            'excluded_from_indexing' => static fn (Builder $q): Builder => $q->where('tr.seo_indexable', false),
            'duplicate_address' => null,
            'missing_translation' => null,
        ];
    }

    /** @var ?Collection<string, int> memoised per report run — {@see self::canonicalUrlGroups()} */
    private ?Collection $canonicalGroups = null;

    public function __construct(private readonly TranslationCompletenessReport $translations) {}

    /** @return array<string, int> warning key => count, in the specification's own order */
    public function summary(): array
    {
        $counts = array_fill_keys(['missing_title', 'missing_description', 'over_length_title', 'excluded_from_indexing'], 0);

        // One aggregate pass per translation table — every field check's
        // count comes from the same scan via `count(*) filter (…)`, rather
        // than a separate query (and a separate scan) per check.
        foreach (self::ENTITIES as $spec) {
            $row = DB::table("{$spec['translations']} as tr")->selectRaw(
                "count(*) filter (where tr.seo_title is null or trim(tr.seo_title) = '') as missing_title,
                 count(*) filter (where tr.seo_description is null or trim(tr.seo_description) = '') as missing_description,
                 count(*) filter (where char_length(tr.seo_title) > ?) as over_length_title,
                 count(*) filter (where tr.seo_indexable = false) as excluded_from_indexing",
                [self::TITLE_MAX_LENGTH],
            )->first();

            $counts['missing_title'] += (int) ($row->missing_title ?? 0);
            $counts['missing_description'] += (int) ($row->missing_description ?? 0);
            $counts['over_length_title'] += (int) ($row->over_length_title ?? 0);
            $counts['excluded_from_indexing'] += (int) ($row->excluded_from_indexing ?? 0);
        }

        $counts['duplicate_address'] = $this->duplicateAddressCount();
        $counts['missing_translation'] = count($this->missingTranslation());

        // Restore the specification's declared order.
        return array_replace(array_fill_keys(array_keys($this->fieldChecks()), 0), $counts);
    }

    /**
     * A bounded sample per check — the drill-down, not the full set. Each
     * list is at most `SAMPLE_LIMIT` rows per entity kind; the accurate
     * total is {@see self::summary()}.
     *
     * @return array<string, list<array{entityType: string, locale: string, name: string}>>
     */
    public function warnings(): array
    {
        $out = [];

        foreach ($this->fieldChecks() as $key => $predicate) {
            if ($predicate === null) {
                continue;
            }

            $out[$key] = $this->sample(fn (array $spec): Builder => $predicate($this->baseQuery($spec)));
        }

        $out['duplicate_address'] = $this->duplicateAddressSample();
        $out['missing_translation'] = $this->missingTranslation();

        return array_replace(array_fill_keys(array_keys($this->fieldChecks()), []), $out);
    }

    /**
     * Runs $build (which applies one check's predicate) against every entity
     * kind, capped at `SAMPLE_LIMIT` rows each, and flattens to the
     * dashboard's row shape.
     *
     * @param  callable(array{table: string, translations: string, fk: string, nameColumn: string}): Builder  $build
     * @return list<array{entityType: string, locale: string, name: string}>
     */
    private function sample(callable $build): array
    {
        $rows = [];

        foreach (self::ENTITIES as $entityType => $spec) {
            $found = $build($spec)
                ->select(['tr.locale', DB::raw("tr.{$spec['nameColumn']} as name")])
                ->limit(self::SAMPLE_LIMIT)
                ->get();

            foreach ($found as $row) {
                $rows[] = ['entityType' => $entityType, 'locale' => (string) $row->locale, 'name' => (string) $row->name];
            }
        }

        return $rows;
    }

    /**
     * @param  array{table: string, translations: string, fk: string, nameColumn: string}  $spec
     */
    private function baseQuery(array $spec): Builder
    {
        return DB::table("{$spec['translations']} as tr")
            ->join("{$spec['table']} as base", 'base.id', '=', "tr.{$spec['fk']}");
    }

    /**
     * Count of rows whose `seo_canonical_url` collides with at least one
     * other row's, across every entity kind — computed in one grouped pass
     * per kind, never by materialising the rows.
     */
    private function duplicateAddressCount(): int
    {
        $groups = $this->canonicalUrlGroups();

        return (int) $groups->filter(static fn (int $n): bool => $n > 1)->sum();
    }

    /** @return list<array{entityType: string, locale: string, name: string}> */
    private function duplicateAddressSample(): array
    {
        $duplicated = $this->canonicalUrlGroups()->filter(static fn (int $n): bool => $n > 1)->keys();

        if ($duplicated->isEmpty()) {
            return [];
        }

        return $this->sample(fn (array $spec): Builder => $this->baseQuery($spec)
            ->whereIn('tr.seo_canonical_url', $duplicated->all()));
    }

    /**
     * url => number of rows carrying it, across every entity kind. One
     * grouped query per kind, merged in PHP — the group count, not the
     * rows, so this stays bounded by the number of distinct canonical URLs
     * an administrator has actually typed, not by the catalog size.
     *
     * @return Collection<string, int>
     */
    private function canonicalUrlGroups(): Collection
    {
        if ($this->canonicalGroups !== null) {
            return $this->canonicalGroups;
        }

        $counts = collect();

        foreach (self::ENTITIES as $spec) {
            DB::table("{$spec['translations']} as tr")
                ->whereNotNull('tr.seo_canonical_url')
                ->whereRaw("trim(tr.seo_canonical_url) <> ''")
                ->groupBy('tr.seo_canonical_url')
                ->select('tr.seo_canonical_url as url', DB::raw('count(*) as n'))
                ->get()
                ->each(function (stdClass $row) use ($counts): void {
                    $counts[$row->url] = ($counts[$row->url] ?? 0) + (int) $row->n;
                });
        }

        return $this->canonicalGroups = $counts;
    }

    /** @return list<array{entityType: string, locale: string, name: string}> */
    private function missingTranslation(): array
    {
        $activeLocales = array_values(Language::query()->where('is_active', true)->pluck('code')->all());
        $summary = $this->translations->summary($activeLocales);
        $modelToEntityType = array_flip(self::ENTITY_MODELS);

        $rows = [];

        foreach ($summary as $row) {
            $entityType = $modelToEntityType[$row['entity']->modelClass] ?? null;

            if ($entityType === null || $row['missing'] === 0) {
                continue;
            }

            $rows[] = [
                'entityType' => $entityType,
                'locale' => $row['locale'],
                'name' => trans_choice('panel.seo_health.missing_translation_count', $row['missing'], ['count' => $row['missing']]),
            ];
        }

        return $rows;
    }
}
