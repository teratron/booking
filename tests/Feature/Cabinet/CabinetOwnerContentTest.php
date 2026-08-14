<?php

declare(strict_types=1);

use App\Filament\Cabinet\Resources\NewsItems\Pages\CreateNewsItem;
use App\Filament\Cabinet\Resources\Promotions\Pages\CreatePromotion;
use App\Models\NewsItem;
use App\Models\Object_;
use App\Models\Promotion;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\RichEditor;
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
| Cabinet Owner Content Authoring (News & Promotions)
|--------------------------------------------------------------------------
|
| Both authoring screens are deliberately minimal — exactly five plain
| fields each, no rich editor, no layout control — and every submission
| routes through the same moderation pipeline the object-editing screen
| already exercises: published immediately, or withheld behind a pending
| ModerationRequest, per the mode resolved for the owner's own object. The
| immediate-mode and review-mode cases below are a falsifying pair on
| purpose, matching the object-editing suite's own proof shape — either one
| passing alone would not prove the branch, only that *some* path was taken.
|
*/

/** @return array{countryId: int, territoryId: int, typeId: int} */
function cabinetOwnerContentGeography(): array
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

function cabinetOwnerContentOwner(string $roleKey): User
{
    // The object_owner role's own real permission set for this screen —
    // content.view/create/edit plus the object/cabinet doors it already
    // held — not an inflated one.
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
function cabinetOwnerContentMakeObject(array $fixture, int $ownerId): Object_
{
    $objectId = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(),
        'owner_id' => $ownerId,
        'object_type_id' => $fixture['typeId'],
        'territory_id' => $fixture['territoryId'],
        'country_id' => $fixture['countryId'],
        'status' => 'published',
        'moderation_status' => 'approved',
        'latitude' => 45.1234500,
        'longitude' => 28.1234500,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => 'Seaside Villa',
        'slug' => 'seaside-villa-'.$objectId, 'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Object_ $object */
    $object = Object_::query()->withoutGlobalScopes()->findOrFail($objectId);

    return $object;
}

function cabinetOwnerContentSetMode(int $objectId, string $mode): void
{
    DB::table('moderation_settings')->insert([
        'scope_level' => 'object', 'scope_reference_id' => $objectId, 'mode' => $mode,
        'set_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function cabinetOwnerContentMountTenant(Object_ $object): void
{
    Filament::setCurrentPanel(Filament::getPanel('cabinet'));
    Filament::bootCurrentPanel();
    Filament::setTenant($object, isQuiet: true);
}

it("renders exactly the news form's own five fields — no rich editor, no layout control", function (): void {
    $fixture = cabinetOwnerContentGeography();
    $owner = cabinetOwnerContentOwner('owner_content_news_fields');
    $object = cabinetOwnerContentMakeObject($fixture, $owner->id);
    cabinetOwnerContentMountTenant($object);

    $page = Livewire::actingAs($owner)->test(CreateNewsItem::class)->instance();
    $fields = $page->getSchema('form')->getFlatFields();

    expect(collect($fields)->map->getName()->values()->all())->toBe(['title', 'summary', 'body', 'image', 'publish_at']);

    foreach ($fields as $field) {
        expect($field)->not->toBeInstanceOf(RichEditor::class)
            ->and($field)->not->toBeInstanceOf(MarkdownEditor::class);
    }
});

it("renders exactly the promotion form's own five fields — no rich editor, no layout control", function (): void {
    $fixture = cabinetOwnerContentGeography();
    $owner = cabinetOwnerContentOwner('owner_content_promotion_fields');
    $object = cabinetOwnerContentMakeObject($fixture, $owner->id);
    cabinetOwnerContentMountTenant($object);

    $page = Livewire::actingAs($owner)->test(CreatePromotion::class)->instance();
    $fields = $page->getSchema('form')->getFlatFields();

    expect(collect($fields)->map->getName()->values()->all())->toBe(['title', 'description', 'image', 'starts_at', 'ends_at']);

    foreach ($fields as $field) {
        expect($field)->not->toBeInstanceOf(RichEditor::class)
            ->and($field)->not->toBeInstanceOf(MarkdownEditor::class);
    }
});

it("publishes a news submission immediately under immediate mode, attributed to the owner's own object", function (): void {
    $fixture = cabinetOwnerContentGeography();
    $owner = cabinetOwnerContentOwner('owner_content_news_immediate');
    $object = cabinetOwnerContentMakeObject($fixture, $owner->id);
    cabinetOwnerContentSetMode($object->id, 'immediate');
    cabinetOwnerContentMountTenant($object);

    Livewire::actingAs($owner)
        ->test(CreateNewsItem::class)
        ->fillForm([
            'title' => 'Grand Reopening',
            'summary' => 'We are back.',
            'body' => 'Full details about the reopening celebration.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $row = DB::table('news_items')->where('object_id', $object->id)->first();

    expect($row)->not->toBeNull()
        ->and($row->status)->toBe('published')
        ->and($row->moderation_status)->toBe('approved')
        // Attribution: this row genuinely belongs to the submitting owner's
        // own object, inheriting its territory too.
        ->and($row->object_id)->toBe($object->id)
        ->and($row->territory_id)->toBe($object->territory_id);

    expect(DB::table('news_translations')->where('news_item_id', $row->id)->where('locale', 'en')->value('title'))
        ->toBe('Grand Reopening');

    expect(DB::table('moderation_requests')->where('target_id', $row->id)->where('target_type', NewsItem::class)->count())
        ->toBe(0);
});

it('withholds a news submission and enqueues a real pending ModerationRequest once the resolved mode is review — the falsifying counterpart', function (): void {
    $fixture = cabinetOwnerContentGeography();
    $owner = cabinetOwnerContentOwner('owner_content_news_review');
    $object = cabinetOwnerContentMakeObject($fixture, $owner->id);
    cabinetOwnerContentSetMode($object->id, 'review');
    cabinetOwnerContentMountTenant($object);

    Livewire::actingAs($owner)
        ->test(CreateNewsItem::class)
        ->fillForm([
            'title' => 'Grand Reopening',
            'summary' => 'We are back.',
            'body' => 'Full details about the reopening celebration.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $row = DB::table('news_items')->where('object_id', $object->id)->first();

    expect($row)->not->toBeNull()
        ->and($row->status)->toBe('draft')
        ->and($row->moderation_status)->toBe('pending')
        ->and($row->object_id)->toBe($object->id)
        ->and($row->territory_id)->toBe($object->territory_id);

    // Withheld: no translation exists on the live row — the authored
    // content lives only inside the pending request's own proposed_data
    // until a moderator approves it.
    expect(DB::table('news_translations')->where('news_item_id', $row->id)->exists())->toBeFalse();

    $request = DB::table('moderation_requests')->where('target_id', $row->id)->where('target_type', NewsItem::class)->first();

    expect($request)->not->toBeNull()
        ->and($request->decision)->toBe('pending')
        ->and($request->section)->toBe('news')
        ->and($request->submitted_by)->toBe($owner->id);

    $proposed = json_decode((string) $request->proposed_data, true);

    expect($proposed['status'])->toBe('published')
        ->and($proposed['moderation_status'])->toBe('approved')
        ->and($proposed['en']['title'])->toBe('Grand Reopening')
        ->and($proposed['en']['body'])->toBe('Full details about the reopening celebration.');
});

it("publishes a promotion submission immediately under immediate mode, attributed to the owner's own object", function (): void {
    $fixture = cabinetOwnerContentGeography();
    $owner = cabinetOwnerContentOwner('owner_content_promotion_immediate');
    $object = cabinetOwnerContentMakeObject($fixture, $owner->id);
    cabinetOwnerContentSetMode($object->id, 'immediate');
    cabinetOwnerContentMountTenant($object);

    Livewire::actingAs($owner)
        ->test(CreatePromotion::class)
        ->fillForm([
            'title' => 'Summer Discount',
            'description' => '20% off every weekday stay.',
            'starts_at' => '2026-09-01',
            'ends_at' => '2026-09-30',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $row = DB::table('promotions')->where('object_id', $object->id)->first();

    expect($row)->not->toBeNull()
        ->and($row->status)->toBe('published')
        ->and($row->moderation_status)->toBe('approved')
        ->and($row->object_id)->toBe($object->id)
        ->and($row->territory_id)->toBe($object->territory_id)
        ->and((string) $row->starts_at)->toBe('2026-09-01')
        ->and((string) $row->ends_at)->toBe('2026-09-30');

    expect(DB::table('promotion_translations')->where('promotion_id', $row->id)->where('locale', 'en')->value('title'))
        ->toBe('Summer Discount');

    expect(DB::table('moderation_requests')->where('target_id', $row->id)->where('target_type', Promotion::class)->count())
        ->toBe(0);
});

it('withholds a promotion submission and enqueues a real pending ModerationRequest once the resolved mode is review — the falsifying counterpart', function (): void {
    $fixture = cabinetOwnerContentGeography();
    $owner = cabinetOwnerContentOwner('owner_content_promotion_review');
    $object = cabinetOwnerContentMakeObject($fixture, $owner->id);
    cabinetOwnerContentSetMode($object->id, 'review');
    cabinetOwnerContentMountTenant($object);

    Livewire::actingAs($owner)
        ->test(CreatePromotion::class)
        ->fillForm([
            'title' => 'Summer Discount',
            'description' => '20% off every weekday stay.',
            'starts_at' => '2026-09-01',
            'ends_at' => '2026-09-30',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $row = DB::table('promotions')->where('object_id', $object->id)->first();

    expect($row)->not->toBeNull()
        ->and($row->status)->toBe('draft')
        ->and($row->moderation_status)->toBe('pending')
        ->and($row->object_id)->toBe($object->id)
        ->and($row->territory_id)->toBe($object->territory_id);

    expect(DB::table('promotion_translations')->where('promotion_id', $row->id)->exists())->toBeFalse();

    $request = DB::table('moderation_requests')->where('target_id', $row->id)->where('target_type', Promotion::class)->first();

    expect($request)->not->toBeNull()
        ->and($request->decision)->toBe('pending')
        ->and($request->section)->toBe('promotions')
        ->and($request->submitted_by)->toBe($owner->id);

    $proposed = json_decode((string) $request->proposed_data, true);

    expect($proposed['status'])->toBe('published')
        ->and($proposed['moderation_status'])->toBe('approved')
        ->and($proposed['en']['title'])->toBe('Summer Discount')
        ->and($proposed['en']['summary'])->toBe('20% off every weekday stay.');
});
