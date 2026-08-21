<?php

declare(strict_types=1);

use App\Exceptions\ContentScheduleRefusedException;
use App\Filament\Admin\Resources\NewsItems\NewsItemResource;
use App\Filament\Admin\Resources\NewsItems\Pages\CreateNewsItem;
use App\Filament\Admin\Resources\NewsItems\Pages\EditNewsItem;
use App\Filament\Admin\Resources\NewsItems\Pages\ListNewsItems;
use App\Models\ArticleCategory;
use App\Models\NewsItem;
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
| News Item Resource (Admin Panel)
|--------------------------------------------------------------------------
|
| The Filament resource pages reached through a real HTTP or Livewire
| request — form save, per-language translation upsert, the header
| lifecycle actions' effect on the actual record, and territory-scope
| narrowing of the list. The lifecycle service's own transitions are
| exercised directly elsewhere; this file exercises the pages that call it.
|
*/

/** @return array<string, mixed> */
function newsItemResourceGeography(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryId = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true, 'display_order' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $otherTerritoryId = DB::table('territories')->insertGetId([
        'country_id' => $countryId, 'level_id' => $levelId, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $objectTypeId = DB::table('object_types')->insertGetId([
        'key' => 'news_item_resource_probe_type', 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $objectId = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(),
        'owner_id' => User::factory()->create()->id,
        'object_type_id' => $objectTypeId,
        'territory_id' => $territoryId,
        'country_id' => $countryId,
        'status' => 'published',
        'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $category = ArticleCategory::create(['slug' => 'news-resource-guides', 'is_active' => true]);
    $category->translateOrNew('en')->name = 'Guides';
    $category->save();

    return [
        'languageId' => $languageId,
        'countryId' => $countryId,
        'territoryId' => $territoryId,
        'otherTerritoryId' => $otherTerritoryId,
        'objectTypeId' => $objectTypeId,
        'objectId' => $objectId,
        'categoryId' => $category->id,
    ];
}

/**
 * @param  list<string>  $permissions
 */
function newsItemResourceActor(
    array $permissions = ['admin_panel_access', 'content.view', 'content.create', 'content.edit', 'content.publish', 'content.delete', 'content.export'],
    string $scopeKind = 'none',
    ?int $scopeReference = null,
    string $roleKey = 'news_item_resource_admin',
): User {
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
        'scope_kind' => $scopeKind, 'scope_reference_id' => $scopeReference,
        'granted_by' => $user->id, 'granted_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $user->fresh();
}

/**
 * @param  array<string, mixed>  $geography
 * @param  array<string, mixed>  $overrides
 */
function newsItemResourceMake(array $geography, array $overrides = []): int
{
    return DB::table('news_items')->insertGetId(array_merge([
        'author_id' => User::factory()->create()->id,
        'object_id' => $geography['objectId'],
        'territory_id' => $geography['territoryId'],
        'article_category_id' => $geography['categoryId'],
        'status' => 'draft',
        'moderation_status' => null,
        'is_pinned' => false,
        'view_count' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));
}

/** @param  array<string, mixed>  $fields */
function newsItemResourceTranslate(int $newsItemId, string $locale, array $fields): void
{
    DB::table('news_translations')->insert(array_merge([
        'news_item_id' => $newsItemId, 'locale' => $locale,
        'created_at' => now(), 'updated_at' => now(),
    ], $fields));
}

it('creates a news item with a translation, territory, object, and category through the admin panel', function (): void {
    $geo = newsItemResourceGeography();
    $actor = newsItemResourceActor();

    Livewire::actingAs($actor)
        ->test(CreateNewsItem::class)
        ->fillForm([
            'author_id' => $actor->id,
            'object_id' => $geo['objectId'],
            'territory_id' => $geo['territoryId'],
            'article_category_id' => $geo['categoryId'],
            'status' => 'draft',
            'is_pinned' => true,
            'translations' => [
                'en' => [
                    'title' => 'Season Opening Announced',
                    'summary' => 'A short teaser.',
                    'body' => 'The full article body.',
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $newsItemId = DB::table('news_items')->where('object_id', $geo['objectId'])->value('id');
    expect($newsItemId)->not->toBeNull();

    $newsItem = NewsItem::withUnmoderated()->findOrFail($newsItemId);
    expect($newsItem->author_id)->toBe($actor->id)
        ->and($newsItem->territory_id)->toBe($geo['territoryId'])
        ->and($newsItem->article_category_id)->toBe($geo['categoryId'])
        ->and($newsItem->is_pinned)->toBeTrue()
        ->and($newsItem->status)->toBe('draft')
        ->and($newsItem->title)->toBe('Season Opening Announced')
        ->and($newsItem->summary)->toBe('A short teaser.')
        ->and($newsItem->body)->toBe('The full article body.');

    // Slug omitted from the form: CreateNewsItem::handleRecordCreation()
    // derives it from the title for a brand-new translation row.
    expect(DB::table('news_translations')->where('news_item_id', $newsItemId)->where('locale', 'en')->value('slug'))
        ->toBe('season-opening-announced');
});

it('edits a news item, updating scalar fields and upserting its translation without losing the existing slug', function (): void {
    $geo = newsItemResourceGeography();
    $actor = newsItemResourceActor();
    $newsItemId = newsItemResourceMake($geo, ['status' => 'draft']);
    newsItemResourceTranslate($newsItemId, 'en', [
        'title' => 'Original Title', 'body' => 'Original body.', 'slug' => 'original-title',
    ]);

    Livewire::actingAs($actor)
        ->test(EditNewsItem::class, ['record' => $newsItemId])
        ->fillForm([
            'is_pinned' => true,
            'territory_id' => $geo['otherTerritoryId'],
            'translations' => [
                'en' => ['title' => 'Updated Title', 'body' => 'Updated body.'],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $newsItem = NewsItem::withUnmoderated()->findOrFail($newsItemId);
    expect($newsItem->is_pinned)->toBeTrue()
        ->and($newsItem->territory_id)->toBe($geo['otherTerritoryId']);

    $translation = DB::table('news_translations')->where('news_item_id', $newsItemId)->where('locale', 'en')->first();
    expect($translation->title)->toBe('Updated Title')
        ->and($translation->body)->toBe('Updated body.')
        // handleRecordUpdate() only falls back to Str::slug() when no slug
        // already exists for the locale — an edit must not silently rewrite
        // a published item's URL.
        ->and($translation->slug)->toBe('original-title');
});

it('publishes a news item through the edit page action, setting status and moderation_status together and journaling it', function (): void {
    $geo = newsItemResourceGeography();
    $actor = newsItemResourceActor();
    $newsItemId = newsItemResourceMake($geo, ['status' => 'draft', 'moderation_status' => null, 'publish_at' => null]);

    Livewire::actingAs($actor)
        ->test(EditNewsItem::class, ['record' => $newsItemId])
        ->callAction('publish');

    // The default query (moderation scope applied) — the invariant the
    // lifecycle service's own docblock states: an administrator-published
    // item must actually clear it, not merely carry the right status.
    $newsItem = NewsItem::query()->find($newsItemId);
    expect($newsItem)->not->toBeNull()
        ->and($newsItem->status)->toBe('published')
        ->and($newsItem->moderation_status)->toBe('approved')
        ->and($newsItem->publish_at)->not->toBeNull();

    expect(DB::table('audits')->where('event', 'news_item_published')->where('auditable_id', $newsItemId)->count())->toBe(1);
});

it('pins and unpins a news item through the header actions, each visible only in its own state', function (): void {
    $geo = newsItemResourceGeography();
    $actor = newsItemResourceActor();
    $newsItemId = newsItemResourceMake($geo, ['status' => 'published', 'moderation_status' => 'approved', 'is_pinned' => false]);

    Livewire::actingAs($actor)
        ->test(EditNewsItem::class, ['record' => $newsItemId])
        ->assertActionVisible('pin')
        ->assertActionHidden('unpin')
        ->callAction('pin')
        ->assertActionHidden('pin')
        ->assertActionVisible('unpin');

    expect(NewsItem::query()->findOrFail($newsItemId)->is_pinned)->toBeTrue()
        ->and(DB::table('audits')->where('event', 'news_item_pinned')->where('auditable_id', $newsItemId)->count())->toBe(1);
});

it('schedules a news item for a future publish date, and refuses one that is not in the future', function (): void {
    $geo = newsItemResourceGeography();
    $actor = newsItemResourceActor();
    $newsItemId = newsItemResourceMake($geo, ['status' => 'draft']);
    $future = now()->addWeek();

    Livewire::actingAs($actor)
        ->test(EditNewsItem::class, ['record' => $newsItemId])
        ->mountAction('schedule')
        ->setActionData(['publish_at' => $future->toDateTimeString()])
        ->callMountedAction();

    $scheduled = NewsItem::withUnmoderated()->findOrFail($newsItemId);
    expect($scheduled->status)->toBe('scheduled')
        ->and($scheduled->publish_at?->isSameDay($future))->toBeTrue()
        ->and(DB::table('audits')->where('event', 'news_item_scheduled')->where('auditable_id', $newsItemId)->count())->toBe(1);

    Livewire::actingAs($actor)
        ->test(EditNewsItem::class, ['record' => $newsItemId])
        ->mountAction('schedule')
        ->setActionData(['publish_at' => now()->subDay()->toDateTimeString()])
        ->callMountedAction()
        ->assertNotified(
            FilamentNotification::make()->danger()
                ->title(__('panel.news_items.lifecycle.schedule_refused'))
                ->body(ContentScheduleRefusedException::notInFuture('NewsItem', $newsItemId)->getMessage())
        );

    // Refused: still the earlier schedule, no second journal entry.
    expect(NewsItem::withUnmoderated()->findOrFail($newsItemId)->status)->toBe('scheduled')
        ->and(DB::table('audits')->where('event', 'news_item_scheduled')->where('auditable_id', $newsItemId)->count())->toBe(1);
});

it('withdraws a published news item on administrator demand, journaling the transition', function (): void {
    $geo = newsItemResourceGeography();
    $actor = newsItemResourceActor();
    $newsItemId = newsItemResourceMake($geo, ['status' => 'published', 'moderation_status' => 'approved']);

    Livewire::actingAs($actor)
        ->test(EditNewsItem::class, ['record' => $newsItemId])
        ->callAction('withdraw');

    expect(NewsItem::query()->findOrFail($newsItemId)->status)->toBe('withdrawn')
        ->and(DB::table('audits')->where('event', 'news_item_withdrawn')->where('auditable_id', $newsItemId)->count())->toBe(1);
});

it('archives a news item from the edit page, keeps its edit page reachable while trashed, and restores it', function (): void {
    $geo = newsItemResourceGeography();
    $actor = newsItemResourceActor();
    $newsItemId = newsItemResourceMake($geo, ['status' => 'withdrawn', 'moderation_status' => 'approved']);

    Livewire::actingAs($actor)
        ->test(EditNewsItem::class, ['record' => $newsItemId])
        ->callAction('archive');

    expect(NewsItem::query()->find($newsItemId))->toBeNull()
        ->and(NewsItem::withUnmoderated()->withTrashed()->findOrFail($newsItemId)->trashed())->toBeTrue()
        ->and(DB::table('audits')->where('event', 'news_item_archived')->where('auditable_id', $newsItemId)->count())->toBe(1);

    // resolveRecord() explicitly withTrashed()'s the scoped query, so the
    // archived item's own edit page — and its restore action — stays
    // reachable instead of 404ing the moment the row is soft-deleted.
    test()->actingAs($actor)
        ->get(NewsItemResource::getUrl('edit', ['record' => $newsItemId], panel: 'admin'))
        ->assertSuccessful();

    Livewire::actingAs($actor)
        ->test(EditNewsItem::class, ['record' => $newsItemId])
        ->callAction('restore');

    expect(NewsItem::query()->findOrFail($newsItemId)->trashed())->toBeFalse()
        ->and(DB::table('audits')->where('event', 'news_item_restored')->where('auditable_id', $newsItemId)->count())->toBe(1);
});

it('lists news items on the index page and narrows a territory-scoped administrator to their own territory', function (): void {
    $geo = newsItemResourceGeography();
    $unrestricted = newsItemResourceActor();

    $inTerritoryId = newsItemResourceMake($geo, ['status' => 'published', 'moderation_status' => 'approved', 'territory_id' => $geo['territoryId']]);
    newsItemResourceMake($geo, ['status' => 'published', 'moderation_status' => 'approved', 'territory_id' => $geo['otherTerritoryId']]);
    newsItemResourceMake($geo, ['status' => 'published', 'moderation_status' => 'approved', 'territory_id' => null]);

    test()->actingAs($unrestricted)
        ->get(NewsItemResource::getUrl('index', panel: 'admin'))
        ->assertSuccessful();

    expect(NewsItemResource::getEloquentQuery()->count())->toBe(3);

    $scoped = newsItemResourceActor(
        ['admin_panel_access', 'content.view'],
        'territory',
        $geo['territoryId'],
        'news_item_resource_territory_scoped',
    );

    test()->actingAs($scoped);

    // A territory-scoped grant reaches only its own territory — the other
    // territory's item and the portal-wide item (null territory_id, which
    // a whereIn against the column can never match) are both excluded.
    expect(NewsItemResource::getEloquentQuery()->pluck('id')->all())->toBe([$inTerritoryId]);
});

it('hides the export header action without the export permission, and shows it once granted', function (): void {
    newsItemResourceGeography();

    $withoutExport = newsItemResourceActor(
        ['admin_panel_access', 'content.view', 'content.create'],
        'none', null, 'news_item_resource_no_export',
    );

    Livewire::actingAs($withoutExport)
        ->test(ListNewsItems::class)
        ->assertActionHidden('export');

    $withExport = newsItemResourceActor(
        ['admin_panel_access', 'content.view', 'content.create', 'content.export'],
        'none', null, 'news_item_resource_with_export',
    );

    Livewire::actingAs($withExport)
        ->test(ListNewsItems::class)
        ->assertActionVisible('export');
});
