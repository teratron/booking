<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\Owners\Pages\ListOwners;
use App\Filament\Admin\Resources\PlacementPackages\Pages\ListPlacementPackages;
use App\Models\User;
use App\Services\DataTransfer\TransferableRegistry;
use Filament\Actions\Exports\Downloaders\CsvDownloader;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader as CsvReader;
use Livewire\Livewire;
use OwenIt\Auditing\Models\Audit;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\StreamedResponse;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Export Permission Narrowing & Journalling
|--------------------------------------------------------------------------
|
| Financial and personal-data columns each need their own standalone
| permission, on top of whichever general `.export` permission gates the
| action itself — and losing one of the two standalone permissions must
| narrow the artefact's columns, never refuse the export outright. Every
| triggered export writes exactly one action-journal entry naming the
| actor, the kind, the row count, and the filter set that was in force.
|
*/

/** @param  list<string>  $permissions */
function exportPermissionActor(string $roleKey, array $permissions): User
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

/**
 * One owner per country — enough to prove both column narrowing (either
 * owner carries every personal-data column) and that a country filter
 * narrows the exported row set the way the journal is expected to record.
 *
 * @return array{countryAId: int, countryBId: int, ownerAId: int, ownerBId: int}
 */
function exportPermissionOwnersFixture(): array
{
    $languageId = DB::table('languages')->insertGetId([
        'code' => 'en', 'short_label' => 'EN', 'is_active' => true, 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $countryAId = DB::table('countries')->insertGetId([
        'code' => 'MD', 'currency' => 'MDL', 'phone_code' => '+373',
        'primary_language_id' => $languageId, 'is_active' => true, 'display_order' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $countryBId = DB::table('countries')->insertGetId([
        'code' => 'UA', 'currency' => 'UAH', 'phone_code' => '+380',
        'primary_language_id' => $languageId, 'is_active' => true, 'display_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    Role::findOrCreate('object_owner', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $ownerA = User::factory()->create([
        'name' => 'Owner Astoria', 'email' => 'owner-astoria@example.test',
        'phone' => '+373600111222', 'company' => 'Astoria SRL', 'country_id' => $countryAId,
    ]);
    $ownerA->assignRole('object_owner');

    $ownerB = User::factory()->create([
        'name' => 'Owner Beta', 'email' => 'owner-beta@example.test',
        'phone' => '+380671234567', 'company' => 'Beta LLC', 'country_id' => $countryBId,
    ]);
    $ownerB->assignRole('object_owner');

    return [
        'countryAId' => $countryAId, 'countryBId' => $countryBId,
        'ownerAId' => $ownerA->id, 'ownerBId' => $ownerB->id,
    ];
}

function exportPermissionPackageFixture(): int
{
    $tierId = DB::table('placement_tiers')->insertGetId([
        'rank' => 1, 'border_colour' => '#000000', 'badge_colour' => '#ffffff', 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return DB::table('placement_packages')->insertGetId([
        'placement_tier_id' => $tierId, 'price' => 49.99, 'currency' => 'EUR', 'validity_days' => 30,
        'bump_allowed' => true, 'paid_bump_price' => 5.00, 'is_active' => true, 'display_order' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function exportPermissionStreamContent(StreamedResponse $response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

/** @return list<string> */
function exportPermissionHeaderRow(Export $export): array
{
    $content = exportPermissionStreamContent(app(CsvDownloader::class)($export));

    $csv = CsvReader::fromString($content);
    $csv->setHeaderOffset(0);

    return $csv->getHeader();
}

/**
 * @param  list<string>  $names
 * @return list<string>
 */
function exportPermissionColumnLabels(string $kind, array $names): array
{
    return collect(TransferableRegistry::get($kind)->columns)
        ->whereIn('name', $names)
        ->pluck('label')
        ->all();
}

it("narrows the owners export's personal-data columns for an actor holding only the general export permission, without refusing the export", function (): void {
    Storage::fake(config('filament.default_filesystem_disk', 'local'));

    exportPermissionOwnersFixture();
    $actor = exportPermissionActor('owner_exporter_general', ['admin_panel_access', 'user.view', 'user.export']);

    Livewire::actingAs($actor)->test(ListOwners::class)->callAction('export');

    $export = Export::query()->where('user_id', $actor->id)->latest('id')->firstOrFail();
    expect($export->completed_at)->not->toBeNull()
        ->and($export->successful_rows)->toBe(2);

    $header = exportPermissionHeaderRow($export);

    foreach (exportPermissionColumnLabels('owners', ['name', 'email', 'phone', 'company']) as $label) {
        expect($header)->not->toContain($label);
    }

    foreach (exportPermissionColumnLabels('owners', ['id', 'country_id', 'locale', 'blocked_at']) as $label) {
        expect($header)->toContain($label);
    }
});

it('includes the owners export\'s personal-data columns for an actor holding personal_data_access', function (): void {
    Storage::fake(config('filament.default_filesystem_disk', 'local'));

    exportPermissionOwnersFixture();
    $actor = exportPermissionActor('owner_exporter_pii', ['admin_panel_access', 'user.view', 'user.export', 'personal_data_access']);

    Livewire::actingAs($actor)->test(ListOwners::class)->callAction('export');

    $export = Export::query()->where('user_id', $actor->id)->latest('id')->firstOrFail();
    $header = exportPermissionHeaderRow($export);

    foreach (exportPermissionColumnLabels('owners', ['name', 'email', 'phone', 'company']) as $label) {
        expect($header)->toContain($label);
    }
});

it("narrows the placement-package export's financial columns for an actor holding only the general export permission, without refusing the export", function (): void {
    Storage::fake(config('filament.default_filesystem_disk', 'local'));

    exportPermissionPackageFixture();
    $actor = exportPermissionActor('package_exporter_general', ['admin_panel_access', 'commerce.view', 'commerce.export']);

    Livewire::actingAs($actor)->test(ListPlacementPackages::class)->callAction('export');

    $export = Export::query()->where('user_id', $actor->id)->latest('id')->firstOrFail();
    expect($export->completed_at)->not->toBeNull()
        ->and($export->successful_rows)->toBe(1);

    $header = exportPermissionHeaderRow($export);

    foreach (exportPermissionColumnLabels('packages', ['price', 'paid_bump_price']) as $label) {
        expect($header)->not->toContain($label);
    }

    foreach (exportPermissionColumnLabels('packages', ['id', 'placement_tier_id', 'currency', 'validity_days']) as $label) {
        expect($header)->toContain($label);
    }
});

it("includes the placement-package export's financial columns for an actor holding financial_access", function (): void {
    Storage::fake(config('filament.default_filesystem_disk', 'local'));

    exportPermissionPackageFixture();
    $actor = exportPermissionActor('package_exporter_finance', ['admin_panel_access', 'commerce.view', 'commerce.export', 'financial_access']);

    Livewire::actingAs($actor)->test(ListPlacementPackages::class)->callAction('export');

    $export = Export::query()->where('user_id', $actor->id)->latest('id')->firstOrFail();
    $header = exportPermissionHeaderRow($export);

    foreach (exportPermissionColumnLabels('packages', ['price', 'paid_bump_price']) as $label) {
        expect($header)->toContain($label);
    }
});

it('writes exactly one action-journal entry per export, naming the actor, the kind, and the row count', function (): void {
    Storage::fake(config('filament.default_filesystem_disk', 'local'));

    exportPermissionOwnersFixture();
    $actor = exportPermissionActor('owner_exporter_journal', ['admin_panel_access', 'user.view', 'user.export']);

    Livewire::actingAs($actor)->test(ListOwners::class)->callAction('export');

    $export = Export::query()->where('user_id', $actor->id)->latest('id')->firstOrFail();

    $entries = Audit::query()->where('event', 'data_exported')->where('auditable_id', $export->id)->get();
    expect($entries)->toHaveCount(1);

    $entry = $entries->first();
    expect($entry->auditable_type)->toBe(Export::class)
        ->and($entry->user_type)->toBe(User::class)
        ->and($entry->user_id)->toBe($actor->id)
        ->and($entry->new_values['kind'])->toBe('owners')
        ->and($entry->new_values['count'])->toBe(2);
});

it('journals the active table filter set that was in force at export time', function (): void {
    Storage::fake(config('filament.default_filesystem_disk', 'local'));

    $fixture = exportPermissionOwnersFixture();
    $actor = exportPermissionActor('owner_exporter_filtered', ['admin_panel_access', 'user.view', 'user.export']);

    Livewire::actingAs($actor)->test(ListOwners::class)
        ->filterTable('country_id', $fixture['countryAId'])
        ->callAction('export');

    $export = Export::query()->where('user_id', $actor->id)->latest('id')->firstOrFail();
    expect($export->successful_rows)->toBe(1);

    $entry = Audit::query()->where('event', 'data_exported')->where('auditable_id', $export->id)->firstOrFail();

    expect(data_get($entry->new_values, 'filters.country_id.value'))->toBe($fixture['countryAId']);
});
