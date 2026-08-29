<?php

declare(strict_types=1);

use App\Filament\Cabinet\Resources\NewsItems\NewsItemResource;
use App\Filament\Cabinet\Resources\NewsItems\Pages\EditNewsItem;
use App\Filament\Cabinet\Resources\Notifications\NotificationResource;
use App\Filament\Cabinet\Resources\Notifications\Pages\ListNotifications;
use App\Filament\Cabinet\Resources\Photos\PhotoResource;
use App\Filament\Cabinet\Resources\Promotions\Pages\EditPromotion;
use App\Filament\Cabinet\Resources\Promotions\PromotionResource;
use App\Filament\Cabinet\Resources\Reviews\ReviewResource;
use App\Filament\Cabinet\Resources\Services\Pages\EditService;
use App\Models\NewsItem;
use App\Models\Object_;
use App\Models\Promotion;
use App\Models\User;
use Filament\Facades\Filament;
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
| Owner-Facing Cabinet Resources — Coverage Gaps
|--------------------------------------------------------------------------
|
| Six cabinet resources/pages with real behavioral gaps: the two content
| edit pages (news, promotions) never had their own moderation branch
| exercised; the notification inbox never proved its fail-closed fallback
| for an unresolved actor; the photo and review resources never proved
| their own eager-loaded, owner-scoped query directly; and the service
| form never proved its empty-catalog and missing-translation fallbacks.
| Every cabinet resource query must stay scoped to the acting owner's own
| records — a missing scope here is a cross-owner data leak, not a
| cosmetic gap — so each section below proves that scoping explicitly,
| alongside the resource's own render/edit behavior.
|
*/

/** @return array{countryId: int, territoryId: int, typeId: int} */
function gapsGeography(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true, 'display_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
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
        'country_id' => $countryId, 'level_id' => $levelId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'accommodation', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('countryId', 'territoryId', 'typeId');
}

function gapsOwner(string $roleKey): User
{
    foreach (['object.view', 'object.edit', 'content.view', 'content.create', 'content.edit', 'cabinet_access'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate($roleKey, 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions(['object.view', 'object.edit', 'content.view', 'content.create', 'content.edit', 'cabinet_access']);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user->fresh();
}

/** @param  array{countryId: int, territoryId: int, typeId: int}  $fixture */
function gapsMakeObject(array $fixture, int $ownerId, string $status = 'published'): Object_
{
    $objectId = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(),
        'owner_id' => $ownerId,
        'object_type_id' => $fixture['typeId'],
        'territory_id' => $fixture['territoryId'],
        'country_id' => $fixture['countryId'],
        'status' => $status,
        'moderation_status' => $status === 'published' ? 'approved' : null,
        'latitude' => 45.1234500,
        'longitude' => 28.1234500,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => 'Gaps Villa',
        'slug' => 'gaps-villa-'.$objectId, 'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Object_ $object */
    $object = Object_::query()->withoutGlobalScopes()->findOrFail($objectId);

    return $object;
}

function gapsMountTenant(Object_ $object): void
{
    Filament::setCurrentPanel(Filament::getPanel('cabinet'));
    Filament::bootCurrentPanel();
    Filament::setTenant($object, isQuiet: true);
}

function gapsSetModerationMode(int $objectId, string $mode): void
{
    DB::table('moderation_settings')->insert([
        'scope_level' => 'object', 'scope_reference_id' => $objectId, 'mode' => $mode,
        'set_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** @param  array{status?: string, moderation_status?: ?string, title?: string, summary?: ?string, body?: string}  $overrides */
function gapsMakeNewsItem(Object_ $object, array $overrides = []): NewsItem
{
    $status = $overrides['status'] ?? 'published';
    $moderationStatus = $overrides['moderation_status'] ?? ($status === 'published' ? 'approved' : null);

    $id = DB::table('news_items')->insertGetId([
        'author_id' => $object->owner_id,
        'object_id' => $object->id,
        'territory_id' => $object->territory_id,
        'status' => $status,
        'moderation_status' => $moderationStatus,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('news_translations')->insert([
        'news_item_id' => $id, 'locale' => 'en',
        'title' => $overrides['title'] ?? 'Original Title',
        'summary' => $overrides['summary'] ?? 'Original summary.',
        'body' => $overrides['body'] ?? 'Original body content.',
        'slug' => 'original-title-'.$id,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var NewsItem $newsItem */
    $newsItem = NewsItem::query()->withUnmoderated()->findOrFail($id);

    return $newsItem;
}

/** @param  array{status?: string, moderation_status?: ?string, title?: string, summary?: ?string}  $overrides */
function gapsMakePromotion(Object_ $object, array $overrides = []): Promotion
{
    $status = $overrides['status'] ?? 'published';
    $moderationStatus = $overrides['moderation_status'] ?? ($status === 'published' ? 'approved' : null);

    $id = DB::table('promotions')->insertGetId([
        'object_id' => $object->id,
        'territory_id' => $object->territory_id,
        'starts_at' => now()->toDateString(),
        'ends_at' => now()->addDays(30)->toDateString(),
        'status' => $status,
        'moderation_status' => $moderationStatus,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('promotion_translations')->insert([
        'promotion_id' => $id, 'locale' => 'en',
        'title' => $overrides['title'] ?? 'Original Promo',
        'summary' => $overrides['summary'] ?? 'Original promo summary.',
        'slug' => 'original-promo-'.$id,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Promotion $promotion */
    $promotion = Promotion::query()->withUnmoderated()->findOrFail($id);

    return $promotion;
}

/*
|--------------------------------------------------------------------------
| EditNewsItem
|--------------------------------------------------------------------------
*/

it('applies a news edit directly when the item is not yet live, regardless of the object\'s resolved moderation mode', function (): void {
    $fixture = gapsGeography();
    $owner = gapsOwner('gaps_news_edit_not_live');
    $object = gapsMakeObject($fixture, $owner->id);
    // The item's own lifecycle, not the object's, decides applyEdit's branch:
    // a draft item bypasses the pipeline entirely even under review mode.
    gapsSetModerationMode($object->id, 'review');
    $newsItem = gapsMakeNewsItem($object, ['status' => 'draft', 'moderation_status' => null]);
    gapsMountTenant($object);

    Livewire::actingAs($owner)
        ->test(EditNewsItem::class, ['record' => $newsItem->getKey()])
        ->fillForm(['title' => 'Updated Draft Title', 'summary' => 'Updated summary.', 'body' => 'Updated body.'])
        ->call('save')
        ->assertHasNoFormErrors();

    $row = DB::table('news_items')->where('id', $newsItem->id)->first();

    expect($row->status)->toBe('draft')
        ->and($row->moderation_status)->toBeNull();

    expect(DB::table('news_translations')->where('news_item_id', $newsItem->id)->where('locale', 'en')->value('title'))
        ->toBe('Updated Draft Title');

    expect(DB::table('moderation_requests')->where('target_id', $newsItem->id)->where('target_type', NewsItem::class)->count())
        ->toBe(0);
});

it('applies a news edit directly under immediate mode when the item is already live, keeping it published', function (): void {
    $fixture = gapsGeography();
    $owner = gapsOwner('gaps_news_edit_immediate');
    $object = gapsMakeObject($fixture, $owner->id);
    gapsSetModerationMode($object->id, 'immediate');
    $newsItem = gapsMakeNewsItem($object);
    gapsMountTenant($object);

    $test = Livewire::actingAs($owner)
        ->test(EditNewsItem::class, ['record' => $newsItem->getKey()])
        ->fillForm(['title' => 'Refreshed Title', 'summary' => 'Refreshed summary.', 'body' => 'Refreshed body.'])
        ->call('save')
        ->assertHasNoFormErrors();

    $row = DB::table('news_items')->where('id', $newsItem->id)->first();

    expect($row->status)->toBe('published')
        ->and($row->moderation_status)->toBe('approved');

    expect(DB::table('news_translations')->where('news_item_id', $newsItem->id)->where('locale', 'en')->value('title'))
        ->toBe('Refreshed Title');

    expect(DB::table('moderation_requests')->where('target_id', $newsItem->id)->where('target_type', NewsItem::class)->count())
        ->toBe(0);

    $method = new ReflectionMethod(EditNewsItem::class, 'getSavedNotificationTitle');
    expect($method->invoke($test->instance()))->toBe(__('panel.cabinet.news_items.lifecycle.published'));
});

it('withholds a news edit behind a pending ModerationRequest under review mode, leaving the published record untouched', function (): void {
    $fixture = gapsGeography();
    $owner = gapsOwner('gaps_news_edit_review');
    $object = gapsMakeObject($fixture, $owner->id);
    gapsSetModerationMode($object->id, 'review');
    $newsItem = gapsMakeNewsItem($object);
    gapsMountTenant($object);

    $test = Livewire::actingAs($owner)->test(EditNewsItem::class, ['record' => $newsItem->getKey()]);

    // mutateFormDataBeforeFill hydrates from the item's own translation, not
    // from a raw model attribute — proven by asserting it on mount, before
    // any edit is submitted.
    $test->assertFormSet(['title' => 'Original Title', 'summary' => 'Original summary.', 'body' => 'Original body content.']);

    $test->fillForm(['title' => 'Withheld Title', 'summary' => 'Withheld summary.', 'body' => 'Withheld body.'])
        ->call('save')
        ->assertHasNoFormErrors();

    // The published record stays untouched while a pending revision exists —
    // this is the edit path (includesLiveState: false), unlike a fresh
    // submission, so moderation_status must NOT flip to pending here.
    $row = DB::table('news_items')->where('id', $newsItem->id)->first();

    expect($row->status)->toBe('published')
        ->and($row->moderation_status)->toBe('approved');

    expect(DB::table('news_translations')->where('news_item_id', $newsItem->id)->where('locale', 'en')->value('title'))
        ->toBe('Original Title');

    $request = DB::table('moderation_requests')->where('target_id', $newsItem->id)->where('target_type', NewsItem::class)->first();

    expect($request)->not->toBeNull()
        ->and($request->decision)->toBe('pending')
        ->and($request->section)->toBe('news')
        ->and($request->submitted_by)->toBe($owner->id);

    $previous = json_decode((string) $request->previous_data, true);
    $proposed = json_decode((string) $request->proposed_data, true);

    expect($previous['en']['title'])->toBe('Original Title')
        ->and($proposed['en']['title'])->toBe('Withheld Title')
        ->and($proposed)->not->toHaveKey('status')
        ->and($proposed)->not->toHaveKey('moderation_status');

    $method = new ReflectionMethod(EditNewsItem::class, 'getSavedNotificationTitle');
    expect($method->invoke($test->instance()))->toBe(__('panel.cabinet.news_items.lifecycle.submitted_for_review'));
});

it("refuses editing another owner's news item through the real edit route, even for an already-live record", function (): void {
    $fixture = gapsGeography();
    $ownerA = gapsOwner('gaps_news_edit_scope_a');
    $ownerB = gapsOwner('gaps_news_edit_scope_b');
    $objectA = gapsMakeObject($fixture, $ownerA->id);
    $objectB = gapsMakeObject($fixture, $ownerB->id);
    $newsA = gapsMakeNewsItem($objectA);
    $newsB = gapsMakeNewsItem($objectB);

    test()->actingAs($ownerA)
        ->get(NewsItemResource::getUrl('edit', ['record' => $newsB], panel: 'cabinet', tenant: $objectA))
        ->assertNotFound();

    test()->actingAs($ownerA)
        ->get(NewsItemResource::getUrl('edit', ['record' => $newsA], panel: 'cabinet', tenant: $objectA))
        ->assertSuccessful();
});

/*
|--------------------------------------------------------------------------
| EditPromotion
|--------------------------------------------------------------------------
*/

it('applies a promotion edit directly when it is not yet live, regardless of the object\'s resolved moderation mode', function (): void {
    $fixture = gapsGeography();
    $owner = gapsOwner('gaps_promo_edit_not_live');
    $object = gapsMakeObject($fixture, $owner->id);
    gapsSetModerationMode($object->id, 'review');
    $promotion = gapsMakePromotion($object, ['status' => 'draft', 'moderation_status' => null]);
    gapsMountTenant($object);

    Livewire::actingAs($owner)
        ->test(EditPromotion::class, ['record' => $promotion->getKey()])
        ->fillForm([
            'title' => 'Updated Draft Promo',
            'description' => 'Updated promo summary.',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addDays(10)->toDateString(),
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $row = DB::table('promotions')->where('id', $promotion->id)->first();

    expect($row->status)->toBe('draft')
        ->and($row->moderation_status)->toBeNull();

    expect(DB::table('promotion_translations')->where('promotion_id', $promotion->id)->where('locale', 'en')->value('title'))
        ->toBe('Updated Draft Promo');

    expect(DB::table('moderation_requests')->where('target_id', $promotion->id)->where('target_type', Promotion::class)->count())
        ->toBe(0);
});

it('applies a promotion edit directly under immediate mode when it is already live, keeping it published', function (): void {
    $fixture = gapsGeography();
    $owner = gapsOwner('gaps_promo_edit_immediate');
    $object = gapsMakeObject($fixture, $owner->id);
    gapsSetModerationMode($object->id, 'immediate');
    $promotion = gapsMakePromotion($object);
    gapsMountTenant($object);

    $test = Livewire::actingAs($owner)
        ->test(EditPromotion::class, ['record' => $promotion->getKey()])
        ->fillForm([
            'title' => 'Refreshed Promo',
            'description' => 'Refreshed promo summary.',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addDays(20)->toDateString(),
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $row = DB::table('promotions')->where('id', $promotion->id)->first();

    expect($row->status)->toBe('published')
        ->and($row->moderation_status)->toBe('approved');

    expect(DB::table('promotion_translations')->where('promotion_id', $promotion->id)->where('locale', 'en')->value('title'))
        ->toBe('Refreshed Promo');

    expect(DB::table('moderation_requests')->where('target_id', $promotion->id)->where('target_type', Promotion::class)->count())
        ->toBe(0);

    $method = new ReflectionMethod(EditPromotion::class, 'getSavedNotificationTitle');
    expect($method->invoke($test->instance()))->toBe(__('panel.cabinet.promotions.lifecycle.published'));
});

it('withholds a promotion edit behind a pending ModerationRequest under review mode, leaving the published record untouched', function (): void {
    $fixture = gapsGeography();
    $owner = gapsOwner('gaps_promo_edit_review');
    $object = gapsMakeObject($fixture, $owner->id);
    gapsSetModerationMode($object->id, 'review');
    $promotion = gapsMakePromotion($object);
    gapsMountTenant($object);

    $test = Livewire::actingAs($owner)->test(EditPromotion::class, ['record' => $promotion->getKey()]);

    $test->assertFormSet(['title' => 'Original Promo', 'description' => 'Original promo summary.']);

    $test->fillForm([
        'title' => 'Withheld Promo',
        'description' => 'Withheld promo summary.',
        'starts_at' => now()->toDateString(),
        'ends_at' => now()->addDays(15)->toDateString(),
    ])
        ->call('save')
        ->assertHasNoFormErrors();

    $row = DB::table('promotions')->where('id', $promotion->id)->first();

    expect($row->status)->toBe('published')
        ->and($row->moderation_status)->toBe('approved');

    expect(DB::table('promotion_translations')->where('promotion_id', $promotion->id)->where('locale', 'en')->value('title'))
        ->toBe('Original Promo');

    $request = DB::table('moderation_requests')->where('target_id', $promotion->id)->where('target_type', Promotion::class)->first();

    expect($request)->not->toBeNull()
        ->and($request->decision)->toBe('pending')
        ->and($request->section)->toBe('promotions')
        ->and($request->submitted_by)->toBe($owner->id);

    $previous = json_decode((string) $request->previous_data, true);
    $proposed = json_decode((string) $request->proposed_data, true);

    expect($previous['en']['title'])->toBe('Original Promo')
        ->and($proposed['en']['title'])->toBe('Withheld Promo')
        ->and($proposed)->not->toHaveKey('status')
        ->and($proposed)->not->toHaveKey('moderation_status');

    $method = new ReflectionMethod(EditPromotion::class, 'getSavedNotificationTitle');
    expect($method->invoke($test->instance()))->toBe(__('panel.cabinet.promotions.lifecycle.submitted_for_review'));
});

it("refuses editing another owner's promotion through the real edit route, even for an already-live record", function (): void {
    $fixture = gapsGeography();
    $ownerA = gapsOwner('gaps_promo_edit_scope_a');
    $ownerB = gapsOwner('gaps_promo_edit_scope_b');
    $objectA = gapsMakeObject($fixture, $ownerA->id);
    $objectB = gapsMakeObject($fixture, $ownerB->id);
    $promoA = gapsMakePromotion($objectA);
    $promoB = gapsMakePromotion($objectB);

    test()->actingAs($ownerA)
        ->get(PromotionResource::getUrl('edit', ['record' => $promoB], panel: 'cabinet', tenant: $objectA))
        ->assertNotFound();

    test()->actingAs($ownerA)
        ->get(PromotionResource::getUrl('edit', ['record' => $promoA], panel: 'cabinet', tenant: $objectA))
        ->assertSuccessful();
});

/*
|--------------------------------------------------------------------------
| NotificationResource
|--------------------------------------------------------------------------
*/

it('never registers a create page and refuses canCreate, since notifications are always system-generated', function (): void {
    expect(NotificationResource::canCreate())->toBeFalse()
        ->and(array_keys(NotificationResource::getPages()))->toBe(['index']);
});

it("renders only the signed-in owner's own notifications in the inbox, never another owner's", function (): void {
    $fixture = gapsGeography();
    $ownerA = gapsOwner('gaps_notification_render_a');
    $ownerB = gapsOwner('gaps_notification_render_b');
    $objectA = gapsMakeObject($fixture, $ownerA->id);
    gapsMakeObject($fixture, $ownerB->id);

    $typeId = DB::table('notification_types')->insertGetId([
        'key' => 'gaps_probe_type', 'class' => 'optional',
        'default_channels' => json_encode(['inbox']),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $ownNotificationId = DB::table('notifications')->insertGetId([
        'recipient_id' => $ownerA->id, 'notification_type_id' => $typeId,
        'title' => 'For owner A', 'body' => '', 'locale' => 'en',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $otherNotificationId = DB::table('notifications')->insertGetId([
        'recipient_id' => $ownerB->id, 'notification_type_id' => $typeId,
        'title' => 'For owner B', 'body' => '', 'locale' => 'en',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // Without an explicit panel context, Filament resolves the current
    // panel to its default (the staff panel), and the page's own chrome
    // then tries to build an admin-panel notifications route that does not
    // exist — this resource is registered on the cabinet panel only. The
    // cabinet panel is tenanted by Object_ (see CabinetPanelProvider), so
    // its own route generation also needs a bound tenant, not just a panel.
    Filament::setCurrentPanel(Filament::getPanel('cabinet'));
    Filament::setTenant($objectA, isQuiet: true);
    Filament::bootCurrentPanel();

    Livewire::actingAs($ownerA)
        ->test(ListNotifications::class)
        ->assertCanSeeTableRecords([$ownNotificationId])
        ->assertCanNotSeeTableRecords([$otherNotificationId]);
});

it('fails closed to an empty result set when no authenticated actor can be resolved, rather than the unnarrowed query', function (): void {
    $fixture = gapsGeography();
    $owner = gapsOwner('gaps_notification_no_actor');
    gapsMakeObject($fixture, $owner->id);

    $typeId = DB::table('notification_types')->insertGetId([
        'key' => 'gaps_probe_type_no_actor', 'class' => 'optional',
        'default_channels' => json_encode(['inbox']),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('notifications')->insert([
        'recipient_id' => $owner->id, 'notification_type_id' => $typeId,
        'title' => 'Unreachable without an actor', 'body' => '', 'locale' => 'en',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // Deliberately no actingAs() call: Filament::auth()->user() resolves to
    // null, which must fall back to the permissive-refusing `1 = 0` branch
    // rather than the unnarrowed query — the exact fallback ScopedResource
    // uses for the identical reason.
    expect(NotificationResource::getEloquentQuery()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| PhotoResource
|--------------------------------------------------------------------------
*/

it('never registers a create page and refuses canCreate, since upload is a table action rather than a form', function (): void {
    expect(PhotoResource::canCreate())->toBeFalse()
        ->and(array_keys(PhotoResource::getPages()))->toBe(['index']);
});

it("scopes its query to the tenant's own photos and eager-loads the owning object, firing no extra query per row", function (): void {
    Storage::fake('public');

    $fixture = gapsGeography();
    $ownerA = gapsOwner('gaps_photo_scope_a');
    $ownerB = gapsOwner('gaps_photo_scope_b');
    $objectA = gapsMakeObject($fixture, $ownerA->id);
    $objectB = gapsMakeObject($fixture, $ownerB->id);

    $objectA->addMedia(UploadedFile::fake()->image('a1.jpg'))->toMediaCollection('photos');
    $objectA->addMedia(UploadedFile::fake()->image('a2.jpg'))->toMediaCollection('photos');
    $objectA->addMedia(UploadedFile::fake()->image('a3.jpg'))->toMediaCollection('photos');
    $objectB->addMedia(UploadedFile::fake()->image('b1.jpg'))->toMediaCollection('photos');

    // Registers Filament's own tenant-scope global scope for the current
    // process, the same real-request prerequisite the rest of the cabinet
    // suite relies on before calling a tenant-scoped resource's static
    // query directly.
    test()->actingAs($ownerA)
        ->get(PhotoResource::getUrl('index', panel: 'cabinet', tenant: $objectA))
        ->assertSuccessful();

    gapsMountTenant($objectA);

    DB::enableQueryLog();
    $photos = PhotoResource::getEloquentQuery()->get();
    DB::flushQueryLog();

    foreach ($photos as $photo) {
        $photo->object?->owner_id;
    }
    $extraQueries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($photos)->toHaveCount(3)
        // ObjectPhoto is a subclass of Spatie's polymorphic Media model —
        // its own foreign key back to the owning object is `model_id`, not
        // `object_id`.
        ->and($photos->pluck('model_id')->unique()->all())->toBe([$objectA->id])
        ->and($extraQueries)->toBeEmpty();
});

/*
|--------------------------------------------------------------------------
| ReviewResource
|--------------------------------------------------------------------------
*/

it('never registers a create page and refuses canCreate, since review submission is a visitor-facing concern', function (): void {
    expect(ReviewResource::canCreate())->toBeFalse()
        ->and(array_keys(ReviewResource::getPages()))->toBe(['index']);
});

it("scopes its query to the tenant's own reviews and eager-loads the owning object, firing no extra query per row", function (): void {
    $fixture = gapsGeography();
    $ownerA = gapsOwner('gaps_review_scope_a');
    $ownerB = gapsOwner('gaps_review_scope_b');
    $objectA = gapsMakeObject($fixture, $ownerA->id);
    $objectB = gapsMakeObject($fixture, $ownerB->id);

    foreach (['First', 'Second', 'Third'] as $name) {
        DB::table('reviews')->insert([
            'object_id' => $objectA->id, 'rating' => 5, 'body' => "{$name} review body.",
            'author_name' => $name, 'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    DB::table('reviews')->insert([
        'object_id' => $objectB->id, 'rating' => 4, 'body' => 'Owner B review body.',
        'author_name' => 'Other', 'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
    ]);

    test()->actingAs($ownerA)
        ->get(ReviewResource::getUrl('index', panel: 'cabinet', tenant: $objectA))
        ->assertSuccessful();

    gapsMountTenant($objectA);

    DB::enableQueryLog();
    $reviews = ReviewResource::getEloquentQuery()->get();
    DB::flushQueryLog();

    foreach ($reviews as $review) {
        $review->object?->owner_id;
    }
    $extraQueries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($reviews)->toHaveCount(3)
        ->and($reviews->pluck('object_id')->unique()->all())->toBe([$objectA->id])
        ->and($extraQueries)->toBeEmpty();
});

/*
|--------------------------------------------------------------------------
| ServiceForm
|--------------------------------------------------------------------------
*/

it('renders exactly one placeholder section, with no checkbox list at all, when the object\'s type has no applicable service group', function (): void {
    $fixture = gapsGeography();
    $typeWithNoGroups = DB::table('object_types')->insertGetId([
        'key' => 'no_services_type', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $owner = gapsOwner('gaps_service_form_empty');
    $objectId = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(), 'owner_id' => $owner->id, 'object_type_id' => $typeWithNoGroups,
        'territory_id' => $fixture['territoryId'], 'country_id' => $fixture['countryId'], 'status' => 'draft',
        'latitude' => 45.1234500, 'longitude' => 28.1234500, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => 'No Services Object',
        'slug' => 'no-services-object-'.$objectId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    /** @var Object_ $object */
    $object = Object_::query()->withoutGlobalScopes()->findOrFail($objectId);
    gapsMountTenant($object);

    $page = Livewire::actingAs($owner)
        ->test(EditService::class, ['record' => $object->getKey()])
        ->assertSee(__('panel.cabinet.services.empty'))
        ->instance();

    // No selectable field at all: the schema resolved to the placeholder
    // branch, not a checkbox list with zero groups rendered.
    expect($page->getSchema('form')->getFlatFields())->toBeEmpty();
});

it('falls back to a "#id" label for a service group or amenity missing its own translation row', function (): void {
    $fixture = gapsGeography();
    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'gaps_untranslated_type', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    // Deliberately no amenity_group_translations / amenity_translations row
    // for either — the exact gap `AmenityGroup::name`/`Amenity::name` return
    // null for, which the form's own `?? "#{$id}"` fallback must cover.
    $groupId = DB::table('amenity_groups')->insertGetId([
        'is_active' => true, 'display_order' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('amenity_group_object_type')->insert(['amenity_group_id' => $groupId, 'object_type_id' => $typeId]);

    $amenityId = DB::table('amenities')->insertGetId([
        'amenity_group_id' => $groupId, 'is_filterable' => true, 'is_active' => true,
        'display_order' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $owner = gapsOwner('gaps_service_form_fallback');
    $objectId = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(), 'owner_id' => $owner->id, 'object_type_id' => $typeId,
        'territory_id' => $fixture['territoryId'], 'country_id' => $fixture['countryId'], 'status' => 'draft',
        'latitude' => 45.1234500, 'longitude' => 28.1234500, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => 'Untranslated Amenities Object',
        'slug' => 'untranslated-amenities-object-'.$objectId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    /** @var Object_ $object */
    $object = Object_::query()->withoutGlobalScopes()->findOrFail($objectId);
    gapsMountTenant($object);

    Livewire::actingAs($owner)
        ->test(EditService::class, ['record' => $object->getKey()])
        ->assertSee("#{$groupId}")
        ->assertSee("#{$amenityId}");
});
