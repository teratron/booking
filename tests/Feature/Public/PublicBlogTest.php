<?php

declare(strict_types=1);

use App\Models\Article;
use App\Models\Object_;
use App\Models\Territory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Public Blog
|--------------------------------------------------------------------------
|
| A paginated listing of published articles and each article's own detail
| page — full body, author, category, tags, and its many-to-many related
| objects and territories, each a real link. A draft or future-scheduled
| article's own detail route is as unreachable as its listing row.
|
*/

/** @return array{languageId: int, countryId: int, territoryId: int, objectTypeId: int} */
function publicBlogRegistry(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryId = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true, 'display_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('territory_translations')->insert([
        'territory_id' => $territoryId, 'country_id' => $countryId, 'locale' => 'en', 'name' => 'Mentioned Territory', 'slug' => 'mentioned-territory',
        'full_slug_path' => 'mentioned-territory',
        'needs_review' => false, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $objectTypeId = DB::table('object_types')->insertGetId([
        'key' => 'hotel', 'is_active' => true, 'has_rooms' => true, 'has_availability_status' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_type_translations')->insert([
        'object_type_id' => $objectTypeId, 'locale' => 'en', 'name' => 'Hotel', 'slug' => 'hotel',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('modules')->insert([
        'key' => 'reviews', 'default_state' => 'enabled',
        'scopable_levels' => json_encode(['portal', 'country', 'category', 'object']),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('languageId', 'countryId', 'territoryId', 'objectTypeId');
}

/** @param  array<string, mixed>  $overrides */
function publicBlogMakeArticle(array $overrides = []): int
{
    return DB::table('articles')->insertGetId(array_merge([
        'author_id' => User::factory()->create()->id,
        'status' => 'published',
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));
}

/** @param  array<string, mixed>  $overrides */
function publicBlogGiveTranslation(int $articleId, string $title, array $overrides = []): void
{
    DB::table('article_translations')->insert(array_merge([
        'article_id' => $articleId, 'locale' => 'en', 'title' => $title,
        'body' => 'The full body of the article.', 'slug' => Str::slug($title).'-'.$articleId,
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));
}

function publicBlogMakeCategory(string $name): int
{
    $categoryId = DB::table('article_categories')->insertGetId([
        'slug' => Str::slug($name), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('article_category_translations')->insert([
        'article_category_id' => $categoryId, 'locale' => 'en', 'name' => $name,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $categoryId;
}

function publicBlogMakeTag(string $name): int
{
    return DB::table('article_tags')->insertGetId([
        'slug' => Str::slug($name),
        'name' => $name, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('renders published articles only in the listing, each carrying its category and tags', function (): void {
    publicBlogRegistry();
    $categoryId = publicBlogMakeCategory('Travel Tips');
    $tagId = publicBlogMakeTag('Family');

    $publishedId = publicBlogMakeArticle(['article_category_id' => $categoryId, 'publish_at' => now()->subDay()]);
    publicBlogGiveTranslation($publishedId, 'Published Article', ['summary' => 'A short summary.']);
    DB::table('article_tag')->insert(['article_id' => $publishedId, 'article_tag_id' => $tagId]);

    $draftId = publicBlogMakeArticle(['status' => 'draft']);
    publicBlogGiveTranslation($draftId, 'Draft Article');

    $response = $this->get(route('public.blog.index', ['lang' => 'en']));

    $response->assertOk()
        ->assertSee('Published Article')
        ->assertSee('A short summary.')
        ->assertSee('Travel Tips')
        ->assertSee('Family')
        ->assertDontSee('Draft Article');
});

it('renders an article\'s cover image on its listing card, not only on its own detail page', function (): void {
    Storage::fake('public');
    publicBlogRegistry();

    $articleId = publicBlogMakeArticle();
    publicBlogGiveTranslation($articleId, 'Article With A Cover');
    Article::query()->findOrFail($articleId)
        ->addMedia(UploadedFile::fake()->image('cover.jpg', 800, 450))
        ->toMediaCollection('cover_image');

    $this->get(route('public.blog.index', ['lang' => 'en']))
        ->assertOk()
        ->assertSee('Article With A Cover')
        ->assertSee('<img', false);
});

it('renders an empty state when no article has been published yet', function (): void {
    publicBlogRegistry();

    $this->get(route('public.blog.index', ['lang' => 'en']))
        ->assertOk()
        ->assertSee(__('public.blog.empty'));
});

it('renders full body, author, category, tags, and real links to related objects and territories on the article page', function (): void {
    $fixture = publicBlogRegistry();
    $categoryId = publicBlogMakeCategory('Travel Tips');
    $tagId = publicBlogMakeTag('Family');
    $author = User::factory()->create(['name' => 'Portal Editor']);

    $objectId = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(), 'owner_id' => User::factory()->create()->id,
        'object_type_id' => $fixture['objectTypeId'], 'territory_id' => $fixture['territoryId'],
        'country_id' => $fixture['countryId'], 'status' => 'published', 'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => 'Mentioned Hotel',
        'slug' => 'mentioned-hotel-'.$objectId, 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $articleId = publicBlogMakeArticle([
        'article_category_id' => $categoryId, 'author_id' => $author->id, 'publish_at' => now()->subDay(),
    ]);
    publicBlogGiveTranslation($articleId, 'Full Article', ['body' => 'The complete article body content.']);
    DB::table('article_tag')->insert(['article_id' => $articleId, 'article_tag_id' => $tagId]);
    DB::table('article_object')->insert(['article_id' => $articleId, 'object_id' => $objectId]);
    DB::table('article_territory')->insert(['article_id' => $articleId, 'territory_id' => $fixture['territoryId']]);

    /** @var Object_ $object */
    $object = Object_::query()->findOrFail($objectId);
    /** @var Territory $territory */
    $territory = Territory::query()->findOrFail($fixture['territoryId']);

    $response = $this->get(route('public.blog.show', ['lang' => 'en', 'slug' => "full-article-{$articleId}"]));

    $objectHref = publicObjectUrl($object);
    $territoryHref = publicTerritoryUrl($territory);

    $response->assertOk()
        ->assertSee('Full Article')
        ->assertSee('The complete article body content.')
        ->assertSee('Portal Editor')
        ->assertSee('Travel Tips')
        ->assertSee('Family')
        ->assertSee('Mentioned Hotel')
        ->assertSee($objectHref, false)
        ->assertSee('Mentioned Territory')
        ->assertSee($territoryHref, false);
});

it('makes a draft or future-scheduled article unreachable publicly', function (): void {
    publicBlogRegistry();

    $draftId = publicBlogMakeArticle(['status' => 'draft']);
    publicBlogGiveTranslation($draftId, 'Draft Article');

    $futureId = publicBlogMakeArticle(['publish_at' => now()->addDay()]);
    publicBlogGiveTranslation($futureId, 'Future Article');

    $this->get(route('public.blog.show', ['lang' => 'en', 'slug' => "draft-article-{$draftId}"]))->assertNotFound();
    $this->get(route('public.blog.show', ['lang' => 'en', 'slug' => "future-article-{$futureId}"]))->assertNotFound();
});

it('404s a non-existent article slug, never a 500 from an invalid bigint comparison', function (): void {
    // F-03: this route used to bind on the raw primary key — any
    // non-integer segment reached Postgres as a `bigint` comparison and
    // threw SQLSTATE[22P02] before Laravel could turn a miss into a 404.
    publicBlogRegistry();

    $this->get('/en/blog/not-a-real-thing')->assertNotFound();
});

it('redirects a legacy numeric-id article link permanently to its slug URL', function (): void {
    publicBlogRegistry();
    $articleId = publicBlogMakeArticle(['publish_at' => now()->subDay()]);
    publicBlogGiveTranslation($articleId, 'Redirect Article');

    $this->get("/en/blog/{$articleId}")
        ->assertRedirect(route('public.blog.show', ['lang' => 'en', 'slug' => "redirect-article-{$articleId}"]))
        ->assertStatus(301);
});

it('404s a numeric-id link for a draft article rather than redirecting to it', function (): void {
    publicBlogRegistry();
    $draftId = publicBlogMakeArticle(['status' => 'draft']);
    publicBlogGiveTranslation($draftId, 'Hidden Article');

    $this->get("/en/blog/{$draftId}")->assertNotFound();
});
