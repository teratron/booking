<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\FinancialRecords\FinancialRecordResource;
use App\Filament\Admin\Resources\FinancialRecords\Pages\CreateFinancialRecord;
use App\Models\FinancialRecord;
use App\Models\Object_;
use App\Models\PlacementPackage;
use App\Models\Territory;
use App\Models\User;
use App\Services\Audit\AuditJournal;
use App\Services\Placement\CommerceReportingService;
use App\Services\Placement\PlacementLifecycleService;
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
| Financial Ledger & Commerce Reports
|--------------------------------------------------------------------------
|
| The ledger is proven from two directions: every automatic grant produces
| a real financial_records row (closing a gap the dashboard finance widget
| already depended on), and an administrator can also enter a row by hand
| for a sale this project has no automatic trigger for yet (advertising).
| Report figures are checked against a fixture built to make each one
| unambiguous, not just non-zero.
|
*/

function ledgerActor(array $permissions = ['admin_panel_access', 'finance.view', 'finance.create', 'finance.edit', 'finance.export']): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate('finance_admin', 'web');
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

/** @return array{country: int, territory: int, type: int} */
function ledgerFixtureGeography(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
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
        'key' => 'hotel', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return ['country' => $countryId, 'territory' => $territoryId, 'type' => $typeId];
}

function ledgerFixtureObject(int $countryId, int $territoryId, int $typeId): int
{
    $owner = User::factory()->create();

    return DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(), 'owner_id' => $owner->id,
        'object_type_id' => $typeId, 'territory_id' => $territoryId, 'country_id' => $countryId,
        'status' => 'published', 'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function ledgerFixturePackage(int $price = 50): int
{
    // Each call needs its own tier (rank is unique) — the next free rank
    // number, not a fixed one, so this helper can be called more than once
    // per test.
    $nextRank = 1 + (int) DB::table('placement_tiers')->max('rank');

    $tierId = DB::table('placement_tiers')->insertGetId([
        'rank' => $nextRank, 'border_colour' => '#000000', 'badge_colour' => '#111111',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return DB::table('placement_packages')->insertGetId([
        'placement_tier_id' => $tierId, 'price' => $price, 'currency' => 'EUR', 'validity_days' => 30,
        'bump_allowed' => false, 'is_active' => true, 'display_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('records a financial_records row automatically on every placement grant', function (): void {
    $geo = ledgerFixtureGeography();
    $packageId = ledgerFixturePackage(29);
    $objectId = ledgerFixtureObject($geo['country'], $geo['territory'], $geo['type']);

    $service = new PlacementLifecycleService(app(AuditJournal::class));
    $service->grant(Object_::query()->findOrFail($objectId), PlacementPackage::query()->findOrFail($packageId));

    $record = FinancialRecord::query()->where('object_id', $objectId)->first();

    expect($record)->not->toBeNull()
        ->and($record->banner_id)->toBeNull()
        ->and($record->service)->toBe('placement')
        ->and($record->placement_package_id)->toBe($packageId)
        ->and((float) $record->amount)->toBe(29.0)
        ->and($record->status)->toBe('granted_free');
});

it('lets an administrator manually enter a ledger row for one subject only, refusing neither or both', function (): void {
    $geo = ledgerFixtureGeography();
    $objectId = ledgerFixtureObject($geo['country'], $geo['territory'], $geo['type']);
    $actor = ledgerActor();

    Livewire::actingAs($actor)
        ->test(CreateFinancialRecord::class)
        ->fillForm([
            'subject_kind' => 'object',
            'object_id' => $objectId,
            'service' => 'placement',
            'amount' => '99.00',
            'currency' => 'eur',
            'status' => 'paid',
            'document_number' => 'DOC-001',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $record = FinancialRecord::query()->where('object_id', $objectId)->where('document_number', 'DOC-001')->firstOrFail();

    expect($record->banner_id)->toBeNull()
        ->and($record->currency)->toBe('EUR')
        ->and((float) $record->amount)->toBe(99.0);
});

it('refuses ledger read and export to an actor without financial_access permissions', function (): void {
    $unrestricted = ledgerActor();
    $this->actingAs($unrestricted)
        ->get(FinancialRecordResource::getUrl('index', panel: 'admin'))
        ->assertSuccessful();

    Permission::findOrCreate('admin_panel_access', 'web');
    $noFinance = Role::findOrCreate('no_finance_admin', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $noFinance->syncPermissions(['admin_panel_access']);
    $blocked = User::factory()->create();
    $blocked->assignRole($noFinance);
    DB::table('role_scopes')->insert([
        'user_id' => $blocked->id, 'role_id' => $noFinance->id,
        'scope_kind' => 'none', 'scope_reference_id' => null,
        'granted_by' => $blocked->id, 'granted_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->actingAs($blocked->fresh())
        ->get(FinancialRecordResource::getUrl('index', panel: 'admin'))
        ->assertForbidden();
});

it('computes each report figure against an unambiguous fixture', function (): void {
    $geo = ledgerFixtureGeography();
    $freePackageId = ledgerFixturePackage(0);
    $paidPackageId = ledgerFixturePackage(50);

    // Active, ending within 3 days.
    $endingSoon = ledgerFixtureObject($geo['country'], $geo['territory'], $geo['type']);
    DB::table('object_placements')->insert([
        'object_id' => $endingSoon, 'placement_package_id' => $paidPackageId,
        'starts_at' => now()->subDays(27)->toDateString(), 'ends_at' => now()->addDays(2)->toDateString(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // Expired.
    $expired = ledgerFixtureObject($geo['country'], $geo['territory'], $geo['type']);
    DB::table('object_placements')->insert([
        'object_id' => $expired, 'placement_package_id' => $paidPackageId,
        'starts_at' => now()->subDays(35)->toDateString(), 'ends_at' => now()->subDays(5)->toDateString(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // Free, active, not ending soon.
    $free = ledgerFixtureObject($geo['country'], $geo['territory'], $geo['type']);
    DB::table('object_placements')->insert([
        'object_id' => $free, 'placement_package_id' => $freePackageId,
        'starts_at' => now()->subDays(10)->toDateString(), 'ends_at' => now()->addDays(300)->toDateString(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // No term set at all.
    ledgerFixtureObject($geo['country'], $geo['territory'], $geo['type']);

    DB::table('bump_events')->insert([
        'object_id' => $endingSoon, 'placement_package_id' => $paidPackageId,
        'scope_type' => Territory::class, 'scope_id' => $geo['territory'],
        'occurred_at' => now(), 'type' => 'paid',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $summary = app(CommerceReportingService::class)->summary();

    // Active: endingSoon and free (both starts_at <= now <= ends_at).
    // expired's ends_at is in the past, so it does not count as active.
    expect($summary['active_placements'])->toBe(2)
        ->and($summary['ending_3'])->toBe(1)
        ->and($summary['expired_placements'])->toBe(1)
        ->and($summary['free_placements'])->toBe(1)
        ->and($summary['no_term_set'])->toBe(1)
        ->and($summary['paid_bump_count'])->toBe(1);
});
