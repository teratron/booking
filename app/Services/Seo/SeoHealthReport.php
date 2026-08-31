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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * The six health checks that make a catalog this size maintainable: at
 * portal scale nobody finds a duplicate slug by browsing. Every check reads
 * the same translation tables {@see MetadataResolver} resolves against,
 * as plain query-builder scans rather than hydrated Eloquent models, since
 * a single report run touches every SEO-bearing row in the portal.
 */
final class SeoHealthReport
{
    private const int TITLE_MAX_LENGTH = 60;

    /**
     * One row per (entity kind, table, translation table, FK column, own
     * display-name column) — the closed set of entity kinds this portal
     * carries SEO data for, minus territories/objects/etc. that are
     * not translatable content in their own right.
     *
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

    public function __construct(private readonly TranslationCompletenessReport $translations) {}

    /** @return array<string, int> warning key => count, in the specification's own order */
    public function summary(): array
    {
        $warnings = $this->warnings();

        return array_map(static fn (array $rows): int => count($rows), $warnings);
    }

    /**
     * @return array<string, list<array{entityType: string, locale: string, name: string}>>
     */
    public function warnings(): array
    {
        return [
            'missing_title' => $this->missingField('seo_title'),
            'missing_description' => $this->missingField('seo_description'),
            'over_length_title' => $this->overLengthTitle(),
            'excluded_from_indexing' => $this->excludedFromIndexing(),
            'duplicate_address' => $this->duplicateAddress(),
            'missing_translation' => $this->missingTranslation(),
        ];
    }

    /** @return list<array{entityType: string, locale: string, name: string}> */
    private function missingField(string $column): array
    {
        $rows = [];

        foreach (self::ENTITIES as $entityType => $spec) {
            foreach ($this->rows($spec, [$column, $spec['nameColumn'].' as name']) as $row) {
                $value = trim((string) $row->{$column});

                if ($value === '') {
                    $rows[] = ['entityType' => $entityType, 'locale' => (string) $row->locale, 'name' => (string) $row->name];
                }
            }
        }

        return $rows;
    }

    /** @return list<array{entityType: string, locale: string, name: string}> */
    private function overLengthTitle(): array
    {
        $rows = [];

        foreach (self::ENTITIES as $entityType => $spec) {
            foreach ($this->rows($spec, ['seo_title', $spec['nameColumn'].' as name']) as $row) {
                if (mb_strlen((string) $row->seo_title) > self::TITLE_MAX_LENGTH) {
                    $rows[] = ['entityType' => $entityType, 'locale' => (string) $row->locale, 'name' => (string) $row->name];
                }
            }
        }

        return $rows;
    }

    /** @return list<array{entityType: string, locale: string, name: string}> */
    private function excludedFromIndexing(): array
    {
        $rows = [];

        foreach (self::ENTITIES as $entityType => $spec) {
            foreach ($this->rows($spec, ['seo_indexable', $spec['nameColumn'].' as name']) as $row) {
                if ($row->seo_indexable !== null && ! (bool) $row->seo_indexable) {
                    $rows[] = ['entityType' => $entityType, 'locale' => (string) $row->locale, 'name' => (string) $row->name];
                }
            }
        }

        return $rows;
    }

    /**
     * A duplicate `seo_canonical_url` across any two rows, regardless of
     * entity kind — a free-text administrator override with no uniqueness
     * constraint of its own, unlike the slug columns the database already
     * enforces.
     *
     * @return list<array{entityType: string, locale: string, name: string}>
     */
    private function duplicateAddress(): array
    {
        $byUrl = [];

        foreach (self::ENTITIES as $entityType => $spec) {
            foreach ($this->rows($spec, ['seo_canonical_url', $spec['nameColumn'].' as name']) as $row) {
                $url = trim((string) $row->seo_canonical_url);

                if ($url === '') {
                    continue;
                }

                $byUrl[$url][] = ['entityType' => $entityType, 'locale' => (string) $row->locale, 'name' => (string) $row->name];
            }
        }

        $rows = [];

        foreach ($byUrl as $matches) {
            if (count($matches) > 1) {
                array_push($rows, ...$matches);
            }
        }

        return $rows;
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

    /**
     * @param  array{table: string, translations: string, fk: string, nameColumn: string}  $spec
     * @param  list<string>  $select
     * @return Collection<int, stdClass>
     */
    private function rows(array $spec, array $select): Collection
    {
        return DB::table("{$spec['translations']} as tr")
            ->join("{$spec['table']} as base", 'base.id', '=', "tr.{$spec['fk']}")
            ->select(['tr.locale', ...array_map(static fn (string $column): string => "tr.{$column}", $select)])
            ->get();
    }
}
