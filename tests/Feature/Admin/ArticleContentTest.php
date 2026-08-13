<?php

declare(strict_types=1);

use App\Exceptions\ArticleScheduleRefusedException;
use App\Filament\Admin\Resources\ArticleCategories\Pages\CreateArticleCategory;
use App\Filament\Admin\Resources\ArticleTags\Pages\CreateArticleTag;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\ArticleTag;
use App\Models\Country;
use App\Models\Object_;
use App\Models\ObjectType;
use App\Models\Territory;
use App\Models\TerritoryLevel;
use App\Models\User;
use App\Services\Content\ArticleLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Article Content Model & Registries
|--------------------------------------------------------------------------
|
| Articles are administrator-authored only — no moderation_status column,
| no FiltersModeration scope. Visibility is decided entirely by Article's
| own published() scope (status = published AND publish_at null-or-past),
| since this model has no moderation gate of its own to lean on.
|
*/

function articleTestGeography(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $country = Country::create([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true,
    ]);
    $country->translateOrNew('en')->name = 'Moldova';
    $country->save();

    $level = TerritoryLevel::create(['country_id' => $country->id, 'depth_rank' => 1]);
    $level->translateOrNew('en')->fill(['singular_name' => 'Region', 'plural_name' => 'Regions']);
    $level->save();

    $territory = Territory::create(['country_id' => $country->id, 'level_id' => $level->id]);
    $territory->translateOrNew('en')->fill(['name' => 'Gagauzia', 'slug' => 'gagauzia']);
    $territory->save();

    $objectType = ObjectType::create(['key' => 'hotel']);
    $objectType->translateOrNew('en')->name = 'Hotel';
    $objectType->save();

    $owner = User::factory()->create();

    $object = Object_::create([
        'ulid' => (string) Str::ulid(),
        'owner_id' => $owner->id,
        'object_type_id' => $objectType->id,
        'territory_id' => $territory->id,
        'country_id' => $country->id,
        'status' => 'published',
        'moderation_status' => 'approved',
    ]);
    $object->translateOrNew('en')->fill(['name' => 'Grand Hotel', 'slug' => 'grand-hotel']);
    $object->save();

    return compact('country', 'territory', 'object');
}

function articleTestLanguage(): void
{
    if (DB::table('languages')->where('code', 'en')->exists()) {
        return;
    }

    DB::table('languages')->insert([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function articleTestCategory(string $slug = 'guides'): ArticleCategory
{
    articleTestLanguage();

    $category = ArticleCategory::create(['slug' => $slug, 'is_active' => true]);
    $category->translateOrNew('en')->name = 'Guides';
    $category->save();

    return $category;
}

function articleContentActor(): User
{
    $permissions = ['admin_panel_access', 'content.view', 'content.create', 'content.edit', 'content.publish', 'content.delete'];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate('content_editor', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    DB::table('role_scopes')->insert([
        'user_id' => $user->id, 'role_id' => $role->id,
        'scope_kind' => 'none', 'scope_reference_id' => null,
        'granted_by' => $user->id, 'granted_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $user->fresh();
}

it('carries an author, a category, per-language fields, related objects/territories/tags, a publish date, and a status', function (): void {
    ['territory' => $territory, 'object' => $object] = articleTestGeography();
    $category = articleTestCategory();
    $author = User::factory()->create();
    $tag = ArticleTag::create(['slug' => 'seasonal', 'name' => 'Seasonal', 'is_active' => true]);

    $article = Article::create([
        'author_id' => $author->id,
        'article_category_id' => $category->id,
        'status' => 'published',
        'publish_at' => now()->subDay(),
    ]);
    $article->translateOrNew('en')->fill([
        'title' => 'Ten Reasons to Visit Gagauzia',
        'summary' => 'A short teaser.',
        'body' => 'The full editorial body.',
        'seo_title' => 'Visit Gagauzia',
        'seo_description' => 'SEO copy.',
        'slug' => 'ten-reasons-to-visit-gagauzia',
    ]);
    $article->save();

    $article->objects()->attach($object->id);
    $article->territories()->attach($territory->id);
    $article->tags()->attach($tag->id);

    $fresh = Article::query()->with(['author', 'category', 'objects', 'territories', 'tags'])->findOrFail($article->id);

    expect($fresh->author->id)->toBe($author->id)
        ->and($fresh->category->id)->toBe($category->id)
        ->and($fresh->translate('en')->title)->toBe('Ten Reasons to Visit Gagauzia')
        ->and($fresh->translate('en')->summary)->toBe('A short teaser.')
        ->and($fresh->translate('en')->body)->toBe('The full editorial body.')
        ->and($fresh->translate('en')->seo_title)->toBe('Visit Gagauzia')
        ->and($fresh->translate('en')->seo_description)->toBe('SEO copy.')
        ->and($fresh->translate('en')->slug)->toBe('ten-reasons-to-visit-gagauzia')
        ->and($fresh->objects->pluck('id')->all())->toBe([$object->id])
        ->and($fresh->territories->pluck('id')->all())->toBe([$territory->id])
        ->and($fresh->tags->pluck('id')->all())->toBe([$tag->id])
        ->and($fresh->status)->toBe('published')
        ->and($fresh->publish_at)->not->toBeNull();
});

it('never returns a draft article from published()', function (): void {
    $category = articleTestCategory();
    $author = User::factory()->create();

    $draft = Article::create([
        'author_id' => $author->id,
        'article_category_id' => $category->id,
        'status' => 'draft',
        'publish_at' => null,
    ]);
    $draft->translateOrNew('en')->fill(['title' => 'Draft Article', 'body' => 'Body.', 'slug' => 'draft-article']);
    $draft->save();

    expect(Article::published()->count())->toBe(0)
        ->and(Article::published()->whereKey($draft->id)->exists())->toBeFalse();
});

it('excludes a scheduled article whose publish_at has not yet arrived, and includes a published one whose date has passed', function (): void {
    $category = articleTestCategory();
    $author = User::factory()->create();

    $scheduled = Article::create([
        'author_id' => $author->id,
        'article_category_id' => $category->id,
        'status' => 'scheduled',
        'publish_at' => now()->addWeek(),
    ]);
    $scheduled->translateOrNew('en')->fill(['title' => 'Future Article', 'body' => 'Body.', 'slug' => 'future-article']);
    $scheduled->save();

    // Guards the embargo case too: status flipped to `published` early
    // while `publish_at` is still in the future must stay hidden — the
    // scope checks the date, not merely the status column.
    $prematurelyFlagged = Article::create([
        'author_id' => $author->id,
        'article_category_id' => $category->id,
        'status' => 'published',
        'publish_at' => now()->addWeek(),
    ]);
    $prematurelyFlagged->translateOrNew('en')->fill(['title' => 'Premature Article', 'body' => 'Body.', 'slug' => 'premature-article']);
    $prematurelyFlagged->save();

    $due = Article::create([
        'author_id' => $author->id,
        'article_category_id' => $category->id,
        'status' => 'published',
        'publish_at' => now()->subHour(),
    ]);
    $due->translateOrNew('en')->fill(['title' => 'Due Article', 'body' => 'Body.', 'slug' => 'due-article']);
    $due->save();

    $visibleIds = Article::published()->pluck('id')->all();

    expect($visibleIds)->toBe([$due->id])
        ->and($visibleIds)->not->toContain($scheduled->id)
        ->and($visibleIds)->not->toContain($prematurelyFlagged->id);
});

it('lets an administrator create a new article category through the admin panel with zero code changes', function (): void {
    $actor = articleContentActor();

    DB::table('languages')->insert([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    Livewire::actingAs($actor)
        ->test(CreateArticleCategory::class)
        ->fillForm([
            'slug' => 'destination-guides',
            'is_active' => true,
            'display_order' => 1,
            'translations' => ['en' => ['name' => 'Destination Guides']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $category = ArticleCategory::query()->where('slug', 'destination-guides')->firstOrFail();

    expect($category->name)->toBe('Destination Guides');
});

it('lets an administrator create a new article tag through the admin panel with zero code changes', function (): void {
    $actor = articleContentActor();

    Livewire::actingAs($actor)
        ->test(CreateArticleTag::class)
        ->fillForm([
            'slug' => 'family-friendly',
            'name' => 'Family Friendly',
            'is_active' => true,
            'display_order' => 2,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $tag = ArticleTag::query()->where('slug', 'family-friendly')->firstOrFail();

    expect($tag->name)->toBe('Family Friendly');
});

it('round-trips a cover image through the media collection', function (): void {
    Storage::fake('public');

    $category = articleTestCategory();
    $author = User::factory()->create();

    $article = Article::create([
        'author_id' => $author->id,
        'article_category_id' => $category->id,
        'status' => 'draft',
    ]);

    $article->addMedia(UploadedFile::fake()->image('cover.jpg', 1600, 900))
        ->toMediaCollection('cover_image');

    $reloaded = Article::query()->findOrFail($article->id);

    expect($reloaded->getMedia('cover_image'))->toHaveCount(1)
        ->and($reloaded->getFirstMedia('cover_image')?->file_name)->toBe('cover.jpg');

    // singleFile() collections replace their prior occupant, not accumulate.
    $article->addMedia(UploadedFile::fake()->image('cover-v2.jpg'))->toMediaCollection('cover_image');

    expect(Article::query()->findOrFail($article->id)->getMedia('cover_image'))->toHaveCount(1)
        ->and(Article::query()->findOrFail($article->id)->getFirstMedia('cover_image')?->file_name)->toBe('cover-v2.jpg');
});

it('publishes, schedules, and archives an article through the lifecycle service, journaling each transition', function (): void {
    config(['audit.console' => true]);

    $category = articleTestCategory();
    $author = User::factory()->create();
    $actor = articleContentActor();

    $article = Article::create([
        'author_id' => $author->id,
        'article_category_id' => $category->id,
        'status' => 'draft',
    ]);
    $article->translateOrNew('en')->fill(['title' => 'Lifecycle Article', 'body' => 'Body.', 'slug' => 'lifecycle-article']);
    $article->save();

    $service = app(ArticleLifecycleService::class);

    $service->publish($article->fresh(), $actor);
    $published = Article::query()->findOrFail($article->id);
    expect($published->status)->toBe('published')
        ->and($published->publish_at)->not->toBeNull()
        ->and(DB::table('audits')->where('event', 'article_published')->count())->toBe(1);

    $future = now()->addWeek();
    $service->schedule($published, $future, $actor);
    $scheduled = Article::query()->findOrFail($article->id);
    expect($scheduled->status)->toBe('scheduled')
        ->and($scheduled->publish_at?->timestamp)->toBe($future->timestamp)
        ->and(DB::table('audits')->where('event', 'article_scheduled')->count())->toBe(1);

    expect(fn () => $service->schedule($scheduled, now()->subDay(), $actor))
        ->toThrow(ArticleScheduleRefusedException::class);

    $service->archive($scheduled, $actor);
    expect(Article::query()->withTrashed()->findOrFail($article->id)->trashed())->toBeTrue()
        ->and(DB::table('audits')->where('event', 'article_archived')->count())->toBe(1);

    $trashed = Article::query()->withTrashed()->findOrFail($article->id);
    $service->restore($trashed, $actor);
    expect(Article::query()->findOrFail($article->id)->trashed())->toBeFalse()
        ->and(DB::table('audits')->where('event', 'article_restored')->count())->toBe(1);
});
