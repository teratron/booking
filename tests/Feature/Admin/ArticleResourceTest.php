<?php

declare(strict_types=1);

use App\Exceptions\ArticleScheduleRefusedException;
use App\Filament\Admin\Resources\Articles\ArticleResource;
use App\Filament\Admin\Resources\Articles\Pages\CreateArticle;
use App\Filament\Admin\Resources\Articles\Pages\EditArticle;
use App\Filament\Admin\Resources\Articles\Pages\ListArticles;
use App\Filament\Admin\Resources\ArticleTags\Pages\EditArticleTag;
use App\Filament\Admin\Resources\ArticleTags\Pages\ListArticleTags;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\ArticleTag;
use App\Models\Country;
use App\Models\Object_;
use App\Models\ObjectType;
use App\Models\Territory;
use App\Models\TerritoryLevel;
use App\Models\User;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Article Tags & Articles — Admin Panel Resources
|--------------------------------------------------------------------------
|
| The Filament surface itself: list rendering backed by real seeded data,
| create/edit round-tripping a translation and its related pivots, the
| lifecycle actions beyond an ordinary field save, and the content policy
| boundary those actions and pages both defer to. The Article model and
| ArticleLifecycleService's own transition logic are covered directly
| elsewhere — this exercises the resource wiring reached through HTTP and
| Livewire the way an administrator actually would.
|
*/

function articleResourceLanguageReady(): void
{
    if (DB::table('languages')->where('code', 'en')->exists()) {
        return;
    }

    DB::table('languages')->insert([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** @return array{country: Country, territoryId: int, object: Object_} */
function articleResourceGeography(): array
{
    articleResourceLanguageReady();
    $languageId = DB::table('languages')->where('code', 'en')->value('id');

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
    $territory->translateOrNew('en')->fill(['country_id' => $country->id, 'name' => 'Gagauzia', 'slug' => 'gagauzia']);
    $territory->save();

    $objectType = ObjectType::create(['key' => 'article_resource_probe_type']);
    $objectType->translateOrNew('en')->fill(['name' => 'Hotel', 'slug' => 'article-resource-hotel']);
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
    $object->translateOrNew('en')->fill(['name' => 'Grand Hotel', 'slug' => 'article-resource-grand-hotel']);
    $object->save();

    return ['country' => $country, 'territoryId' => $territory->id, 'object' => $object];
}

function articleResourceCategory(string $slug = 'guides'): ArticleCategory
{
    articleResourceLanguageReady();

    $category = ArticleCategory::create(['slug' => $slug, 'is_active' => true]);
    $category->translateOrNew('en')->name = 'Guides '.$slug;
    $category->save();

    return $category;
}

/** @param  list<string>  $permissions */
function articleResourceActor(array $permissions, string $roleKey): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate($roleKey, 'web');
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

/** @return list<string> */
function articleResourceFullPermissions(): array
{
    return ['admin_panel_access', 'content.view', 'content.create', 'content.edit', 'content.publish', 'content.delete'];
}

// -----------------------------------------------------------------------
// Article Tags — list, edit, filter, delete.
// -----------------------------------------------------------------------

it('lists article tags with their name, slug, active flag, and display order backed by real rows', function (): void {
    $actor = articleResourceActor(articleResourceFullPermissions(), 'article_tags_list_actor');

    $active = ArticleTag::create(['slug' => 'seasonal', 'name' => 'Seasonal', 'is_active' => true, 'display_order' => 1]);
    $inactive = ArticleTag::create(['slug' => 'archived-tag', 'name' => 'Archived Tag', 'is_active' => false, 'display_order' => 2]);

    Livewire::actingAs($actor)
        ->test(ListArticleTags::class)
        ->assertCanSeeTableRecords([$active, $inactive])
        ->assertTableColumnStateSet('name', 'Seasonal', $active)
        ->assertTableColumnStateSet('slug', 'seasonal', $active)
        ->assertTableColumnStateSet('is_active', true, $active)
        ->assertTableColumnStateSet('is_active', false, $inactive)
        ->assertTableColumnStateSet('display_order', 1, $active)
        ->assertTableColumnStateSet('display_order', 2, $inactive);
});

it('filters the article tag list down to inactive tags via the active-status filter', function (): void {
    $actor = articleResourceActor(articleResourceFullPermissions(), 'article_tags_filter_actor');

    $active = ArticleTag::create(['slug' => 'live-tag', 'name' => 'Live Tag', 'is_active' => true]);
    $inactive = ArticleTag::create(['slug' => 'retired-tag', 'name' => 'Retired Tag', 'is_active' => false]);

    Livewire::actingAs($actor)
        ->test(ListArticleTags::class)
        ->filterTable('is_active', false)
        ->assertCanSeeTableRecords([$inactive])
        ->assertCanNotSeeTableRecords([$active]);
});

it('edits an article tag\'s fields through the resource', function (): void {
    $actor = articleResourceActor(articleResourceFullPermissions(), 'article_tags_edit_actor');
    $tag = ArticleTag::create(['slug' => 'old-slug', 'name' => 'Old Name', 'is_active' => true, 'display_order' => 3]);

    Livewire::actingAs($actor)
        ->test(EditArticleTag::class, ['record' => $tag->id])
        ->fillForm([
            'slug' => 'new-slug',
            'name' => 'New Name',
            'is_active' => false,
            'display_order' => 9,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $fresh = ArticleTag::query()->findOrFail($tag->id);

    expect($fresh->slug)->toBe('new-slug')
        ->and($fresh->name)->toBe('New Name')
        ->and($fresh->is_active)->toBeFalse()
        ->and($fresh->display_order)->toBe(9);
});

it('deletes an article tag through the edit page\'s header delete action', function (): void {
    $actor = articleResourceActor(articleResourceFullPermissions(), 'article_tags_delete_actor');
    $tag = ArticleTag::create(['slug' => 'doomed-tag', 'name' => 'Doomed Tag', 'is_active' => true]);

    Livewire::actingAs($actor)
        ->test(EditArticleTag::class, ['record' => $tag->id])
        ->callAction('delete');

    expect(ArticleTag::query()->find($tag->id))->toBeNull();
});

// -----------------------------------------------------------------------
// Articles — list rendering.
// -----------------------------------------------------------------------

it('lists articles with title, author, category, and status columns, showing a dash for an uncategorized article', function (): void {
    $category = articleResourceCategory('list-category');
    $author = User::factory()->create(['name' => 'Elena Rusu']);
    $actor = articleResourceActor(articleResourceFullPermissions(), 'articles_list_actor');

    $categorized = Article::create([
        'author_id' => $author->id, 'article_category_id' => $category->id,
        'status' => 'published', 'publish_at' => now()->subDay(),
    ]);
    $categorized->translateOrNew('en')->fill(['title' => 'Categorized Piece', 'body' => 'Body.', 'slug' => 'categorized-piece']);
    $categorized->save();

    $uncategorized = Article::create(['author_id' => $author->id, 'article_category_id' => null, 'status' => 'draft']);
    $uncategorized->translateOrNew('en')->fill(['title' => 'Uncategorized Piece', 'body' => 'Body.', 'slug' => 'uncategorized-piece']);
    $uncategorized->save();

    Livewire::actingAs($actor)
        ->test(ListArticles::class)
        ->assertTableColumnStateSet('title', 'Categorized Piece', $categorized)
        ->assertTableColumnStateSet('author.name', 'Elena Rusu', $categorized)
        ->assertTableColumnStateSet('category.name', $category->name, $categorized)
        ->assertTableColumnFormattedStateSet('status', __('panel.articles.status.published'), $categorized)
        ->assertTableColumnStateSet('title', 'Uncategorized Piece', $uncategorized)
        ->assertTableColumnStateSet('category.name', '—', $uncategorized)
        ->assertTableColumnFormattedStateSet('status', __('panel.articles.status.draft'), $uncategorized);
});

// -----------------------------------------------------------------------
// Creating an article — translation and every related pivot.
// -----------------------------------------------------------------------

it('creates an article, persisting its translation and every related pivot, through the resource', function (): void {
    $geo = articleResourceGeography();
    $category = articleResourceCategory('create-category');
    $tag = ArticleTag::create(['slug' => 'seasonal-picks', 'name' => 'Seasonal Picks', 'is_active' => true]);
    $actor = articleResourceActor(articleResourceFullPermissions(), 'articles_create_actor');

    Livewire::actingAs($actor)
        ->test(CreateArticle::class)
        ->fillForm([
            'author_id' => $actor->id,
            'article_category_id' => $category->id,
            'status' => 'draft',
            'objects' => [$geo['object']->id],
            'territories' => [$geo['territoryId']],
            'tags' => [$tag->id],
            'translations' => [
                'en' => [
                    'title' => 'Ten Days in Gagauzia',
                    'summary' => 'A short teaser.',
                    'body' => 'The full editorial body.',
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $article = Article::query()
        ->whereHas('translations', fn ($query) => $query->where('title', 'Ten Days in Gagauzia'))
        ->firstOrFail();

    expect($article->author_id)->toBe($actor->id)
        ->and($article->article_category_id)->toBe($category->id)
        ->and($article->status)->toBe('draft')
        ->and($article->translate('en')->summary)->toBe('A short teaser.')
        ->and($article->translate('en')->body)->toBe('The full editorial body.')
        // No slug was supplied — the create page derives one from the title.
        ->and($article->translate('en')->slug)->toBe(Str::slug('Ten Days in Gagauzia'))
        ->and($article->objects()->pluck('objects.id')->all())->toBe([$geo['object']->id])
        ->and($article->territories()->pluck('territories.id')->all())->toBe([$geo['territoryId']])
        ->and($article->tags()->pluck('article_tags.id')->all())->toBe([$tag->id]);
});

// -----------------------------------------------------------------------
// Editing an article — translation reconciliation and relation updates.
// -----------------------------------------------------------------------

it('edits an article\'s translation and category through the resource, reconciling the translation row', function (): void {
    $geo = articleResourceGeography();
    $categoryOriginal = articleResourceCategory('edit-original');
    $categoryNew = articleResourceCategory('edit-new');
    $author = User::factory()->create();
    $actor = articleResourceActor(articleResourceFullPermissions(), 'articles_edit_actor');

    $article = Article::create([
        'author_id' => $author->id, 'article_category_id' => $categoryOriginal->id, 'status' => 'draft',
    ]);
    $article->translateOrNew('en')->fill(['title' => 'Original Title', 'body' => 'Original body.', 'slug' => 'original-title']);
    $article->save();
    $article->territories()->attach($geo['territoryId']);

    Livewire::actingAs($actor)
        ->test(EditArticle::class, ['record' => $article->id])
        ->assertFormSet(['translations.en.title' => 'Original Title'])
        ->fillForm([
            'article_category_id' => $categoryNew->id,
            'translations' => ['en' => ['title' => 'Updated Title', 'body' => 'Updated body.']],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $fresh = Article::query()->findOrFail($article->id);

    expect($fresh->article_category_id)->toBe($categoryNew->id)
        ->and($fresh->translate('en')->title)->toBe('Updated Title')
        ->and($fresh->translate('en')->body)->toBe('Updated body.')
        // The slug field was not resubmitted — reconciliation keeps the
        // prior value rather than blanking or regenerating it.
        ->and($fresh->translate('en')->slug)->toBe('original-title')
        ->and($fresh->territories()->pluck('territories.id')->all())->toBe([$geo['territoryId']]);
});

it('reaches a soft-deleted article\'s own edit page directly, since record resolution explicitly queries withTrashed', function (): void {
    $category = articleResourceCategory('trashed-category');
    $author = User::factory()->create();
    $actor = articleResourceActor(articleResourceFullPermissions(), 'articles_trashed_actor');

    $article = Article::create(['author_id' => $author->id, 'article_category_id' => $category->id, 'status' => 'draft']);
    $article->translateOrNew('en')->fill(['title' => 'Retired Piece', 'body' => 'Body.', 'slug' => 'retired-piece']);
    $article->save();
    $article->delete();

    expect(Article::query()->find($article->id))->toBeNull();

    test()->actingAs($actor)
        ->get(ArticleResource::getUrl('edit', ['record' => $article->id], panel: 'admin'))
        ->assertSuccessful();
});

// -----------------------------------------------------------------------
// Lifecycle actions on the edit page — publish, schedule, archive, restore.
// -----------------------------------------------------------------------

it('publishes a draft article via the edit page\'s publish action, journaling the transition', function (): void {
    config(['audit.console' => true]);

    $category = articleResourceCategory('publish-category');
    $author = User::factory()->create();
    $actor = articleResourceActor(articleResourceFullPermissions(), 'articles_publish_actor');

    $article = Article::create(['author_id' => $author->id, 'article_category_id' => $category->id, 'status' => 'draft']);
    $article->translateOrNew('en')->fill(['title' => 'Publish Me', 'body' => 'Body.', 'slug' => 'publish-me']);
    $article->save();

    Livewire::actingAs($actor)
        ->test(EditArticle::class, ['record' => $article->id])
        ->callAction('publish');

    $fresh = Article::query()->findOrFail($article->id);

    expect($fresh->status)->toBe('published')
        ->and($fresh->publish_at)->not->toBeNull()
        ->and(DB::table('audits')->where('event', 'article_published')->where('auditable_id', $article->id)->count())->toBe(1);
});

it('schedules an article for a future date, and refuses a past date with a translated notification', function (): void {
    config(['audit.console' => true]);

    $category = articleResourceCategory('schedule-category');
    $author = User::factory()->create();
    $actor = articleResourceActor(articleResourceFullPermissions(), 'articles_schedule_actor');

    $article = Article::create(['author_id' => $author->id, 'article_category_id' => $category->id, 'status' => 'draft']);
    $article->translateOrNew('en')->fill(['title' => 'Schedule Me', 'body' => 'Body.', 'slug' => 'schedule-me']);
    $article->save();

    $future = now()->addWeek()->startOfSecond();

    Livewire::actingAs($actor)
        ->test(EditArticle::class, ['record' => $article->id])
        ->callAction('schedule', data: ['publish_at' => $future->toDateTimeString()]);

    $scheduled = Article::query()->findOrFail($article->id);

    expect($scheduled->status)->toBe('scheduled')
        ->and($scheduled->publish_at?->timestamp)->toBe($future->timestamp)
        ->and(DB::table('audits')->where('event', 'article_scheduled')->where('auditable_id', $article->id)->count())->toBe(1);

    Livewire::actingAs($actor)
        ->test(EditArticle::class, ['record' => $article->id])
        ->callAction('schedule', data: ['publish_at' => now()->subDay()->toDateTimeString()])
        ->assertNotified(
            FilamentNotification::make()->danger()
                ->title(__('panel.articles.lifecycle.schedule_refused'))
                ->body(ArticleScheduleRefusedException::notInFuture($article->id)->getMessage())
        );

    // The refused attempt left the prior successful schedule untouched, and
    // journaled nothing new.
    expect(Article::query()->findOrFail($article->id)->status)->toBe('scheduled')
        ->and(DB::table('audits')->where('event', 'article_scheduled')->where('auditable_id', $article->id)->count())->toBe(1);
});

it('archives a published article via the edit page and restores it, reaching the trashed record throughout', function (): void {
    config(['audit.console' => true]);

    $category = articleResourceCategory('archive-category');
    $author = User::factory()->create();
    $actor = articleResourceActor(articleResourceFullPermissions(), 'articles_archive_actor');

    $article = Article::create([
        'author_id' => $author->id, 'article_category_id' => $category->id,
        'status' => 'published', 'publish_at' => now()->subDay(),
    ]);
    $article->translateOrNew('en')->fill(['title' => 'Archive Me', 'body' => 'Body.', 'slug' => 'archive-me']);
    $article->save();

    Livewire::actingAs($actor)
        ->test(EditArticle::class, ['record' => $article->id])
        ->callAction('archive');

    expect(Article::query()->find($article->id))->toBeNull()
        ->and(Article::withTrashed()->findOrFail($article->id)->trashed())->toBeTrue()
        ->and(DB::table('audits')->where('event', 'article_archived')->where('auditable_id', $article->id)->count())->toBe(1);

    Livewire::actingAs($actor)
        ->test(EditArticle::class, ['record' => $article->id])
        ->assertActionVisible('restore')
        ->callAction('restore');

    expect(Article::query()->findOrFail($article->id)->trashed())->toBeFalse()
        ->and(DB::table('audits')->where('event', 'article_restored')->where('auditable_id', $article->id)->count())->toBe(1);
});

it('hides the publish, schedule, and archive actions from an actor lacking their permissions, and shows them to one holding the full grant', function (): void {
    $category = articleResourceCategory('visibility-category');
    $author = User::factory()->create();

    $article = Article::create(['author_id' => $author->id, 'article_category_id' => $category->id, 'status' => 'draft']);
    $article->translateOrNew('en')->fill(['title' => 'Visibility Probe', 'body' => 'Body.', 'slug' => 'visibility-probe']);
    $article->save();

    $editorOnly = articleResourceActor(['admin_panel_access', 'content.view', 'content.edit'], 'articles_editor_only');

    Livewire::actingAs($editorOnly)
        ->test(EditArticle::class, ['record' => $article->id])
        ->assertActionHidden('publish')
        ->assertActionHidden('schedule')
        ->assertActionHidden('archive');

    $full = articleResourceActor(articleResourceFullPermissions(), 'articles_full_grant');

    Livewire::actingAs($full)
        ->test(EditArticle::class, ['record' => $article->id])
        ->assertActionVisible('publish')
        ->assertActionVisible('schedule')
        ->assertActionVisible('archive');
});

// -----------------------------------------------------------------------
// ArticlePolicy — every ability's permission boundary, and the create/edit
// pages that defer to it.
// -----------------------------------------------------------------------

it('permits each article ability for a full content grant, and refuses every ability but viewing for a view-only grant', function (): void {
    $category = articleResourceCategory('policy-category');
    $author = User::factory()->create();

    $article = Article::create(['author_id' => $author->id, 'article_category_id' => $category->id, 'status' => 'draft']);
    $article->translateOrNew('en')->fill(['title' => 'Policy Probe', 'body' => 'Body.', 'slug' => 'policy-probe']);
    $article->save();

    $full = articleResourceActor(articleResourceFullPermissions(), 'policy_full_grant');
    $viewOnly = articleResourceActor(['admin_panel_access', 'content.view'], 'policy_view_only_grant');

    expect($full->can('viewAny', Article::class))->toBeTrue()
        ->and($full->can('view', $article))->toBeTrue()
        ->and($full->can('create', Article::class))->toBeTrue()
        ->and($full->can('update', $article))->toBeTrue()
        ->and($full->can('publish', $article))->toBeTrue()
        ->and($full->can('delete', $article))->toBeTrue()
        ->and($full->can('restore', $article))->toBeTrue();

    expect($viewOnly->can('viewAny', Article::class))->toBeTrue()
        ->and($viewOnly->can('view', $article))->toBeTrue()
        ->and($viewOnly->can('create', Article::class))->toBeFalse()
        ->and($viewOnly->can('update', $article))->toBeFalse()
        ->and($viewOnly->can('publish', $article))->toBeFalse()
        ->and($viewOnly->can('delete', $article))->toBeFalse()
        ->and($viewOnly->can('restore', $article))->toBeFalse();
});

it('refuses HTTP access to the create and edit pages for an actor holding only content.view, and admits the full grant', function (): void {
    $category = articleResourceCategory('http-policy-category');
    $author = User::factory()->create();

    $article = Article::create(['author_id' => $author->id, 'article_category_id' => $category->id, 'status' => 'draft']);
    $article->translateOrNew('en')->fill(['title' => 'HTTP Policy Probe', 'body' => 'Body.', 'slug' => 'http-policy-probe']);
    $article->save();

    $viewOnly = articleResourceActor(['admin_panel_access', 'content.view'], 'policy_http_view_only');

    test()->actingAs($viewOnly)
        ->get(ArticleResource::getUrl('create', panel: 'admin'))
        ->assertForbidden();

    test()->actingAs($viewOnly)
        ->get(ArticleResource::getUrl('edit', ['record' => $article->id], panel: 'admin'))
        ->assertForbidden();

    $full = articleResourceActor(articleResourceFullPermissions(), 'policy_http_full_grant');

    test()->actingAs($full)
        ->get(ArticleResource::getUrl('create', panel: 'admin'))
        ->assertSuccessful();

    test()->actingAs($full)
        ->get(ArticleResource::getUrl('edit', ['record' => $article->id], panel: 'admin'))
        ->assertSuccessful();
});
