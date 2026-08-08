<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\ModerationRequests\ModerationRequestResource;
use App\Filament\Admin\Resources\ModerationRequests\Tables\ModerationRequestsTable;
use App\Models\ModerationRequest;
use App\Models\Object_;
use App\Models\User;
use App\Services\Moderation\ModerationQueueService;
use Filament\Facades\Filament;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Fixtures\Filament\TableHost;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Moderation Queue
|--------------------------------------------------------------------------
|
| The queue is scoped and filtered in the query, never in the view — the
| same posture every other panel list in this project takes — and its
| default ordering is oldest-pending-first, since any recency-first default
| quietly starves the entries that have waited longest.
|
*/

function moderationQueueGeography(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $countries = [];
    foreach (['MD' => 'MDL', 'UA' => 'UAH'] as $code => $currency) {
        $countries[$code] = DB::table('countries')->insertGetId([
            'code' => $code, 'currency' => $currency, 'phone_code' => '+000',
            'primary_language_id' => $languageId, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'accommodation', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $levelId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countries['MD'], 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryId = DB::table('territories')->insertGetId([
        'country_id' => $countries['MD'], 'level_id' => $levelId, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('countries', 'typeId', 'territoryId');
}

/** @param  array<string, mixed>  $overrides */
function moderationQueueSeedObject(array $geo, int $ownerId): Object_
{
    $objectId = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(),
        'owner_id' => $ownerId,
        'object_type_id' => $geo['typeId'],
        'territory_id' => $geo['territoryId'],
        'country_id' => $geo['countries']['MD'],
        'status' => 'published',
        'moderation_status' => 'approved',
        'availability_status' => 'available',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('object_translations')->insert([
        'object_id' => $objectId, 'locale' => 'en', 'name' => "Object {$objectId}",
        'slug' => "object-{$objectId}", 'needs_review' => false, 'published_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    /** @var Object_ $object */
    $object = Object_::query()->withUnmoderated()->findOrFail($objectId);

    return $object;
}

/** @param  array<string, mixed>  $overrides */
function moderationQueueSeedRequest(array $geo, Object_ $object, array $overrides = []): ModerationRequest
{
    return ModerationRequest::create(array_merge([
        'target_type' => Object_::class,
        'target_id' => $object->id,
        'country_id' => $geo['countries']['MD'],
        'owner_id' => $object->owner_id,
        'section' => 'description',
        'previous_data' => ['short_description' => 'Old text'],
        'proposed_data' => ['short_description' => 'New text', 'seo_title' => 'New SEO'],
        'submitted_by' => $object->owner_id,
        'submitted_at' => now(),
        'decision' => 'pending',
    ], $overrides));
}

function moderationQueueActor(string $scopeKind = 'none', ?int $reference = null): User
{
    foreach (['admin_panel_access', 'moderation.view', 'moderation.edit'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate('moderation_admin_'.$scopeKind.'_'.($reference ?? 'null'), 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions(['admin_panel_access', 'moderation.view', 'moderation.edit']);

    $user = User::factory()->create();
    $user->assignRole($role);

    DB::table('role_scopes')->insert([
        'user_id' => $user->id, 'role_id' => $role->id,
        'scope_kind' => $scopeKind, 'scope_reference_id' => $reference,
        'granted_by' => $user->id, 'granted_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    Filament::setCurrentPanel('admin');
    test()->actingAs($user->fresh());

    return $user->fresh();
}

it('carries the change date, owner, object, section, and change summary an entry needs to render', function (): void {
    $geo = moderationQueueGeography();
    $owner = User::factory()->create(['name' => 'Ana Owner']);
    $object = moderationQueueSeedObject($geo, $owner->id);
    $request = moderationQueueSeedRequest($geo, $object);
    moderationQueueActor();

    /** @var ModerationRequest $loaded */
    $loaded = ModerationRequestResource::getEloquentQuery()->with(['target', 'owner'])->findOrFail($request->id);

    expect($loaded->submitted_at)->not->toBeNull()
        ->and($loaded->owner->name)->toBe('Ana Owner')
        ->and($loaded->target->name)->toBe("Object {$object->id}")
        ->and($loaded->section)->toBe('description')
        // jsonb does not preserve key insertion order the way a PHP array
        // literal does — the keys themselves are what this asserts, not their order.
        ->and(array_keys($loaded->proposed_data))->toEqualCanonicalizing(['short_description', 'seo_title'])
        ->and($loaded->decision)->toBe('pending');
});

it('filters the queue by country, object, owner, change type, and submission date', function (): void {
    $geo = moderationQueueGeography();
    $owner = User::factory()->create();
    $object = moderationQueueSeedObject($geo, $owner->id);
    $request = moderationQueueSeedRequest($geo, $object, ['submitted_at' => now()->subDays(5)]);
    moderationQueueActor();

    $table = ModerationRequestsTable::configure(Table::make(new TableHost));
    $filters = collect($table->getFilters());

    $countryFilter = $filters->first(fn ($f): bool => $f instanceof SelectFilter && $f->getName() === 'country_id');
    $objectFilter = $filters->first(fn ($f): bool => $f instanceof SelectFilter && $f->getName() === 'target_id');
    $ownerFilter = $filters->first(fn ($f): bool => $f instanceof SelectFilter && $f->getName() === 'owner_id');
    $sectionFilter = $filters->first(fn ($f): bool => $f instanceof SelectFilter && $f->getName() === 'section');
    $dateFilter = $filters->first(fn ($f): bool => $f instanceof Filter && $f->getName() === 'submitted_between');

    expect($countryFilter->apply(ModerationRequest::query(), ['value' => $geo['countries']['MD']])->pluck('id')->all())
        ->toBe([$request->id])
        ->and($countryFilter->apply(ModerationRequest::query(), ['value' => $geo['countries']['UA']])->pluck('id')->all())
        ->toBe([])
        ->and($objectFilter->apply(ModerationRequest::query(), ['value' => $object->id])->pluck('id')->all())
        ->toBe([$request->id])
        ->and($ownerFilter->apply(ModerationRequest::query(), ['value' => $owner->id])->pluck('id')->all())
        ->toBe([$request->id])
        ->and($sectionFilter->apply(ModerationRequest::query(), ['value' => 'description'])->pluck('id')->all())
        ->toBe([$request->id])
        ->and($dateFilter->apply(ModerationRequest::query(), ['from' => now()->subDays(10)->toDateString()])->pluck('id')->all())
        ->toBe([$request->id])
        ->and($dateFilter->apply(ModerationRequest::query(), ['from' => now()->subDays(2)->toDateString()])->pluck('id')->all())
        ->toBe([]);
});

it('lets a moderator reassign an entry to a colleague, and journals the reassignment', function (): void {
    $geo = moderationQueueGeography();
    $owner = User::factory()->create();
    $object = moderationQueueSeedObject($geo, $owner->id);
    $request = moderationQueueSeedRequest($geo, $object);
    $actor = moderationQueueActor();
    $colleague = User::factory()->create();

    app(ModerationQueueService::class)->reassign($request, $colleague, $actor);

    expect($request->fresh()->assigned_moderator_id)->toBe($colleague->id)
        ->and(DB::table('audits')
            ->where('event', 'moderation_request_reassigned')
            ->where('auditable_id', $request->id)
            ->exists())->toBeTrue();
});

it('scopes a country-scoped moderator\'s queue to their own country only', function (): void {
    $geo = moderationQueueGeography();
    $owner = User::factory()->create();
    $mdObject = moderationQueueSeedObject($geo, $owner->id);
    $mdRequest = moderationQueueSeedRequest($geo, $mdObject);

    $uaObject = moderationQueueSeedObject($geo, $owner->id);
    DB::table('objects')->where('id', $uaObject->id)->update(['country_id' => $geo['countries']['UA']]);
    $uaRequest = moderationQueueSeedRequest($geo, $uaObject, ['country_id' => $geo['countries']['UA']]);

    moderationQueueActor('country', $geo['countries']['MD']);

    $ids = ModerationRequestResource::getEloquentQuery()->pluck('id')->all();

    expect($ids)->toContain($mdRequest->id)
        ->and($ids)->not->toContain($uaRequest->id);
});

it('orders the queue oldest-pending-first by default', function (): void {
    $table = ModerationRequestsTable::configure(Table::make(new TableHost));

    expect($table->getDefaultSortColumn())->toBe('submitted_at')
        ->and($table->getDefaultSortDirection())->toBe('asc');
});
