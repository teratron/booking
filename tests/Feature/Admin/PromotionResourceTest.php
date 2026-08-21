<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\Promotions\Pages\CreatePromotion;
use App\Filament\Admin\Resources\Promotions\Pages\EditPromotion;
use App\Filament\Admin\Resources\Promotions\Pages\ListPromotions;
use App\Filament\Admin\Resources\Promotions\PromotionResource;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use OwenIt\Auditing\Models\Audit;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Promotion Resource (Admin Panel)
|--------------------------------------------------------------------------
|
| The Filament pages reached through the staff panel — create/edit form
| submission with per-locale translations, the edit page's publish/
| schedule/archive/delete/restore header actions, the list page's export
| gating, and territory-scope narrowing. The lifecycle service itself and
| the bare model shape are covered elsewhere; this file exercises the same
| behaviour only insofar as the resource pages actually reach it over HTTP
| or Livewire.
|
*/

/** @return array{languageId: int, countryId: int, territoryId: int, objectTypeId: int} */
function promotionResourceGeography(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('languages')->insert([
        'code' => 'ru', 'short_label' => 'RU', 'is_active' => true, 'is_primary' => false,
        'display_order' => 1, 'created_at' => now(), 'updated_at' => now(),
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
    $objectTypeId = DB::table('object_types')->insertGetId([
        'key' => 'promotion_resource_probe_type', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('languageId', 'countryId', 'territoryId', 'objectTypeId');
}

/**
 * @param  array{languageId: int, countryId: int, territoryId: int, objectTypeId: int}  $geography
 */
function promotionResourceObjectId(array $geography): int
{
    return DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(),
        'owner_id' => User::factory()->create()->id,
        'object_type_id' => $geography['objectTypeId'],
        'territory_id' => $geography['territoryId'],
        'country_id' => $geography['countryId'],
        'status' => 'published',
        'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** @param  list<string>  $permissions */
function promotionResourceActor(array $permissions = ['admin_panel_access', 'content.view', 'content.create', 'content.edit', 'content.publish', 'content.delete'], string $roleKey = 'promotion_admin'): User
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

function promotionResourceLatestJournalEntry(string $event, int $promotionId): ?Audit
{
    return Audit::query()
        ->where('event', $event)
        ->where('auditable_type', Promotion::class)
        ->where('auditable_id', $promotionId)
        ->latest('id')
        ->first();
}

it('creates a promotion with per-locale translations through the resource', function (): void {
    $geography = promotionResourceGeography();
    $objectId = promotionResourceObjectId($geography);
    $actor = promotionResourceActor();

    Livewire::actingAs($actor)
        ->test(CreatePromotion::class)
        ->fillForm([
            'object_id' => $objectId,
            'territory_id' => $geography['territoryId'],
            'status' => 'draft',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'translations' => [
                'en' => ['title' => 'Summer Discount', 'summary' => 'Twenty percent off all summer bookings.', 'slug' => 'summer-discount'],
                'ru' => ['title' => 'Летняя скидка', 'summary' => 'Скидка двадцать процентов.', 'slug' => 'letnyaya-skidka'],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    /** @var Promotion $promotion */
    $promotion = Promotion::withUnmoderated()->where('object_id', $objectId)->firstOrFail();

    expect($promotion->territory_id)->toBe($geography['territoryId'])
        ->and($promotion->status)->toBe('draft')
        ->and($promotion->moderation_status)->toBeNull()
        ->and($promotion->translate('en')->title)->toBe('Summer Discount')
        ->and($promotion->translate('en')->slug)->toBe('summer-discount')
        ->and($promotion->translate('ru')->title)->toBe('Летняя скидка')
        ->and($promotion->translate('ru')->slug)->toBe('letnyaya-skidka');
});

it('derives a slug from the title when none is given for a locale', function (): void {
    $geography = promotionResourceGeography();
    $objectId = promotionResourceObjectId($geography);
    $actor = promotionResourceActor();

    Livewire::actingAs($actor)
        ->test(CreatePromotion::class)
        ->fillForm([
            'object_id' => $objectId,
            'territory_id' => $geography['territoryId'],
            'status' => 'draft',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'translations' => [
                'en' => ['title' => 'Autumn Special', 'summary' => 'A short teaser.'],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    /** @var Promotion $promotion */
    $promotion = Promotion::withUnmoderated()->where('object_id', $objectId)->firstOrFail();

    expect($promotion->translate('en')->slug)->toBe('autumn-special');
});

it('loads existing translations into the edit form and updates one locale, leaving the other untouched', function (): void {
    $geography = promotionResourceGeography();
    $objectId = promotionResourceObjectId($geography);
    $promotionId = DB::table('promotions')->insertGetId([
        'object_id' => $objectId, 'territory_id' => $geography['territoryId'],
        'starts_at' => now()->toDateString(), 'ends_at' => now()->addMonth()->toDateString(),
        'status' => 'draft', 'moderation_status' => null,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('promotion_translations')->insert([
        ['promotion_id' => $promotionId, 'locale' => 'en', 'title' => 'Original EN', 'slug' => 'original-en', 'created_at' => now(), 'updated_at' => now()],
        ['promotion_id' => $promotionId, 'locale' => 'ru', 'title' => 'Original RU', 'slug' => 'original-ru', 'created_at' => now(), 'updated_at' => now()],
    ]);
    $actor = promotionResourceActor();

    Livewire::actingAs($actor)
        ->test(EditPromotion::class, ['record' => $promotionId])
        ->assertSchemaStateSet([
            'translations.en.title' => 'Original EN',
            'translations.ru.title' => 'Original RU',
        ])
        ->fillForm(['translations' => ['en' => ['title' => 'Updated EN']]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(DB::table('promotion_translations')->where('promotion_id', $promotionId)->where('locale', 'en')->value('title'))->toBe('Updated EN')
        ->and(DB::table('promotion_translations')->where('promotion_id', $promotionId)->where('locale', 'ru')->value('title'))->toBe('Original RU');
});

it('publishes a promotion via the header action, approving moderation and journalling the transition', function (): void {
    $geography = promotionResourceGeography();
    $objectId = promotionResourceObjectId($geography);
    $promotionId = DB::table('promotions')->insertGetId([
        'object_id' => $objectId, 'territory_id' => $geography['territoryId'],
        'starts_at' => now()->addDays(3)->toDateString(), 'ends_at' => now()->addMonth()->toDateString(),
        'status' => 'draft', 'moderation_status' => null,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $actor = promotionResourceActor();

    Livewire::actingAs($actor)
        ->test(EditPromotion::class, ['record' => $promotionId])
        ->callAction('publish');

    $fresh = Promotion::withUnmoderated()->findOrFail($promotionId);
    expect($fresh->status)->toBe('published')
        ->and($fresh->moderation_status)->toBe('approved')
        ->and($fresh->starts_at->isFuture())->toBeFalse();

    $entry = promotionResourceLatestJournalEntry('promotion_published', $promotionId);
    expect($entry)->not->toBeNull()
        ->and($entry->user_id)->toBe($actor->id);
});

it('schedules a promotion for a future start date via the header action', function (): void {
    $geography = promotionResourceGeography();
    $objectId = promotionResourceObjectId($geography);
    $promotionId = DB::table('promotions')->insertGetId([
        'object_id' => $objectId, 'territory_id' => $geography['territoryId'],
        'starts_at' => now()->toDateString(), 'ends_at' => now()->addMonth()->toDateString(),
        'status' => 'draft', 'moderation_status' => null,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $actor = promotionResourceActor();
    $futureDate = now()->addDays(10)->toDateString();

    Livewire::actingAs($actor)
        ->test(EditPromotion::class, ['record' => $promotionId])
        ->callAction('schedule', data: ['starts_at' => $futureDate]);

    $fresh = Promotion::withUnmoderated()->findOrFail($promotionId);
    expect($fresh->status)->toBe('scheduled')
        ->and($fresh->starts_at->toDateString())->toBe($futureDate);

    $entry = promotionResourceLatestJournalEntry('promotion_scheduled', $promotionId);
    expect($entry)->not->toBeNull()
        ->and($entry->new_values['status'])->toBe('scheduled');
});

it('refuses to schedule a promotion for a past date and surfaces a translated notification, leaving the record unchanged', function (): void {
    $geography = promotionResourceGeography();
    $objectId = promotionResourceObjectId($geography);
    $promotionId = DB::table('promotions')->insertGetId([
        'object_id' => $objectId, 'territory_id' => $geography['territoryId'],
        'starts_at' => now()->toDateString(), 'ends_at' => now()->addMonth()->toDateString(),
        'status' => 'draft', 'moderation_status' => null,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $actor = promotionResourceActor();

    Livewire::actingAs($actor)
        ->test(EditPromotion::class, ['record' => $promotionId])
        ->callAction('schedule', data: ['starts_at' => now()->subDay()->toDateString()])
        ->assertNotified(__('panel.promotions.lifecycle.schedule_refused'));

    expect(Promotion::withUnmoderated()->findOrFail($promotionId)->status)->toBe('draft')
        ->and(promotionResourceLatestJournalEntry('promotion_scheduled', $promotionId))->toBeNull();
});

it('archives a promotion via the header action and journals it', function (): void {
    $geography = promotionResourceGeography();
    $objectId = promotionResourceObjectId($geography);
    $promotionId = DB::table('promotions')->insertGetId([
        'object_id' => $objectId, 'territory_id' => $geography['territoryId'],
        'starts_at' => now()->subDay()->toDateString(), 'ends_at' => now()->addMonth()->toDateString(),
        'status' => 'published', 'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $actor = promotionResourceActor();

    Livewire::actingAs($actor)
        ->test(EditPromotion::class, ['record' => $promotionId])
        ->callAction('archive');

    expect(Promotion::withUnmoderated()->findOrFail($promotionId)->status)->toBe('archived');

    $entry = promotionResourceLatestJournalEntry('promotion_archived', $promotionId);
    expect($entry)->not->toBeNull()
        ->and($entry->old_values['status'])->toBe('published');
});

it('soft-deletes a promotion via the header action, exposes the restore action, and restores it', function (): void {
    $geography = promotionResourceGeography();
    $objectId = promotionResourceObjectId($geography);
    $promotionId = DB::table('promotions')->insertGetId([
        'object_id' => $objectId, 'territory_id' => $geography['territoryId'],
        'starts_at' => now()->subDay()->toDateString(), 'ends_at' => now()->addMonth()->toDateString(),
        'status' => 'published', 'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $actor = promotionResourceActor();

    Livewire::actingAs($actor)
        ->test(EditPromotion::class, ['record' => $promotionId])
        ->assertActionHidden('restore')
        ->callAction('delete')
        ->assertActionVisible('restore')
        ->callAction('restore');

    expect(Promotion::withUnmoderated()->withTrashed()->findOrFail($promotionId)->trashed())->toBeFalse();

    $deleteEntry = promotionResourceLatestJournalEntry('promotion_deleted', $promotionId);
    $restoreEntry = promotionResourceLatestJournalEntry('promotion_restored', $promotionId);
    expect($deleteEntry)->not->toBeNull()
        ->and($restoreEntry)->not->toBeNull()
        ->and($restoreEntry->user_id)->toBe($actor->id);
});

it('keeps a soft-deleted promotion\'s edit page reachable, unlike the default moderation-scoped query', function (): void {
    $geography = promotionResourceGeography();
    $objectId = promotionResourceObjectId($geography);
    $promotionId = DB::table('promotions')->insertGetId([
        'object_id' => $objectId, 'territory_id' => $geography['territoryId'],
        'starts_at' => now()->subMonth()->toDateString(), 'ends_at' => now()->subDay()->toDateString(),
        'status' => 'archived', 'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $actor = promotionResourceActor();

    Promotion::withUnmoderated()->findOrFail($promotionId)->delete();

    expect(Promotion::query()->find($promotionId))->toBeNull();

    test()->actingAs($actor)
        ->get(PromotionResource::getUrl('edit', ['record' => $promotionId], panel: 'admin'))
        ->assertSuccessful();
});

it('hides the export action from an actor without the export permission and shows it to one who holds it', function (): void {
    promotionResourceGeography();
    $viewer = promotionResourceActor(['admin_panel_access', 'content.view'], 'promotion_viewer');

    Livewire::actingAs($viewer)
        ->test(ListPromotions::class)
        ->assertActionHidden('export');

    $exporter = promotionResourceActor(['admin_panel_access', 'content.view', 'content.export'], 'promotion_exporter');

    Livewire::actingAs($exporter)
        ->test(ListPromotions::class)
        ->assertActionVisible('export');
});

it('narrows the promotion list to an actor\'s own territory scope', function (): void {
    $geography = promotionResourceGeography();
    $otherTerritoryId = DB::table('territories')->insertGetId([
        'country_id' => $geography['countryId'], 'level_id' => DB::table('territory_levels')->where('country_id', $geography['countryId'])->value('id'),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $objectId = promotionResourceObjectId($geography);

    DB::table('promotions')->insert([
        'object_id' => $objectId, 'territory_id' => $geography['territoryId'],
        'starts_at' => now()->toDateString(), 'ends_at' => now()->addMonth()->toDateString(),
        'status' => 'published', 'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('promotions')->insert([
        'object_id' => $objectId, 'territory_id' => $otherTerritoryId,
        'starts_at' => now()->toDateString(), 'ends_at' => now()->addMonth()->toDateString(),
        'status' => 'published', 'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $unrestricted = promotionResourceActor(['admin_panel_access', 'content.view'], 'promotion_unrestricted');
    test()->actingAs($unrestricted);
    expect(PromotionResource::getEloquentQuery()->count())->toBe(2);

    Permission::findOrCreate('admin_panel_access', 'web');
    Permission::findOrCreate('content.view', 'web');
    $scopedRole = Role::findOrCreate('promotion_territory_scoped', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $scopedRole->syncPermissions(['admin_panel_access', 'content.view']);
    $scoped = User::factory()->create();
    $scoped->assignRole($scopedRole);
    DB::table('role_scopes')->insert([
        'user_id' => $scoped->id, 'role_id' => $scopedRole->id,
        'scope_kind' => 'territory', 'scope_reference_id' => $geography['territoryId'],
        'granted_by' => $scoped->id, 'granted_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    test()->actingAs($scoped->fresh());
    expect(PromotionResource::getEloquentQuery()->count())->toBe(1);
});
