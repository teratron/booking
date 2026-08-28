<?php

declare(strict_types=1);

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\ArticleTag;
use App\Models\Module;
use App\Models\ModuleSetting;
use App\Models\SeoMetadataTemplate;
use App\Models\TerritoryLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Registry Models — custom casts, translations, and non-default relations
|--------------------------------------------------------------------------
|
| These six models are otherwise plain Eloquent registries; this file
| covers only what each one adds beyond inherited boilerplate:
| ModuleSetting's `set_at` datetime cast, SeoMetadataTemplate's
| locale-to-code relation keys, ArticleCategory/TerritoryLevel's
| Translatable proxy plus `is_active` cast, and ArticleTag's `is_active`
| cast plus its shared-pivot `articles()` relation. ApiTokenScope is
| deliberately absent: it carries only `$guarded` and a default-keyed
| `belongsTo`, with no cast, scope, accessor, or business-rule method of
| its own to exercise.
|
*/

function registryModelsLanguage(string $code, bool $isPrimary): void
{
    DB::table('languages')->insert([
        'code' => $code, 'short_label' => strtoupper($code),
        'is_active' => true, 'is_primary' => $isPrimary,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function registryModelsCountry(): int
{
    registryModelsLanguage('en', true);

    return DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => DB::table('languages')->where('code', 'en')->value('id'),
        'is_active' => true, 'display_order' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function registryModelsModule(string $key): Module
{
    return Module::query()->create([
        'key' => $key,
        'default_state' => 'enabled',
        'scopable_levels' => ['portal', 'country'],
        'is_active' => true,
    ]);
}

function registryModelsArticle(?int $categoryId = null): Article
{
    $author = User::factory()->create();

    return Article::query()->create([
        'author_id' => $author->id,
        'article_category_id' => $categoryId,
        'status' => 'draft',
    ]);
}

// --- ModuleSetting -----------------------------------------------------

it('casts ModuleSetting set_at to a Carbon instance rather than a raw string', function (): void {
    $module = registryModelsModule('online_booking');

    $setting = ModuleSetting::query()->create([
        'module_id' => $module->id,
        'scope_level' => 'portal',
        'scope_reference_id' => null,
        'state' => 'enabled',
        'set_at' => '2026-01-15 10:00:00',
    ]);

    $fresh = ModuleSetting::query()->findOrFail($setting->id);

    expect($fresh->set_at)->toBeInstanceOf(Carbon::class)
        ->and($fresh->set_at->format('Y-m-d H:i:s'))->toBe('2026-01-15 10:00:00');
});

it('resolves the ModuleSetting module relation', function (): void {
    $module = registryModelsModule('payment_gateway');

    $setting = ModuleSetting::query()->create([
        'module_id' => $module->id,
        'scope_level' => 'country',
        'scope_reference_id' => 7,
        'state' => 'disabled',
        'set_at' => now(),
    ]);

    expect($setting->module)->not->toBeNull()
        ->and($setting->module->id)->toBe($module->id)
        ->and($setting->module->key)->toBe('payment_gateway');
});

// --- SeoMetadataTemplate -------------------------------------------------

it('resolves the SeoMetadataTemplate language relation through the locale-to-code key pair', function (): void {
    registryModelsLanguage('en', true);
    registryModelsLanguage('ru', false);

    $template = SeoMetadataTemplate::query()->create([
        'entity_type' => 'object',
        'locale' => 'ru',
        'field' => 'seo_title',
        'template' => '{name} in {territory}',
    ]);

    // A default belongsTo would guess `language_id` as the foreign key and
    // `id` as the owner key — neither column exists here, so a broken
    // key pair would return null instead of the 'ru' language row.
    expect($template->language)->not->toBeNull()
        ->and($template->language->code)->toBe('ru')
        ->and($template->language->is_primary)->toBeFalse();
});

// --- ArticleCategory -----------------------------------------------------

it('casts ArticleCategory is_active to boolean and proxies name through the active translation', function (): void {
    registryModelsLanguage('en', true);
    registryModelsLanguage('ru', false);

    $category = ArticleCategory::query()->create([
        'slug' => 'travel-tips',
        'is_active' => 0,
        'display_order' => 1,
    ]);
    $category->translateOrNew('en')->name = 'Travel Tips';
    $category->translateOrNew('ru')->name = 'Советы туристам';
    $category->save();

    $fresh = ArticleCategory::query()->findOrFail($category->id);

    expect($fresh->is_active)->toBeFalse()
        ->and($fresh->is_active)->toBeBool()
        ->and($fresh->name)->toBe('Travel Tips')
        ->and($fresh->translate('ru')->name)->toBe('Советы туристам');
});

it('resolves the ArticleCategory articles relation to only its own category\'s articles', function (): void {
    $category = ArticleCategory::query()->create([
        'slug' => 'destinations',
        'is_active' => true,
        'display_order' => 0,
    ]);
    $otherCategory = ArticleCategory::query()->create([
        'slug' => 'events',
        'is_active' => true,
        'display_order' => 0,
    ]);

    $ownArticle = registryModelsArticle($category->id);
    registryModelsArticle($otherCategory->id);

    $ids = $category->articles()->pluck('id')->all();

    expect($ids)->toBe([$ownArticle->id]);
});

// --- ArticleTag ------------------------------------------------------------

it('casts ArticleTag is_active to boolean', function (): void {
    $tag = ArticleTag::query()->create([
        'slug' => 'budget',
        'name' => 'Budget',
        'is_active' => 1,
        'display_order' => 0,
    ]);

    expect($tag->is_active)->toBeTrue()
        ->and($tag->is_active)->toBeBool();
});

it('resolves ArticleTag articles through the article_tag pivot with its explicit, non-default keys', function (): void {
    $tag = ArticleTag::query()->create([
        'slug' => 'family-friendly',
        'name' => 'Family Friendly',
        'is_active' => true,
        'display_order' => 0,
    ]);
    $article = registryModelsArticle();

    // Laravel's own convention would guess the pivot table as
    // `article_article_tag`; the relation instead points at the
    // `article_tag` table Article::tags() also uses, with the foreign and
    // related pivot keys swapped from what the class-name guess would
    // produce. Attaching would fail against the wrong table/keys.
    $tag->articles()->attach($article->id);

    expect($tag->fresh()->articles->pluck('id')->all())->toBe([$article->id]);
});

// --- TerritoryLevel --------------------------------------------------------

it('casts TerritoryLevel is_active to boolean and proxies singular/plural names through the active translation', function (): void {
    $countryId = registryModelsCountry();

    $level = TerritoryLevel::query()->create([
        'country_id' => $countryId,
        'depth_rank' => 1,
        'is_active' => 0,
    ]);
    $level->translateOrNew('en')->singular_name = 'Region';
    $level->translateOrNew('en')->plural_name = 'Regions';
    $level->save();

    $fresh = TerritoryLevel::query()->findOrFail($level->id);

    expect($fresh->is_active)->toBeFalse()
        ->and($fresh->is_active)->toBeBool()
        ->and($fresh->singular_name)->toBe('Region')
        ->and($fresh->plural_name)->toBe('Regions');
});

it('resolves the TerritoryLevel country relation', function (): void {
    $countryId = registryModelsCountry();

    $level = TerritoryLevel::query()->create([
        'country_id' => $countryId,
        'depth_rank' => 2,
        'is_active' => true,
    ]);

    expect($level->country)->not->toBeNull()
        ->and($level->country->id)->toBe($countryId);
});
