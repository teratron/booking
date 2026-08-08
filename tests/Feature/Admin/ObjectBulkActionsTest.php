<?php

declare(strict_types=1);

use App\Exceptions\BulkSelectionScopeException;
use App\Filament\Admin\Resources\Objects\Pages\ListObjects;
use App\Filament\Admin\Resources\Objects\Tables\ObjectsTable;
use App\Filament\Admin\Support\CountedBulkAction;
use App\Jobs\ExecuteObjectBulkActionJob;
use App\Models\User;
use App\Services\Objects\ObjectBulkActionService;
use App\Services\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Object Bulk Actions
|--------------------------------------------------------------------------
|
| Four claims matter more than any individual operation: every one of the
| nine bulk operations actually mutates the database it claims to; a
| selection that reaches even one object outside the acting administrator's
| scope is refused as a whole, before anything is touched — a bug here means
| a scoped administrator's mistaken selection silently reaches records
| outside their authority; every operation journals one entry per affected
| record, not one per invocation; and a selection large enough to cross the
| configured threshold moves to a queued job instead of running inline in
| the request.
|
*/

function bulkGeography(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryMd = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true, 'display_order' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryUa = DB::table('countries')->insertGetId([
        'code' => 'UA', 'currency' => 'UAH', 'phone_code' => '+380',
        'primary_language_id' => $languageId, 'is_active' => true, 'display_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $levelMd = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryMd, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $levelUa = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryUa, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryMd = DB::table('territories')->insertGetId([
        'country_id' => $countryMd, 'level_id' => $levelMd, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryUa = DB::table('territories')->insertGetId([
        'country_id' => $countryUa, 'level_id' => $levelUa, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $typeStay = DB::table('object_types')->insertGetId([
        'key' => 'accommodation', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('languageId', 'countryMd', 'countryUa', 'territoryMd', 'territoryUa', 'typeStay');
}

/**
 * @param  array<string, mixed>  $geo
 * @param  array<string, mixed>  $overrides
 */
function bulkSeedObject(array $geo, array $overrides = []): int
{
    $objectId = DB::table('objects')->insertGetId(array_merge([
        'ulid' => (string) Str::ulid(),
        'owner_id' => User::factory()->create()->id,
        'object_type_id' => $geo['typeStay'],
        'territory_id' => $geo['territoryMd'],
        'country_id' => $geo['countryMd'],
        'status' => 'draft',
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));

    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en',
        'name' => 'Object #'.$objectId, 'slug' => 'object-'.$objectId,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $objectId;
}

function bulkActor(array $permissions, string $scopeKind, ?int $reference, string $roleKey): User
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
        'scope_kind' => $scopeKind, 'scope_reference_id' => $reference,
        'granted_by' => $user->id, 'granted_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $user->fresh();
}

/** @return list<string> */
function bulkFullPermissions(): array
{
    return ['admin_panel_access', 'object.view', 'object.edit', 'object.publish', 'object.delete', 'object.export'];
}

function bulkNotificationType(): int
{
    return DB::table('notification_types')->insertGetId([
        'key' => 'administration_message', 'class' => 'transactional',
        'default_channels' => json_encode(['email']),
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function bulkPromotionLabel(): int
{
    return DB::table('promotion_labels')->insertGetId([
        'border_colour' => '#000000', 'text_colour' => '#ffffff', 'background_colour' => '#ff0000',
        'position_on_card' => 'top-left', 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

// -----------------------------------------------------------------------
// Each of the nine operations, executed over a real selection.
// -----------------------------------------------------------------------

it('publishes a selection and journals one entry per object', function (): void {
    $geo = bulkGeography();
    $id1 = bulkSeedObject($geo, ['status' => 'draft']);
    $id2 = bulkSeedObject($geo, ['status' => 'draft']);
    $actor = bulkActor(bulkFullPermissions(), 'none', null, 'unrestricted');

    app(ObjectBulkActionService::class)->execute('change_status', [$id1, $id2], ['status' => 'published'], $actor);

    expect(DB::table('objects')->where('id', $id1)->value('status'))->toBe('published')
        ->and(DB::table('objects')->where('id', $id2)->value('status'))->toBe('published')
        ->and(DB::table('audits')->where('event', 'object_bulk_published')->count())->toBe(2)
        ->and(DB::table('audits')->where('event', 'object_bulk_published')->pluck('auditable_id')->sort()->values()->all())
        ->toBe([$id1, $id2]);
});

it('hides a selection and journals one entry per object', function (): void {
    $geo = bulkGeography();
    $id1 = bulkSeedObject($geo, ['status' => 'published']);
    $id2 = bulkSeedObject($geo, ['status' => 'published']);
    $actor = bulkActor(bulkFullPermissions(), 'none', null, 'unrestricted');

    app(ObjectBulkActionService::class)->execute('change_status', [$id1, $id2], ['status' => 'hidden'], $actor);

    expect(DB::table('objects')->where('id', $id1)->value('status'))->toBe('hidden')
        ->and(DB::table('objects')->where('id', $id2)->value('status'))->toBe('hidden')
        ->and(DB::table('audits')->where('event', 'object_bulk_hidden')->count())->toBe(2);
});

it('sets a selection to draft and journals one entry per object', function (): void {
    $geo = bulkGeography();
    $id1 = bulkSeedObject($geo, ['status' => 'published']);
    $id2 = bulkSeedObject($geo, ['status' => 'published']);
    $actor = bulkActor(bulkFullPermissions(), 'none', null, 'unrestricted');

    app(ObjectBulkActionService::class)->execute('change_status', [$id1, $id2], ['status' => 'draft'], $actor);

    expect(DB::table('objects')->where('id', $id1)->value('status'))->toBe('draft')
        ->and(DB::table('objects')->where('id', $id2)->value('status'))->toBe('draft')
        ->and(DB::table('audits')->where('event', 'object_bulk_status_changed')->count())->toBe(2);
});

it('archives a selection as a soft delete, journalling exactly one entry per affected record', function (): void {
    $geo = bulkGeography();
    $ids = [
        bulkSeedObject($geo),
        bulkSeedObject($geo),
        bulkSeedObject($geo),
        bulkSeedObject($geo),
    ];
    $actor = bulkActor(bulkFullPermissions(), 'none', null, 'unrestricted');

    app(ObjectBulkActionService::class)->execute('archive', $ids, [], $actor);

    foreach ($ids as $id) {
        expect(DB::table('objects')->where('id', $id)->whereNotNull('deleted_at')->exists())->toBeTrue();
    }

    $auditedIds = DB::table('audits')->where('event', 'object_bulk_archived')->pluck('auditable_id')->sort()->values()->all();

    expect(DB::table('audits')->where('event', 'object_bulk_archived')->count())->toBe(4)
        ->and($auditedIds)->toBe($ids);
});

it('assigns a promotional label to every selected object, replacing any currently active grant', function (): void {
    $geo = bulkGeography();
    $labelId = bulkPromotionLabel();
    $id1 = bulkSeedObject($geo);
    $id2 = bulkSeedObject($geo);
    $actor = bulkActor(bulkFullPermissions(), 'none', null, 'unrestricted');

    app(ObjectBulkActionService::class)->execute('assign_promotion_label', [$id1, $id2], [
        'promotion_label_id' => $labelId,
        'starts_at' => now()->toDateString(),
        'ends_at' => now()->addDays(30)->toDateString(),
    ], $actor);

    expect(DB::table('object_promotions')->where('object_id', $id1)->where('promotion_label_id', $labelId)->exists())->toBeTrue()
        ->and(DB::table('object_promotions')->where('object_id', $id2)->where('promotion_label_id', $labelId)->exists())->toBeTrue()
        ->and(DB::table('audits')->where('event', 'object_bulk_promotion_assigned')->count())->toBe(2);
});

it('moves a selection to another territory and recomputes the denormalized country', function (): void {
    $geo = bulkGeography();
    $id1 = bulkSeedObject($geo);
    $id2 = bulkSeedObject($geo);
    $actor = bulkActor(bulkFullPermissions(), 'none', null, 'unrestricted');

    app(ObjectBulkActionService::class)->execute('move_territory', [$id1, $id2], [
        'territory_id' => $geo['territoryUa'],
    ], $actor);

    foreach ([$id1, $id2] as $id) {
        $row = DB::table('objects')->where('id', $id)->first();
        expect($row->territory_id)->toBe($geo['territoryUa'])
            ->and($row->country_id)->toBe($geo['countryUa']);
    }

    expect(DB::table('audits')->where('event', 'object_bulk_moved_territory')->count())->toBe(2);
});

it('attaches a manager to every selected object without detaching whoever is already attached', function (): void {
    $geo = bulkGeography();
    $id1 = bulkSeedObject($geo);
    $id2 = bulkSeedObject($geo);
    $existingStaff = User::factory()->create();
    DB::table('object_user')->insert([
        'object_id' => $id1, 'user_id' => $existingStaff->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $manager = User::factory()->create();
    $actor = bulkActor(bulkFullPermissions(), 'none', null, 'unrestricted');

    app(ObjectBulkActionService::class)->execute('assign_manager', [$id1, $id2], ['manager_id' => $manager->id], $actor);

    expect(DB::table('object_user')->where('object_id', $id1)->where('user_id', $manager->id)->exists())->toBeTrue()
        ->and(DB::table('object_user')->where('object_id', $id2)->where('user_id', $manager->id)->exists())->toBeTrue()
        ->and(DB::table('object_user')->where('object_id', $id1)->where('user_id', $existingStaff->id)->exists())->toBeTrue()
        ->and(DB::table('audits')->where('event', 'object_bulk_manager_assigned')->count())->toBe(2);
});

it('creates one notification per selected object, addressed to that object\'s own owner', function (): void {
    $geo = bulkGeography();
    bulkNotificationType();
    $owner1 = User::factory()->create();
    $owner2 = User::factory()->create();
    $id1 = bulkSeedObject($geo, ['owner_id' => $owner1->id]);
    $id2 = bulkSeedObject($geo, ['owner_id' => $owner2->id]);
    $actor = bulkActor(bulkFullPermissions(), 'none', null, 'unrestricted');

    app(ObjectBulkActionService::class)->execute('notify_owners', [$id1, $id2], [
        'title' => 'Maintenance notice',
        'body' => 'The catalog will be briefly unavailable tonight.',
    ], $actor);

    $notifications = DB::table('notifications')->orderBy('recipient_id')->get();

    expect($notifications)->toHaveCount(2)
        ->and($notifications->pluck('recipient_id')->sort()->values()->all())
        ->toBe(collect([$owner1->id, $owner2->id])->sort()->values()->all())
        ->and($notifications->first()->title)->toBe('Maintenance notice')
        ->and($notifications->first()->locale)->toBe('en')
        ->and(DB::table('audits')->where('event', 'object_bulk_owners_notified')->count())->toBe(2);
});

it('writes one CSV for the whole selection to the local disk and journals completion per object', function (): void {
    Storage::fake('local');
    $geo = bulkGeography();
    $id1 = bulkSeedObject($geo);
    $id2 = bulkSeedObject($geo);
    $actor = bulkActor(bulkFullPermissions(), 'none', null, 'unrestricted');

    app(ObjectBulkActionService::class)->execute('export', [$id1, $id2], [], $actor);

    $files = Storage::disk('local')->allFiles('exports');
    expect($files)->toHaveCount(1);

    $content = Storage::disk('local')->get($files[0]);
    expect($content)->toContain((string) $id1)
        ->and($content)->toContain((string) $id2)
        ->and(DB::table('audits')->where('event', 'object_bulk_exported')->count())->toBe(2);
});

// -----------------------------------------------------------------------
// Confirmation naming the affected count.
// -----------------------------------------------------------------------

it('demands a confirmation for every registered bulk action on the objects table', function (): void {
    $method = new ReflectionMethod(ObjectsTable::class, 'bulkActions');
    $method->setAccessible(true);

    /** @var list<CountedBulkAction> $actions */
    $actions = $method->invoke(null);

    expect($actions)->toHaveCount(10);

    foreach ($actions as $action) {
        expect($action)->toBeInstanceOf(CountedBulkAction::class)
            ->and($action->isConfirmationRequired())->toBeTrue()
            ->and($action->shouldDeselectRecordsAfterCompletion())->toBeTrue();
    }
});

it('names the real selected count in the confirmation when a bulk action is actually mounted over a selection', function (): void {
    $geo = bulkGeography();
    $ids = [bulkSeedObject($geo), bulkSeedObject($geo), bulkSeedObject($geo)];
    $actor = bulkActor(bulkFullPermissions(), 'none', null, 'unrestricted');

    foreach (['publish', 'archive'] as $actionName) {
        $test = Livewire::actingAs($actor)
            ->test(ListObjects::class)
            ->mountTableBulkAction($actionName, $ids)
            ->assertTableBulkActionHalted($actionName);

        $mounted = $test->instance()->getMountedAction();

        expect($mounted)->not->toBeNull()
            ->and($mounted->isConfirmationRequired())->toBeTrue()
            ->and((string) $mounted->getModalDescription())->toContain('3');
    }
});

// -----------------------------------------------------------------------
// Whole-batch scope rejection — the single most important assertion here.
// -----------------------------------------------------------------------

it('refuses a selection spanning outside the actor scope, mutating none of the in-scope objects either', function (): void {
    $geo = bulkGeography();
    $inScope1 = bulkSeedObject($geo, [
        'country_id' => $geo['countryMd'], 'territory_id' => $geo['territoryMd'], 'status' => 'draft',
    ]);
    $inScope2 = bulkSeedObject($geo, [
        'country_id' => $geo['countryMd'], 'territory_id' => $geo['territoryMd'], 'status' => 'draft',
    ]);
    $outOfScope = bulkSeedObject($geo, [
        'country_id' => $geo['countryUa'], 'territory_id' => $geo['territoryUa'], 'status' => 'draft',
    ]);

    $actor = bulkActor(['admin_panel_access', 'object.view', 'object.edit'], 'country', $geo['countryMd'], 'md_only');

    $attempt = fn () => app(ObjectBulkActionService::class)->execute(
        'change_status',
        [$inScope1, $inScope2, $outOfScope],
        ['status' => 'hidden'],
        $actor,
    );

    expect($attempt)->toThrow(BulkSelectionScopeException::class);

    // Not one of the in-scope objects was mutated either — a scoped
    // administrator's mistaken selection must never silently reach records
    // outside their own authority, but it must also never partially apply
    // to the records that WERE within it.
    expect(DB::table('objects')->where('id', $inScope1)->value('status'))->toBe('draft')
        ->and(DB::table('objects')->where('id', $inScope2)->value('status'))->toBe('draft')
        ->and(DB::table('objects')->where('id', $outOfScope)->value('status'))->toBe('draft')
        ->and(DB::table('audits')->where('event', 'object_bulk_hidden')->count())->toBe(0);
});

it('reports the correct in-scope and total counts on the refusal exception', function (): void {
    $geo = bulkGeography();
    $inScope = bulkSeedObject($geo, ['country_id' => $geo['countryMd'], 'territory_id' => $geo['territoryMd']]);
    $outOfScope1 = bulkSeedObject($geo, ['country_id' => $geo['countryUa'], 'territory_id' => $geo['territoryUa']]);
    $outOfScope2 = bulkSeedObject($geo, ['country_id' => $geo['countryUa'], 'territory_id' => $geo['territoryUa']]);

    $actor = bulkActor(['admin_panel_access', 'object.view', 'object.edit'], 'country', $geo['countryMd'], 'md_only');

    try {
        app(ObjectBulkActionService::class)->execute(
            'change_status',
            [$inScope, $outOfScope1, $outOfScope2],
            ['status' => 'hidden'],
            $actor,
        );
        $this->fail('Expected a BulkSelectionScopeException to be thrown.');
    } catch (BulkSelectionScopeException $exception) {
        expect($exception->outOfScopeCount)->toBe(2)
            ->and($exception->totalCount)->toBe(3);
    }
});

// -----------------------------------------------------------------------
// Threshold-driven inline vs. queued dispatch.
// -----------------------------------------------------------------------

it('dispatches to the queue when the selection exceeds the configured threshold', function (): void {
    Queue::fake();
    app(SettingsRepository::class)->set('back_office.bulk_queue_threshold', 2);

    $geo = bulkGeography();
    $ids = [bulkSeedObject($geo), bulkSeedObject($geo), bulkSeedObject($geo)];
    $actor = bulkActor(bulkFullPermissions(), 'none', null, 'unrestricted');

    Livewire::actingAs($actor)
        ->test(ListObjects::class)
        ->callTableBulkAction('publish', $ids);

    Queue::assertPushed(ExecuteObjectBulkActionJob::class);

    // Queued, not executed inline — the request thread never touched the rows.
    foreach ($ids as $id) {
        expect(DB::table('objects')->where('id', $id)->value('status'))->toBe('draft');
    }
});

it('executes inline, without touching the queue, when the selection sits at or under the threshold', function (): void {
    Queue::fake();
    app(SettingsRepository::class)->set('back_office.bulk_queue_threshold', 10);

    $geo = bulkGeography();
    $id1 = bulkSeedObject($geo, ['status' => 'published']);
    $id2 = bulkSeedObject($geo, ['status' => 'published']);
    $actor = bulkActor(bulkFullPermissions(), 'none', null, 'unrestricted');

    Livewire::actingAs($actor)
        ->test(ListObjects::class)
        ->callTableBulkAction('hide', [$id1, $id2]);

    Queue::assertNotPushed(ExecuteObjectBulkActionJob::class);
    expect(DB::table('objects')->where('id', $id1)->value('status'))->toBe('hidden')
        ->and(DB::table('objects')->where('id', $id2)->value('status'))->toBe('hidden');
});

it('always dispatches notify_owners to the queue, even for a selection well under the threshold', function (): void {
    Queue::fake();
    bulkNotificationType();

    $geo = bulkGeography();
    $id1 = bulkSeedObject($geo);
    $actor = bulkActor(bulkFullPermissions(), 'none', null, 'unrestricted');

    Livewire::actingAs($actor)
        ->test(ListObjects::class)
        ->callTableBulkAction('notify_owners', [$id1], ['title' => 'Hi', 'body' => 'Body text']);

    Queue::assertPushed(ExecuteObjectBulkActionJob::class, fn (ExecuteObjectBulkActionJob $job): bool => true);
    expect(DB::table('notifications')->count())->toBe(0);
});

it('always dispatches export to the queue, even for a selection well under the threshold', function (): void {
    Queue::fake();

    $geo = bulkGeography();
    $id1 = bulkSeedObject($geo);
    $actor = bulkActor(bulkFullPermissions(), 'none', null, 'unrestricted');

    Livewire::actingAs($actor)
        ->test(ListObjects::class)
        ->callTableBulkAction('export', [$id1]);

    Queue::assertPushed(ExecuteObjectBulkActionJob::class);
    expect(DB::table('audits')->where('event', 'object_bulk_exported')->count())->toBe(0);
});
