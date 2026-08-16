<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Language;
use App\Services\Seo\MetadataResolver;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the middle rung of {@see MetadataResolver}'s
 * resolution ladder — a starting title/description template per entity kind
 * for every active language, so a freshly-seeded portal ships real
 * templated copy rather than falling through to the bare name-derivation
 * rung on every page. An administrator edits these afterwards; this seeder
 * only avoids an empty starting table.
 *
 * Wording is declared only for the languages this project actually launches
 * with (English, Russian) — an activated language this dictionary has no
 * entry for is not a gap: the resolver's own derivation rung already
 * guarantees non-empty metadata, and an administrator authors that
 * language's templates once it goes live.
 */
final class SeoMetadataTemplateSeeder extends Seeder
{
    use WithoutModelEvents;

    /** @var array<string, array<string, array<string, string>>> locale => entity_type => field => template */
    private const array WORDING = [
        'en' => [
            'territory' => ['title' => '{name} Travel Guide', 'description' => 'Explore {name}: places to stay, things to do, and travel tips.'],
            'object_type' => ['title' => '{name} in {territory}', 'description' => 'Browse {name} in {territory} — verified listings with direct owner contact.'],
            'object' => ['title' => '{name} — {territory}', 'description' => 'See photos, amenities, and contact details for {name} in {territory}.'],
            'promotion' => ['title' => '{name}', 'description' => 'Current promotion: {name}.'],
            'news_item' => ['title' => '{name}', 'description' => 'Portal news: {name}.'],
            'article' => ['title' => '{name}', 'description' => 'Read: {name}.'],
        ],
        'ru' => [
            'territory' => ['title' => '{name}: путеводитель', 'description' => 'Изучите {name}: где остановиться, чем заняться и полезные советы.'],
            'object_type' => ['title' => '{name} в {territory}', 'description' => 'Подборка «{name}» в {territory} — проверенные объекты с прямым контактом владельца.'],
            'object' => ['title' => '{name} — {territory}', 'description' => 'Фото, удобства и контакты объекта {name} в {territory}.'],
            'promotion' => ['title' => '{name}', 'description' => 'Текущая акция: {name}.'],
            'news_item' => ['title' => '{name}', 'description' => 'Новости портала: {name}.'],
            'article' => ['title' => '{name}', 'description' => 'Читать: {name}.'],
        ],
    ];

    public function run(): void
    {
        $now = now();
        $rows = [];

        foreach (Language::query()->where('is_active', true)->pluck('code') as $locale) {
            foreach (self::WORDING[$locale] ?? [] as $entityType => $fields) {
                foreach ($fields as $field => $template) {
                    $rows[] = [
                        'entity_type' => $entityType,
                        'locale' => $locale,
                        'field' => $field,
                        'template' => $template,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        if ($rows === []) {
            return;
        }

        DB::table('seo_metadata_templates')->insert($rows);
    }
}
