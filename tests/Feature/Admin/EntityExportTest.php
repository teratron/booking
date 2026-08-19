<?php

declare(strict_types=1);

use App\Filament\Admin\Exports\JsonDownloader;
use App\Filament\Admin\Pages\Exports\StatDailyExporter;
use App\Filament\Admin\Resources\ActionJournal\Exports\ActionJournalExporter;
use App\Filament\Admin\Resources\FinancialRecords\Exports\FinancialRecordExporter;
use App\Filament\Admin\Resources\Objects\Pages\ListObjects;
use App\Models\User;
use App\Services\DataTransfer\TransferableRegistry;
use Filament\Actions\Exports\Downloaders\CsvDownloader;
use Filament\Actions\Exports\Downloaders\XlsxDownloader;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Csv\Reader as CsvReader;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\StreamedResponse;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Entity Export
|--------------------------------------------------------------------------
|
| One export action, driven by the transferable data-type registry, offered
| on the object list in all three declared formats — and proof that the
| registry reading did not regress the three exporters that pre-date it.
| Filament's own queued export pipeline runs for real here (the test
| environment's queue connection is sync), never re-implemented, so a
| genuine artefact lands on the fake disk exactly as it would in
| production, and the download step reads that same real artefact back.
|
*/

/**
 * Grants the role-scoped permissions this panel's list resources require —
 * `spatie/laravel-permission`'s own role/permission pair narrows *what* an
 * action allows, but `ScopedResource::getEloquentQuery()` separately fails
 * closed to zero rows for any role holding no `role_scopes` grant at all
 * (see `App\Services\Authorization\ScopeAuthorizer`), so a role granted a
 * view/export permission without an accompanying unrestricted scope row
 * sees an empty list regardless of the permission it holds.
 */
function exportOperator(string $roleName, array $permissions): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate($roleName, 'web');
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
 * Two objects in one country, one in another — the fixture the "respects
 * the active filter" case needs, reused by the format cases since three
 * exported rows is enough to prove a header-plus-data artefact either way.
 *
 * @return array{countryAId: int, countryBId: int, countryAObjectIds: list<int>, countryBObjectId: int}
 */
function exportObjectsFixture(): array
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

    $levelAId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryAId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $levelBId = DB::table('territory_levels')->insertGetId([
        'country_id' => $countryBId, 'depth_rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $territoryAId = DB::table('territories')->insertGetId([
        'country_id' => $countryAId, 'level_id' => $levelAId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $territoryBId = DB::table('territories')->insertGetId([
        'country_id' => $countryBId, 'level_id' => $levelBId, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $typeId = DB::table('object_types')->insertGetId([
        'key' => 'accommodation', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $owner = User::factory()->create();

    $countryAObjectIds = [];

    foreach (['Villa One', 'Villa Two'] as $address) {
        $countryAObjectIds[] = DB::table('objects')->insertGetId([
            'ulid' => (string) Str::ulid(), 'owner_id' => $owner->id,
            'object_type_id' => $typeId, 'territory_id' => $territoryAId, 'country_id' => $countryAId,
            'address' => $address, 'status' => 'published', 'moderation_status' => 'approved',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $countryBObjectId = DB::table('objects')->insertGetId([
        'ulid' => (string) Str::ulid(), 'owner_id' => $owner->id,
        'object_type_id' => $typeId, 'territory_id' => $territoryBId, 'country_id' => $countryBId,
        'address' => 'Chateau One', 'status' => 'published', 'moderation_status' => 'approved',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return [
        'countryAId' => $countryAId,
        'countryBId' => $countryBId,
        'countryAObjectIds' => $countryAObjectIds,
        'countryBObjectId' => $countryBObjectId,
    ];
}

function captureStreamedResponse(StreamedResponse $response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

/** Drives the real header ExportAction through the real Livewire page. */
function triggerObjectsExport(User $actor): Export
{
    Livewire::actingAs($actor)->test(ListObjects::class)->callAction('export');

    return Export::query()->where('user_id', $actor->id)->latest('id')->firstOrFail();
}

/** @return list<string> */
function objectsRegistryHeaderRow(): array
{
    return array_map(
        static fn ($column): string => $column->label,
        TransferableRegistry::get('objects')->columns,
    );
}

it('exports the object list as a parseable XLSX artefact carrying the registry declared header row', function (): void {
    Storage::fake(config('filament.default_filesystem_disk', 'local'));

    exportObjectsFixture();
    $actor = exportOperator('xlsx_exporter', ['admin_panel_access', 'object.view', 'object.export']);

    $export = triggerObjectsExport($actor);
    expect($export->completed_at)->not->toBeNull()
        ->and($export->total_rows)->toBe(3);

    $content = captureStreamedResponse(app(XlsxDownloader::class)($export));

    $path = tempnam(sys_get_temp_dir(), 'entity-export-').'.xlsx';
    file_put_contents($path, $content);

    $rows = [];
    $reader = new XlsxReader;
    $reader->open($path);

    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }

        break;
    }

    $reader->close();
    @unlink($path);

    expect($rows[0])->toBe(objectsRegistryHeaderRow())
        ->and($rows)->toHaveCount(4); // header row + 3 objects
});

it('exports the object list as a parseable CSV artefact carrying the registry declared header row', function (): void {
    Storage::fake(config('filament.default_filesystem_disk', 'local'));

    exportObjectsFixture();
    $actor = exportOperator('csv_exporter', ['admin_panel_access', 'object.view', 'object.export']);

    $export = triggerObjectsExport($actor);

    $content = captureStreamedResponse(app(CsvDownloader::class)($export));

    $csv = CsvReader::fromString($content);
    $csv->setHeaderOffset(0);

    expect($csv->getHeader())->toBe(objectsRegistryHeaderRow())
        ->and(iterator_to_array($csv->getRecords(), false))->toHaveCount(3);
});

it('exports the object list as a parseable JSON artefact carrying the registry declared header row', function (): void {
    Storage::fake(config('filament.default_filesystem_disk', 'local'));

    exportObjectsFixture();
    $actor = exportOperator('json_exporter', ['admin_panel_access', 'object.view', 'object.export']);

    $export = triggerObjectsExport($actor);

    $content = captureStreamedResponse(app(JsonDownloader::class)($export));
    $decoded = json_decode($content, true);

    expect($decoded)->toHaveCount(3)
        ->and(array_keys($decoded[0]))->toBe(objectsRegistryHeaderRow());
});

it("respects the object list's active country filter, exporting only that country's rows", function (): void {
    Storage::fake(config('filament.default_filesystem_disk', 'local'));

    $fixture = exportObjectsFixture();
    $actor = exportOperator('filtered_exporter', ['admin_panel_access', 'object.view', 'object.export']);

    Livewire::actingAs($actor)->test(ListObjects::class)
        ->filterTable('country_id', $fixture['countryAId'])
        ->callAction('export');

    $export = Export::query()->where('user_id', $actor->id)->latest('id')->firstOrFail();
    expect($export->total_rows)->toBe(2);

    $content = captureStreamedResponse(app(CsvDownloader::class)($export));

    $csv = CsvReader::fromString($content);
    $csv->setHeaderOffset(0);

    $idLabel = collect(TransferableRegistry::get('objects')->columns)->firstWhere('name', 'id')->label;

    $exportedIds = array_map(
        static fn (array $record): int => (int) $record[$idLabel],
        iterator_to_array($csv->getRecords(), false),
    );

    sort($exportedIds);
    $expectedIds = $fixture['countryAObjectIds'];
    sort($expectedIds);

    expect($exportedIds)->toBe($expectedIds)
        ->and($exportedIds)->not->toContain($fixture['countryBObjectId']);
});

it('keeps every FinancialRecordExporter column and label unchanged after reading the transferable registry', function (): void {
    $byName = collect(FinancialRecordExporter::getColumns())->keyBy(fn ($column) => $column->getName());

    expect($byName->keys()->all())->toEqualCanonicalizing([
        'id', 'object_id', 'banner_id', 'service', 'package.name', 'amount',
        'currency', 'status', 'paid_at', 'valid_from', 'valid_until',
        'payment_method', 'document_number', 'responsibleStaff.name',
    ]);

    expect($byName->get('id')->getLabel())->toBe('Id')
        ->and($byName->get('object_id')->getLabel())->toBe(__('panel.financial_records.form.object'))
        ->and($byName->get('banner_id')->getLabel())->toBe(__('panel.financial_records.form.banner'))
        ->and($byName->get('service')->getLabel())->toBe(__('panel.financial_records.form.service'))
        ->and($byName->get('package.name')->getLabel())->toBe(__('panel.financial_records.form.package'))
        ->and($byName->get('amount')->getLabel())->toBe(__('panel.financial_records.form.amount'))
        ->and($byName->get('currency')->getLabel())->toBe(__('panel.financial_records.form.currency'))
        ->and($byName->get('status')->getLabel())->toBe(__('panel.financial_records.form.status'))
        ->and($byName->get('paid_at')->getLabel())->toBe(__('panel.financial_records.form.paid_at'))
        ->and($byName->get('valid_from')->getLabel())->toBe(__('panel.financial_records.form.valid_from'))
        ->and($byName->get('valid_until')->getLabel())->toBe(__('panel.financial_records.form.valid_until'))
        ->and($byName->get('payment_method')->getLabel())->toBe(__('panel.financial_records.form.payment_method'))
        ->and($byName->get('document_number')->getLabel())->toBe(__('panel.financial_records.form.document_number'))
        ->and($byName->get('responsibleStaff.name')->getLabel())->toBe(__('panel.financial_records.form.responsible_staff'));
});

it('keeps every ActionJournalExporter pre-existing column and label unchanged, and adds the registry columns it previously omitted', function (): void {
    $columns = ActionJournalExporter::getColumns();
    $names = array_map(fn ($column) => $column->getName(), $columns);

    // The deliberate duplicate 'event' entry — raw action vs. the
    // AuditPresenter-derived outcome — is still exactly two columns.
    expect(array_count_values($names)['event'] ?? 0)->toBe(2);

    foreach (['created_at', 'user.name', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'ip_address', 'user_agent'] as $preExisting) {
        expect($names)->toContain($preExisting);
    }

    // Registry columns this exporter never emitted before it read the
    // registry, now picked up automatically rather than staying an
    // undetected gap — a disclosed addition, not a loss.
    expect($names)->toContain('id', 'user_type')
        // 'user_id' stays covered by the 'user.name' relation-display
        // substitute rather than also appearing as a second, raw column.
        ->and($names)->not->toContain('user_id');

    $byName = collect($columns)->keyBy(fn ($column) => $column->getName());
    expect($byName->get('created_at')->getLabel())->toBe(__('panel.action_journal.columns.occurred_at'))
        ->and($byName->get('user.name')->getLabel())->toBe(__('panel.action_journal.columns.actor'))
        ->and($byName->get('auditable_type')->getLabel())->toBe(__('panel.action_journal.columns.target'))
        ->and($byName->get('auditable_id')->getLabel())->toBe(__('panel.action_journal.columns.target_id'))
        ->and($byName->get('old_values')->getLabel())->toBe(__('panel.action_journal.columns.previous_value'))
        ->and($byName->get('new_values')->getLabel())->toBe(__('panel.action_journal.columns.new_value'))
        ->and($byName->get('ip_address')->getLabel())->toBe(__('panel.action_journal.columns.ip_address'))
        ->and($byName->get('user_agent')->getLabel())->toBe(__('panel.action_journal.columns.device'));
});

it('keeps every StatDailyExporter pre-existing column unchanged, and adds the one registry column it previously omitted', function (): void {
    $names = array_map(fn ($column) => $column->getName(), StatDailyExporter::getColumns());

    expect($names)->toEqualCanonicalizing([
        'date', 'kind', 'subject_type', 'subject_id', 'contact_channel_type_id',
        'territory_id', 'country_id', 'locale', 'count', 'id',
    ]);
});
