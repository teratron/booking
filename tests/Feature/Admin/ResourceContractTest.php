<?php

declare(strict_types=1);

use App\Filament\Admin\Support\CountedBulkAction;
use App\Models\Object_;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Fixtures\Filament\ContractProbeResource;
use Tests\Fixtures\Filament\GlobalProbeResource;
use Tests\Fixtures\Filament\PolicylessProbeResource;
use Tests\Fixtures\Filament\TableHost;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Shared Resource Contract
|--------------------------------------------------------------------------
|
| Everything asserted here is behaviour a resource author would otherwise have
| to remember, in every resource, forever. The assertions are made against a
| probe resource that adds nothing of its own, so a pass means the base class
| supplies the behaviour — not that one particular resource happens to.
|
| The narrowing cases are the load-bearing ones. A policy refuses a record; it
| does not remove the record from a list, and a list that still counts records
| the actor may not open has already disclosed how many there are.
|
*/

function seedContractFixture(): array
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

    $levels = [];
    foreach ($countries as $code => $countryId) {
        $levels[$code] = DB::table('territory_levels')->insertGetId([
            'country_id' => $countryId, 'depth_rank' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $mdRegion = DB::table('territories')->insertGetId([
        'country_id' => $countries['MD'], 'level_id' => $levels['MD'],
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $mdCity = DB::table('territories')->insertGetId([
        'country_id' => $countries['MD'], 'level_id' => $levels['MD'], 'parent_id' => $mdRegion,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $uaCity = DB::table('territories')->insertGetId([
        'country_id' => $countries['UA'], 'level_id' => $levels['UA'],
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $stay = DB::table('object_types')->insertGetId([
        'key' => 'accommodation', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $dining = DB::table('object_types')->insertGetId([
        'key' => 'dining', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $owner = User::factory()->create();

    $objects = [
        'md_stay' => [$countries['MD'], $mdCity, $stay],
        'ua_stay' => [$countries['UA'], $uaCity, $stay],
        'ua_dining' => [$countries['UA'], $uaCity, $dining],
    ];

    $ids = [];
    foreach ($objects as $name => [$countryId, $territoryId, $typeId]) {
        $ids[$name] = DB::table('objects')->insertGetId([
            'ulid' => (string) Str::ulid(), 'owner_id' => $owner->id,
            'object_type_id' => $typeId, 'territory_id' => $territoryId, 'country_id' => $countryId,
            'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return [
        'countries' => $countries,
        'territories' => ['md_region' => $mdRegion, 'md_city' => $mdCity, 'ua_city' => $uaCity],
        'types' => ['stay' => $stay, 'dining' => $dining],
        'objects' => $ids,
    ];
}

function actorScoped(string $scopeKind, ?int $reference): User
{
    Permission::findOrCreate('object.view', 'web');

    $role = Role::findOrCreate('probe_role_'.$scopeKind.'_'.($reference ?? 'null'), 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role->syncPermissions(['object.view']);

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

    return $user;
}

it('leaves an unrestricted grant unnarrowed', function (): void {
    seedContractFixture();
    actorScoped('none', null);

    expect(ContractProbeResource::getEloquentQuery()->count())->toBe(3);
});

it('shows the staff panel content the public site is not allowed to see', function (): void {
    $fixture = seedContractFixture();
    actorScoped('none', null);

    DB::table('objects')->where('id', $fixture['objects']['md_stay'])->update(['moderation_status' => 'rejected']);

    // The global scope that keeps unapproved rows off public pages would also
    // hide a rejected object from the only people able to fix it. A queue that
    // cannot see what is pending has nothing to moderate.
    expect(ContractProbeResource::getEloquentQuery()->count())->toBe(3)
        ->and(Object_::query()->count())->toBe(0);
});

it('narrows a country-scoped grant to that country', function (): void {
    $fixture = seedContractFixture();
    actorScoped('country', $fixture['countries']['MD']);

    $reached = ContractProbeResource::getEloquentQuery()->pluck('id')->all();

    expect($reached)->toBe([$fixture['objects']['md_stay']]);
});

it('narrows a territory-scoped grant to the whole subtree and no further', function (): void {
    $fixture = seedContractFixture();
    actorScoped('territory', $fixture['territories']['md_region']);

    // The object sits on the child city, not on the granted region itself:
    // a grant that only matched the named node would return nothing here.
    expect(ContractProbeResource::getEloquentQuery()->pluck('id')->all())
        ->toBe([$fixture['objects']['md_stay']]);
});

it('narrows a category-scoped grant across countries', function (): void {
    $fixture = seedContractFixture();
    actorScoped('category', $fixture['types']['dining']);

    expect(ContractProbeResource::getEloquentQuery()->pluck('id')->all())
        ->toBe([$fixture['objects']['ua_dining']]);
});

it('reaches nothing when the resource carries no axis the grant is bounded to', function (): void {
    $fixture = seedContractFixture();
    actorScoped('country', $fixture['countries']['MD']);

    expect(GlobalProbeResource::getEloquentQuery()->count())->toBe(0);
});

it('reaches nothing when no actor is resolved', function (): void {
    seedContractFixture();
    Filament::setCurrentPanel('admin');

    expect(ContractProbeResource::getEloquentQuery()->count())->toBe(0);
});

it('persists filters, search and sort without the resource asking', function (): void {
    $table = ContractProbeResource::table(Table::make(new TableHost));

    expect($table->persistsFiltersInSession())->toBeTrue()
        ->and($table->persistsSearchInSession())->toBeTrue()
        ->and($table->persistsSortInSession())->toBeTrue()
        ->and($table->persistsColumnSearchesInSession())->toBeTrue();
});

it('demands a confirmation of every bulk action built on the contract', function (): void {
    $action = CountedBulkAction::make('probe');

    // The count in the confirmation copy is asserted against a mounted action
    // in T-2B03, where a real selection exists to be counted. What belongs
    // here is that the confirmation cannot be skipped by omission.
    expect($action->isConfirmationRequired())->toBeTrue()
        ->and($action->shouldDeselectRecordsAfterCompletion())->toBeTrue()
        ->and(__('panel.bulk.confirmation', ['count' => 87]))->toContain('87');
});

it('guards navigation away from an unsaved form and fails loudly on a missing policy', function (): void {
    $panel = Filament::getPanel('admin');

    expect($panel->hasUnsavedChangesAlerts())->toBeTrue()
        ->and($panel->isAuthorizationStrict())->toBeTrue();
});

it('throws rather than allowing when a resource has no policy', function (): void {
    seedContractFixture();
    actorScoped('none', null);

    // The toolkit's own default here is to permit — a resource whose author
    // forgot the policy is fully open, with no symptom until someone finds
    // the section. Strict mode turns that into a failure at the first request.
    PolicylessProbeResource::canViewAny();
})->throws(LogicException::class);
