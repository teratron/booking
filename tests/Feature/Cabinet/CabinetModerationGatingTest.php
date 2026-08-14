<?php

declare(strict_types=1);

use App\Filament\Cabinet\Resources\NewsItems\Pages\CreateNewsItem;
use App\Filament\Cabinet\Resources\Objects\Pages\EditObject;
use App\Filament\Cabinet\Resources\Promotions\Pages\CreatePromotion;
use App\Models\NewsItem;
use App\Models\Object_;
use App\Models\Promotion;
use App\Models\User;
use App\Services\Cabinet\AvailabilityToggleService;
use App\Services\Moderation\ModerationModeResolver;
use App\Services\Moderation\ModerationPipeline;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Cabinet Moderation Gating & Availability Bypass Invariant
|--------------------------------------------------------------------------
|
| T-4B01 (object editing) and T-4C01 (news/promotions) each already prove
| their own moderation routing in isolation — one fixture per mode, never
| switched mid-test. What only this task proves is that the SAME scope's
| resolved mode genuinely drives behaviour live: an edit queued under review
| mode, followed immediately by another edit to the identical scope now
| switched to immediate, must apply directly — not merely that two
| differently-configured fixtures each behave as their own fixture would
| predict. The availability toggle's own bypass gets the same treatment,
| plus a structural falsification: rebinding the moderation collaborators to
| throw and confirming the toggle still succeeds untouched, run specifically
| with review mode active for the same scope, not an unset default.
|
*/

/** @return array{countryId: int, territoryId: int, typeId: int} */
function cabinetModerationGatingGeography(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryId = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true,
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

function cabinetModerationGatingOwner(string $roleKey): User
{
    $permissions = ['object.view', 'object.edit', 'content.view', 'content.create', 'content.edit', 'cabinet_access'];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate($roleKey, 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user->fresh();
}

/** @param  array{countryId: int, territoryId: int, typeId: int}  $fixture */
function cabinetModerationGatingMakeObject(array $fixture, int $ownerId): Object_
{
    $objectId = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(),
        'owner_id' => $ownerId,
        'object_type_id' => $fixture['typeId'],
        'territory_id' => $fixture['territoryId'],
        'country_id' => $fixture['countryId'],
        'status' => 'published', 'moderation_status' => 'approved',
        'availability_status' => 'available',
        'latitude' => 45.1234500, 'longitude' => 28.1234500,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => 'Original Villa',
        'slug' => 'original-villa-'.$objectId, 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Object_ $object */
    $object = Object_::query()->withUnmoderated()->findOrFail($objectId);

    return $object;
}

/**
 * Updates the scope's mode in place rather than inserting a second row —
 * `moderation_settings` carries a unique `(scope_level, scope_reference_id)`
 * constraint, and `ModerationModeResolver::resolve()` reads it with no
 * `ORDER BY`, so a second row for the same scope would be an ambiguous,
 * not merely a redundant, way to switch modes mid-test.
 */
function cabinetModerationGatingSetMode(int $objectId, string $mode): void
{
    DB::table('moderation_settings')->updateOrInsert(
        ['scope_level' => 'object', 'scope_reference_id' => $objectId],
        ['mode' => $mode, 'set_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    );
}

function cabinetModerationGatingMountTenant(Object_ $object): void
{
    Filament::setCurrentPanel(Filament::getPanel('cabinet'));
    Filament::bootCurrentPanel();
    Filament::setTenant($object, isQuiet: true);
}

it("routes an owner's object edit to the queue under review mode, then applies the next edit directly once the same scope switches to immediate", function (): void {
    $fixture = cabinetModerationGatingGeography();
    $owner = cabinetModerationGatingOwner('gating_owner_object_edit');
    $object = cabinetModerationGatingMakeObject($fixture, $owner->id);
    cabinetModerationGatingMountTenant($object);

    cabinetModerationGatingSetMode($object->id, 'review');

    Livewire::actingAs($owner)
        ->test(EditObject::class, ['record' => $object->getKey()])
        ->fillForm(['translations' => ['en' => ['name' => 'Renamed Under Review']]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(DB::table('object_translations')->where('object_id', $object->id)->where('locale', 'en')->value('name'))
        ->toBe('Original Villa')
        ->and(DB::table('moderation_requests')->where('target_id', $object->id)->where('target_type', Object_::class)->count())
        ->toBe(1);

    // Same scope, switched live — not a second, differently-configured
    // fixture standing in for "immediate mode".
    cabinetModerationGatingSetMode($object->id, 'immediate');

    Livewire::actingAs($owner)
        ->test(EditObject::class, ['record' => $object->getKey()])
        ->fillForm(['translations' => ['en' => ['name' => 'Renamed Under Immediate']]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(DB::table('object_translations')->where('object_id', $object->id)->where('locale', 'en')->value('name'))
        ->toBe('Renamed Under Immediate')
        // No second request — the immediate-mode edit never entered the queue.
        ->and(DB::table('moderation_requests')->where('target_id', $object->id)->where('target_type', Object_::class)->count())
        ->toBe(1);
});

it('routes a news submission to the queue under review mode, then applies the next submission directly once the same scope switches to immediate', function (): void {
    $fixture = cabinetModerationGatingGeography();
    $owner = cabinetModerationGatingOwner('gating_owner_news');
    $object = cabinetModerationGatingMakeObject($fixture, $owner->id);
    cabinetModerationGatingMountTenant($object);

    cabinetModerationGatingSetMode($object->id, 'review');

    Livewire::actingAs($owner)
        ->test(CreateNewsItem::class)
        ->fillForm(['title' => 'Queued Announcement', 'summary' => 'Under review.', 'body' => 'Withheld until approved.'])
        ->call('create')
        ->assertHasNoFormErrors();

    $queued = DB::table('news_items')->where('object_id', $object->id)->first();

    expect($queued->status)->toBe('draft')
        ->and($queued->moderation_status)->toBe('pending')
        ->and(DB::table('moderation_requests')->where('target_id', $queued->id)->where('target_type', NewsItem::class)->count())
        ->toBe(1);

    cabinetModerationGatingSetMode($object->id, 'immediate');

    Livewire::actingAs($owner)
        ->test(CreateNewsItem::class)
        ->fillForm(['title' => 'Live Announcement', 'summary' => 'Applied directly.', 'body' => 'Published immediately.'])
        ->call('create')
        ->assertHasNoFormErrors();

    $immediate = DB::table('news_items')->where('object_id', $object->id)->where('id', '!=', $queued->id)->first();

    expect($immediate->status)->toBe('published')
        ->and($immediate->moderation_status)->toBe('approved')
        ->and(DB::table('moderation_requests')->where('target_id', $immediate->id)->where('target_type', NewsItem::class)->count())
        ->toBe(0);
});

it('routes a promotion submission to the queue under review mode, then applies the next submission directly once the same scope switches to immediate', function (): void {
    $fixture = cabinetModerationGatingGeography();
    $owner = cabinetModerationGatingOwner('gating_owner_promotion');
    $object = cabinetModerationGatingMakeObject($fixture, $owner->id);
    cabinetModerationGatingMountTenant($object);

    cabinetModerationGatingSetMode($object->id, 'review');

    Livewire::actingAs($owner)
        ->test(CreatePromotion::class)
        ->fillForm(['title' => 'Queued Offer', 'description' => 'Under review.', 'starts_at' => '2026-09-01', 'ends_at' => '2026-09-30'])
        ->call('create')
        ->assertHasNoFormErrors();

    $queued = DB::table('promotions')->where('object_id', $object->id)->first();

    expect($queued->status)->toBe('draft')
        ->and($queued->moderation_status)->toBe('pending')
        ->and(DB::table('moderation_requests')->where('target_id', $queued->id)->where('target_type', Promotion::class)->count())
        ->toBe(1);

    cabinetModerationGatingSetMode($object->id, 'immediate');

    Livewire::actingAs($owner)
        ->test(CreatePromotion::class)
        ->fillForm(['title' => 'Live Offer', 'description' => 'Applied directly.', 'starts_at' => '2026-10-01', 'ends_at' => '2026-10-31'])
        ->call('create')
        ->assertHasNoFormErrors();

    $immediate = DB::table('promotions')->where('object_id', $object->id)->where('id', '!=', $queued->id)->first();

    expect($immediate->status)->toBe('published')
        ->and($immediate->moderation_status)->toBe('approved')
        ->and(DB::table('moderation_requests')->where('target_id', $immediate->id)->where('target_type', Promotion::class)->count())
        ->toBe(0);
});

it('bypasses moderation for the availability toggle even immediately after switching the same scope to review mode', function (): void {
    $fixture = cabinetModerationGatingGeography();
    $owner = cabinetModerationGatingOwner('gating_owner_availability');
    $object = cabinetModerationGatingMakeObject($fixture, $owner->id);
    cabinetModerationGatingMountTenant($object);

    cabinetModerationGatingSetMode($object->id, 'review');

    // Confirm review mode is genuinely active for this exact scope first —
    // an ordinary edit to the same object must queue, or the toggle's own
    // bypass below would prove nothing about a scope that was never really
    // gated to begin with.
    Livewire::actingAs($owner)
        ->test(EditObject::class, ['record' => $object->getKey()])
        ->fillForm(['translations' => ['en' => ['name' => 'Should Be Queued']]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(DB::table('moderation_requests')->where('target_id', $object->id)->where('target_type', Object_::class)->count())
        ->toBe(1);

    // The toggle, run immediately after, against the identical review-mode
    // scope: no new moderation request, and the write lands directly.
    app(AvailabilityToggleService::class)->toggle($object, $owner);

    expect($object->fresh()->availability_status)->toBe('unavailable')
        ->and(DB::table('moderation_requests')->where('target_id', $object->id)->where('target_type', Object_::class)->count())
        ->toBe(1);

    // Structural falsification, on top of the behavioural proof above: both
    // moderation collaborators are `final` (this codebase's own convention),
    // which rules out a Mockery class-name spy, so rebinding the container
    // slot to throw is the falsification this warrants — any resolution of
    // either class from the toggle path would blow up here, proving the
    // bypass is structural rather than a side effect of this particular
    // object happening not to trigger it.
    app()->bind(ModerationPipeline::class, function (): never {
        throw new RuntimeException('ModerationPipeline must never be resolved from an availability toggle, review mode or not.');
    });
    app()->bind(ModerationModeResolver::class, function (): never {
        throw new RuntimeException('ModerationModeResolver must never be resolved from an availability toggle, review mode or not.');
    });

    app(AvailabilityToggleService::class)->toggle($object, $owner);

    expect($object->fresh()->availability_status)->toBe('available');
});
